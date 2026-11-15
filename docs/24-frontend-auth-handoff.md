# 24 — Frontend Auth Integration Handoff (`korak-api` ➔ `korak-web`)

Comprehensive specification for integrating the Next.js frontend (`korak-web`) with the Laravel JSON API backend (`korak-api`).

---

## 1. Network & Protocol Overview

* **Backend Base URL (Local):** `http://localhost:8070`
* **Frontend Base URL (Local):** `http://localhost:3000`
* **Authentication Strategy:** Laravel Sanctum **Stateful SPA Session Cookie** (Doc 04 §2 — Locked).
  * **No Bearer tokens in JavaScript storage** (no localStorage, no sessionStorage).
  * Session resides inside a secure, encrypted, `HttpOnly` cookie (`korak_session`).
  * CSRF protection is handled via standard double-submit cookie (`XSRF-TOKEN`).

### Required Client Settings
For every request from the browser (`axios`, `fetch`, or `@tanstack/react-query`):
1. **Credentials:** Must send `credentials: 'include'` (in `fetch`) or `withCredentials: true` (in `axios`).
2. **Headers:**
   * `Accept: application/json`
   * `Content-Type: application/json`
   * `X-XSRF-TOKEN: <value>` (for state-changing methods: `POST`, `PUT`, `PATCH`, `DELETE`).

---

## 2. Standard JSON Response Envelopes (Doc 06 §3)

All API responses strictly adhere to the locked response envelope. The frontend must parse responses according to these schemas:

### A. Success Envelope (`200 OK`, `201 Created`)
```typescript
interface ApiResponse<T, M = Record<string, unknown>> {
  data: T;
  meta: M;
}
```

### B. Error Envelope (`400`, `401`, `403`, `404`, `429`, `500`)
```typescript
interface ApiErrorResponse {
  message: string;        // Human-readable safe message
  code: string;           // Machine-readable error code (e.g., 'invalid_credentials', 'too_many_requests')
  errors?: Record<string, string[]>; // Field-level validation errors (present on 422)
  reference_id?: string;  // Unique error tracing ID (present on 500)
}
```

---

## 3. Ready-to-Use Next.js / TypeScript Axios Client

Drop this implementation into your frontend repository at `lib/api/client.ts`:

```typescript
import axios, { AxiosError, InternalAxiosRequestConfig } from 'axios';

const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8070';

export const apiClient = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
  withCredentials: true, // Required for Sanctum session cookies
  withXSRFToken: true,   // Automatically reads XSRF-TOKEN cookie and sets X-XSRF-TOKEN header
});

/**
 * Helper to initialize CSRF cookie before authentication calls.
 */
export async function initCsrf(): Promise<void> {
  await apiClient.get('/sanctum/csrf-cookie');
}
```

---

## 4. Endpoint Specifications

### 4.1 Initialize CSRF Protection
Before calling `register`, `login`, `forgot-password`, or `reset-password`, fetch the CSRF cookie once:

* **Method:** `GET`
* **Path:** `/sanctum/csrf-cookie`
* **Auth Required:** No
* **Response:** `204 No Content`
* **Browser Cookies Set:**
  * `XSRF-TOKEN` (Readable by client script, used to sign mutations)
  * `korak_session` (Encrypted, `HttpOnly`, `SameSite=Lax`)

---

### 4.2 Register Account
Creates a new customer account.

* **Method:** `POST`
* **Path:** `/api/v1/auth/register`
* **Auth Required:** No
* **Request Body:**
  ```json
  {
    "username": "alice",
    "email": "alice@example.com",
    "password": "Password123!",
    "password_confirmation": "Password123!"
  }
  ```
  *Validation Rules:*
  * `username`: required, string, 3–30 characters, alphanumeric with dashes, underscores, and periods (`^[a-zA-Z0-9_.-]+$`), unique.
  * `email`: required, string, valid email, max 255 characters, unique.
  * `password`: required, string, min 8 characters, confirmed via `password_confirmation`.

* **Success Response:** `201 Created`
  ```json
  {
    "data": {
      "id": "01JMCZ77D34R33E893P14D8W4B",
      "username": "alice",
      "email": "alice@example.com",
      "email_verified_at": null,
      "created_at": "2026-11-14T12:00:00.000000Z"
    },
    "meta": {}
  }
  ```
  *(Note: `id` is a public ULID string. Sequential integer IDs are never exposed.)*

* **Error Responses:**
  * `422 Unprocessable Entity`: Validation failure.
    ```json
    {
      "message": "The given data was invalid.",
      "code": "validation_failed",
      "errors": {
        "email": ["The email has already been taken."],
        "password": ["The password field confirmation does not match."]
      }
    }
    ```

---

### 4.3 Login
Authenticates customer and establishes an encrypted session.

* **Method:** `POST`
* **Path:** `/api/v1/auth/login`
* **Auth Required:** No
* **Rate Limit:** 5 requests / minute per `identifier|IP` (`429 Too Many Requests`).
* **Brute-Force Lockout:** 5 consecutive failures triggers a 15-minute account lock and queues an email alert to the account owner.
* **Request Body:**
  ```json
  {
    "login": "alice@example.com",
    "password": "Password123!",
    "device_name": "Chrome on macOS" // optional
  }
  ```
  *(Note: `"login"` accepts either username OR email address).*

* **Success Response (Recognized Device):** `200 OK`
  ```json
  {
    "data": {
      "id": "01JMCZ77D34R33E893P14D8W4B",
      "username": "alice",
      "email": "alice@example.com",
      "email_verified_at": null,
      "created_at": "2026-11-14T12:00:00.000000Z"
    },
    "meta": {}
  }
  ```

* **Intermediate Response (Unrecognised Device — Doc 08 §4, Doc 22 B7.2):** `200 OK`
  ```json
  {
    "data": {
      "status": "device_verification_required",
      "challenge_token": "wyAf0s8vH54s59bhs9q2s8G1rZ0D9F8ynJV2K4onc1U6R6TCxI3N1xuNYzwjK7K5",
      "expires_at": "2026-11-14T12:10:00.000000Z"
    },
    "meta": {}
  }
  ```
  *(Note: The session is NOT authenticated yet. Client must proceed to `/api/v1/auth/verify-device` with the emailed OTP).*

* **Error Responses:**
  * `401 Unauthorized`: Anti-enumeration protection. Returns identical payload whether the account doesn't exist, password is wrong, or account is temporarily locked:
    ```json
    {
      "message": "Invalid credentials.",
      "code": "invalid_credentials"
    }
    ```
  * `429 Too Many Requests`:
    ```json
    {
      "message": "Too many requests. Please try again later.",
      "code": "too_many_requests"
    }
    ```
    *(Includes standard `Retry-After: <seconds>` HTTP header).*

---

### 4.4 Verify Device (OTP Challenge)
Completes device verification using the 6-digit OTP emailed to the account owner.

* **Method:** `POST`
* **Path:** `/api/v1/auth/verify-device`
* **Auth Required:** No (Unverified state)
* **Rate Limit:** 3 attempts / minute (`throttle:otp`).
* **Attempt Limit:** 3 wrong code attempts destroys the challenge (`challenge_limit_exceeded`).
* **Request Body:**
  ```json
  {
    "challenge_token": "wyAf0s8vH54s59bhs9q2s8G1rZ0D9F8ynJV2K4onc1U6R6TCxI3N1xuNYzwjK7K5",
    "code": "123456",
    "remember_device": true,
    "device_name": "Chrome on macOS"
  }
  ```

* **Success Response:** `200 OK`
  ```json
  {
    "data": {
      "id": "01JMCZ77D34R33E893P14D8W4B",
      "username": "alice",
      "email": "alice@example.com",
      "email_verified_at": null,
      "created_at": "2026-11-14T12:00:00.000000Z"
    },
    "meta": {
      "device_token": "64_character_hex_or_string" // present if remember_device was true
    }
  }
  ```
  *(Also sets a secure HttpOnly `device_token` cookie for 1 year so subsequent logins from this browser are recognized automatically).*

* **Error Responses:**
  * `400 Bad Request` (`code: "invalid_verification_code"`):
    ```json
    {
      "message": "Invalid verification code.",
      "code": "invalid_verification_code"
    }
    ```
  * `400 Bad Request` (`code: "challenge_limit_exceeded"`):
    ```json
    {
      "message": "Too many failed verification attempts. Please log in again.",
      "code": "challenge_limit_exceeded"
    }
    ```
  * `400 Bad Request` (`code: "invalid_or_expired_challenge"`):
    ```json
    {
      "message": "Invalid or expired verification challenge.",
      "code": "invalid_or_expired_challenge"
    }
    ```
  * `429 Too Many Requests` (`code: "too_many_requests"`).

---

### 4.4 Get Current User Profile (`auth/me`)
Retrieves profile for the currently authenticated session. Call this on page reload or app mount to restore user state.

* **Method:** `GET`
* **Path:** `/api/v1/auth/me`
* **Auth Required:** Yes (Active `customer` session)

* **Success Response:** `200 OK`
  ```json
  {
    "data": {
      "id": "01JMCZ77D34R33E893P14D8W4B",
      "username": "alice",
      "email": "alice@example.com",
      "email_verified_at": null,
      "created_at": "2026-11-14T12:00:00.000000Z"
    },
    "meta": {}
  }
  ```

* **Error Responses:**
  * `401 Unauthorized`: Session expired or unauthenticated.
    ```json
    {
      "message": "Unauthenticated.",
      "code": "unauthenticated"
    }
    ```

---

### 4.5 Logout
Terminates the session, purges backend session storage, and rotates tokens.

* **Method:** `POST`
* **Path:** `/api/v1/auth/logout`
* **Auth Required:** Yes (Active `customer` session)
* **Request Body:** `{}` (empty object)

* **Success Response:** `200 OK`
  ```json
  {
    "data": {
      "message": "Logged out successfully."
    },
    "meta": {}
  }
  ```

---

### 4.6 Forgot Password
Initiates a password reset flow.

* **Method:** `POST`
* **Path:** `/api/v1/auth/forgot-password`
* **Auth Required:** No
* **Rate Limit:** 5 requests / minute (`throttle:password-reset`).
* **Request Body:**
  ```json
  {
    "email": "alice@example.com"
  }
  ```

* **Success Response:** `200 OK` (Strict Anti-Enumeration: returns the exact same success response whether or not the email exists):
  ```json
  {
    "data": {
      "message": "If that email address is in our database, we have sent a password reset link."
    },
    "meta": {}
  }
  ```

* **Email Sent:** Contains a reset link pointing back to the frontend:
  `${FRONTEND_URL}/auth/reset-password?token=${token}&email=${encodedEmail}`
  *(Link is single-use and expires in 60 minutes).*

---

### 4.7 Reset Password
Completes the password reset using the token from the email.

* **Method:** `POST`
* **Path:** `/api/v1/auth/reset-password`
* **Auth Required:** No
* **Behavior:**
  * Single-use token (cannot be replayed).
  * Automatically purges all existing database sessions for that user.
  * Clears any active temporary account lockout.
* **Request Body:**
  ```json
  {
    "token": "49f6ad0a5639f75fbfa8808d254f1...",
    "email": "alice@example.com",
    "password": "NewSecurePassword123!",
    "password_confirmation": "NewSecurePassword123!"
  }
  ```

* **Success Response:** `200 OK`
  ```json
  {
    "data": {
      "message": "Your password has been reset."
    },
    "meta": {}
  }
  ```

* **Error Responses:**
  * `400 Bad Request` (Invalid, expired, or already-used token, or non-existent email):
    ```json
    {
      "message": "Invalid or expired password reset token.",
      "code": "invalid_reset_token"
    }
    ```
  * `422 Unprocessable Entity`: Password validation error or confirmation mismatch.

---

## 5. Typical Frontend Workflows

### Workflow 1: App Boot / Session Hydration
```typescript
import { apiClient } from '@/lib/api/client';

export async function checkSession() {
  try {
    const { data } = await apiClient.get('/api/v1/auth/me');
    return data.data; // Authenticated user
  } catch (error) {
    // 401 unauthenticated - user is a guest, clear client auth state
    return null;
  }
}
```

### Workflow 2: Login
```typescript
import { apiClient, initCsrf } from '@/lib/api/client';

export async function login(loginIdentifier: string, password: string) {
  // 1. Initialize CSRF protection
  await initCsrf();

  // 2. Post login credentials
  const { data } = await apiClient.post('/api/v1/auth/login', {
    login: loginIdentifier,
    password,
  });

  return data.data; // Authenticated Customer Resource
}
```

### Workflow 3: Logout
```typescript
import { apiClient } from '@/lib/api/client';

export async function logout() {
  await apiClient.post('/api/v1/auth/logout');
  // Clear frontend user store / redirect to /login
}
```

### Workflow 4: Password Reset Page (`/auth/reset-password`)
In your Next.js page component:
```typescript
// Read searchParams
const token = searchParams.get('token');
const email = searchParams.get('email');

// Submit new password
await apiClient.post('/api/v1/auth/reset-password', {
  token,
  email,
  password: newPassword,
  password_confirmation: confirmPassword,
});
// Redirect to /auth/login with success banner
```

---

## 6. Key Security Rules for Frontend Developers

1. **Anti-Enumeration is Intentional:** Do not attempt to infer account existence from error messages. The backend purposely returns identical `401` on login and `200` on forgot-password.
2. **Never Persist Passwords or Tokens:** No authentication tokens exist in localStorage. The cookie is automatically handled by the browser.
3. **Handle 429 Status Gracefully:** When a user hits rate limiting, inspect `response.headers['retry-after']` to display a countdown (e.g., *"Too many attempts. Please wait 45 seconds."*).
4. **Local Port:** Always point `NEXT_PUBLIC_API_URL` to `http://localhost:8070`.

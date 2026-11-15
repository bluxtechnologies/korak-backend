<?php

namespace App\Services\Auth;

use App\Models\Customer;

class DeviceVerificationResult
{
    /**
     * Create a new device verification result instance.
     */
    public function __construct(
        public bool $successful,
        public ?Customer $customer = null,
        public ?string $errorMessage = null,
        public ?string $errorCode = null,
        public int $status = 400,
    ) {}

    /**
     * Create a successful verification result.
     */
    public static function success(Customer $customer): self
    {
        return new self(
            successful: true,
            customer: $customer,
        );
    }

    /**
     * Create a failed verification result.
     */
    public static function failure(string $message, string $code, int $status = 400): self
    {
        return new self(
            successful: false,
            errorMessage: $message,
            errorCode: $code,
            status: $status,
        );
    }
}

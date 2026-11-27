<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $service->unavailable_headline ?? 'Service Unavailable' }} - Korak</title>
    <!-- Favicon -->
    <link rel="shortcut icon" href="{{ asset('admin/assets/images/favicon.ico') }}">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Tabler Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #0b0f19 0%, #111827 50%, #1a1625 100%);
            color: #f3f4f6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 24px;
            position: relative;
            overflow-x: hidden;
        }
        .glow-sphere {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
            opacity: 0.18;
            pointer-events: none;
            z-index: 0;
        }
        .glow-1 {
            width: 420px;
            height: 420px;
            background: #ef4444;
            top: -100px;
            right: -100px;
        }
        .glow-2 {
            width: 380px;
            height: 380px;
            background: #7266ed;
            bottom: -100px;
            left: -100px;
        }
        .preview-banner {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #f59e0b;
            color: #000;
            font-weight: 700;
            font-size: 13px;
            text-align: center;
            padding: 8px 16px;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        .card-container {
            position: relative;
            z-index: 1;
            max-width: 540px;
            width: 100%;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 48px 36px;
            text-align: center;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), inset 0 1px 1px rgba(255, 255, 255, 0.1);
        }
        .icon-wrapper {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 96px;
            height: 96px;
            border-radius: 28px;
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, rgba(220, 38, 38, 0.05) 100%);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
            font-size: 44px;
            margin-bottom: 28px;
            animation: pulse-slow 3s infinite ease-in-out;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 9999px;
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 18px;
        }
        .status-indicator {
            width: 8px;
            height: 8px;
            background: #ef4444;
            border-radius: 50%;
            box-shadow: 0 0 10px #ef4444;
        }
        h1 {
            font-size: 26px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 14px;
            line-height: 1.3;
            letter-spacing: -0.02em;
        }
        .message-text {
            font-size: 15px;
            line-height: 1.6;
            color: #9ca3af;
            margin-bottom: 32px;
            max-width: 440px;
            margin-left: auto;
            margin-right: auto;
        }
        .status-box {
            background: rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 14px;
            padding: 16px 20px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            text-align: left;
        }
        .status-box-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .status-box-label {
            font-size: 11px;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.05em;
        }
        .status-box-val {
            font-size: 13px;
            font-weight: 600;
            color: #e5e7eb;
        }
        .btn-home {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 28px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #ffffff;
            font-weight: 600;
            font-size: 14px;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-home:hover {
            background: rgba(255, 255, 255, 0.14);
            border-color: rgba(255, 255, 255, 0.25);
            color: #ffffff;
            transform: translateY(-1px);
        }
        .footer-note {
            margin-top: 36px;
            font-size: 12px;
            color: #4b5563;
        }
        @keyframes pulse-slow {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.04);
            }
        }
    </style>
</head>
<body>
    @if(!empty($isPreview))
        <div class="preview-banner">
            <i class="ti ti-eye"></i> Admin Preview: This is how visitors see this service when it is deactivated.
        </div>
    @endif

    <div class="glow-sphere glow-1"></div>
    <div class="glow-sphere glow-2"></div>

    <div class="card-container">
        <div class="icon-wrapper">
            <i class="{{ $service->icon ?: 'ti ti-alert-triangle' }}"></i>
        </div>

        <div class="status-badge">
            <span class="status-indicator"></span>
            Service Temporarily Offline
        </div>

        <h1>{{ $service->unavailable_headline ?: 'Service Unavailable' }}</h1>

        <p class="message-text">
            {{ $service->maintenance_message ?: "{$service->name} is currently undergoing scheduled system updates. Our team is actively working on it. Please check back shortly." }}
        </p>

        <div class="status-box">
            <div class="status-box-item">
                <span class="status-box-label">Service</span>
                <span class="status-box-val">{{ $service->name }}</span>
            </div>
            <div class="status-box-item" style="text-align: right;">
                <span class="status-box-label">Current Status</span>
                <span class="status-box-val" style="color: #f87171;">Under Maintenance</span>
            </div>
        </div>

        <div>
            <a href="/" class="btn-home">
                <i class="ti ti-arrow-left"></i> Return to Homepage
            </a>
        </div>

        <div class="footer-note">
            &copy; {{ date('Y') }} Korak Digitals. All rights reserved.
        </div>
    </div>
</body>
</html>

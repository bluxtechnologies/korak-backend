<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $service->name }} - Korak Services</title>
    <link rel="shortcut icon" href="{{ asset('admin/assets/images/favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #0b0f19;
            color: #f3f4f6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            max-width: 500px;
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
        }
        .icon {
            font-size: 48px;
            color: #10b981;
            margin-bottom: 20px;
        }
        .badge {
            display: inline-block;
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 16px;
        }
        h1 { font-size: 24px; margin-bottom: 12px; }
        p { color: #9ca3af; font-size: 14px; line-height: 1.6; margin-bottom: 24px; }
        .btn {
            display: inline-block;
            background: #7266ed;
            color: #fff;
            padding: 10px 24px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">
            <i class="{{ $service->icon }}"></i>
        </div>
        <div class="badge">Operational & Active</div>
        <h1>{{ $service->name }}</h1>
        <p>{{ $service->description ?? 'This service is online and fully functional.' }}</p>
        <a href="/" class="btn">Go to Dashboard</a>
    </div>
</body>
</html>

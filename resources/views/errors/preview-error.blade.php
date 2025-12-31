<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview Error</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: #f3f4f6;
            color: #374151;
        }
        .error-container {
            text-align: center;
            padding: 2rem;
            max-width: 400px;
        }
        .error-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 1rem;
            color: #ef4444;
        }
        h1 {
            font-size: 1.25rem;
            margin: 0 0 0.5rem;
        }
        p {
            color: #6b7280;
            font-size: 0.875rem;
            margin: 0;
        }
        .domain {
            margin-top: 1rem;
            padding: 0.5rem 1rem;
            background: #e5e7eb;
            border-radius: 0.5rem;
            font-family: monospace;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <svg class="error-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
        </svg>
        <h1>Preview Unavailable</h1>
        <p>{{ $message ?? 'Unable to load the preview.' }}</p>
        @if(isset($domain))
            <div class="domain">{{ $domain }}</div>
        @endif
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Invitation</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .button {
            display: inline-block;
            background-color: #3b82f6;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 500;
            margin: 20px 0;
        }
        .button:hover {
            background-color: #2563eb;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <h1>You're Invited!</h1>

    <p>You've been invited to join <strong>{{ $teamName }}</strong> on {{ config('app.name') }}.</p>

    <p>Click the button below to accept this invitation:</p>

    <a href="{{ $acceptUrl }}" class="button">Accept Invitation</a>

    <p>This invitation will expire on {{ $expiresAt->format('F j, Y \a\t g:i A') }} (48 hours from when it was sent).</p>

    <p>If you don't have an account yet, you'll be able to create one after clicking the link above.</p>

    <div class="footer">
        <p>If you didn't expect this invitation, you can safely ignore this email.</p>
        <p>If the button above doesn't work, copy and paste this link into your browser:</p>
        <p style="word-break: break-all;">{{ $acceptUrl }}</p>
    </div>
</body>
</html>

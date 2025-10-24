<!-- resources/views/emails/reset-password.blade.php -->
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
</head>

<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2>Reset Your Password</h2>

        <p>Hello {{ $user->name }},</p>

        <p>You are receiving this email because we received a password reset request for your account.</p>

        <p style="margin: 30px 0;">
            <a href="{{ $resetUrl }}"
                style="background-color: #4CAF50; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;">
                Reset Password
            </a>
        </p>

        <p>Or copy and paste this link into your browser:</p>
        <p style="word-break: break-all; color: #666;">{{ $resetUrl }}</p>

        <p style="margin-top: 30px; color: #666; font-size: 14px;">
            This password reset link will expire in 60 minutes.
        </p>

        <p style="color: #666; font-size: 14px;">
            If you did not request a password reset, no further action is required.
        </p>

        <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">

        <p style="color: #999; font-size: 12px;">
            If you're having trouble clicking the "Reset Password" button, copy and paste the URL above into your web
            browser.
        </p>
    </div>
</body>

</html>

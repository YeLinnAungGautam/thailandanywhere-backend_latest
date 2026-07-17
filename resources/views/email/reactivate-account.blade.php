<!-- resources/views/emails/reactivate-account.blade.php -->
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reactivate Your Account</title>
</head>

<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2>Welcome Back!</h2>

        <p>Hello {{ $user->name }},</p>

        <p>We noticed your account was previously deleted. No problem — you can reactivate it by setting a new password
            below.</p>

        <p style="margin: 30px 0;">
            <a href="{{ $resetUrl }}"
                style="background-color: #4CAF50; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;">
                Reactivate My Account
            </a>
        </p>

        <p>Or copy and paste this link into your browser:</p>
        <p style="word-break: break-all; color: #666;">{{ $resetUrl }}</p>

        <p style="margin-top: 30px; color: #666; font-size: 14px;">
            This reactivation link will expire in 60 minutes.
        </p>

        <p style="color: #666; font-size: 14px;">
            If you did not request this, no further action is required — your account will remain deleted.
        </p>

        <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">

        <p style="color: #999; font-size: 12px;">
            If you're having trouble clicking the "Reactivate My Account" button, copy and paste the URL above into your
            web
            browser.
        </p>
    </div>
</body>

</html>

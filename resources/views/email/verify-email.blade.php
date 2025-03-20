{{-- resources/views/emails/verify-email.blade.php --}}
<!DOCTYPE html>
<html>

<head>
    <title>Verify Your Email</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .verification-code {
            font-size: 32px;
            font-weight: bold;
            letter-spacing: 5px;
            text-align: center;
            padding: 20px;
            margin: 20px 0;
            background-color: #f5f5f5;
            border-radius: 5px;
            color: #333;
        }

        .footer {
            margin-top: 30px;
            font-size: 12px;
            color: #777;
        }
    </style>
</head>

<body>
    <h2>Hello {{ $user->first_name }},</h2>

    <p>Thank you for registering with our application. Please use the verification code below to verify your email
        address:</p>

    <div class="verification-code">{{ $verificationCode }}</div>

    <p>Enter this code in the email verification page to complete your registration.</p>

    <p>This verification code will expire in 24 hours.</p>

    <p>If you did not create an account, no further action is required.</p>

    <div class="footer">
        <p>Regards,<br>Thanywhere Application Team</p>
    </div>
</body>

</html>

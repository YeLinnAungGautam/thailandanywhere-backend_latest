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

        .button {
            display: inline-block;
            background-color: #4CAF50;
            color: white !important;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 5px;
            margin: 20px 0;
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

    <p>Thank you for registering with our application. Please click the button below to verify your email address:</p>

    <a href="{{ $verificationUrl }}" class="button">Verify Email Address</a>

    <p>If the button doesn't work, copy and paste the following URL into your browser:</p>

    <p>{{ $verificationUrl }}</p>

    <p>This verification link will expire in 24 hours.</p>

    <p>If you did not create an account, no further action is required.</p>

    <div class="footer">
        <p>Regards,<br>Your Application Team</p>
    </div>
</body>

</html>

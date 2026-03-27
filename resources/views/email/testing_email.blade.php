<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Test Email</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <div style="text-align: center; margin-bottom: 20px;">
            <h1 style="color: #2c3e50; margin: 0;">Hotel Service</h1>
        </div>
        
        <h2 style="color: #333333; font-size: 20px;">Hello!</h2>
        
        <p style="color: #555555; line-height: 1.6; font-size: 16px;">
            This is a test email sent from the <strong>Thailand Anywhere</strong> application to verify the new email integration.
        </p>

        <p style="color: #555555; line-height: 1.6; font-size: 16px;">
            It uses the custom <code>UsesHotelServiceMail</code> trait to dynamically inject the Hotel Service mailer credentials from your environment configuration.
        </p>
        
        <div style="background-color: #e8f4fd; padding: 15px; border-left: 4px solid #3498db; margin: 25px 0;">
            <p style="margin: 0; color: #2980b9; font-weight: bold;">
                Success! If you are reading this, the integration works perfectly.
            </p>
        </div>

        <p style="color: #555555; line-height: 1.6; font-size: 16px;">
            Best regards,<br>
            <strong>Thailand Anywhere Team</strong>
        </p>
        
        <hr style="border: none; border-top: 1px solid #eeeeee; margin: 30px 0;">
        
        <p style="font-size: 12px; color: #999999; text-align: center;">
            &copy; {{ date('Y') }} Thailand Anywhere. All rights reserved.
        </p>
    </div>
</body>
</html>

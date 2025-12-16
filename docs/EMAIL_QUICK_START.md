# Email Management System - Quick Start Guide

## Overview
This system provides comprehensive email tracking, Gmail integration, and retry functionality for all emails sent from your Laravel application.

## Quick Setup

### 1. Install Dependencies
```bash
composer require google/apiclient
```

### 2. Gmail API Setup
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing
3. Enable Gmail API
4. Create OAuth 2.0 credentials
5. Download credentials JSON file

### 3. Environment Configuration
Add to your `.env` file:
```env
GOOGLE_APPLICATION_CREDENTIALS=/path/to/your/credentials.json
GMAIL_API_ENABLED=true
```

### 4. Run Migrations
```bash
php artisan migrate
```

## Testing the System

### 1. Test Reservation Email with Tracking
```php
// This will now automatically create EmailLog entries
$reservationController = new ReservationController();
$reservationController->sendNotifyEmail($reservationId);
```

### 2. Check Email Status
```bash
# Get email statistics
GET /admin/email-status/stats

# Get failed emails
GET /admin/email-status/failed

# View dashboard
GET /admin/email-status/dashboard
```

### 3. Retry Failed Emails
```bash
# Retry single email
POST /admin/email-status/{email_log_id}/retry

# Bulk retry failed emails
POST /admin/email-status/bulk-retry
```

## API Endpoints Summary

### Email Logs Management
- `GET /admin/email-logs` - List all emails
- `GET /admin/email-logs/{id}` - View specific email
- `POST /admin/email-logs` - Send new email
- `PUT /admin/email-logs/{id}` - Update email
- `DELETE /admin/email-logs/{id}` - Delete email
- `PATCH /admin/email-logs/{id}/mark-read` - Mark as read
- `POST /admin/email-logs/bulk-mark-read` - Bulk mark as read

### Email Status Management
- `GET /admin/email-status/dashboard` - Email dashboard
- `GET /admin/email-status/failed` - Failed emails list
- `GET /admin/email-status/stats` - Email statistics
- `POST /admin/email-status/{id}/retry` - Retry failed email
- `POST /admin/email-status/bulk-retry` - Bulk retry

## Queue Processing

Make sure your queue worker is running:
```bash
php artisan queue:work
```

The email job will automatically retry up to 3 times on failure.

## Monitoring

Check the email logs table to monitor:
- Email status (pending, sent, failed, delivered, read)
- Failure reasons
- Retry attempts
- Gmail thread IDs
- Message IDs

## Integration Notes

The system automatically integrates with your existing reservation email notifications. All emails sent through `ReservationController::sendNotifyEmail()` are now tracked in the database with retry functionality.

## Gmail Integration Features

- **Send Emails**: Send through Gmail API
- **Sync Replies**: Automatically sync customer replies
- **Thread Management**: Track email conversations
- **Delivery Tracking**: Monitor email delivery status
- **Read Receipts**: Track when emails are read (where supported)

## Troubleshooting

1. **Gmail API Issues**: Check your credentials and API quotas
2. **Queue Processing**: Ensure queue worker is running
3. **Database Issues**: Check email_logs table structure
4. **Permission Issues**: Verify Gmail API scopes in your OAuth setup

## Next Steps

1. Test with a real Gmail account
2. Set up proper error monitoring
3. Configure email templates
4. Set up automated retry schedules
5. Add email analytics dashboard

The system is now ready for production use with comprehensive email tracking and management capabilities!

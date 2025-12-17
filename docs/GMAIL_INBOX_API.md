# Gmail Inbox API Documentation

This API provides comprehensive Gmail-like functionality for managing emails, including inbox management, threading, replies, and synchronization with Gmail.

## Authentication

All endpoints require admin authentication via Sanctum token.

### Gmail Setup Endpoints

#### Get Authorization URL
```http
GET /admin/gmail/auth/url
```

**Response:**
```json
{
  "success": true,
  "data": {
    "auth_url": "https://accounts.google.com/oauth/v2/auth?..."
  },
  "message": "Gmail authorization URL generated"
}
```

#### Handle OAuth Callback
```http
POST /admin/gmail/auth/callback
```

**Request:**
```json
{
  "code": "4/0AY0e-g7..."
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "email_address": "your@email.com",
    "messages_total": 1234,
    "threads_total": 567,
    "token_expires_at": "2024-12-21T10:30:00Z"
  },
  "message": "Gmail authorization successful"
}
```

#### Check Connection Status
```http
GET /admin/gmail/auth/status
```

**Response:**
```json
{
  "success": true,
  "data": {
    "connected": true,
    "email_address": "your@email.com",
    "messages_total": 1234,
    "threads_total": 567,
    "token_expires_at": "2024-12-21T10:30:00Z"
  }
}
```

#### Disconnect Gmail
```http
DELETE /admin/gmail/auth/disconnect
```

## Inbox Management

#### Get Inbox Emails
```http
GET /admin/gmail/inbox?per_page=20&status=sent&search=booking&unread_only=true
```

**Query Parameters:**
- `per_page` (int): Number of emails per page (default: 20)
- `status` (string): Filter by status (sent, received, failed, etc.)
- `search` (string): Search in subject, body, from/to emails
- `unread_only` (boolean): Show only unread emails
- `start_date` (string): Filter from date (Y-m-d)
- `end_date` (string): Filter to date (Y-m-d)
- `booking_id` (int): Filter by related booking

**Response:**
```json
{
  "success": true,
  "data": {
    "emails": [
      {
        "id": 1,
        "thread_id": "thread_12345",
        "type": "sent",
        "type_label": "Sent",
        "from": {
          "email": "booking@yourcompany.com",
          "name": "Booking Team",
          "display": "Booking Team <booking@yourcompany.com>"
        },
        "to": {
          "email": "customer@example.com",
          "display": "customer@example.com"
        },
        "subject": "Booking Confirmation - #CRM-123",
        "preview": "Your booking has been confirmed...",
        "status": "sent",
        "status_color": "success",
        "is_read": true,
        "has_attachments": true,
        "attachment_count": 2,
        "dates": {
          "created_at": "2024-12-14T10:30:00Z",
          "sent_at": "2024-12-14T10:30:05Z",
          "delivered_at": "2024-12-14T10:30:10Z",
          "read_at": "2024-12-14T11:00:00Z"
        },
        "related_booking": {
          "id": 123,
          "crm_id": "CRM-123",
          "customer_name": "John Doe"
        }
      }
    ],
    "pagination": {
      "current_page": 1,
      "last_page": 5,
      "per_page": 20,
      "total": 100,
      "has_more": true
    },
    "stats": {
      "total_emails": 1000,
      "unread_count": 25,
      "sent_count": 800,
      "received_count": 200,
      "failed_count": 5,
      "today_count": 15,
      "this_week_count": 87
    }
  }
}
```

#### Get Email Threads
```http
GET /admin/gmail/threads?per_page=15&search=booking&unread_only=true
```

**Response:**
```json
{
  "success": true,
  "data": {
    "threads": [
      {
        "thread_id": "thread_12345",
        "subject": "Booking Confirmation - #CRM-123",
        "message_count": 3,
        "has_unread": false,
        "has_attachments": true,
        "last_activity": "2024-12-14T15:30:00Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "last_page": 3,
      "per_page": 15,
      "total": 45,
      "has_more": true
    }
  }
}
```

#### Get Specific Thread
```http
GET /admin/gmail/threads/{threadId}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "thread_id": "thread_12345",
    "subject": "Booking Confirmation - #CRM-123",
    "participants": ["booking@yourcompany.com", "customer@example.com"],
    "message_count": 3,
    "emails": [
      {
        "id": 1,
        "type": "sent",
        "from": {
          "email": "booking@yourcompany.com",
          "name": "Booking Team"
        },
        "to": {
          "email": "customer@example.com"
        },
        "subject": "Booking Confirmation - #CRM-123",
        "body": "<html><body>Your booking has been confirmed...</body></html>",
        "plain_body": "Your booking has been confirmed...",
        "attachments": ["booking_voucher.pdf", "itinerary.pdf"],
        "status": "delivered",
        "is_read": true,
        "created_at": "2024-12-14T10:30:00Z"
      }
    ]
  }
}
```

## Email Actions

#### Compose New Email
```http
POST /admin/gmail/compose
```

**Request:**
```json
{
  "to": "customer@example.com",
  "cc": ["manager@yourcompany.com"],
  "subject": "Your Booking Update",
  "body": "<html><body>Dear Customer, <p>Your booking has been updated...</p></body></html>",
  "booking_id": 123,
  "related_model_type": "App\\Models\\BookingItem",
  "related_model_id": 456,
  "attachments": [
    {
      "name": "updated_voucher.pdf",
      "content": "base64_encoded_content",
      "type": "application/pdf"
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "email": {
      "id": 123,
      "thread_id": "thread_67890",
      "status": "sent",
      "gmail_message_id": "msg_abc123"
    },
    "message_id": "msg_abc123"
  },
  "message": "Email sent successfully"
}
```

#### Send Reply
```http
POST /admin/gmail/emails/{emailId}/reply
```

**Request:**
```json
{
  "body": "<html><body>Thank you for your inquiry. Here's the information you requested...</body></html>",
  "subject": "Re: Booking Inquiry",
  "attachments": []
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "email": {
      "id": 124,
      "thread_id": "thread_12345",
      "status": "sent",
      "in_reply_to": "msg_original123"
    },
    "message_id": "msg_reply456"
  },
  "message": "Reply sent successfully"
}
```

#### Mark Emails as Read
```http
PATCH /admin/gmail/emails/mark-read
```

**Request:**
```json
{
  "email_ids": [1, 2, 3, 4, 5]
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "updated_count": 5
  },
  "message": "Emails marked as read"
}
```

#### Mark Emails as Unread
```http
PATCH /admin/gmail/emails/mark-unread
```

**Request:**
```json
{
  "email_ids": [1, 2, 3]
}
```

#### Archive Emails
```http
PATCH /admin/gmail/emails/archive
```

**Request:**
```json
{
  "email_ids": [1, 2, 3]
}
```

#### Delete Emails (Soft Delete)
```http
DELETE /admin/gmail/emails
```

**Request:**
```json
{
  "email_ids": [1, 2, 3]
}
```

#### Sync from Gmail
```http
POST /admin/gmail/sync?limit=50
```

**Response:**
```json
{
  "success": true,
  "data": {
    "synced_count": 47
  },
  "message": "Gmail sync completed successfully"
}
```

## Error Handling

All endpoints return consistent error responses:

```json
{
  "success": false,
  "data": null,
  "message": "Error description",
  "errors": {
    "field_name": ["Validation error message"]
  }
}
```

**Common HTTP Status Codes:**
- `200` - Success
- `400` - Bad Request (validation errors)
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `500` - Internal Server Error

## Email Status Values

- `pending` - Email queued for sending
- `sent` - Email sent successfully
- `delivered` - Email delivered to recipient
- `read` - Email opened by recipient
- `failed` - Email sending failed

## Setup Instructions

1. **Install Google Client Library:**
   ```bash
   composer require google/apiclient
   ```

2. **Configure Environment Variables:**
   ```env
   GOOGLE_CLIENT_ID=your_google_client_id
   GOOGLE_CLIENT_SECRET=your_google_client_secret
   GOOGLE_REDIRECT_URL=your_redirect_url
   ```

3. **Run Migrations:**
   ```bash
   php artisan migrate
   ```

4. **Authorize Gmail Access:**
   - Call `GET /admin/gmail/auth/url`
   - Redirect user to the authorization URL
   - Handle callback with `POST /admin/gmail/auth/callback`

5. **Start Using the API:**
   - Sync existing emails: `POST /admin/gmail/sync`
   - Browse inbox: `GET /admin/gmail/inbox`
   - Send emails: `POST /admin/gmail/compose`

## Integration Notes

- All reservation emails sent through `ReservationController::sendNotifyEmail()` are automatically tracked
- Email logs are stored locally for offline access and reporting
- Gmail API quotas apply - monitor usage in Google Cloud Console
- Access tokens are automatically refreshed when expired
- Failed emails can be retried through the Email Status API

This API provides a complete Gmail-like experience within your admin panel!

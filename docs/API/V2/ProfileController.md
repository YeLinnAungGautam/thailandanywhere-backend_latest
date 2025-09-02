# ProfileController API Documentation

## Overview
The `ProfileController` provides endpoints for authenticated users to view and update their profile, including changing their password and uploading a profile image.

---

## Endpoints

### 1. GET /api/v2/profile
**Description:** Get the authenticated user's profile.
**Method:** GET
**Auth Required:** Yes (Bearer Token)

#### Response
```json
{
  "success": true,
  "message": "User profile retrieved successfully",
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "0912345678",
    "address": "123 Main St",
    "gender": "male",
    "dob": "1990-01-01",
    "profile": "profile.jpg",
    // ...other user fields
  }
}
```

---

### 2. PUT /api/v2/profile
**Description:** Update the authenticated user's profile.
**Method:** PUT
**Auth Required:** Yes (Bearer Token)
**Payload:** Multipart/form-data (for profile image)

#### Request Body
| Field      | Type     | Required | Description                |
|------------|----------|----------|----------------------------|
| name       | string   | No       | User's name                |
| email      | string   | No       | User's email               |
| phone      | string   | No       | User's phone number        |
| address    | string   | No       | User's address             |
| gender     | string   | No       | User's gender              |
| dob        | date     | No       | Date of birth (YYYY-MM-DD) |
| profile    | file     | No       | Profile image (jpeg, png)  |

#### Response
```json
{
  "success": true,
  "message": "Successfully updated profile",
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "profile": "profile.jpg",
    // ...other user fields
  }
}
```

---

### 3. POST /api/v2/profile/change-password
**Description:** Change the authenticated user's password.
**Method:** POST
**Auth Required:** Yes (Bearer Token)

#### Request Body
| Field        | Type     | Required | Description                  |
|--------------|----------|----------|------------------------------|
| old_password | string   | Yes      | Current password             |
| password     | string   | Yes      | New password                 |
| password_confirmation | string | Yes | Confirmation of new password |

#### Response (Success)
```json
{
  "success": true,
  "message": "Successfully changed password",
  "data": {
    "id": 1,
    "name": "John Doe",
    // ...other user fields
  }
}
```

#### Response (Failure)
```json
{
  "success": false,
  "message": "Password is incorrect",
  "data": null
}
```

---

## Error Responses
- All endpoints return a consistent error format:
```json
{
  "success": false,
  "message": "Failed to update profile",
  "data": null
}
```

---

## Notes
- All endpoints require authentication (Bearer token).
- Profile image uploads are stored in the `images/` directory.
- Changing password will invalidate all previous tokens (logout everywhere).
- Validation errors will return HTTP 422 with details.

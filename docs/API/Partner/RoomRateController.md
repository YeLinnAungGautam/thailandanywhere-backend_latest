# RoomRateController API Documentation

Manage daily room rates, stock, and discounts for each partner and room. Supports batch upload for multiple days with booking conflict prevention.

---

## Endpoints

### GET /api/partner/room-rates
**Description:** List room rates. Supports filtering by partner, room, and date.
**Query Parameters:**
- `partner_id` (optional)
- `room_id` (optional)
- `date` (optional, format: YYYY-MM-DD)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "partner_id": 2,
      "room_id": 123,
      "date": "2025-10-01",
      "stock": 5,
      "cost_price": 100.00,
      "selling_price": 120.00,
      "discount": 10.00,
      "created_at": "2025-10-01T09:00:00Z",
      "updated_at": "2025-10-01T09:00:00Z"
    }
  ]
}
```

---

### GET /api/partner/room-rates/{id}
**Description:** Get a single room rate by ID.

**Response:**
```json
{
  "data": {
    "id": 1,
    "partner_id": 2,
    "room_id": 123,
    "date": "2025-10-01",
    "stock": 5,
    "cost_price": 100.00,
    "selling_price": 120.00,
    "discount": 10.00,
    "created_at": "2025-10-01T09:00:00Z",
    "updated_at": "2025-10-01T09:00:00Z"
  }
}
```

---

### POST /api/partner/room-rates
**Description:** Batch create or update room rates for multiple days. Prevents setting stock below already booked quantity for each day.

**Request Body:**
```json
{
  "room_id": 123,
  "stock": 5,
  "cost_price": 100.00,
  "selling_price": 120.00,
  "discount": 10.00,
  "dates": ["2025-10-01", "2025-10-02", "2025-10-03"]
}
```

**Validation:**
- `room_id`: required, exists
- `dates`: required, array of dates (YYYY-MM-DD)
- `stock`: required, integer >= 0
- `cost_price`: required, numeric >= 0
- `selling_price`: required, numeric >= 0
- `discount`: optional, numeric >= 0

**Response:**
```json
{
  "results": [
    {
      "date": "2025-10-01",
      "success": true,
      "rate": {
        "id": 1,
        "room_id": 123,
        "date": "2025-10-01",
        "stock": 5,
        "cost_price": 100.00,
        "selling_price": 120.00,
        "discount": 10.00
      }
    },
    {
      "date": "2025-10-02",
      "error": "Cannot set stock below already booked quantity (6) for this room and date."
    }
  ],
  "message": "Batch room rates processed"
}
```

---

### PUT /api/partner/room-rates/{id}
**Description:** Update a single room rate. Checks booking conflicts if stock is changed.

**Request Body:**
```json
{
  "stock": 4,
  "cost_price": 100.00,
  "selling_price": 120.00,
  "discount": 5.00
}
```

**Response:**
```json
{
  "data": {
    "id": 1,
    "room_id": 123,
    "date": "2025-10-01",
    "stock": 4,
    "cost_price": 100.00,
    "selling_price": 120.00,
    "discount": 5.00
  },
  "message": "Room rate updated"
}
```

---

### DELETE /api/partner/room-rates/{id}
**Description:** Delete a room rate.

**Response:**
```json
{
  "message": "Room rate deleted"
}
```

---

## Error Responses

**Booking conflict (422):**
```json
{
  "date": "2025-10-02",
  "error": "Cannot set stock below already booked quantity (6) for this room and date."
}
```

**Validation error (422):**
```json
{
  "error": "The given data was invalid.",
  "errors": {
    "room_id": ["The room_id field is required."],
    "dates.0": ["The dates.0 field must be a valid date."]
  }
}
```

---

## Notes
- Batch POST is recommended for mobile/web bulk uploads.
- Booking conflict logic ensures you cannot overbook rooms for any day.
- All endpoints require authentication as a partner.
- Model: `App\Models\PartnerRoomRate`
- Controller: `App\Http\Controllers\API\Partner\RoomRateController`

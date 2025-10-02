# RoomRateController API Documentation

Manage daily room rates, stock, and discounts for specific dates. Supports batch operations and booking conflict prevention.

---

## Endpoints

### POST /api/partner/hotels/{hotel}/rooms/{room}/rates
**Description:** Create or update daily room rates for specific dates. Supports batch operations and booking conflict checks.

**Path Parameters:**
- `hotel` (required): Hotel ID
- `room` (required): Room ID

**Request Body:**
```json
{
  "dates": ["2025-10-15", "2025-10-16", "2025-10-17"],
  "stock": 5,
  "discount": 200.00
}
```

**Special Parameters:**
- `type`: Set to `"all"` to automatically apply to all dates in current month

**Batch Request (Current Month):**
```json
{
  "type": "all",
  "stock": 8,
  "discount": 150.00
}
**Validation:**
- `dates`: required (unless type=all), array of dates (YYYY-MM-DD)
- `stock`: required, integer >= 0
- `discount`: optional, numeric >= 0
- `type`: optional, string, accepts "all"

**Response:**
```json
{
  "success": true,
  "message": "Room Detail",
  "data": {
    "id": 123,
    "name": "Deluxe Room",
    "hotel": {
      "id": 45,
      "name": "Grand Hotel"
    },
    "room_price": 1500,
    "cost": 800,
    "description": "Spacious room with city view",
    "images": [
      {
        "id": 1,
        "url": "room1.jpg"
      }
    ],
    "room_rates": {
      "2025-10-15": {
        "room_name": "Deluxe Room",
        "stock": 5,
        "available_rooms": 3,
        "room_price": 1500,
        "current_price": 1300,
        "discount": 200
      },
      "2025-10-16": {
        "room_name": "Deluxe Room",
        "stock": 5,
        "available_rooms": 5,
        "room_price": 1500,
        "current_price": 1300,
        "discount": 200
      }
    }
  }
}
```

---

### DELETE /api/partner/hotels/{hotel}/rooms/{room}/rates
**Description:** Delete a specific daily room rate for a date.

**Request Body:**
```json
{
  "date": "2025-10-15"
}
```

**Validation:**
- `date`: required, date format YYYY-MM-DD

**Response:**
```json
{
  "success": true,
  "message": "Room rate deleted successfully",
  "data": null
}
```

---

## Key Features

### 1. **Booking Conflict Prevention**
- Checks existing bookings before allowing stock changes
- Prevents setting stock below already booked quantity
- Returns detailed error messages for conflicts

### 2. **Batch Operations**
Support for multiple date operations:

**Specific Dates:**
```json
{
  "dates": ["2025-10-15", "2025-10-16", "2025-10-17"],
  "stock": 5,
  "discount": 100
}
```

**Entire Month:**
```json
{
  "type": "all",
  "stock": 8,
  "discount": 150
}
```

### 3. **Calendar Response Format**
Returns room information with calendar-style rate breakdown:
- Date-keyed object for easy frontend integration
- Calculated availability (stock - bookings)
- Current price after discount application

---

## Error Responses

### Booking Conflict
```json
{
  "date": "2025-10-15",
  "error": "Cannot set stock below already booked quantity (6) for this room and date."
}
```

### Validation Error (422)
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "dates": ["The dates field is required."],
    "stock": ["The stock field is required."]
  }
}
```

---

## Notes
- **Authentication Required**: Partner must be authenticated
- **Partner Scoping**: Can only manage own room rates
- **Booking Integration**: Automatically checks booking conflicts
- **Calendar Format**: Perfect for frontend calendar components
- **Fallback System**: Integrates with default room metadata
- **Models**: `App\Models\PartnerRoomRate`, `App\Models\BookingItem`
- **Controller**: `App\Http\Controllers\API\Partner\RoomRateController`
- **Service**: `App\Services\PartnerRoomRateService`

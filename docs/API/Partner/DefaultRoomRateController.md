# DefaultRoomRateController API Documentation

Manage default room metadata (stock and discount) for each partner-room combination. These values serve as fallback defaults when specific daily rates don't exist.

---

## Endpoints

### POST /api/partner/hotels/{hotel}/rooms/{room}/default-rates
**Description:** Create or update default room metadata for a specific room.

**Path Parameters:**
- `hotel` (required): Hotel ID
- `room` (required): Room ID

**Request Body:**
```json
{
  "stock": 10,
  "discount": 50.00
}
```

**Validation:**
- `stock`: required, integer >= 0
- `discount`: optional, numeric >= 0

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
      "2025-10-01": {
        "room_name": "Deluxe Room",
        "stock": 10,
        "available_rooms": 8,
        "room_price": 1500,
        "current_price": 1450,
        "discount": 50
      },
      "2025-10-02": {
        "room_name": "Deluxe Room",
        "stock": 10,
        "available_rooms": 10,
        "room_price": 1500,
        "current_price": 1450,
        "discount": 50
      }
      // ... continues for all dates in current month
    }
  }
}
```

---

## Key Features

### 1. **Upsert Logic**
- Creates new default metadata if none exists
- Updates existing metadata for the partner-room combination
- Uses `updateOrCreate()` for atomic operations

### 2. **Calendar Integration**
- Returns complete room information with calendar details
- Shows how default values apply to dates without specific daily rates
- Includes current month's calendar view

### 3. **Fallback System**
- These defaults are used when no `PartnerRoomRate` exists for specific dates
- Ensures every date has rate information available
- Seamless integration with daily rate system

### 4. **Response Format**
- Returns complete `RoomResource` with embedded rates
- Calendar format shows daily breakdown
- Includes availability calculations (stock - booked)

---

## Use Cases

### 1. **Initial Setup**
Set default values when first configuring a room:
```json
POST /api/partner/hotels/45/rooms/123/default-rates
{
  "stock": 5,
  "discount": 100
}
```

### 2. **Bulk Default Updates**
Update defaults that will apply to all future dates without specific rates:
```json
POST /api/partner/hotels/45/rooms/123/default-rates
{
  "stock": 8,
  "discount": 200
}
```

### 3. **Emergency Stock Updates**
Quickly adjust default availability:
```json
POST /api/partner/hotels/45/rooms/123/default-rates
{
  "stock": 0,
  "discount": 0
}
```

---

## Error Responses

**Validation Error (422):**
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "stock": ["The stock field is required."],
    "discount": ["The discount must be at least 0."]
  }
}
```

**Hotel/Room Not Found (404):**
```json
{
  "success": false,
  "message": "No query results for model [App\\Models\\Room]."
}
```

---

## Notes

- **Authentication Required**: Partner must be authenticated
- **Partner Scoping**: Can only manage own room defaults
- **Fallback Priority**: Daily rates override defaults when they exist
- **Calendar View**: Response includes current month calendar with applied defaults
- **Stock Management**: Default stock is used for availability calculations
- **Model**: `App\Models\PartnerRoomMeta`
- **Controller**: `App\Http\Controllers\API\Partner\DefaultRoomRateController`

## Integration

Works seamlessly with:
- `RoomRateController` for daily-specific rates
- `PartnerRoomRateService` for fallback logic
- Room calendar displays in frontend
- Booking availability checks
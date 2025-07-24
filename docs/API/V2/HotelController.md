# Hotel Controller API Documentation

## Overview

The Hotel Controller provides API endpoints for managing and retrieving hotel information in the Thailand Anywhere booking system. This controller handles hotel listings, filtering, searching, and detailed hotel information retrieval.

## Base URL

```
/api/v2/hotels
```

## Endpoints

### 1. Get Hotels List

**Endpoint:** `GET /api/v2/hotels`

**Description:** Retrieves a paginated list of hotels with various filtering and sorting options.

#### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `search` | string | No | Search hotels by name (starts with matching) |
| `max_price` | decimal | No | Filter hotels with rooms having price less than or equal to this value |
| `city_id` | integer | No | Filter hotels by city ID |
| `place` | string | No | Filter hotels by place/location |
| `price_range` | string | No | Filter by price range (format: "min-max", e.g., "100-500") |
| `rating` | integer | No | Filter hotels by rating (1-5) |
| `facilities` | string | No | Filter by facility IDs (comma-separated, e.g., "1,2,3") |
| `category_id` | integer | No | Filter hotels by category ID |
| `order_by` | string | No | Sorting option. Available: `top_selling_products` |
| `limit` | integer | No | Number of items per page (default: 10) |

#### Request Examples

**Basic request:**
```http
GET /api/v2/hotels
```

**Search hotels by name:**
```http
GET /api/v2/hotels?search=Grand
```

**Filter by city and price range:**
```http
GET /api/v2/hotels?city_id=1&price_range=100-500
```

**Filter by facilities and rating:**
```http
GET /api/v2/hotels?facilities=1,2,3&rating=4
```

**Sort by top selling:**
```http
GET /api/v2/hotels?order_by=top_selling_products
```

#### Response Format

```json
{
  "data": [
    {
      "id": 1,
      "name": "Grand Hotel Bangkok",
      "rating": 4,
      "place": "Sukhumvit",
      "booking_items_count": 25,
      "city": {
        "id": 1,
        "name": "Bangkok"
      },
      "rooms": [
        {
          "id": 1,
          "room_type": "Deluxe",
          "room_price": 150.00,
          "is_extra": 0
        }
      ],
      "images": [
        {
          "id": 1,
          "image_url": "https://example.com/hotel1.jpg"
        }
      ],
      "facilities": [
        {
          "id": 1,
          "name": "WiFi"
        }
      ]
    }
  ],
  "links": {
    "first": "http://localhost/api/v2/hotels?page=1",
    "last": "http://localhost/api/v2/hotels?page=10",
    "prev": null,
    "next": "http://localhost/api/v2/hotels?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 10,
    "per_page": 10,
    "to": 10,
    "total": 100
  },
  "result": 1,
  "message": "success"
}
```

#### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Hotel unique identifier |
| `name` | string | Hotel name |
| `rating` | integer | Hotel rating (1-5 stars) |
| `place` | string | Hotel location/area |
| `booking_items_count` | integer | Number of bookings for this hotel |
| `city` | object | City information |
| `rooms` | array | Available rooms with pricing |
| `images` | array | Hotel images |
| `facilities` | array | Hotel facilities/amenities |

---

### 2. Get Hotel Details

**Endpoint:** `GET /api/v2/hotels/{hotel_id}`

**Description:** Retrieves detailed information about a specific hotel.

#### Path Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `hotel_id` | integer/string | Yes | Hotel unique identifier |

#### Request Example

```http
GET /api/v2/hotels/1
```

#### Response Format

**Success Response (200):**
```json
{
  "result": 1,
  "message": "success",
  "data": {
    "id": 1,
    "name": "Grand Hotel Bangkok",
    "description": "Luxury hotel in the heart of Bangkok",
    "rating": 4,
    "place": "Sukhumvit",
    "address": "123 Sukhumvit Road, Bangkok",
    "phone": "+66-2-123-4567",
    "email": "info@grandhotel.com",
    "check_in_time": "14:00",
    "check_out_time": "12:00",
    "city": {
      "id": 1,
      "name": "Bangkok",
      "country": "Thailand"
    },
    "rooms": [
      {
        "id": 1,
        "room_type": "Deluxe",
        "room_price": 150.00,
        "bed_type": "King",
        "max_occupancy": 2,
        "room_size": 35,
        "is_extra": 0,
        "images": [
          {
            "id": 1,
            "image_url": "https://example.com/room1.jpg"
          }
        ]
      }
    ],
    "contracts": [
      {
        "id": 1,
        "contract_type": "Standard",
        "start_date": "2024-01-01",
        "end_date": "2024-12-31"
      }
    ],
    "images": [
      {
        "id": 1,
        "image_url": "https://example.com/hotel1.jpg",
        "alt_text": "Hotel exterior"
      }
    ],
    "facilities": [
      {
        "id": 1,
        "name": "Free WiFi",
        "icon": "wifi"
      },
      {
        "id": 2,
        "name": "Swimming Pool",
        "icon": "pool"
      }
    ]
  }
}
```

**Error Response (404):**
```json
{
  "result": 0,
  "message": "Hotel not found"
}
```

#### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Hotel unique identifier |
| `name` | string | Hotel name |
| `description` | string | Hotel description |
| `rating` | integer | Hotel rating (1-5 stars) |
| `place` | string | Hotel location/area |
| `address` | string | Full hotel address |
| `phone` | string | Hotel contact phone |
| `email` | string | Hotel contact email |
| `check_in_time` | string | Check-in time (HH:MM format) |
| `check_out_time` | string | Check-out time (HH:MM format) |
| `city` | object | Detailed city information |
| `rooms` | array | Detailed room information with images |
| `contracts` | array | Hotel contract information |
| `images` | array | Hotel images with metadata |
| `facilities` | array | Detailed facility information |

---

## Response Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 404 | Hotel not found |
| 422 | Validation error |
| 500 | Internal server error |

## Features

### Filtering Capabilities

1. **Text Search**: Search hotels by name with prefix matching
2. **Price Filtering**: Filter by maximum price or price range
3. **Location Filtering**: Filter by city or specific place
4. **Rating Filtering**: Filter by hotel star rating
5. **Facility Filtering**: Filter by available amenities
6. **Category Filtering**: Filter by hotel category

### Sorting Options

1. **Default**: Sort by creation date (newest first)
2. **Top Selling**: Sort by number of bookings (most popular first)

### Performance Optimizations

1. **Eager Loading**: Related data (city, rooms, images, facilities) is loaded efficiently
2. **Selective Loading**: Only necessary relationships are loaded per endpoint
3. **Database Optimization**: Subqueries for complex filtering to maintain performance

### Data Relationships

- **Hotels** belong to **Cities**
- **Hotels** have many **Rooms**
- **Hotels** have many **Images**
- **Hotels** have many **Facilities** (many-to-many)
- **Hotels** have many **Contracts**
- **Hotels** have many **Booking Items**

## Usage Examples

### Frontend Integration

```javascript
// Fetch hotels with filters
const fetchHotels = async (filters = {}) => {
  const params = new URLSearchParams(filters);
  const response = await fetch(`/api/v2/hotels?${params}`);
  return response.json();
};

// Example usage
const hotels = await fetchHotels({
  city_id: 1,
  price_range: '100-500',
  rating: 4,
  facilities: '1,2,3'
});

// Fetch specific hotel
const fetchHotelDetails = async (hotelId) => {
  const response = await fetch(`/api/v2/hotels/${hotelId}`);
  return response.json();
};
```

### Mobile App Integration

```dart
// Flutter/Dart example
class HotelService {
  static Future<Map<String, dynamic>> getHotels({
    String? search,
    int? cityId,
    String? priceRange,
    int? rating,
  }) async {
    final uri = Uri.parse('/api/v2/hotels').replace(queryParameters: {
      if (search != null) 'search': search,
      if (cityId != null) 'city_id': cityId.toString(),
      if (priceRange != null) 'price_range': priceRange,
      if (rating != null) 'rating': rating.toString(),
    });

    final response = await http.get(uri);
    return json.decode(response.body);
  }
}
```

## Notes

- All prices are in the system's base currency
- Hotel ratings are on a 1-5 scale
- Images URLs are fully qualified
- The `directBooking()` scope ensures only directly bookable hotels are returned
- Pagination is handled automatically with Laravel's built-in pagination

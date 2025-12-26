# AmBilet.ro - Marketplace Client

This folder contains the custom website for AmBilet.ro marketplace client.

## Folder Structure

```
ambilet/
├── index.html          # Main landing page
├── events.html         # Events listing page
├── event.html          # Single event detail page
├── checkout.html       # Checkout page
├── order-complete.html # Order confirmation page
├── css/
│   └── styles.css      # Custom styles
├── js/
│   ├── api.js          # API client for Core communication
│   ├── app.js          # Main application logic
│   └── checkout.js     # Checkout logic
├── images/             # Static images
└── pages/              # Additional static pages
```

## API Configuration

### Authentication

All API requests require the `X-API-Key` header:

```javascript
const API_BASE_URL = 'https://core.tixello.com/api/marketplace-client';
const API_KEY = 'mpc_YOUR_API_KEY_HERE';

fetch(`${API_BASE_URL}/events`, {
    headers: {
        'X-API-Key': API_KEY,
        'Content-Type': 'application/json'
    }
});
```

### Available Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/config` | GET | Get marketplace client configuration |
| `/tenants` | GET | List tenants you can sell tickets for |
| `/events` | GET | List all available events |
| `/events/{id}` | GET | Get single event details |
| `/events/{id}/availability` | GET | Get real-time ticket availability |
| `/orders` | GET | List your orders |
| `/orders` | POST | Create a new order |
| `/orders/{id}` | GET | Get order details |
| `/orders/{id}/cancel` | POST | Cancel a pending order |

### Event Listing

```javascript
// Get events with filters
const response = await fetch(`${API_BASE_URL}/events?` + new URLSearchParams({
    tenant_id: 123,        // Optional: filter by tenant
    category: 'concert',   // Optional: filter by category
    city: 'Bucuresti',     // Optional: filter by city
    from_date: '2025-01-01', // Optional: filter by date range
    to_date: '2025-12-31',
    search: 'rock',        // Optional: text search
    per_page: 20,          // Pagination (max 100)
    page: 1
}), {
    headers: { 'X-API-Key': API_KEY }
});

const data = await response.json();
// data.data = array of events
// data.meta = pagination info
```

### Creating an Order

```javascript
const response = await fetch(`${API_BASE_URL}/orders`, {
    method: 'POST',
    headers: {
        'X-API-Key': API_KEY,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        event_id: 123,
        tickets: [
            { ticket_type_id: 1, quantity: 2 },
            { ticket_type_id: 2, quantity: 1 }
        ],
        customer: {
            email: 'client@example.com',
            first_name: 'Ion',
            last_name: 'Popescu',
            phone: '+40712345678'
        }
    })
});

const data = await response.json();
// data.data.order = order details
// data.data.payment_url = redirect URL for payment
```

### Order Response

```json
{
    "success": true,
    "data": {
        "order": {
            "id": 12345,
            "order_number": "MPC-ABCD1234",
            "status": "pending",
            "subtotal": 150.00,
            "commission_amount": 3.00,
            "total": 153.00,
            "currency": "RON",
            "expires_at": "2025-12-26T10:15:00Z"
        },
        "payment_url": "https://core.tixello.com/marketplace/payment/12345"
    }
}
```

## Deployment

This website is hosted on AmBilet.ro's own server. To deploy:

1. Copy all files to your web server
2. Update `js/api.js` with your API key
3. Configure your web server to serve `index.html` for all routes (SPA mode)
4. Ensure HTTPS is enabled

## CORS

The Core API allows requests from any origin when authenticated with a valid API key. Your website can be hosted on any domain.

## Support

Contact the Core platform team for:
- API key generation
- Tenant access configuration
- Commission rate setup
- Technical support

# Tixello Mobile App - Structure Guide for Claude Code

## Current Files

### 1. `tixello-app.jsx` (Full Admin App)
Complete event management app with all features:
- Dashboard with live stats
- Check-in scanner
- POS sales with Stripe Tap to Pay
- Reports
- Settings with admin controls
- Gate management (admin)
- Staff assignment & scheduling (admin)

### 2. `tixello-staff-app.jsx` (Staff App with Auth)
Simplified version with login:
- Login page (admin/scanner roles)
- Role-based feature access
- Same core functionality

---

## Recommended File Split for Production

```
/src
├── /components
│   ├── /common
│   │   ├── Header.jsx
│   │   ├── BottomNav.jsx
│   │   ├── Modal.jsx
│   │   ├── Toggle.jsx
│   │   └── LoadingSpinner.jsx
│   │
│   ├── /auth
│   │   ├── LoginPage.jsx
│   │   ├── SplashScreen.jsx
│   │   └── AuthContext.jsx
│   │
│   ├── /dashboard
│   │   ├── Dashboard.jsx
│   │   ├── StatsGrid.jsx
│   │   ├── QuickActions.jsx
│   │   └── RecentActivity.jsx
│   │
│   ├── /checkin
│   │   ├── CheckIn.jsx
│   │   ├── Scanner.jsx
│   │   ├── ScanResult.jsx
│   │   └── RecentScans.jsx
│   │
│   ├── /sales
│   │   ├── Sales.jsx
│   │   ├── TicketSelector.jsx
│   │   ├── Cart.jsx
│   │   ├── PaymentMethods.jsx
│   │   ├── StripeTapToPay.jsx
│   │   └── EmailCapture.jsx
│   │
│   ├── /reports
│   │   ├── Reports.jsx
│   │   ├── ReportCard.jsx
│   │   └── ChartPlaceholder.jsx
│   │
│   ├── /settings
│   │   ├── Settings.jsx
│   │   ├── ScannerSettings.jsx
│   │   ├── HardwareSettings.jsx
│   │   └── AdminSettings.jsx
│   │
│   ├── /admin
│   │   ├── GateManager.jsx
│   │   ├── StaffAssignment.jsx
│   │   └── EventSelector.jsx
│   │
│   └── /modals
│       ├── StaffModal.jsx
│       ├── GuestListModal.jsx
│       ├── NotificationsPanel.jsx
│       └── ManualEntryModal.jsx
│
├── /hooks
│   ├── useAuth.js
│   ├── useScanner.js
│   ├── useStripe.js
│   ├── useOfflineMode.js
│   └── useEvents.js
│
├── /services
│   ├── api.js
│   ├── authService.js
│   ├── eventService.js
│   ├── ticketService.js
│   ├── stripeService.js
│   └── emailService.js
│
├── /context
│   ├── AuthContext.jsx
│   ├── EventContext.jsx
│   └── CartContext.jsx
│
├── /styles
│   ├── globals.css
│   ├── variables.css
│   └── components/
│
└── /utils
    ├── formatters.js
    ├── validators.js
    └── constants.js
```

---

## API Integration Points

### Authentication
```javascript
// POST /api/auth/login
{ email: string, password: string }
// Returns: { token, user: { id, name, role, assignedGate } }

// POST /api/auth/logout
// Ends shift and logs user out
```

### Events
```javascript
// GET /api/events
// Returns all events for the organizer

// GET /api/events/:id
// Returns event details with stats

// GET /api/events/:id/tickets
// Returns ticket types for event
```

### Check-in / Scanning
```javascript
// POST /api/checkin/scan
{ eventId, ticketCode, gateId, scannerId }
// Returns: { status: 'valid'|'duplicate'|'invalid', attendee, message }

// GET /api/checkin/recent
// Returns recent scans for dashboard
```

### Sales (Stripe Tap to Pay)
```javascript
// POST /api/sales/create-payment-intent
{ eventId, items: [], amount }
// Returns: { clientSecret, paymentIntentId }

// POST /api/sales/confirm
{ paymentIntentId, paymentMethod: 'card'|'cash', email? }
// Returns: { success, tickets: [], receiptUrl }

// For Stripe Tap to Pay integration:
// - Use Stripe Terminal SDK
// - Reader type: 'tap_to_pay_ios' or 'tap_to_pay_android'
// - See: https://stripe.com/docs/terminal/payments/setup-reader/tap-to-pay
```

### Email Sending
```javascript
// POST /api/tickets/send-email
{ saleId, email }
// Sends ticket PDFs to customer email
```

### Admin - Gates
```javascript
// GET /api/events/:id/gates
// POST /api/events/:id/gates
// PUT /api/events/:id/gates/:gateId
// DELETE /api/events/:id/gates/:gateId
```

### Admin - Staff Assignment
```javascript
// GET /api/events/:id/staff
// POST /api/events/:id/staff
// PUT /api/events/:id/staff/:assignmentId
// DELETE /api/events/:id/staff/:assignmentId
```

---

## State Management Notes

### Key State Variables
```javascript
// Authentication
currentUser: { id, name, role, assignedGate }
currentUserRole: 'admin' | 'scanner' | 'pos'

// Event Context
selectedEvent: Event
events: Event[]
isReportsOnlyMode: boolean // true for past events

// Sales Flow
cartItems: CartItem[]
paymentMethod: 'card' | 'cash' | null
showPaymentSuccess: boolean
showEmailCapture: boolean
buyerEmail: string

// Admin Features
gates: Gate[]
staffSchedules: StaffSchedule[]
showGateManager: boolean
showStaffAssignment: boolean

// Scanner Settings
vibrationFeedback: boolean
soundEffects: boolean
autoConfirmValid: boolean
offlineMode: boolean
```

---

## Stripe Tap to Pay Integration

### Required Setup
1. Install Stripe Terminal SDK
2. Register for Tap to Pay (requires Apple/Google approval)
3. Set location permissions

### Implementation
```javascript
import { useStripeTerminal } from '@stripe/stripe-terminal-react-native';

const { discoverReaders, connectReader, collectPaymentMethod } = useStripeTerminal();

// For Tap to Pay
const handleTapToPay = async (amount) => {
  // 1. Create payment intent on server
  const { clientSecret } = await api.createPaymentIntent({ amount });
  
  // 2. Collect payment via tap
  const { paymentIntent } = await collectPaymentMethod({ clientSecret });
  
  // 3. Confirm on server
  await api.confirmPayment({ paymentIntentId: paymentIntent.id });
};
```

### Fallback for Testing
Use Stripe Terminal's simulated reader for development.

---

## Design Tokens

### Colors
```css
--bg-primary: #0A0A0F;
--bg-secondary: #15151F;
--bg-card: rgba(255,255,255,0.03);

--accent-primary: #8B5CF6; /* Violet */
--accent-secondary: #6366F1;
--accent-cyan: #06B6D4;
--accent-green: #10B981;
--accent-amber: #F59E0B;
--accent-red: #EF4444;

--text-primary: #FFFFFF;
--text-secondary: rgba(255,255,255,0.7);
--text-muted: rgba(255,255,255,0.5);
```

### Typography
```css
--font-primary: 'DM Sans', sans-serif;
--font-mono: 'JetBrains Mono', monospace;
```

---

## User Roles & Permissions

| Feature | Admin | Scanner | POS |
|---------|-------|---------|-----|
| Dashboard | ✅ Full | ✅ Basic | ✅ Basic |
| Check-in | ✅ | ✅ | ❌ |
| Sales | ✅ | ❌ | ✅ |
| Reports | ✅ Full | ✅ Own stats | ✅ Own stats |
| Settings | ✅ Full | ✅ Scanner only | ✅ POS only |
| Gate Management | ✅ | ❌ | ❌ |
| Staff Assignment | ✅ | ❌ | ❌ |
| Event Switching | ✅ | ❌ | ❌ |

---

## Offline Mode

### Requirements
- Cache ticket data for offline scanning
- Queue sales for sync when online
- Local storage for scans

### Implementation Notes
```javascript
// Use IndexedDB for ticket cache
// SQLite for React Native

// Sync queue structure
{
  type: 'scan' | 'sale',
  timestamp: Date,
  data: {},
  synced: false
}
```

---

## Testing Credentials

```
Admin:
- Email: admin@tixello.com
- Password: admin

Scanner:
- Email: scanner@tixello.com
- Password: scanner
```

---

## TODO for Claude Code

1. [ ] Split components into separate files
2. [ ] Set up React Router / Navigation
3. [ ] Implement AuthContext
4. [ ] Connect to backend API
5. [ ] Integrate Stripe Terminal SDK for Tap to Pay
6. [ ] Add email service (SendGrid/Resend)
7. [ ] Implement offline mode with IndexedDB
8. [ ] Add error handling & loading states
9. [ ] Add form validation
10. [ ] Implement real QR scanner (camera access)
11. [ ] Add push notifications
12. [ ] Write unit tests

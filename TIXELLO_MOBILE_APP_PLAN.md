# Tixello Mobile App - Implementation Plan

## Executive Summary

This document outlines a comprehensive plan for building a hybrid mobile application for Tixello that will enable tenants (event organizers) to:
1. Perform live ticket check-ins at events
2. Sell tickets as a Point-of-Sale (POS) system
3. View live reports and analytics for their events

The mobile app will communicate with the existing Tixello Laravel backend APIs.

---

## 1. Technology Stack Recommendation

### Recommended Framework: **React Native with Expo**

**Rationale:**
- **Cross-platform**: Single codebase for iOS and Android
- **Mature ecosystem**: Large community, extensive libraries
- **Expo**: Simplifies development, OTA updates, easier deployment
- **TypeScript support**: Better code quality and maintainability
- **Stripe Terminal SDK**: Official React Native support for POS functionality
- **Camera/Barcode scanning**: Excellent library support

**Alternative Considered:** Flutter
- Also viable, but React Native has better Stripe Terminal integration

### Tech Stack Details

| Component | Technology |
|-----------|------------|
| Framework | React Native 0.74+ with Expo SDK 51+ |
| Language | TypeScript |
| State Management | Zustand (lightweight) or Redux Toolkit |
| Navigation | React Navigation 6 |
| HTTP Client | Axios with interceptors |
| Storage | expo-secure-store (tokens), AsyncStorage (cache) |
| UI Components | React Native Paper or NativeBase |
| Barcode Scanner | expo-camera + expo-barcode-scanner |
| Card Payments | @stripe/stripe-terminal-react-native |
| Forms | React Hook Form + Zod validation |
| Offline Support | @tanstack/react-query with persistence |

---

## 2. App Architecture

### 2.1 High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Tixello Mobile App                        │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐       │
│  │  Check-In    │  │     POS      │  │   Reports    │       │
│  │   Module     │  │    Module    │  │    Module    │       │
│  └──────────────┘  └──────────────┘  └──────────────┘       │
│                                                              │
│  ┌────────────────────────────────────────────────────────┐ │
│  │                    Shared Services                      │ │
│  │  Auth | API Client | Offline Queue | Notifications     │ │
│  └────────────────────────────────────────────────────────┘ │
│                                                              │
│  ┌────────────────────────────────────────────────────────┐ │
│  │               Storage Layer                             │ │
│  │  SecureStore (tokens) | AsyncStorage | SQLite (cache)  │ │
│  └────────────────────────────────────────────────────────┘ │
│                                                              │
└─────────────────────────────────────────────────────────────┘
                              │
                              │ HTTPS/REST API
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                 Tixello Backend (Laravel)                    │
│                                                              │
│  /api/tenant-client/*     - Tenant operations               │
│  /api/door-sales/*        - POS operations                  │
│  /api/marketplace-client/* - Organizer operations           │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### 2.2 Directory Structure

```
tixello-mobile/
├── app/                          # Expo Router app directory
│   ├── (auth)/                   # Auth screens (login, register)
│   │   ├── login.tsx
│   │   └── forgot-password.tsx
│   ├── (main)/                   # Main app screens (requires auth)
│   │   ├── (tabs)/               # Bottom tab navigator
│   │   │   ├── check-in/
│   │   │   │   ├── index.tsx     # Event selection
│   │   │   │   └── [eventId].tsx # Scanner screen
│   │   │   ├── pos/
│   │   │   │   ├── index.tsx     # Event selection
│   │   │   │   ├── [eventId].tsx # Ticket selection
│   │   │   │   └── checkout.tsx  # Payment processing
│   │   │   ├── reports/
│   │   │   │   ├── index.tsx     # Dashboard overview
│   │   │   │   └── [eventId].tsx # Event details
│   │   │   └── settings/
│   │   │       └── index.tsx     # Account settings
│   │   └── _layout.tsx
│   ├── _layout.tsx               # Root layout
│   └── index.tsx                 # Entry point
├── src/
│   ├── api/                      # API client and endpoints
│   │   ├── client.ts             # Axios instance
│   │   ├── auth.ts               # Auth endpoints
│   │   ├── events.ts             # Event endpoints
│   │   ├── check-in.ts           # Check-in endpoints
│   │   ├── door-sales.ts         # POS endpoints
│   │   └── reports.ts            # Analytics endpoints
│   ├── components/               # Reusable components
│   │   ├── ui/                   # Base UI components
│   │   ├── check-in/             # Check-in specific
│   │   ├── pos/                  # POS specific
│   │   └── reports/              # Reports specific
│   ├── hooks/                    # Custom hooks
│   │   ├── useAuth.ts
│   │   ├── useEvents.ts
│   │   ├── useCheckIn.ts
│   │   ├── usePOS.ts
│   │   └── useReports.ts
│   ├── stores/                   # Zustand stores
│   │   ├── authStore.ts
│   │   ├── eventStore.ts
│   │   ├── cartStore.ts
│   │   └── offlineStore.ts
│   ├── services/                 # Business logic
│   │   ├── auth.service.ts
│   │   ├── stripe-terminal.service.ts
│   │   ├── offline-queue.service.ts
│   │   └── notification.service.ts
│   ├── utils/                    # Utility functions
│   │   ├── formatters.ts
│   │   ├── validators.ts
│   │   └── constants.ts
│   └── types/                    # TypeScript types
│       ├── api.types.ts
│       ├── models.types.ts
│       └── navigation.types.ts
├── assets/                       # Static assets
├── app.json                      # Expo config
├── eas.json                      # EAS Build config
├── package.json
└── tsconfig.json
```

---

## 3. Feature Specifications

### 3.1 Authentication Module

**Screens:**
- Login Screen
- Forgot Password Screen
- Tenant Selection (if user has multiple tenants)

**API Endpoints Used:**
```
POST /api/tenant-client/auth/login
POST /api/tenant-client/auth/logout
GET  /api/tenant-client/auth/me
POST /api/tenant-client/auth/forgot-password
```

**Features:**
- Email/password authentication
- Secure token storage (expo-secure-store)
- Auto-login with stored token
- Token refresh handling
- Biometric authentication option (Face ID / Fingerprint)
- Multi-tenant support (select tenant after login)

**User Flow:**
```
┌─────────────┐     ┌──────────────┐     ┌─────────────────┐
│   Login     │ ──▶ │   Validate   │ ──▶ │ Select Tenant   │
│   Screen    │     │   Token      │     │ (if multiple)   │
└─────────────┘     └──────────────┘     └─────────────────┘
                                                   │
                                                   ▼
                                         ┌─────────────────┐
                                         │   Main App      │
                                         │   Dashboard     │
                                         └─────────────────┘
```

---

### 3.2 Check-In Module

**Screens:**
1. **Event Selection Screen**
   - List of upcoming events for the tenant
   - Search/filter functionality
   - Event details preview (date, venue, tickets sold)

2. **Scanner Screen**
   - Camera-based QR/barcode scanner
   - Manual code entry option
   - Real-time validation feedback
   - Check-in history (last 20 scans)
   - Undo check-in functionality
   - Offline mode indicator

**API Endpoints Used:**
```
GET  /api/marketplace-client/organizer/events
GET  /api/marketplace-client/organizer/events/{event}/participants
POST /api/marketplace-client/organizer/events/{event}/check-in/{barcode}
DELETE /api/marketplace-client/organizer/events/{event}/check-in/{barcode}
```

**Check-In Response Handling:**
```typescript
interface CheckInResult {
  success: boolean;
  ticket: {
    code: string;
    holder_name: string;
    ticket_type: string;
    seat_label?: string;
    status: 'valid' | 'used' | 'cancelled';
  };
  message: string;
  timestamp: string;
}
```

**Offline Capability:**
- Cache event and participant data locally
- Queue check-ins when offline
- Sync when connection restored
- Visual indicator of pending syncs
- Conflict resolution for duplicate scans

**UI/UX Considerations:**
- Large, clear success/failure indicators
- Sound feedback (beep for success, buzz for error)
- Haptic feedback
- Quick scan mode (auto-continue after scan)
- Stats overlay (checked in / total)

---

### 3.3 POS (Point of Sale) Module

**Screens:**
1. **Event Selection Screen**
   - List of events available for door sales
   - Current event quick-access

2. **Ticket Selection Screen**
   - Available ticket types with prices
   - Quantity selectors
   - Real-time availability
   - Cart summary
   - Customer info entry

3. **Checkout Screen**
   - Order summary
   - Payment method selection
   - Card reader integration
   - Receipt options

4. **Transaction History**
   - Recent transactions
   - Refund capability
   - Resend tickets

**API Endpoints Used:**
```
GET  /api/door-sales/events
GET  /api/door-sales/events/{eventId}/ticket-types
POST /api/door-sales/calculate
POST /api/door-sales/process
GET  /api/door-sales/{id}
GET  /api/door-sales/history
POST /api/door-sales/{id}/refund
POST /api/door-sales/{id}/resend
```

**Payment Integration - Stripe Terminal:**

```typescript
// Stripe Terminal SDK Integration
import { useStripeTerminal } from '@stripe/stripe-terminal-react-native';

const POSCheckout = () => {
  const {
    discoverReaders,
    connectBluetoothReader,
    collectPaymentMethod,
    processPayment
  } = useStripeTerminal();

  // 1. Discover nearby readers
  // 2. Connect to selected reader
  // 3. Create payment intent (via backend)
  // 4. Collect payment method
  // 5. Process payment
  // 6. Handle result
};
```

**Supported Payment Methods:**
- Card (tap/insert/swipe via Stripe Terminal)
- Apple Pay (via Stripe Terminal)
- Google Pay (via Stripe Terminal)
- Cash (manual entry, no terminal needed)

**Hardware Requirements:**
- Stripe Terminal readers: BBPOS Chipper, WisePOS E, Stripe Reader M2
- iPad/iPhone or Android device with Bluetooth

**POS Flow:**
```
┌──────────────┐     ┌───────────────┐     ┌──────────────┐
│ Select Event │ ──▶ │ Add Tickets   │ ──▶ │ Enter        │
│              │     │ to Cart       │     │ Customer Info│
└──────────────┘     └───────────────┘     └──────────────┘
                                                   │
                                                   ▼
┌──────────────┐     ┌───────────────┐     ┌──────────────┐
│ Show Receipt │ ◀── │ Process       │ ◀── │ Select       │
│              │     │ Payment       │     │ Payment Type │
└──────────────┘     └───────────────┘     └──────────────┘
```

---

### 3.4 Reports Module

**Screens:**
1. **Dashboard Overview**
   - Key metrics cards (revenue, tickets sold, check-ins)
   - Quick event selector
   - Time period filter

2. **Event Report Detail**
   - Ticket sales breakdown by type
   - Revenue chart (timeline)
   - Check-in progress
   - Recent orders list

**API Endpoints Used:**
```
GET /api/tenant-client/admin/dashboard
GET /api/marketplace-client/organizer/dashboard
GET /api/marketplace-client/organizer/dashboard/timeline
GET /api/marketplace-client/organizer/events/{event}/participants
GET /api/marketplace-client/organizer/orders
```

**Dashboard Metrics:**
```typescript
interface DashboardStats {
  total_events: number;
  active_events: number;
  total_orders: number;
  total_revenue: number;
  tickets_sold: number;
  checked_in: number;
  check_in_rate: number; // percentage
  customers: number;
}

interface TimelineData {
  period: string;
  orders: number;
  revenue: number;
  tickets: number;
}
```

**Visualizations:**
- Revenue over time (line chart)
- Tickets by type (pie chart)
- Check-in progress (progress bar)
- Sales velocity (bar chart)

**Real-Time Updates:**
- Pull-to-refresh
- Optional auto-refresh (configurable interval)
- Push notifications for key events (optional future enhancement)

---

## 4. API Client Implementation

### 4.1 Base API Client

```typescript
// src/api/client.ts
import axios, { AxiosInstance, AxiosError } from 'axios';
import * as SecureStore from 'expo-secure-store';

const API_BASE_URL = process.env.EXPO_PUBLIC_API_URL;

class ApiClient {
  private client: AxiosInstance;
  private tenantId: string | null = null;

  constructor() {
    this.client = axios.create({
      baseURL: API_BASE_URL,
      timeout: 30000,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    });

    this.setupInterceptors();
  }

  private setupInterceptors() {
    // Request interceptor - add auth token
    this.client.interceptors.request.use(async (config) => {
      const token = await SecureStore.getItemAsync('auth_token');
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }
      if (this.tenantId) {
        config.headers['X-Tenant-ID'] = this.tenantId;
      }
      return config;
    });

    // Response interceptor - handle errors
    this.client.interceptors.response.use(
      (response) => response,
      async (error: AxiosError) => {
        if (error.response?.status === 401) {
          // Token expired - trigger logout
          await this.handleTokenExpiry();
        }
        return Promise.reject(error);
      }
    );
  }

  setTenantId(id: string) {
    this.tenantId = id;
  }

  get instance() {
    return this.client;
  }
}

export const apiClient = new ApiClient();
```

### 4.2 Offline Queue Service

```typescript
// src/services/offline-queue.service.ts
import AsyncStorage from '@react-native-async-storage/async-storage';
import NetInfo from '@react-native-community/netinfo';

interface QueuedOperation {
  id: string;
  type: 'check-in' | 'door-sale';
  endpoint: string;
  method: 'POST' | 'DELETE';
  data: any;
  timestamp: number;
  retries: number;
}

class OfflineQueueService {
  private queue: QueuedOperation[] = [];
  private isProcessing = false;

  async init() {
    const stored = await AsyncStorage.getItem('offline_queue');
    if (stored) {
      this.queue = JSON.parse(stored);
    }
    this.startNetworkListener();
  }

  private startNetworkListener() {
    NetInfo.addEventListener((state) => {
      if (state.isConnected && this.queue.length > 0) {
        this.processQueue();
      }
    });
  }

  async addToQueue(operation: Omit<QueuedOperation, 'id' | 'timestamp' | 'retries'>) {
    const item: QueuedOperation = {
      ...operation,
      id: crypto.randomUUID(),
      timestamp: Date.now(),
      retries: 0,
    };
    this.queue.push(item);
    await this.persistQueue();
  }

  private async processQueue() {
    if (this.isProcessing) return;
    this.isProcessing = true;

    while (this.queue.length > 0) {
      const operation = this.queue[0];
      try {
        await this.executeOperation(operation);
        this.queue.shift();
        await this.persistQueue();
      } catch (error) {
        operation.retries++;
        if (operation.retries >= 3) {
          // Move to failed queue
          this.queue.shift();
        }
        break;
      }
    }

    this.isProcessing = false;
  }
}

export const offlineQueue = new OfflineQueueService();
```

---

## 5. Security Considerations

### 5.1 Authentication Security
- Store tokens in `expo-secure-store` (uses Keychain/Keystore)
- Implement token refresh before expiry
- Clear tokens on logout
- Support biometric authentication

### 5.2 API Security
- All communications over HTTPS
- Certificate pinning (optional, for high-security deployments)
- Request signing for sensitive operations (optional)
- Rate limiting handling

### 5.3 Offline Data Security
- Encrypt sensitive cached data
- Auto-clear cache after configurable period
- No sensitive data in AsyncStorage (use SecureStore)

### 5.4 Payment Security
- PCI DSS compliance via Stripe Terminal
- No card data touches the app (Stripe handles)
- Secure reader pairing
- Transaction signing

---

## 6. Implementation Phases

### Phase 1: Foundation (Core Setup)
**Scope:**
- Project setup with Expo
- Navigation structure
- Authentication module (login/logout)
- API client with interceptors
- Basic state management
- UI component library setup

**Deliverables:**
- Working login flow
- Authenticated API calls
- Tenant selection (if applicable)
- Settings screen with logout

### Phase 2: Check-In Module
**Scope:**
- Event list screen
- Camera barcode scanner
- Check-in API integration
- Success/failure feedback
- Check-in history
- Undo functionality

**Deliverables:**
- Fully functional check-in flow
- Real-time validation
- Scan history

### Phase 3: Offline Support
**Scope:**
- Offline queue implementation
- Local data caching
- Sync status indicators
- Conflict resolution
- Network state handling

**Deliverables:**
- Offline check-in capability
- Auto-sync when online
- Visual sync status

### Phase 4: POS Module
**Scope:**
- Event/ticket type selection
- Shopping cart
- Customer info form
- Stripe Terminal integration
- Payment processing
- Receipt handling

**Deliverables:**
- Complete POS flow
- Card payment processing
- Cash payment tracking
- Transaction history

### Phase 5: Reports Module
**Scope:**
- Dashboard with key metrics
- Event-specific reports
- Charts and visualizations
- Export functionality

**Deliverables:**
- Real-time dashboard
- Event analytics
- Sales timeline

### Phase 6: Polish & Launch
**Scope:**
- Performance optimization
- Error handling refinement
- Accessibility improvements
- App store preparation
- Documentation

**Deliverables:**
- Production-ready app
- App Store / Play Store submissions

---

## 7. API Requirements for Backend

The existing Tixello backend already provides most required endpoints. However, the following enhancements may be needed:

### 7.1 Required New Endpoints (if not existing)
```
POST /api/mobile/auth/biometric     # For biometric auth registration
POST /api/mobile/device/register    # Register device for push notifications
GET  /api/mobile/sync/status        # Get sync status for offline queue
POST /api/mobile/sync/batch         # Batch sync offline operations
```

### 7.2 Endpoint Enhancements
- Add pagination to list endpoints if not present
- Ensure consistent error response format
- Add `X-Request-ID` support for tracing
- WebSocket support for real-time updates (optional enhancement)

### 7.3 Stripe Terminal Backend Requirements
```
POST /api/door-sales/stripe/connection-token  # Get Stripe Terminal connection token
POST /api/door-sales/stripe/payment-intent    # Create payment intent for terminal
POST /api/door-sales/stripe/capture           # Capture payment after terminal success
```

---

## 8. Testing Strategy

### 8.1 Unit Tests
- Business logic functions
- State management
- Utility functions
- API response parsing

### 8.2 Integration Tests
- API client with mock server
- Offline queue sync
- Authentication flows

### 8.3 E2E Tests (Detox)
- Full user flows
- Check-in scanning
- POS checkout
- Report viewing

### 8.4 Manual Testing
- Real device testing (iOS and Android)
- Stripe Terminal reader testing
- Network condition testing
- Performance testing

---

## 9. Deployment & Distribution

### 9.1 Build Configuration
- Use EAS Build for cloud builds
- Separate environments: dev, staging, production
- Environment-specific API URLs

### 9.2 Distribution Options
1. **App Store / Play Store** (public or unlisted)
2. **Enterprise Distribution** (for internal use)
3. **Expo Updates** (OTA updates for JS changes)

### 9.3 CI/CD Pipeline
- GitHub Actions or EAS Build webhooks
- Automated testing on PR
- Automated builds on merge to main
- Automated submission to stores (optional)

---

## 10. Success Metrics

### 10.1 Performance Targets
- App launch time: < 2 seconds
- Check-in scan processing: < 500ms
- API response handling: < 1 second
- Offline sync: < 30 seconds for 100 items

### 10.2 User Experience Targets
- Check-in success rate: > 99%
- Payment success rate: > 98%
- Crash-free rate: > 99.5%

### 10.3 Business Metrics
- Time saved per check-in vs manual
- Revenue processed through POS
- Report access frequency

---

## 11. Feasibility Assessment

### Can We Build This? **YES**

**Reasons:**

1. **Backend is Ready**: The Tixello Laravel backend already has all necessary API endpoints for:
   - Authentication (Sanctum tokens)
   - Event management
   - Check-ins (with barcode validation)
   - Door Sales (POS) with Stripe integration
   - Dashboard and reporting

2. **Technology is Mature**:
   - React Native + Expo is production-ready
   - Stripe Terminal SDK has official React Native support
   - Barcode scanning libraries are reliable

3. **Clear Scope**:
   - Three focused features (check-in, POS, reports)
   - Well-defined API contracts
   - Standard mobile patterns

### Potential Challenges

| Challenge | Mitigation |
|-----------|------------|
| Stripe Terminal hardware costs | Start with one reader model, expand later |
| Offline sync complexity | Use battle-tested libraries (React Query) |
| iOS/Android differences | Use Expo managed workflow |
| App Store review | Follow guidelines, proper entitlements |

---

## 12. Summary

This mobile app is **fully feasible** with the existing Tixello backend. The recommended approach is:

1. **React Native with Expo** for cross-platform development
2. **Phased implementation** starting with auth and check-in
3. **Stripe Terminal** for POS payments
4. **Offline-first architecture** for reliability

The backend already provides comprehensive APIs for all three main features:
- **Check-ins**: `/api/marketplace-client/organizer/events/{event}/check-in/{barcode}`
- **POS**: `/api/door-sales/*` endpoints with Stripe integration
- **Reports**: `/api/tenant-client/admin/dashboard` and related endpoints

The app will significantly enhance the Tixello platform by enabling on-site operations for event organizers.

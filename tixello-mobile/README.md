# Tixello Mobile App

A React Native/Expo mobile application for event staff to perform ticket check-ins, POS sales, and view live reports.

## Features

- **Ticket Check-In**: Scan QR codes/barcodes to validate and check in event attendees
- **POS Sales**: Sell tickets at the door with card (Tap to Pay) or cash payments
- **Live Reports**: View real-time event statistics and analytics
- **Offline Support**: Continue scanning tickets even without internet connection
- **Multi-Role Support**: Different interfaces for admins vs. gate staff

## Requirements

- Node.js 18+
- npm or yarn
- Expo CLI
- iOS Simulator (macOS) or Android Emulator
- Expo Go app (for device testing)

## Quick Start

### 1. Install Dependencies

```bash
cd tixello-mobile
npm install
```

### 2. Configure API URL

Create a `.env` file in the project root:

```
EXPO_PUBLIC_API_URL=https://your-tixello-backend.com
```

### 3. Run Development Server

```bash
npx expo start
```

This will display a QR code. Scan it with:
- **iOS**: Camera app → opens in Expo Go
- **Android**: Expo Go app → Scan QR Code

## Building APK (Android)

### Method 1: EAS Build (Recommended - No Android Studio)

```bash
# 1. Install EAS CLI
npm install -g eas-cli

# 2. Login to Expo
eas login

# 3. Configure project (one-time)
eas build:configure

# 4. Build APK
eas build -p android --profile preview

# 5. Download APK from the link provided (~10-15 min build time)
```

### Method 2: Local Build (Requires Android Studio)

```bash
npx expo run:android
```

## Building for iOS

```bash
# Using EAS Build
eas build -p ios --profile preview

# Or for local development (macOS only)
npx expo run:ios
```

## Project Structure

```
tixello-mobile/
├── app/                      # Expo Router screens
│   ├── (auth)/               # Auth screens (login)
│   ├── (main)/               # Main app screens
│   │   └── (tabs)/           # Tab navigation
│   │       ├── dashboard.tsx
│   │       ├── checkin.tsx
│   │       ├── sales.tsx
│   │       ├── reports.tsx
│   │       └── settings.tsx
│   ├── _layout.tsx
│   └── index.tsx             # Splash screen
├── src/
│   ├── api/                  # API client & endpoints
│   ├── components/           # Reusable components
│   │   └── ui/               # Base UI components
│   ├── hooks/                # Custom hooks
│   ├── stores/               # Zustand state management
│   ├── services/             # Business logic services
│   ├── types/                # TypeScript definitions
│   └── utils/                # Utilities & theme
├── assets/                   # Static assets
├── app.json                  # Expo config
├── eas.json                  # EAS Build config
└── package.json
```

## Key Technologies

- **Framework**: React Native with Expo SDK 51
- **Navigation**: Expo Router (file-based)
- **State Management**: Zustand
- **API Client**: Axios
- **UI**: Custom components + Ionicons
- **Barcode Scanning**: expo-camera + expo-barcode-scanner
- **Payments**: Stripe Terminal (Tap to Pay)
- **Offline Storage**: AsyncStorage + SecureStore

## API Integration

The app connects to the Tixello Laravel backend using these endpoints:

### Authentication
- `POST /api/tenant-client/auth/login` - Login
- `GET /api/tenant-client/auth/me` - Get current user

### Events
- `GET /api/door-sales/events` - Get events
- `GET /api/door-sales/events/{id}/ticket-types` - Get ticket types

### Check-In
- `POST /api/marketplace-client/organizer/events/{event}/check-in/{barcode}`
- `DELETE /api/marketplace-client/organizer/events/{event}/check-in/{barcode}` (undo)

### Door Sales (POS)
- `POST /api/door-sales/calculate` - Calculate order totals
- `POST /api/door-sales/process` - Process payment

### Reports
- `GET /api/tenant-client/admin/dashboard` - Dashboard stats
- `GET /api/marketplace-client/organizer/dashboard/timeline` - Timeline data

## Stripe Tap to Pay

The app supports Stripe Tap to Pay for contactless payments. The phone itself becomes the payment terminal - no external hardware needed.

### Requirements:
- **iPhone**: XS or later
- **Android**: NFC-enabled, Android 9+

### Setup:
1. Configure Stripe Connect in your Tixello backend
2. The app automatically initializes Stripe Terminal SDK
3. Staff taps "Card" button → Customer taps card/phone on device

## Offline Mode

The app can continue checking in tickets without internet:

1. Ticket data is cached locally
2. Check-ins are queued when offline
3. Queue syncs automatically when back online
4. Visual indicator shows pending sync count

## Customization

### Theme
Edit `src/utils/theme.ts` to change colors, typography, and spacing.

### API URL
Set `EXPO_PUBLIC_API_URL` in your environment or `.env` file.

### App Icons
Replace files in `assets/`:
- `icon.png` (1024x1024)
- `adaptive-icon.png` (1024x1024)
- `splash.png` (1284x2778)

## License

Proprietary - Tixello

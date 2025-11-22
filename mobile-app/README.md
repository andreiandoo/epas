# EPAS Mobile App

React Native Expo app for tenant event management and ticket validation.

## Features

- **Customer Login**: View tickets, orders, and upcoming events
- **Admin Login**: Manage events, scan tickets, view reports
- **Offline Support**: Validate tickets without internet connection
- **Cross-platform**: iOS, Android, and Web support

## Getting Started

### Prerequisites

- Node.js 18+
- npm or yarn

### Installation

```bash
cd mobile-app
npm install
```

### Running the App

```bash
# Start development server
npm start

# Run on web (for desktop testing)
npm run web

# Run on iOS simulator
npm run ios

# Run on Android emulator
npm run android
```

### Testing on Desktop

The app runs in your browser with `npm run web`. This is the easiest way to test on desktop.

## Project Structure

```
mobile-app/
├── App.tsx                 # Entry point
├── src/
│   ├── navigation/         # Navigation configuration
│   ├── screens/
│   │   ├── auth/          # Login screens
│   │   ├── customer/      # Customer dashboard
│   │   └── admin/         # Admin dashboard
│   ├── services/          # API services
│   ├── store/             # Zustand state management
│   └── types/             # TypeScript types
└── assets/                # Images and fonts
```

## Configuration

Set your API URL in the environment:

```bash
EXPO_PUBLIC_API_URL=http://your-api-url/api
```

## Authentication

### Customer Flow
1. Enter email/password
2. Access tickets, orders, events

### Admin Flow
1. Tap "Login as Tenant Admin"
2. Enter tenant domain (e.g., events.company.com)
3. Enter email/password
4. Access admin dashboard with scanner and reports

import React, { useState, useEffect } from 'react';

// SVG Icon Components
const Icons = {
  home: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>,
  camera: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>,
  cart: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>,
  chart: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><path d="M18 20V10M12 20V4M6 20v-6"/></svg>,
  settings: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>,
  bell: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>,
  pause: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>,
  play: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><polygon points="5 3 19 12 5 21 5 3"/></svg>,
  alert: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>,
  check: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><polyline points="20 6 9 17 4 12"/></svg>,
  checkCircle: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>,
  x: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>,
  xCircle: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>,
  money: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>,
  cash: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>,
  card: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>,
  clock: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>,
  logout: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>,
  users: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>,
  user: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>,
  clipboard: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/></svg>,
  qrCode: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 7h3v3H7zM14 7h3v3h-3zM7 14h3v3H7z"/><path d="M14 14h3v3"/></svg>,
  plus: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>,
  minus: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><line x1="5" y1="12" x2="19" y2="12"/></svg>,
  arrowLeft: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>,
  info: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>,
  ticket: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/></svg>,
  wristband: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>,
  battery: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><rect x="1" y="6" width="18" height="12" rx="2"/><line x1="23" y1="13" x2="23" y2="11"/><line x1="5" y1="10" x2="5" y2="14"/><line x1="9" y1="10" x2="9" y2="14"/></svg>,
  printer: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>,
  wifi: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>,
  shield: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>,
  vibrate: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><rect x="8" y="4" width="8" height="16" rx="2"/><path d="M2 8v8"/><path d="M6 6v12"/><path d="M18 6v12"/><path d="M22 8v8"/></svg>,
  volume: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/></svg>,
  zap: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>,
  database: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>,
  smartphone: (props) => <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...props}><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>,
};

export default function TixelloApp() {
  const [appState, setAppState] = useState('splash');
  const [userRole, setUserRole] = useState(null);
  const [userName, setUserName] = useState('');
  const [loginEmail, setLoginEmail] = useState('');
  const [loginPassword, setLoginPassword] = useState('');
  const [loginError, setLoginError] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [activeTab, setActiveTab] = useState('dashboard');
  const [activeView, setActiveView] = useState(null);
  const [scanResult, setScanResult] = useState(null);
  const [isScanning, setIsScanning] = useState(false);
  const [cartItems, setCartItems] = useState([]);
  const [selectedEvent, setSelectedEvent] = useState(null);
  const [paymentMethod, setPaymentMethod] = useState(null);
  const [showPaymentSuccess, setShowPaymentSuccess] = useState(false);
  const [notifications, setNotifications] = useState([
    { id: 1, type: 'alert', message: 'VIP gate running low on wristbands', time: '2m ago', unread: true },
    { id: 2, type: 'info', message: '500 check-ins milestone reached!', time: '15m ago', unread: true },
    { id: 3, type: 'success', message: 'Card reader reconnected', time: '1h ago', unread: false },
  ]);
  const [showNotifications, setShowNotifications] = useState(false);
  const [offlineMode, setOfflineMode] = useState(false);
  const [shiftPaused, setShiftPaused] = useState(false);
  const [showEmergencyModal, setShowEmergencyModal] = useState(false);
  const [emergencySent, setEmergencySent] = useState(null);
  const [shiftCashCollected, setShiftCashCollected] = useState(1250);
  const [shiftCardCollected, setShiftCardCollected] = useState(3450);
  const shiftStartTime = '18:00';
  
  // Scanner settings
  const [vibrationFeedback, setVibrationFeedback] = useState(true);
  const [soundEffects, setSoundEffects] = useState(true);
  const [autoConfirmValid, setAutoConfirmValid] = useState(false);
  const cachedTickets = 12456;

  const emergencyOptions = [
    { id: 1, icon: 'ticket', label: 'No more tickets', severity: 'high' },
    { id: 2, icon: 'wristband', label: 'No more wristbands', severity: 'high' },
    { id: 3, icon: 'battery', label: 'Scanner battery low', severity: 'medium' },
    { id: 4, icon: 'card', label: 'Card reader issue', severity: 'high' },
    { id: 5, icon: 'printer', label: 'Printer not working', severity: 'medium' },
    { id: 6, icon: 'wifi', label: 'Connection problems', severity: 'medium' },
    { id: 7, icon: 'users', label: 'Need backup staff', severity: 'low' },
    { id: 8, icon: 'shield', label: 'Security needed', severity: 'high' },
  ];
  
  const getEmergencyIcon = (iconName) => {
    const iconMap = {
      ticket: Icons.ticket,
      wristband: Icons.wristband,
      battery: Icons.battery,
      card: Icons.card,
      printer: Icons.printer,
      wifi: Icons.wifi,
      users: Icons.users,
      shield: Icons.shield,
    };
    const IconComponent = iconMap[iconName];
    return IconComponent ? <IconComponent style={{width: 28, height: 28}} /> : null;
  };

  const events = [
    { id: 1, name: 'Summer Music Festival 2025', date: 'Dec 28, 2025', venue: 'Arena Națională', capacity: 50000, checkedIn: 34567, sold: 48234, revenue: 4823400, status: 'live' },
    { id: 2, name: 'Tech Conference Romania', date: 'Jan 15, 2026', venue: 'Palatul Parlamentului', capacity: 2000, checkedIn: 0, sold: 1856, revenue: 927800, status: 'upcoming' },
  ];

  const ticketTypes = [
    { id: 1, name: 'General Admission', price: 150, available: 234, color: '#8B5CF6' },
    { id: 2, name: 'VIP Access', price: 450, available: 45, color: '#F59E0B' },
    { id: 3, name: 'Early Bird', price: 100, available: 0, color: '#10B981' },
    { id: 4, name: 'Student', price: 75, available: 89, color: '#06B6D4' },
  ];

  const recentScans = [
    { id: 1, name: 'Maria Ionescu', ticket: 'VIP Access', time: '10:32', status: 'valid', zone: 'Gate A' },
    { id: 2, name: 'Andrei Popescu', ticket: 'General', time: '10:31', status: 'valid', zone: 'Gate A' },
    { id: 3, name: 'Elena Dumitrescu', ticket: 'General', time: '10:30', status: 'duplicate', zone: 'Gate A' },
    { id: 4, name: 'Mihai Constantinescu', ticket: 'Student', time: '10:28', status: 'valid', zone: 'Gate A' },
  ];

  const salesHistory = [
    { id: 1, tickets: 2, type: 'General Admission', total: 300, method: 'card', time: '10:45' },
    { id: 2, tickets: 1, type: 'VIP Access', total: 450, method: 'cash', time: '10:38' },
    { id: 3, tickets: 4, type: 'Student', total: 300, method: 'card', time: '10:22' },
  ];

  const liveStats = { checkInsPerMinute: 45, salesPerMinute: 12, currentCapacity: 69, avgWaitTime: '2.3 min' };

  useEffect(() => {
    if (appState === 'splash') {
      const timer = setTimeout(() => setAppState('login'), 2500);
      return () => clearTimeout(timer);
    }
  }, [appState]);

  useEffect(() => {
    if (!selectedEvent) setSelectedEvent(events[0]);
  }, []);

  const handleLogin = () => {
    setLoginError('');
    if (loginEmail === 'admin@tixello.com' && loginPassword === 'admin') {
      setUserRole('admin');
      setUserName('Admin User');
      setActiveTab('dashboard');
      setAppState('app');
    } else if (loginEmail === 'scanner@tixello.com' && loginPassword === 'scanner') {
      setUserRole('scanner');
      setUserName('Alexandru M.');
      setActiveTab('checkin');
      setAppState('app');
    } else if (loginEmail && loginPassword) {
      if (loginEmail.includes('admin')) {
        setUserRole('admin');
        setUserName('Admin User');
        setActiveTab('dashboard');
      } else {
        setUserRole('scanner');
        setUserName(loginEmail.split('@')[0]);
        setActiveTab('checkin');
      }
      setAppState('app');
    } else {
      setLoginError('Please enter email and password');
    }
  };

  const handleScan = () => {
    if (shiftPaused) return;
    setIsScanning(true);
    setScanResult(null);
    setTimeout(() => {
      const results = [
        { status: 'valid', name: 'Alexandru Marin', ticket: 'VIP Access', seat: 'Section A, Row 3', message: 'Welcome!' },
        { status: 'valid', name: 'Maria Popescu', ticket: 'General Admission', seat: null, message: 'Access granted.' },
        { status: 'duplicate', name: 'Ion Georgescu', ticket: 'General', seat: null, message: 'Already scanned at 18:45' },
        { status: 'invalid', name: null, ticket: null, seat: null, message: 'Invalid QR code' },
      ];
      setScanResult(results[Math.floor(Math.random() * results.length)]);
      setIsScanning(false);
    }, 1500);
  };

  const addToCart = (ticket) => {
    if (shiftPaused) return;
    const existing = cartItems.find(item => item.id === ticket.id);
    if (existing) {
      setCartItems(cartItems.map(item => item.id === ticket.id ? { ...item, quantity: item.quantity + 1 } : item));
    } else {
      setCartItems([...cartItems, { ...ticket, quantity: 1 }]);
    }
  };

  const updateQuantity = (ticketId, delta) => {
    setCartItems(cartItems.map(item => {
      if (item.id === ticketId) {
        const newQty = Math.max(0, item.quantity + delta);
        return newQty === 0 ? null : { ...item, quantity: newQty };
      }
      return item;
    }).filter(Boolean));
  };

  const cartTotal = cartItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);
  const cartCount = cartItems.reduce((sum, item) => sum + item.quantity, 0);

  const processPayment = (method) => {
    setPaymentMethod(method);
    setTimeout(() => {
      setShowPaymentSuccess(true);
      if (method === 'cash') setShiftCashCollected(prev => prev + cartTotal);
      else setShiftCardCollected(prev => prev + cartTotal);
      setTimeout(() => {
        setShowPaymentSuccess(false);
        setPaymentMethod(null);
        setCartItems([]);
        setActiveView(null);
      }, 2500);
    }, 2000);
  };

  const formatCurrency = (amount) => new Intl.NumberFormat('ro-RO').format(amount) + ' lei';

  const sendEmergency = (emergency) => {
    setEmergencySent(emergency);
    setNotifications(prev => [{ id: Date.now(), type: 'alert', message: `Emergency: ${emergency.label}`, time: 'Just now', unread: true }, ...prev]);
    setTimeout(() => {
      setEmergencySent(null);
      setShowEmergencyModal(false);
    }, 2000);
  };

  const handleLogout = () => {
    setAppState('login');
    setUserRole(null);
    setUserName('');
    setLoginEmail('');
    setLoginPassword('');
    setActiveTab('dashboard');
    setActiveView(null);
    setShiftPaused(false);
  };

  // SPLASH SCREEN
  if (appState === 'splash') {
    return (
      <div style={styles.container}>
        <style>{keyframes}</style>
        <div style={styles.splash}>
          <div style={styles.splashContent}>
            <div style={styles.splashLogo}>
              <div style={styles.splashIconWrap}>
                <svg viewBox="0 0 64 64" fill="none" style={styles.splashIcon}>
                  <rect width="64" height="64" rx="16" fill="url(#g1)"/>
                  <path d="M16 24h32M16 32h24M16 40h16" stroke="white" strokeWidth="4" strokeLinecap="round"/>
                  <defs><linearGradient id="g1" x1="0" y1="0" x2="64" y2="64"><stop stopColor="#8B5CF6"/><stop offset="1" stopColor="#6366F1"/></linearGradient></defs>
                </svg>
              </div>
              <div style={styles.splashText}>Tixello</div>
            </div>
            <div style={styles.splashTagline}>Event Staff App</div>
            <div style={styles.loader}>
              <div style={styles.loaderBar}></div>
            </div>
          </div>
          <div style={styles.splashFooter}>Made with <span style={{color: '#EF4444'}}>♥</span> for events</div>
        </div>
      </div>
    );
  }

  // LOGIN SCREEN
  if (appState === 'login') {
    return (
      <div style={styles.container}>
        <style>{keyframes}</style>
        <div style={styles.loginScreen}>
          <div style={styles.loginHeader}>
            <div style={styles.loginLogo}>
              <svg viewBox="0 0 48 48" fill="none" style={styles.loginIcon}>
                <rect width="48" height="48" rx="12" fill="url(#g2)"/>
                <path d="M12 18h24M12 24h18M12 30h12" stroke="white" strokeWidth="3" strokeLinecap="round"/>
                <defs><linearGradient id="g2" x1="0" y1="0" x2="48" y2="48"><stop stopColor="#8B5CF6"/><stop offset="1" stopColor="#6366F1"/></linearGradient></defs>
              </svg>
              <span style={styles.loginBrand}>Tixello</span>
            </div>
            <h1 style={styles.loginTitle}>Welcome back</h1>
            <p style={styles.loginSubtitle}>Sign in to manage your event</p>
          </div>

          <div style={styles.loginForm}>
            <div style={styles.formField}>
              <label style={styles.label}>Email</label>
              <input 
                type="email" 
                placeholder="you@example.com" 
                value={loginEmail} 
                onChange={(e) => setLoginEmail(e.target.value)} 
                style={styles.input}
              />
            </div>
            <div style={styles.formField}>
              <label style={styles.label}>Password</label>
              <div style={styles.passwordWrap}>
                <input 
                  type={showPassword ? 'text' : 'password'} 
                  placeholder="Enter your password" 
                  value={loginPassword} 
                  onChange={(e) => setLoginPassword(e.target.value)} 
                  style={styles.input}
                  onKeyDown={(e) => e.key === 'Enter' && handleLogin()}
                />
                <button 
                  type="button" 
                  style={styles.showPassBtn} 
                  onClick={() => setShowPassword(!showPassword)}
                >
                  {showPassword ? '🙈' : '👁️'}
                </button>
              </div>
            </div>
            {loginError && <div style={styles.error}>{loginError}</div>}
            <button 
              type="button" 
              style={styles.loginBtn} 
              onClick={handleLogin}
            >
              Sign In →
            </button>
          </div>

          <div style={styles.demoSection}>
            <span style={styles.demoLabel}>Demo accounts:</span>
            <div style={styles.demoButtons}>
              <button 
                type="button"
                style={styles.demoBtn} 
                onClick={() => { setLoginEmail('admin@tixello.com'); setLoginPassword('admin'); }}
              >
                <span style={styles.demoRole}><Icons.user style={{width: 14, height: 14, marginRight: 4}} /> Admin</span>
                <span style={styles.demoEmail}>admin@tixello.com</span>
              </button>
              <button 
                type="button"
                style={styles.demoBtn} 
                onClick={() => { setLoginEmail('scanner@tixello.com'); setLoginPassword('scanner'); }}
              >
                <span style={styles.demoRole}><Icons.smartphone style={{width: 14, height: 14, marginRight: 4}} /> Scanner</span>
                <span style={styles.demoEmail}>scanner@tixello.com</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  // MAIN APP
  return (
    <div style={styles.container}>
      <style>{keyframes}</style>
      
      {/* Header */}
      <div style={{...styles.header, ...(shiftPaused ? styles.headerPaused : {})}}>
        <div style={styles.headerLeft}>
          <svg viewBox="0 0 32 32" fill="none" style={styles.headerLogo}>
            <rect width="32" height="32" rx="8" fill="url(#g3)"/>
            <path d="M8 12h16M8 16h12M8 20h8" stroke="white" strokeWidth="2" strokeLinecap="round"/>
            <defs><linearGradient id="g3" x1="0" y1="0" x2="32" y2="32"><stop stopColor="#8B5CF6"/><stop offset="1" stopColor="#6366F1"/></linearGradient></defs>
          </svg>
          <span style={styles.logoText}>Tixello</span>
        </div>
        <div style={styles.headerRight}>
          {shiftPaused && <div style={styles.pausedBadge}><Icons.pause style={{width: 12, height: 12}} /> PAUSED</div>}
          <div style={offlineMode ? styles.statusOffline : styles.statusOnline}>
            <span style={styles.statusDot}></span>
            {offlineMode ? 'Offline' : 'Live'}
          </div>
          <button style={styles.notifBtn} onClick={() => setShowNotifications(!showNotifications)}>
            <Icons.bell style={{width: 18, height: 18}} />
            {notifications.filter(n => n.unread).length > 0 && (
              <span style={styles.notifBadge}>{notifications.filter(n => n.unread).length}</span>
            )}
          </button>
        </div>
      </div>

      {/* Event Selector */}
      <div style={styles.eventSelector}>
        <div style={styles.eventInfo}>
          <div style={styles.eventName}>{selectedEvent?.name}</div>
          <div style={styles.eventMeta}>{selectedEvent?.date} • {selectedEvent?.venue}</div>
        </div>
        <div style={selectedEvent?.status === 'live' ? styles.statusLive : styles.statusUpcoming}>
          {selectedEvent?.status === 'live' ? <><span style={styles.liveDot}></span> LIVE</> : 'Upcoming'}
        </div>
      </div>

      {/* Scanner Shift Bar */}
      {userRole === 'scanner' && (
        <div style={{...styles.shiftBar, ...(shiftPaused ? styles.shiftBarPaused : {})}}>
          <div style={styles.shiftInfo}>
            <div style={styles.shiftTime}><Icons.clock style={{width: 14, height: 14}} /> Started {shiftStartTime}</div>
            <div style={styles.turnoverRow}>
              <span style={styles.turnoverLabel}>Cash to turn over:</span>
              <span style={styles.turnoverAmount}>{formatCurrency(shiftCashCollected)}</span>
            </div>
          </div>
          <div style={styles.shiftActions}>
            <button 
              style={shiftPaused ? styles.resumeBtn : styles.pauseBtn} 
              onClick={() => setShiftPaused(!shiftPaused)}
            >
              {shiftPaused ? <><Icons.play style={{width: 14, height: 14}} /> Resume</> : <><Icons.pause style={{width: 14, height: 14}} /> Pause</>}
            </button>
            <button style={styles.emergencyBtn} onClick={() => setShowEmergencyModal(true)}>
              <Icons.alert style={{width: 18, height: 18}} />
            </button>
          </div>
        </div>
      )}

      {/* Main Content */}
      <div style={styles.mainContent}>
        {/* Dashboard */}
        {activeTab === 'dashboard' && (
          <div style={styles.dashboard}>
            {userRole === 'admin' ? (
              <>
                <div style={styles.statsGrid}>
                  <div style={styles.statCardPrimary}>
                    <div style={styles.statValue}>{selectedEvent?.checkedIn.toLocaleString()}</div>
                    <div style={styles.statLabel}>Checked In</div>
                    <div style={styles.statTrend}>+{liveStats.checkInsPerMinute}/min</div>
                  </div>
                  <div style={styles.statCard}>
                    <div style={styles.statValue}>{formatCurrency(selectedEvent?.revenue)}</div>
                    <div style={styles.statLabel}>Total Revenue</div>
                  </div>
                  <div style={styles.statCard}>
                    <div style={styles.statValue}>{liveStats.currentCapacity}%</div>
                    <div style={styles.statLabel}>Capacity</div>
                  </div>
                  <div style={styles.statCard}>
                    <div style={styles.statValue}>{selectedEvent?.sold.toLocaleString()}</div>
                    <div style={styles.statLabel}>Tickets Sold</div>
                  </div>
                </div>
                <h3 style={styles.sectionTitle}>Quick Actions</h3>
                <div style={styles.actionsGrid}>
                  <button style={styles.actionBtn} onClick={() => setActiveTab('checkin')}><Icons.camera style={{width: 24, height: 24}} /><br/>Scan</button>
                  <button style={styles.actionBtn} onClick={() => setActiveTab('sales')}><Icons.cart style={{width: 24, height: 24}} /><br/>Sell</button>
                  <button style={styles.actionBtn} onClick={() => setActiveView('guestlist')}><Icons.clipboard style={{width: 24, height: 24}} /><br/>Guests</button>
                  <button style={styles.actionBtn} onClick={() => setActiveView('staff')}><Icons.users style={{width: 24, height: 24}} /><br/>Staff</button>
                </div>
              </>
            ) : (
              <>
                <div style={styles.turnoverCard}>
                  <h3 style={styles.turnoverTitle}><Icons.money style={{width: 20, height: 20, marginRight: 8}} /> Shift Summary</h3>
                  <div style={styles.turnoverGrid}>
                    <div style={styles.turnoverItem}>
                      <div style={styles.turnoverIcon}><Icons.cash style={{width: 24, height: 24, color: '#10B981'}} /></div>
                      <div>
                        <div style={styles.turnoverItemLabel}>Cash to turn over</div>
                        <div style={styles.turnoverItemValue}>{formatCurrency(shiftCashCollected)}</div>
                      </div>
                    </div>
                    <div style={styles.turnoverItem}>
                      <div style={styles.turnoverIcon}><Icons.card style={{width: 24, height: 24, color: '#06B6D4'}} /></div>
                      <div>
                        <div style={styles.turnoverItemLabel}>Card payments</div>
                        <div style={styles.turnoverItemValueBlue}>{formatCurrency(shiftCardCollected)}</div>
                      </div>
                    </div>
                  </div>
                </div>
                <div style={styles.scannerStats}>
                  <div style={styles.scannerStatItem}>
                    <div style={styles.scannerStatValue}>234</div>
                    <div style={styles.scannerStatLabel}>My Scans</div>
                  </div>
                  <div style={styles.scannerStatItem}>
                    <div style={styles.scannerStatValue}>12</div>
                    <div style={styles.scannerStatLabel}>My Sales</div>
                  </div>
                  <div style={styles.scannerStatItem}>
                    <div style={styles.scannerStatValue}>{liveStats.avgWaitTime}</div>
                    <div style={styles.scannerStatLabel}>Avg Wait</div>
                  </div>
                </div>
                <div style={styles.quickActionsLarge}>
                  <button style={styles.actionBtnLarge} onClick={() => setActiveTab('checkin')}>
                    <Icons.camera style={{width: 20, height: 20, marginRight: 8}} /> Scan Tickets
                  </button>
                  <button style={styles.actionBtnLargeSell} onClick={() => setActiveTab('sales')}>
                    <Icons.cart style={{width: 20, height: 20, marginRight: 8}} /> Sell Tickets
                  </button>
                </div>
              </>
            )}
          </div>
        )}

        {/* Check-in */}
        {activeTab === 'checkin' && (
          <div style={styles.checkinView}>
            {shiftPaused && (
              <div style={styles.pausedOverlay}>
                <div style={styles.pausedContent}>
                  <div style={styles.pausedIcon}><Icons.pause style={{width: 64, height: 64}} /></div>
                  <div style={styles.pausedText}>Shift Paused</div>
                  <button style={styles.resumeBtnLarge} onClick={() => setShiftPaused(false)}>
                    Resume Shift
                  </button>
                </div>
              </div>
            )}
            <div style={styles.scannerContainer}>
              <div style={{
                ...styles.scannerFrame,
                ...(isScanning ? styles.scannerFrameScanning : {}),
                ...(scanResult?.status === 'valid' ? styles.scannerFrameValid : {}),
                ...(scanResult?.status === 'duplicate' ? styles.scannerFrameDuplicate : {}),
                ...(scanResult?.status === 'invalid' ? styles.scannerFrameInvalid : {})
              }}>
                {!isScanning && !scanResult && (
                  <div style={styles.scannerPrompt}>
                    <div style={{marginBottom: 12}}><Icons.smartphone style={{width: 48, height: 48, color: 'rgba(255,255,255,0.4)'}} /></div>
                    <div>Point camera at QR code</div>
                  </div>
                )}
                {isScanning && <div style={styles.scanningText}>Scanning...</div>}
                {scanResult && (
                  <div style={styles.scanResultIcon}>
                    {scanResult.status === 'valid' && <Icons.checkCircle style={{width: 64, height: 64, color: '#10B981'}} />}
                    {scanResult.status === 'duplicate' && <Icons.alert style={{width: 64, height: 64, color: '#F59E0B'}} />}
                    {scanResult.status === 'invalid' && <Icons.xCircle style={{width: 64, height: 64, color: '#EF4444'}} />}
                  </div>
                )}
              </div>

              {scanResult && (
                <div style={{
                  ...styles.resultCard,
                  ...(scanResult.status === 'valid' ? styles.resultCardValid : {}),
                  ...(scanResult.status === 'duplicate' ? styles.resultCardDuplicate : {}),
                  ...(scanResult.status === 'invalid' ? styles.resultCardInvalid : {})
                }}>
                  <div style={{
                    ...styles.resultStatus,
                    color: scanResult.status === 'valid' ? '#10B981' : scanResult.status === 'duplicate' ? '#F59E0B' : '#EF4444'
                  }}>
                    {scanResult.status === 'valid' && 'ACCESS GRANTED'}
                    {scanResult.status === 'duplicate' && 'ALREADY SCANNED'}
                    {scanResult.status === 'invalid' && 'INVALID TICKET'}
                  </div>
                  {scanResult.name && <div style={styles.resultName}>{scanResult.name}</div>}
                  {scanResult.ticket && <div style={styles.resultTicket}>{scanResult.ticket}</div>}
                  <div style={styles.resultMessage}>{scanResult.message}</div>
                </div>
              )}

              <button 
                style={{...styles.scanBtn, ...(shiftPaused ? {opacity: 0.5} : {})}} 
                onClick={handleScan} 
                disabled={isScanning || shiftPaused}
              >
                {isScanning ? <><span style={styles.spinner}></span> Scanning...</> : scanResult ? <><Icons.camera style={{width: 20, height: 20, marginRight: 8}} /> Scan Next</> : <><Icons.camera style={{width: 20, height: 20, marginRight: 8}} /> Start Scanning</>}
              </button>
            </div>

            <div style={styles.recentScans}>
              <h4 style={styles.recentTitle}>Recent Scans</h4>
              {recentScans.map(scan => (
                <div key={scan.id} style={styles.scanItem}>
                  <div style={scan.status === 'valid' ? styles.scanStatusValid : styles.scanStatusDuplicate}>
                    {scan.status === 'valid' ? <Icons.check style={{width: 14, height: 14}} /> : <Icons.alert style={{width: 14, height: 14}} />}
                  </div>
                  <div style={styles.scanInfo}>
                    <div style={styles.scanName}>{scan.name}</div>
                    <div style={styles.scanTicket}>{scan.ticket}</div>
                  </div>
                  <div style={styles.scanTime}>{scan.time}</div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Sales */}
        {activeTab === 'sales' && (
          <div style={styles.salesView}>
            {shiftPaused && (
              <div style={styles.pausedOverlay}>
                <div style={styles.pausedContent}>
                  <div style={styles.pausedIcon}><Icons.pause style={{width: 64, height: 64}} /></div>
                  <div style={styles.pausedText}>Shift Paused</div>
                  <button style={styles.resumeBtnLarge} onClick={() => setShiftPaused(false)}>
                    Resume Shift
                  </button>
                </div>
              </div>
            )}

            {activeView === 'cart' ? (
              <div style={styles.cartView}>
                <div style={styles.cartHeader}>
                  <button style={styles.backBtn} onClick={() => setActiveView(null)}><Icons.arrowLeft style={{width: 20, height: 20}} /></button>
                  <h3 style={{flex: 1, margin: 0}}>Cart ({cartCount})</h3>
                </div>

                {cartItems.map(item => (
                  <div key={item.id} style={styles.cartItem}>
                    <div style={{...styles.cartItemBadge, background: item.color}}></div>
                    <div style={styles.cartItemInfo}>
                      <div style={styles.cartItemName}>{item.name}</div>
                      <div style={styles.cartItemPrice}>{formatCurrency(item.price)}</div>
                    </div>
                    <div style={styles.qtyControls}>
                      <button style={styles.qtyBtn} onClick={() => updateQuantity(item.id, -1)}>−</button>
                      <span style={styles.qtyValue}>{item.quantity}</span>
                      <button style={styles.qtyBtn} onClick={() => updateQuantity(item.id, 1)}>+</button>
                    </div>
                    <div style={styles.cartItemTotal}>{formatCurrency(item.price * item.quantity)}</div>
                  </div>
                ))}

                <div style={styles.cartTotal}>
                  <span>Total</span>
                  <span style={styles.cartTotalValue}>{formatCurrency(cartTotal)}</span>
                </div>

                <h4 style={styles.paymentTitle}>Payment Method</h4>
                <div style={styles.paymentGrid}>
                  <button 
                    style={{...styles.paymentBtn, ...(paymentMethod === 'card' ? styles.paymentBtnActive : {})}} 
                    onClick={() => processPayment('card')} 
                    disabled={paymentMethod}
                  >
                    <Icons.card style={{width: 24, height: 24, marginRight: 8}} /> Card {paymentMethod === 'card' && '...'}
                  </button>
                  <button 
                    style={{...styles.paymentBtn, ...(paymentMethod === 'cash' ? styles.paymentBtnActive : {})}} 
                    onClick={() => processPayment('cash')} 
                    disabled={paymentMethod}
                  >
                    <Icons.cash style={{width: 24, height: 24, marginRight: 8}} /> Cash {paymentMethod === 'cash' && '...'}
                  </button>
                </div>

                {showPaymentSuccess && (
                  <div style={styles.paymentSuccess}>
                    <div style={styles.successContent}>
                      <div style={styles.successIcon}><Icons.checkCircle style={{width: 64, height: 64, color: '#10B981'}} /></div>
                      <div style={styles.successText}>Payment Successful!</div>
                      <div style={styles.successAmount}>{formatCurrency(cartTotal)}</div>
                    </div>
                  </div>
                )}
              </div>
            ) : (
              <>
                <h3 style={styles.sectionTitle}>Select Tickets</h3>
                {ticketTypes.map(ticket => (
                  <div 
                    key={ticket.id} 
                    style={{...styles.ticketCard, ...(ticket.available === 0 ? styles.ticketCardSoldout : {})}} 
                    onClick={() => ticket.available > 0 && !shiftPaused && addToCart(ticket)}
                  >
                    <div style={{...styles.ticketBadge, background: ticket.color}}></div>
                    <div style={styles.ticketInfo}>
                      <div style={styles.ticketName}>{ticket.name}</div>
                      <div style={styles.ticketPrice}>{formatCurrency(ticket.price)}</div>
                    </div>
                    <div style={ticket.available > 0 ? styles.ticketAvailable : styles.ticketSoldout}>
                      {ticket.available > 0 ? `${ticket.available} left` : 'Sold Out'}
                    </div>
                    {ticket.available > 0 && <button style={styles.addBtn}><Icons.plus style={{width: 18, height: 18}} /></button>}
                  </div>
                ))}

                <div style={styles.salesHistory}>
                  <h4 style={styles.historyTitle}>My Sales Today</h4>
                  {salesHistory.map(sale => (
                    <div key={sale.id} style={styles.historyItem}>
                      <div style={styles.historyIcon}>{sale.method === 'card' ? <Icons.card style={{width: 18, height: 18}} /> : <Icons.cash style={{width: 18, height: 18}} />}</div>
                      <div style={styles.historyInfo}>
                        <div>{sale.tickets}x {sale.type}</div>
                        <div style={styles.historyTime}>{sale.time}</div>
                      </div>
                      <div style={styles.historyAmount}>{formatCurrency(sale.total)}</div>
                    </div>
                  ))}
                </div>

                {cartCount > 0 && (
                  <div style={styles.cartFab} onClick={() => setActiveView('cart')}>
                    <span style={styles.fabBadge}>{cartCount}</span>
                    <Icons.cart style={{width: 22, height: 22, marginRight: 8}} /> {formatCurrency(cartTotal)}
                  </div>
                )}
              </>
            )}
          </div>
        )}

        {/* Reports (Admin only) */}
        {activeTab === 'reports' && userRole === 'admin' && (
          <div style={styles.reportsView}>
            <h3 style={styles.reportsTitle}><Icons.chart style={{width: 20, height: 20, marginRight: 8}} /> Live Reports</h3>
            <div style={styles.metricsGrid}>
              <div style={styles.metricCard}>
                <div style={styles.metricValue}>{liveStats.checkInsPerMinute}/min</div>
                <div style={styles.metricLabel}>Check-in Rate</div>
              </div>
              <div style={styles.metricCard}>
                <div style={styles.metricValue}>{liveStats.salesPerMinute}/min</div>
                <div style={styles.metricLabel}>Sales Rate</div>
              </div>
            </div>
            <h4 style={styles.reportSection}>Gate Performance</h4>
            {['Gate A', 'Gate B', 'Gate C', 'VIP'].map((gate, i) => (
              <div key={gate} style={styles.gateItem}>
                <span style={styles.gateName}>{gate}</span>
                <div style={styles.gateBar}>
                  <div style={{...styles.gateProgress, width: `${[68,82,45,23][i]}%`}}></div>
                </div>
                <span style={styles.gatePercent}>{[68,82,45,23][i]}%</span>
              </div>
            ))}
          </div>
        )}

        {/* Settings */}
        {activeTab === 'settings' && (
          <div style={styles.settingsView}>
            <h3 style={styles.settingsTitle}><Icons.settings style={{width: 20, height: 20, marginRight: 8}} /> Settings</h3>
            
            <div style={styles.settingsSection}>
              <h4 style={styles.settingsSectionTitle}>Account</h4>
              <div style={styles.settingItem}>
                <div>
                  <div style={styles.settingLabel}>Staff Member</div>
                  <div style={styles.settingValue}>{userName}</div>
                </div>
              </div>
              <div style={styles.settingItem}>
                <div>
                  <div style={styles.settingLabel}>Role</div>
                  <div style={styles.settingValue}>{userRole === 'admin' ? 'Administrator' : 'Gate Staff + POS'}</div>
                </div>
              </div>
            </div>

            <div style={styles.settingsSection}>
              <h4 style={styles.settingsSectionTitle}>Scanner</h4>
              <div style={styles.settingItem}>
                <div style={styles.settingRow}>
                  <div style={styles.settingIconBox}><Icons.vibrate style={{width: 18, height: 18}} /></div>
                  <div>
                    <div style={styles.settingLabel}>Vibration Feedback</div>
                    <div style={styles.settingDesc}>Vibrate on successful scan</div>
                  </div>
                </div>
                <button 
                  style={vibrationFeedback ? styles.toggleOn : styles.toggleOff} 
                  onClick={() => setVibrationFeedback(!vibrationFeedback)}
                >
                  <div style={styles.toggleThumb}></div>
                </button>
              </div>
              <div style={styles.settingItem}>
                <div style={styles.settingRow}>
                  <div style={styles.settingIconBox}><Icons.volume style={{width: 18, height: 18}} /></div>
                  <div>
                    <div style={styles.settingLabel}>Sound Effects</div>
                    <div style={styles.settingDesc}>Play sound on scan</div>
                  </div>
                </div>
                <button 
                  style={soundEffects ? styles.toggleOn : styles.toggleOff} 
                  onClick={() => setSoundEffects(!soundEffects)}
                >
                  <div style={styles.toggleThumb}></div>
                </button>
              </div>
              <div style={styles.settingItem}>
                <div style={styles.settingRow}>
                  <div style={styles.settingIconBox}><Icons.zap style={{width: 18, height: 18}} /></div>
                  <div>
                    <div style={styles.settingLabel}>Auto-confirm Valid</div>
                    <div style={styles.settingDesc}>Skip confirmation for valid tickets</div>
                  </div>
                </div>
                <button 
                  style={autoConfirmValid ? styles.toggleOn : styles.toggleOff} 
                  onClick={() => setAutoConfirmValid(!autoConfirmValid)}
                >
                  <div style={styles.toggleThumb}></div>
                </button>
              </div>
            </div>

            <div style={styles.settingsSection}>
              <h4 style={styles.settingsSectionTitle}>Offline Mode</h4>
              <div style={styles.settingItem}>
                <div style={styles.settingRow}>
                  <div style={styles.settingIconBox}><Icons.wifi style={{width: 18, height: 18}} /></div>
                  <div>
                    <div style={styles.settingLabel}>Enable Offline Mode</div>
                    <div style={styles.settingDesc}>Continue scanning without internet</div>
                  </div>
                </div>
                <button 
                  style={offlineMode ? styles.toggleOn : styles.toggleOff} 
                  onClick={() => setOfflineMode(!offlineMode)}
                >
                  <div style={styles.toggleThumb}></div>
                </button>
              </div>
              <div style={styles.offlineInfo}>
                <Icons.database style={{width: 18, height: 18, color: '#06B6D4', flexShrink: 0}} />
                <span>{cachedTickets.toLocaleString()} tickets cached for offline scanning</span>
              </div>
            </div>

            <button style={styles.logoutBtn} onClick={handleLogout}>
              <Icons.logout style={{width: 18, height: 18, marginRight: 8}} /> End Shift & Logout
            </button>
          </div>
        )}
      </div>

      {/* Bottom Navigation */}
      <nav style={styles.bottomNav}>
        <button 
          style={activeTab === 'dashboard' ? styles.navItemActive : styles.navItem} 
          onClick={() => { setActiveTab('dashboard'); setActiveView(null); }}
        >
          <Icons.home style={{width: 22, height: 22}} />
          <span style={styles.navLabel}>Home</span>
        </button>
        <button 
          style={activeTab === 'checkin' ? styles.navItemActive : styles.navItem} 
          onClick={() => { setActiveTab('checkin'); setActiveView(null); }}
        >
          <Icons.camera style={{width: 22, height: 22}} />
          <span style={styles.navLabel}>Scan</span>
        </button>
        <button 
          style={activeTab === 'sales' ? styles.navItemActive : styles.navItem} 
          onClick={() => { setActiveTab('sales'); setActiveView(null); }}
        >
          <Icons.cart style={{width: 22, height: 22}} />
          <span style={styles.navLabel}>Sell</span>
        </button>
        {userRole === 'admin' && (
          <button 
            style={activeTab === 'reports' ? styles.navItemActive : styles.navItem} 
            onClick={() => { setActiveTab('reports'); setActiveView(null); }}
          >
            <Icons.chart style={{width: 22, height: 22}} />
            <span style={styles.navLabel}>Reports</span>
          </button>
        )}
        <button 
          style={activeTab === 'settings' ? styles.navItemActive : styles.navItem} 
          onClick={() => { setActiveTab('settings'); setActiveView(null); }}
        >
          <Icons.settings style={{width: 22, height: 22}} />
          <span style={styles.navLabel}>Settings</span>
        </button>
      </nav>

      {/* Emergency Modal */}
      {showEmergencyModal && (
        <div style={styles.modalOverlay} onClick={() => !emergencySent && setShowEmergencyModal(false)}>
          <div style={styles.modalContent} onClick={e => e.stopPropagation()}>
            {emergencySent ? (
              <div style={styles.emergencySent}>
                <div style={{marginBottom: 16}}><Icons.checkCircle style={{width: 64, height: 64, color: '#10B981'}} /></div>
                <h3 style={{marginBottom: 8}}>Alert Sent!</h3>
                <p style={{color: 'rgba(255,255,255,0.6)'}}>"{emergencySent.label}" reported to supervisors.</p>
              </div>
            ) : (
              <>
                <h3 style={styles.modalTitle}><Icons.alert style={{width: 20, height: 20, color: '#EF4444', marginRight: 8}} /> Report Issue</h3>
                <p style={styles.modalDesc}>Select an issue to notify supervisors</p>
                <div style={styles.emergencyGrid}>
                  {emergencyOptions.map(opt => (
                    <button 
                      key={opt.id} 
                      style={{
                        ...styles.emergencyOption,
                        background: opt.severity === 'high' ? 'rgba(239,68,68,0.15)' : 
                                   opt.severity === 'medium' ? 'rgba(245,158,11,0.15)' : 
                                   'rgba(255,255,255,0.05)'
                      }} 
                      onClick={() => sendEmergency(opt)}
                    >
                      {getEmergencyIcon(opt.icon)}
                      <span style={{fontSize: 12, fontWeight: 500}}>{opt.label}</span>
                    </button>
                  ))}
                </div>
              </>
            )}
          </div>
        </div>
      )}

      {/* Notifications */}
      {showNotifications && (
        <div style={styles.notifPanel} onClick={() => setShowNotifications(false)}>
          <div style={styles.notifContent} onClick={e => e.stopPropagation()}>
            <h3 style={styles.notifTitle}>Notifications</h3>
            {notifications.map(n => (
              <div key={n.id} style={{...styles.notifItem, ...(n.unread ? styles.notifUnread : {})}}>
                <div style={styles.notifIcon}>
                  {n.type === 'alert' && <Icons.alert style={{width: 18, height: 18, color: '#EF4444'}} />}
                  {n.type === 'info' && <Icons.info style={{width: 18, height: 18, color: '#06B6D4'}} />}
                  {n.type === 'success' && <Icons.checkCircle style={{width: 18, height: 18, color: '#10B981'}} />}
                </div>
                <div style={styles.notifText}>
                  <div style={{fontSize: 13}}>{n.message}</div>
                  <div style={styles.notifTime}>{n.time}</div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

const keyframes = `
  @keyframes loading {
    0% { width: 0%; }
    100% { width: 100%; }
  }
  @keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
  }
  @keyframes glow {
    0%, 100% { box-shadow: 0 0 20px rgba(139, 92, 246, 0.4); }
    50% { box-shadow: 0 0 40px rgba(139, 92, 246, 0.8); }
  }
  @keyframes spin {
    to { transform: rotate(360deg); }
  }
`;

const styles = {
  container: { 
    fontFamily: "'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif", 
    background: '#0A0A0F', 
    minHeight: '100vh', 
    color: '#fff', 
    maxWidth: 430, 
    margin: '0 auto', 
    position: 'relative',
    overflow: 'hidden'
  },
  
  // Splash
  splash: { 
    minHeight: '100vh', 
    display: 'flex', 
    flexDirection: 'column',
    alignItems: 'center', 
    justifyContent: 'center', 
    background: 'linear-gradient(180deg, #0A0A0F 0%, #12121A 100%)',
    padding: 40
  },
  splashContent: { display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 20 },
  splashLogo: { display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 16 },
  splashIconWrap: { animation: 'glow 2s ease-in-out infinite' },
  splashIcon: { width: 80, height: 80 },
  splashText: { fontSize: 36, fontWeight: 700, color: '#fff', letterSpacing: -1 },
  splashTagline: { fontSize: 14, color: 'rgba(255,255,255,0.5)' },
  loader: { width: 120, height: 3, background: 'rgba(255,255,255,0.1)', borderRadius: 2, overflow: 'hidden', marginTop: 20 },
  loaderBar: { height: '100%', background: 'linear-gradient(90deg, #8B5CF6, #6366F1)', borderRadius: 2, animation: 'loading 2s ease-in-out forwards' },
  splashFooter: { position: 'absolute', bottom: 40, color: 'rgba(255,255,255,0.3)', fontSize: 13, display: 'flex', alignItems: 'center', gap: 6 },

  // Login
  loginScreen: { minHeight: '100vh', padding: '60px 24px 40px', display: 'flex', flexDirection: 'column' },
  loginHeader: { textAlign: 'center', marginBottom: 40 },
  loginLogo: { display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 12, marginBottom: 32 },
  loginIcon: { width: 48, height: 48 },
  loginBrand: { fontSize: 24, fontWeight: 700 },
  loginTitle: { fontSize: 28, fontWeight: 700, marginBottom: 8, margin: 0 },
  loginSubtitle: { color: 'rgba(255,255,255,0.5)', fontSize: 15, margin: 0 },
  loginForm: { display: 'flex', flexDirection: 'column', gap: 20 },
  formField: { display: 'flex', flexDirection: 'column', gap: 8 },
  label: { fontSize: 13, fontWeight: 600, color: 'rgba(255,255,255,0.7)' },
  input: { 
    padding: 16, 
    background: 'rgba(255,255,255,0.05)', 
    border: '1px solid rgba(255,255,255,0.1)', 
    borderRadius: 14, 
    color: '#fff', 
    fontSize: 15, 
    outline: 'none',
    width: '100%',
    boxSizing: 'border-box'
  },
  passwordWrap: { position: 'relative' },
  showPassBtn: { 
    position: 'absolute', 
    right: 12, 
    top: '50%', 
    transform: 'translateY(-50%)', 
    background: 'none', 
    border: 'none', 
    fontSize: 18, 
    cursor: 'pointer',
    padding: 8
  },
  error: { padding: 14, background: 'rgba(239,68,68,0.1)', border: '1px solid rgba(239,68,68,0.2)', borderRadius: 12, color: '#EF4444', fontSize: 14 },
  loginBtn: { 
    padding: 18, 
    background: 'linear-gradient(135deg, #8B5CF6, #6366F1)', 
    border: 'none', 
    borderRadius: 14, 
    color: '#fff', 
    fontSize: 16, 
    fontWeight: 600, 
    cursor: 'pointer', 
    marginTop: 8 
  },
  demoSection: { marginTop: 'auto', paddingTop: 40, textAlign: 'center' },
  demoLabel: { display: 'block', fontSize: 12, color: 'rgba(255,255,255,0.4)', marginBottom: 12 },
  demoButtons: { display: 'flex', gap: 10 },
  demoBtn: { 
    flex: 1, 
    padding: '14px 12px', 
    background: 'rgba(255,255,255,0.03)', 
    border: '1px solid rgba(255,255,255,0.08)', 
    borderRadius: 12, 
    cursor: 'pointer',
    textAlign: 'left'
  },
  demoRole: { display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 13, fontWeight: 600, color: '#fff', marginBottom: 4 },
  demoEmail: { display: 'block', fontSize: 11, color: 'rgba(255,255,255,0.4)' },

  // Header
  header: { 
    display: 'flex', 
    justifyContent: 'space-between', 
    alignItems: 'center', 
    padding: '16px 20px', 
    background: 'rgba(10,10,15,0.98)', 
    position: 'sticky', 
    top: 0, 
    zIndex: 100, 
    borderBottom: '1px solid rgba(255,255,255,0.05)',
    backdropFilter: 'blur(10px)'
  },
  headerPaused: { background: 'rgba(245,158,11,0.1)', borderBottomColor: 'rgba(245,158,11,0.2)' },
  headerLeft: { display: 'flex', alignItems: 'center', gap: 10 },
  headerLogo: { width: 32, height: 32 },
  headerRight: { display: 'flex', alignItems: 'center', gap: 12 },
  logoText: { fontSize: 20, fontWeight: 700 },
  pausedBadge: { display: 'flex', alignItems: 'center', gap: 6, padding: '6px 12px', background: 'rgba(245,158,11,0.2)', borderRadius: 8, color: '#F59E0B', fontSize: 11, fontWeight: 700 },
  statusOnline: { display: 'flex', alignItems: 'center', gap: 6, padding: '6px 12px', borderRadius: 20, fontSize: 12, fontWeight: 600, background: 'rgba(16,185,129,0.15)', color: '#10B981' },
  statusOffline: { display: 'flex', alignItems: 'center', gap: 6, padding: '6px 12px', borderRadius: 20, fontSize: 12, fontWeight: 600, background: 'rgba(239,68,68,0.15)', color: '#EF4444' },
  statusDot: { width: 6, height: 6, borderRadius: '50%', background: 'currentColor', animation: 'pulse 2s infinite' },
  notifBtn: { position: 'relative', width: 40, height: 40, borderRadius: 12, background: 'rgba(255,255,255,0.05)', border: '1px solid rgba(255,255,255,0.08)', cursor: 'pointer', fontSize: 18, display: 'flex', alignItems: 'center', justifyContent: 'center' },
  notifBadge: { position: 'absolute', top: -4, right: -4, minWidth: 18, height: 18, borderRadius: 9, background: '#EF4444', fontSize: 10, fontWeight: 700, display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#fff', padding: '0 4px' },

  // Event Selector
  eventSelector: { display: 'flex', alignItems: 'center', gap: 12, padding: '14px 20px', background: 'rgba(139,92,246,0.08)', borderBottom: '1px solid rgba(139,92,246,0.2)' },
  eventInfo: { flex: 1 },
  eventName: { fontSize: 14, fontWeight: 600, marginBottom: 2 },
  eventMeta: { fontSize: 12, color: 'rgba(255,255,255,0.5)' },
  statusLive: { display: 'flex', alignItems: 'center', padding: '4px 10px', borderRadius: 6, fontSize: 11, fontWeight: 700, background: 'rgba(16,185,129,0.2)', color: '#10B981' },
  statusUpcoming: { padding: '4px 10px', borderRadius: 6, fontSize: 11, fontWeight: 700, background: 'rgba(255,255,255,0.1)', color: 'rgba(255,255,255,0.6)' },

  // Shift Bar
  shiftBar: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '12px 20px', background: 'rgba(255,255,255,0.02)', borderBottom: '1px solid rgba(255,255,255,0.05)' },
  shiftBarPaused: { background: 'rgba(245,158,11,0.1)', borderBottomColor: 'rgba(245,158,11,0.2)' },
  shiftInfo: { display: 'flex', flexDirection: 'column', gap: 4 },
  shiftTime: { display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, color: 'rgba(255,255,255,0.5)' },
  turnoverRow: { display: 'flex', alignItems: 'center', gap: 6, fontSize: 13 },
  turnoverLabel: { color: 'rgba(255,255,255,0.6)' },
  turnoverAmount: { fontWeight: 700, color: '#10B981', fontFamily: 'monospace' },
  shiftActions: { display: 'flex', gap: 8 },
  pauseBtn: { display: 'flex', alignItems: 'center', gap: 6, padding: '8px 14px', background: 'rgba(245,158,11,0.15)', border: '1px solid rgba(245,158,11,0.3)', borderRadius: 10, color: '#F59E0B', fontSize: 13, fontWeight: 600, cursor: 'pointer' },
  resumeBtn: { display: 'flex', alignItems: 'center', gap: 6, padding: '8px 14px', background: 'rgba(16,185,129,0.15)', border: '1px solid rgba(16,185,129,0.3)', borderRadius: 10, color: '#10B981', fontSize: 13, fontWeight: 600, cursor: 'pointer' },
  emergencyBtn: { display: 'flex', alignItems: 'center', justifyContent: 'center', width: 40, height: 40, background: 'rgba(239,68,68,0.15)', border: '1px solid rgba(239,68,68,0.3)', borderRadius: 10, fontSize: 18, cursor: 'pointer' },

  // Main Content
  mainContent: { paddingBottom: 100 },

  // Dashboard
  dashboard: { padding: 20 },
  statsGrid: { display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: 12, marginBottom: 24 },
  statCardPrimary: { gridColumn: 'span 2', background: 'linear-gradient(135deg, rgba(139,92,246,0.15) 0%, rgba(99,102,241,0.1) 100%)', border: '1px solid rgba(139,92,246,0.3)', borderRadius: 16, padding: 16, position: 'relative' },
  statCard: { background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.06)', borderRadius: 16, padding: 16 },
  statValue: { fontSize: 24, fontWeight: 700, fontFamily: 'monospace' },
  statLabel: { fontSize: 12, color: 'rgba(255,255,255,0.5)', marginTop: 4 },
  statTrend: { position: 'absolute', top: 16, right: 16, fontSize: 12, fontWeight: 600, color: '#10B981' },
  sectionTitle: { fontSize: 14, fontWeight: 600, color: 'rgba(255,255,255,0.7)', marginBottom: 12, marginTop: 0 },
  actionsGrid: { display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 10, marginBottom: 24 },
  actionBtn: { display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 8, padding: '16px 8px', background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.06)', borderRadius: 16, color: '#fff', cursor: 'pointer', fontSize: 11, textAlign: 'center' },

  // Scanner Dashboard
  turnoverCard: { background: 'linear-gradient(135deg, rgba(16,185,129,0.1) 0%, rgba(6,182,212,0.1) 100%)', border: '1px solid rgba(16,185,129,0.2)', borderRadius: 18, padding: 20, marginBottom: 20 },
  turnoverTitle: { fontSize: 16, fontWeight: 600, marginBottom: 16, marginTop: 0, display: 'flex', alignItems: 'center' },
  turnoverGrid: { display: 'flex', flexDirection: 'column', gap: 12 },
  turnoverItem: { display: 'flex', alignItems: 'center', gap: 14, padding: 14, background: 'rgba(0,0,0,0.2)', borderRadius: 12 },
  turnoverIcon: { fontSize: 28 },
  turnoverItemLabel: { fontSize: 12, color: 'rgba(255,255,255,0.5)', marginBottom: 4 },
  turnoverItemValue: { fontSize: 22, fontWeight: 700, fontFamily: 'monospace', color: '#10B981' },
  turnoverItemValueBlue: { fontSize: 22, fontWeight: 700, fontFamily: 'monospace', color: '#06B6D4' },
  scannerStats: { display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 10, marginBottom: 20 },
  scannerStatItem: { background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.06)', borderRadius: 14, padding: '14px 12px', textAlign: 'center' },
  scannerStatValue: { fontSize: 20, fontWeight: 700, fontFamily: 'monospace' },
  scannerStatLabel: { fontSize: 11, color: 'rgba(255,255,255,0.5)', marginTop: 4 },
  quickActionsLarge: { display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: 12 },
  actionBtnLarge: { display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 24, background: 'linear-gradient(135deg, rgba(139,92,246,0.2), rgba(139,92,246,0.1))', border: '1px solid rgba(139,92,246,0.3)', borderRadius: 16, color: '#fff', fontSize: 16, fontWeight: 600, cursor: 'pointer', textAlign: 'center' },
  actionBtnLargeSell: { display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 24, background: 'linear-gradient(135deg, rgba(16,185,129,0.2), rgba(16,185,129,0.1))', border: '1px solid rgba(16,185,129,0.3)', borderRadius: 16, color: '#fff', fontSize: 16, fontWeight: 600, cursor: 'pointer', textAlign: 'center' },

  // Check-in
  checkinView: { padding: 20, position: 'relative', minHeight: 'calc(100vh - 200px)' },
  pausedOverlay: { position: 'absolute', inset: 0, background: 'rgba(0,0,0,0.9)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 50, borderRadius: 0 },
  pausedContent: { display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 16, textAlign: 'center' },
  pausedIcon: { fontSize: 64, color: '#F59E0B' },
  pausedText: { fontSize: 24, fontWeight: 700, color: '#F59E0B' },
  resumeBtnLarge: { padding: '14px 28px', background: 'linear-gradient(135deg, #10B981, #059669)', border: 'none', borderRadius: 12, color: '#fff', fontSize: 16, fontWeight: 600, cursor: 'pointer' },
  scannerContainer: { display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 20 },
  scannerFrame: { 
    width: 260, 
    height: 260, 
    borderRadius: 24, 
    background: 'rgba(255,255,255,0.02)', 
    border: '2px solid rgba(255,255,255,0.1)', 
    display: 'flex', 
    alignItems: 'center', 
    justifyContent: 'center', 
    textAlign: 'center', 
    color: 'rgba(255,255,255,0.4)', 
    fontSize: 14, 
    transition: 'all 0.3s' 
  },
  scannerFrameScanning: { borderColor: '#8B5CF6', boxShadow: '0 0 40px rgba(139,92,246,0.3)' },
  scannerFrameValid: { borderColor: '#10B981', boxShadow: '0 0 40px rgba(16,185,129,0.3)' },
  scannerFrameDuplicate: { borderColor: '#F59E0B', boxShadow: '0 0 40px rgba(245,158,11,0.3)' },
  scannerFrameInvalid: { borderColor: '#EF4444', boxShadow: '0 0 40px rgba(239,68,68,0.3)' },
  scannerPrompt: { textAlign: 'center' },
  scanningText: { fontSize: 18, color: '#8B5CF6' },
  scanResultIcon: { fontSize: 64 },
  resultCard: { width: '100%', padding: 20, borderRadius: 16, textAlign: 'center' },
  resultCardValid: { background: 'rgba(16,185,129,0.1)', border: '1px solid rgba(16,185,129,0.3)' },
  resultCardDuplicate: { background: 'rgba(245,158,11,0.1)', border: '1px solid rgba(245,158,11,0.3)' },
  resultCardInvalid: { background: 'rgba(239,68,68,0.1)', border: '1px solid rgba(239,68,68,0.3)' },
  resultStatus: { fontSize: 12, fontWeight: 700, letterSpacing: 1, marginBottom: 12 },
  resultName: { fontSize: 20, fontWeight: 700, marginBottom: 4 },
  resultTicket: { fontSize: 14, color: 'rgba(255,255,255,0.6)', marginBottom: 12 },
  resultMessage: { fontSize: 14, color: 'rgba(255,255,255,0.7)' },
  scanBtn: { display: 'flex', alignItems: 'center', justifyContent: 'center', width: '100%', padding: 18, background: 'linear-gradient(135deg, #8B5CF6, #6366F1)', border: 'none', borderRadius: 16, color: '#fff', fontSize: 16, fontWeight: 600, cursor: 'pointer' },
  recentScans: { marginTop: 24 },
  recentTitle: { fontSize: 14, fontWeight: 600, color: 'rgba(255,255,255,0.7)', marginBottom: 12, marginTop: 0 },
  scanItem: { display: 'flex', alignItems: 'center', gap: 12, padding: 12, background: 'rgba(255,255,255,0.02)', borderRadius: 12, marginBottom: 8 },
  scanStatusValid: { width: 28, height: 28, borderRadius: 8, background: 'rgba(16,185,129,0.2)', color: '#10B981', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 700 },
  scanStatusDuplicate: { width: 28, height: 28, borderRadius: 8, background: 'rgba(245,158,11,0.2)', color: '#F59E0B', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 700 },
  scanInfo: { flex: 1 },
  scanName: { fontSize: 14, fontWeight: 500, marginBottom: 2 },
  scanTicket: { fontSize: 12, color: 'rgba(255,255,255,0.5)' },
  scanTime: { fontSize: 12, color: 'rgba(255,255,255,0.4)', fontFamily: 'monospace' },

  // Sales
  salesView: { padding: 20, position: 'relative', minHeight: 'calc(100vh - 200px)' },
  ticketCard: { display: 'flex', alignItems: 'center', gap: 14, padding: 16, background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.06)', borderRadius: 16, marginBottom: 12, cursor: 'pointer' },
  ticketCardSoldout: { opacity: 0.5, cursor: 'not-allowed' },
  ticketBadge: { width: 6, height: 40, borderRadius: 3 },
  ticketInfo: { flex: 1 },
  ticketName: { fontSize: 15, fontWeight: 600, marginBottom: 4 },
  ticketPrice: { fontSize: 14, color: 'rgba(255,255,255,0.6)', fontFamily: 'monospace' },
  ticketAvailable: { fontSize: 12, color: '#10B981' },
  ticketSoldout: { fontSize: 12, color: '#EF4444' },
  addBtn: { width: 36, height: 36, borderRadius: 10, background: 'rgba(139,92,246,0.2)', border: 'none', color: '#8B5CF6', fontSize: 20, cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' },
  salesHistory: { background: 'rgba(255,255,255,0.02)', borderRadius: 20, padding: 20, marginTop: 20, border: '1px solid rgba(255,255,255,0.05)' },
  historyTitle: { fontSize: 14, fontWeight: 600, marginBottom: 12, marginTop: 0 },
  historyItem: { display: 'flex', alignItems: 'center', gap: 12, marginBottom: 12 },
  historyIcon: { width: 36, height: 36, borderRadius: 10, background: 'rgba(255,255,255,0.05)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 18 },
  historyInfo: { flex: 1 },
  historyTime: { fontSize: 12, color: 'rgba(255,255,255,0.4)' },
  historyAmount: { fontSize: 14, fontWeight: 600, color: '#10B981', fontFamily: 'monospace' },
  cartFab: { position: 'fixed', bottom: 100, left: '50%', transform: 'translateX(-50%)', display: 'flex', alignItems: 'center', gap: 12, padding: '14px 24px', background: 'linear-gradient(135deg, #8B5CF6, #6366F1)', borderRadius: 20, cursor: 'pointer', boxShadow: '0 10px 40px rgba(139,92,246,0.4)', zIndex: 50, fontSize: 16, fontWeight: 600 },
  fabBadge: { position: 'absolute', top: -8, left: -8, width: 24, height: 24, borderRadius: '50%', background: '#EF4444', fontSize: 12, fontWeight: 700, display: 'flex', alignItems: 'center', justifyContent: 'center' },

  // Cart
  cartView: { padding: 0 },
  cartHeader: { display: 'flex', alignItems: 'center', gap: 16, marginBottom: 24 },
  backBtn: { width: 40, height: 40, borderRadius: 12, background: 'rgba(255,255,255,0.05)', border: '1px solid rgba(255,255,255,0.1)', color: '#fff', cursor: 'pointer', fontSize: 18, display: 'flex', alignItems: 'center', justifyContent: 'center' },
  cartItem: { display: 'flex', alignItems: 'center', gap: 12, padding: 16, background: 'rgba(255,255,255,0.03)', borderRadius: 16, marginBottom: 12 },
  cartItemBadge: { width: 4, height: 40, borderRadius: 2 },
  cartItemInfo: { flex: 1 },
  cartItemName: { fontSize: 14, fontWeight: 600, marginBottom: 4 },
  cartItemPrice: { fontSize: 13, color: 'rgba(255,255,255,0.5)' },
  qtyControls: { display: 'flex', alignItems: 'center', gap: 12 },
  qtyBtn: { width: 32, height: 32, borderRadius: 8, background: 'rgba(255,255,255,0.1)', border: 'none', color: '#fff', fontSize: 18, cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' },
  qtyValue: { fontSize: 16, fontWeight: 600, minWidth: 24, textAlign: 'center' },
  cartItemTotal: { fontSize: 15, fontWeight: 600, fontFamily: 'monospace', minWidth: 80, textAlign: 'right' },
  cartTotal: { display: 'flex', justifyContent: 'space-between', padding: 20, background: 'rgba(255,255,255,0.03)', borderRadius: 16, marginBottom: 24, fontSize: 18, fontWeight: 700 },
  cartTotalValue: { fontFamily: 'monospace' },
  paymentTitle: { fontSize: 14, fontWeight: 600, color: 'rgba(255,255,255,0.7)', marginBottom: 12, marginTop: 0 },
  paymentGrid: { display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: 12 },
  paymentBtn: { display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 24, background: 'rgba(255,255,255,0.03)', border: '2px solid rgba(255,255,255,0.1)', borderRadius: 16, color: '#fff', fontSize: 16, fontWeight: 600, cursor: 'pointer' },
  paymentBtnActive: { borderColor: '#8B5CF6', background: 'rgba(139,92,246,0.15)' },
  paymentSuccess: { position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.9)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 200 },
  successContent: { display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 16 },
  successIcon: { fontSize: 64 },
  successText: { fontSize: 20, fontWeight: 600 },
  successAmount: { fontSize: 32, fontWeight: 700, fontFamily: 'monospace', color: '#10B981' },

  // Reports
  reportsView: { padding: 20 },
  reportsTitle: { fontSize: 20, fontWeight: 600, marginBottom: 20, marginTop: 0, display: 'flex', alignItems: 'center' },
  metricsGrid: { display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: 12, marginBottom: 24 },
  metricCard: { background: 'rgba(255,255,255,0.03)', border: '1px solid rgba(255,255,255,0.06)', borderRadius: 16, padding: 16 },
  metricValue: { fontSize: 24, fontWeight: 700, fontFamily: 'monospace' },
  metricLabel: { fontSize: 12, color: 'rgba(255,255,255,0.5)', marginTop: 4 },
  reportSection: { fontSize: 14, fontWeight: 600, color: 'rgba(255,255,255,0.7)', marginBottom: 16, marginTop: 0 },
  gateItem: { display: 'flex', alignItems: 'center', gap: 12, marginBottom: 12 },
  gateName: { width: 60, fontSize: 13 },
  gateBar: { flex: 1, height: 8, background: 'rgba(255,255,255,0.1)', borderRadius: 4, overflow: 'hidden' },
  gateProgress: { height: '100%', background: 'linear-gradient(90deg, #8B5CF6, #06B6D4)', borderRadius: 4 },
  gatePercent: { width: 40, textAlign: 'right', fontSize: 13, fontFamily: 'monospace' },

  // Settings
  settingsView: { padding: 20 },
  settingsTitle: { fontSize: 20, fontWeight: 600, marginBottom: 24, marginTop: 0, display: 'flex', alignItems: 'center' },
  settingsSection: { marginBottom: 24 },
  settingsSectionTitle: { fontSize: 12, fontWeight: 600, color: 'rgba(255,255,255,0.4)', textTransform: 'uppercase', letterSpacing: 1, marginBottom: 12, marginTop: 0 },
  settingItem: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: 16, background: 'rgba(255,255,255,0.02)', borderRadius: 14, marginBottom: 8 },
  settingRow: { display: 'flex', alignItems: 'center', gap: 12 },
  settingIconBox: { width: 36, height: 36, borderRadius: 10, background: 'rgba(139,92,246,0.15)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#8B5CF6' },
  settingLabel: { fontSize: 15, fontWeight: 500, marginBottom: 2 },
  settingValue: { fontSize: 13, color: 'rgba(255,255,255,0.5)' },
  settingDesc: { fontSize: 12, color: 'rgba(255,255,255,0.4)' },
  toggleOn: { position: 'relative', width: 48, height: 28, borderRadius: 14, background: '#8B5CF6', border: 'none', cursor: 'pointer', padding: 2, display: 'flex', alignItems: 'center', justifyContent: 'flex-end' },
  toggleOff: { position: 'relative', width: 48, height: 28, borderRadius: 14, background: 'rgba(255,255,255,0.1)', border: 'none', cursor: 'pointer', padding: 2, display: 'flex', alignItems: 'center', justifyContent: 'flex-start' },
  toggleThumb: { width: 24, height: 24, borderRadius: 12, background: '#fff', transition: 'transform 0.2s' },
  offlineInfo: { display: 'flex', alignItems: 'center', gap: 10, padding: 14, background: 'rgba(6, 182, 212, 0.1)', border: '1px solid rgba(6, 182, 212, 0.2)', borderRadius: 12, marginTop: 8, fontSize: 13, color: 'rgba(255,255,255,0.7)' },
  logoutBtn: { display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 10, width: '100%', padding: 16, background: 'rgba(239,68,68,0.1)', border: '1px solid rgba(239,68,68,0.2)', borderRadius: 14, color: '#EF4444', fontSize: 15, fontWeight: 600, cursor: 'pointer', marginTop: 20 },

  // Bottom Nav
  bottomNav: { position: 'fixed', bottom: 0, left: '50%', transform: 'translateX(-50%)', width: '100%', maxWidth: 430, display: 'flex', justifyContent: 'space-around', padding: '12px 20px 28px', background: 'linear-gradient(180deg, rgba(10,10,15,0) 0%, rgba(10,10,15,1) 20%)', zIndex: 100 },
  navItem: { display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 4, padding: '8px 16px', background: 'none', border: 'none', color: 'rgba(255,255,255,0.4)', cursor: 'pointer', borderRadius: 12 },
  navItemActive: { display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 4, padding: '8px 16px', background: 'rgba(139,92,246,0.1)', border: 'none', color: '#8B5CF6', cursor: 'pointer', borderRadius: 12 },
  navLabel: { fontSize: 11, fontWeight: 500 },

  // Modals
  modalOverlay: { position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.8)', display: 'flex', alignItems: 'flex-end', justifyContent: 'center', zIndex: 150 },
  modalContent: { width: '100%', maxWidth: 430, maxHeight: '85vh', background: '#15151F', borderRadius: '24px 24px 0 0', padding: 24, overflow: 'auto' },
  modalTitle: { fontSize: 18, fontWeight: 600, marginBottom: 8, marginTop: 0, display: 'flex', alignItems: 'center' },
  modalDesc: { fontSize: 14, color: 'rgba(255,255,255,0.5)', marginBottom: 20, marginTop: 0 },
  emergencyGrid: { display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: 10 },
  emergencyOption: { display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 8, padding: 16, borderRadius: 14, cursor: 'pointer', textAlign: 'center', border: 'none', color: '#fff' },
  emergencySent: { textAlign: 'center', padding: 40 },

  // Notifications
  notifPanel: { position: 'fixed', inset: 0, zIndex: 160 },
  notifContent: { position: 'absolute', top: 70, right: 20, width: 300, maxHeight: 400, background: '#1A1A24', borderRadius: 20, border: '1px solid rgba(255,255,255,0.1)', boxShadow: '0 20px 60px rgba(0,0,0,0.5)', overflow: 'hidden', padding: 16 },
  notifTitle: { fontSize: 15, fontWeight: 600, marginBottom: 12, paddingBottom: 12, borderBottom: '1px solid rgba(255,255,255,0.05)', marginTop: 0 },
  notifItem: { display: 'flex', gap: 12, padding: '12px 0', borderBottom: '1px solid rgba(255,255,255,0.03)' },
  notifUnread: { background: 'rgba(139,92,246,0.05)', margin: '0 -16px', padding: '12px 16px' },
  notifIcon: { width: 36, height: 36, borderRadius: 10, background: 'rgba(255,255,255,0.05)', display: 'flex', alignItems: 'center', justifyContent: 'center' },
  notifText: { flex: 1 },
  notifTime: { fontSize: 11, color: 'rgba(255,255,255,0.4)', marginTop: 4 },
  
  // Live dot
  liveDot: { width: 8, height: 8, borderRadius: '50%', background: '#EF4444', display: 'inline-block', marginRight: 6, animation: 'pulse 1.5s infinite' },
  
  // Spinner
  spinner: { width: 18, height: 18, border: '2px solid rgba(255,255,255,0.3)', borderTopColor: '#fff', borderRadius: '50%', animation: 'spin 0.8s linear infinite', marginRight: 8 },
};

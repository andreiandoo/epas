import { create } from 'zustand';
import { CartItem, TicketType, SaleHistoryItem } from '../types';

interface CartState {
  items: CartItem[];
  customerName: string;
  customerEmail: string;
  salesHistory: SaleHistoryItem[];
  shiftCashCollected: number;
  shiftCardCollected: number;
  isProcessingPayment: boolean;

  // Computed
  cartTotal: () => number;
  cartCount: () => number;

  // Actions
  addItem: (ticketType: TicketType) => void;
  removeItem: (ticketTypeId: number) => void;
  updateQuantity: (ticketTypeId: number, delta: number) => void;
  clearCart: () => void;
  setCustomerName: (name: string) => void;
  setCustomerEmail: (email: string) => void;
  setProcessingPayment: (processing: boolean) => void;
  addSaleToHistory: (sale: SaleHistoryItem) => void;
  addToShiftTotal: (amount: number, method: 'card' | 'cash') => void;
  resetShift: () => void;
}

export const useCartStore = create<CartState>((set, get) => ({
  items: [],
  customerName: '',
  customerEmail: '',
  salesHistory: [],
  shiftCashCollected: 0,
  shiftCardCollected: 0,
  isProcessingPayment: false,

  cartTotal: () => {
    const { items } = get();
    return items.reduce((sum, item) => {
      const price = item.ticket_type.sale_price_cents || item.ticket_type.price_cents;
      return sum + (price * item.quantity) / 100;
    }, 0);
  },

  cartCount: () => {
    const { items } = get();
    return items.reduce((sum, item) => sum + item.quantity, 0);
  },

  addItem: (ticketType) => {
    const { items } = get();
    const existing = items.find(item => item.ticket_type.id === ticketType.id);

    if (existing) {
      set({
        items: items.map(item =>
          item.ticket_type.id === ticketType.id
            ? { ...item, quantity: item.quantity + 1 }
            : item
        ),
      });
    } else {
      set({
        items: [...items, { ticket_type: ticketType, quantity: 1 }],
      });
    }
  },

  removeItem: (ticketTypeId) => {
    const { items } = get();
    set({
      items: items.filter(item => item.ticket_type.id !== ticketTypeId),
    });
  },

  updateQuantity: (ticketTypeId, delta) => {
    const { items } = get();
    set({
      items: items
        .map(item => {
          if (item.ticket_type.id === ticketTypeId) {
            const newQty = Math.max(0, item.quantity + delta);
            return newQty === 0 ? null : { ...item, quantity: newQty };
          }
          return item;
        })
        .filter(Boolean) as CartItem[],
    });
  },

  clearCart: () => {
    set({
      items: [],
      customerName: '',
      customerEmail: '',
    });
  },

  setCustomerName: (customerName) => set({ customerName }),

  setCustomerEmail: (customerEmail) => set({ customerEmail }),

  setProcessingPayment: (isProcessingPayment) => set({ isProcessingPayment }),

  addSaleToHistory: (sale) => {
    const { salesHistory } = get();
    set({ salesHistory: [sale, ...salesHistory].slice(0, 50) }); // Keep last 50
  },

  addToShiftTotal: (amount, method) => {
    if (method === 'cash') {
      set(state => ({ shiftCashCollected: state.shiftCashCollected + amount }));
    } else {
      set(state => ({ shiftCardCollected: state.shiftCardCollected + amount }));
    }
  },

  resetShift: () => {
    set({
      salesHistory: [],
      shiftCashCollected: 0,
      shiftCardCollected: 0,
    });
  },
}));

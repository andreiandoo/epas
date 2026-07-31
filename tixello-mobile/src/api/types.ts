/** Shared API DTOs (subset used by the app foundation). */

export interface Organizer {
  id: number;
  name: string;
  slug: string;
  contact_name?: string | null;
  logo?: string | null;
  city?: string | null;
  organizer_type?: string | null;
}

export interface AvailableOrganizer {
  id: number;
  name: string;
  slug: string;
  logo?: string | null;
  organizer_type?: string | null;
  role?: string | null;
}

export interface TeamMember {
  id: number;
  role: string;
  leisure_role?: string | null;
  permissions: string[];
}

export interface LoginResult {
  organizer: Organizer;
  token: string;
  team_member?: TeamMember | null;
  available_organizers?: AvailableOrganizer[];
}

export interface EventSummary {
  id: number;
  name: string;
  venue?: string | null;
  starts_at?: string | null;
  status?: string | null;
  tickets_sold?: number;
  capacity?: number;
  revenue?: number;
}

export interface EventStats {
  entered: number;
  sold: number;
  revenue: number;
  available: number;
  capacity: number;
  check_in_rate?: number;
  online_count?: number;
  door_count?: number;
}

export interface TicketTypeSummary {
  id: number;
  name: string;
  price: number;
  available?: number;
  color?: string | null;
  is_entry_ticket?: boolean;
  has_seating?: boolean;
}

export interface ScanResult {
  success: boolean;
  message?: string;
  ticket?: {
    id: number;
    barcode: string;
    ticket_type?: string;
    status?: string;
    checked_in_at?: string | null;
    checked_in_by?: string | null;
    seat_label?: string | null;
    section?: string | null;
    row?: string | null;
    seat?: string | null;
    attendee_name?: string | null;
    is_invitation?: boolean;
  };
  customer?: { name?: string; email?: string };
  order?: { source?: string; customer_name?: string };
  venue_notes?: string | null;
}

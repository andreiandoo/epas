export interface User {
  id: string;
  email: string;
  first_name: string;
  last_name: string;
}

export interface Tenant {
  id: string;
  name: string;
  slug: string;
  domain: string;
}

export interface CustomerAuthState {
  type: 'customer';
  user: User;
  token: string;
  tenants: Tenant[];
}

export interface AdminAuthState {
  type: 'admin';
  user: User;
  token: string;
  tenant: Tenant;
  permissions: string[];
}

export type AuthState = CustomerAuthState | AdminAuthState | null;

export interface LoginCredentials {
  email: string;
  password: string;
}

export interface AdminLoginCredentials extends LoginCredentials {
  domain: string;
}

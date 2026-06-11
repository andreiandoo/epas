import { ApiClient } from './ApiClient';

interface User {
    id: number;
    name: string;
    email: string;
    role: 'customer' | 'admin' | 'super_admin';
}

export class AuthManager {
    private apiClient: ApiClient;
    private user: User | null = null;
    private tokenKey = 'tixello_token';

    constructor(apiClient: ApiClient) {
        this.apiClient = apiClient;
        this.loadToken();
    }

    private loadToken(): void {
        const token = localStorage.getItem(this.tokenKey);
        if (token) {
            this.apiClient.setAccessToken(token);
        }
    }

    async checkAuth(): Promise<boolean> {
        const token = localStorage.getItem(this.tokenKey);
        if (!token) return false;

        const response = await this.apiClient.get('/auth/me');
        if (response.success && response.data) {
            this.user = response.data;
            return true;
        }

        this.logout();
        return false;
    }

    async login(email: string, password: string): Promise<{ success: boolean; message?: string }> {
        const response = await this.apiClient.post('/auth/login', { email, password }, { withAuth: false });

        if (response.success && response.data) {
            const { token, user } = response.data;
            localStorage.setItem(this.tokenKey, token);
            this.apiClient.setAccessToken(token);
            this.user = user;

            return { success: true };
        }

        return { success: false, message: response.message };
    }

    async register(data: { name: string; email: string; password: string }): Promise<{ success: boolean; message?: string }> {
        const response = await this.apiClient.post('/auth/register', data, { withAuth: false });

        if (response.success && response.data) {
            const { token, user } = response.data;
            localStorage.setItem(this.tokenKey, token);
            this.apiClient.setAccessToken(token);
            this.user = user;

            return { success: true };
        }

        return { success: false, message: response.message };
    }

    logout(): void {
        localStorage.removeItem(this.tokenKey);
        this.apiClient.setAccessToken(null);
        this.user = null;
        window.location.hash = '/';
    }

    isAuthenticated(): boolean {
        return this.user !== null;
    }

    isAdmin(): boolean {
        return this.user?.role === 'admin' || this.user?.role === 'super_admin';
    }

    isSuperAdmin(): boolean {
        return this.user?.role === 'super_admin';
    }

    getUser(): User | null {
        return this.user;
    }
}

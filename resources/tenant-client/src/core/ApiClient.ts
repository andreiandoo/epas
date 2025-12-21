import { TixelloConfig } from './ConfigManager';

interface ApiResponse<T = any> {
    success: boolean;
    data?: T;
    message?: string;
    errors?: Record<string, string[]>;
}

interface RequestOptions {
    method?: 'GET' | 'POST' | 'PUT' | 'DELETE' | 'PATCH';
    body?: any;
    headers?: Record<string, string>;
    withAuth?: boolean;
}

export class ApiClient {
    private config: TixelloConfig;
    private accessToken: string | null = null;

    constructor(config: TixelloConfig) {
        this.config = config;
    }

    setAccessToken(token: string | null): void {
        this.accessToken = token;
    }

    async request<T = any>(endpoint: string, options: RequestOptions = {}): Promise<ApiResponse<T>> {
        const {
            method = 'GET',
            body,
            headers = {},
            withAuth = true,
        } = options;

        // Build URL with hostname parameter for tenant resolution
        const separator = endpoint.includes('?') ? '&' : '?';
        const url = `${this.config.apiEndpoint}${endpoint}${separator}hostname=${encodeURIComponent(this.config.domain)}`;
        const timestamp = Date.now();
        const nonce = this.generateNonce();

        // Build headers
        const requestHeaders: Record<string, string> = {
            'Content-Type': 'application/json',
            'X-Tenant-ID': String(this.config.tenantId),
            'X-Domain-ID': String(this.config.domainId),
            'X-Package-Hash': this.config.packageHash,
            'X-Timestamp': String(timestamp),
            'X-Nonce': nonce,
            ...headers,
        };

        // Add auth header if token exists and auth is required
        if (withAuth && this.accessToken) {
            requestHeaders['Authorization'] = `Bearer ${this.accessToken}`;
        }

        // Generate request signature
        const signature = await this.signRequest(method, endpoint, body, timestamp, nonce);
        requestHeaders['X-Signature'] = signature;

        try {
            const response = await fetch(url, {
                method,
                headers: requestHeaders,
                body: body ? JSON.stringify(body) : undefined,
                credentials: 'include',
            });

            const data = await response.json();

            if (!response.ok) {
                return {
                    success: false,
                    message: data.message || 'Request failed',
                    errors: data.errors,
                };
            }

            return {
                success: true,
                data: data.data || data,
            };
        } catch (error) {
            return {
                success: false,
                message: error instanceof Error ? error.message : 'Network error',
            };
        }
    }

    private generateNonce(): string {
        return Array.from(crypto.getRandomValues(new Uint8Array(16)))
            .map(b => b.toString(16).padStart(2, '0'))
            .join('');
    }

    private async signRequest(
        method: string,
        endpoint: string,
        body: any,
        timestamp: number,
        nonce: string
    ): Promise<string> {
        const payload = [
            method,
            endpoint,
            body ? JSON.stringify(body) : '',
            timestamp,
            nonce,
            this.config.packageHash,
        ].join('|');

        const encoder = new TextEncoder();
        const data = encoder.encode(payload);

        const hashBuffer = await crypto.subtle.digest('SHA-256', data);
        const hashArray = Array.from(new Uint8Array(hashBuffer));

        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    }

    // Convenience methods
    async get<T = any>(endpoint: string, options?: Omit<RequestOptions, 'method'>): Promise<ApiResponse<T>> {
        return this.request<T>(endpoint, { ...options, method: 'GET' });
    }

    async post<T = any>(endpoint: string, body?: any, options?: Omit<RequestOptions, 'method' | 'body'>): Promise<ApiResponse<T>> {
        return this.request<T>(endpoint, { ...options, method: 'POST', body });
    }

    async put<T = any>(endpoint: string, body?: any, options?: Omit<RequestOptions, 'method' | 'body'>): Promise<ApiResponse<T>> {
        return this.request<T>(endpoint, { ...options, method: 'PUT', body });
    }

    async delete<T = any>(endpoint: string, options?: Omit<RequestOptions, 'method'>): Promise<ApiResponse<T>> {
        return this.request<T>(endpoint, { ...options, method: 'DELETE' });
    }
}

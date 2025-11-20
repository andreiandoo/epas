/**
 * Tixello Tenant Client - Main Entry Point
 * This file bootstraps the entire Tixello experience on tenant domains
 */

import { TixelloApp } from './core/App';
import { ConfigManager } from './core/ConfigManager';
import { ApiClient } from './core/ApiClient';
import { Router } from './core/Router';
import { AuthManager } from './core/AuthManager';
import { SecurityGuard } from './core/SecurityGuard';

declare global {
    interface Window {
        Tixello: TixelloApp;
        __TIXELLO_CONFIG__: string;
    }
}

// Initialize security measures
SecurityGuard.init();

// Bootstrap the application
(async function bootstrap() {
    try {
        // Get encrypted config from script tag
        const scriptTag = document.currentScript as HTMLScriptElement;
        const encryptedConfig = scriptTag?.getAttribute('data-config') ||
                               window.__TIXELLO_CONFIG__;

        if (!encryptedConfig) {
            throw new Error('Tixello configuration not found');
        }

        // Initialize configuration
        const config = await ConfigManager.init(encryptedConfig);

        // Verify domain binding
        if (!SecurityGuard.verifyDomain(config.domain)) {
            throw new Error('Domain verification failed');
        }

        // Initialize API client
        const apiClient = new ApiClient(config);

        // Initialize auth manager
        const authManager = new AuthManager(apiClient);

        // Initialize router
        const router = new Router(config);

        // Create and mount the app
        const app = new TixelloApp({
            config,
            apiClient,
            authManager,
            router,
        });

        // Mount to DOM
        const mountPoint = document.getElementById('tixello-app');
        if (!mountPoint) {
            throw new Error('Mount point #tixello-app not found');
        }

        await app.mount(mountPoint);

        // Expose global instance
        window.Tixello = app;

        console.log(`Tixello v${config.version} initialized`);
    } catch (error) {
        console.error('Tixello initialization failed:', error);

        // Show error UI
        const mountPoint = document.getElementById('tixello-app');
        if (mountPoint) {
            mountPoint.innerHTML = `
                <div style="padding: 20px; text-align: center; color: #dc2626;">
                    <h2>Unable to load Tixello</h2>
                    <p>Please refresh the page or contact support.</p>
                </div>
            `;
        }
    }
})();

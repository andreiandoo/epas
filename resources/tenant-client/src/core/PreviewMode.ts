import { ConfigManager, ThemeConfig } from './ConfigManager';

interface PreviewMessage {
    type: 'THEME_UPDATE' | 'LAYOUT_UPDATE' | 'PREVIEW_READY';
    theme?: Partial<ThemeConfig>;
    layout?: { blocks: any[] };
}

type LayoutUpdateCallback = (layout: { blocks: any[] }) => void;

export class PreviewMode {
    private static isPreviewMode = false;
    private static layoutUpdateCallbacks: LayoutUpdateCallback[] = [];

    /**
     * Initialize preview mode if query param is present
     */
    static init(): boolean {
        const urlParams = new URLSearchParams(window.location.search);
        this.isPreviewMode = urlParams.has('preview') || urlParams.get('preview') === '1';

        if (this.isPreviewMode) {
            this.setupMessageListener();
            this.notifyReady();
            console.log('[PreviewMode] Initialized');
        }

        return this.isPreviewMode;
    }

    /**
     * Check if we're in preview mode
     */
    static isActive(): boolean {
        return this.isPreviewMode;
    }

    /**
     * Setup message listener for parent window communication
     */
    private static setupMessageListener(): void {
        window.addEventListener('message', (event: MessageEvent<PreviewMessage>) => {
            // In production, you should verify the origin
            // if (event.origin !== 'https://admin.example.com') return;

            const data = event.data;

            if (!data || !data.type) {
                return;
            }

            switch (data.type) {
                case 'THEME_UPDATE':
                    if (data.theme) {
                        this.handleThemeUpdate(data.theme);
                    }
                    break;

                case 'LAYOUT_UPDATE':
                    if (data.layout) {
                        this.handleLayoutUpdate(data.layout);
                    }
                    break;

                default:
                    console.log('[PreviewMode] Unknown message type:', data.type);
            }
        });
    }

    /**
     * Notify parent that preview is ready
     */
    private static notifyReady(): void {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'PREVIEW_READY' }, '*');
        }
    }

    /**
     * Handle theme update from parent
     */
    private static handleThemeUpdate(theme: Partial<ThemeConfig>): void {
        console.log('[PreviewMode] Theme update received:', theme);

        // Apply theme changes
        ConfigManager.updateTheme(theme);
    }

    /**
     * Handle layout update from parent
     */
    private static handleLayoutUpdate(layout: { blocks: any[] }): void {
        console.log('[PreviewMode] Layout update received:', layout);

        // Notify registered callbacks
        this.layoutUpdateCallbacks.forEach(callback => {
            try {
                callback(layout);
            } catch (error) {
                console.error('[PreviewMode] Layout callback error:', error);
            }
        });
    }

    /**
     * Register a callback for layout updates
     */
    static onLayoutUpdate(callback: LayoutUpdateCallback): void {
        this.layoutUpdateCallbacks.push(callback);
    }

    /**
     * Remove a layout update callback
     */
    static offLayoutUpdate(callback: LayoutUpdateCallback): void {
        const index = this.layoutUpdateCallbacks.indexOf(callback);
        if (index > -1) {
            this.layoutUpdateCallbacks.splice(index, 1);
        }
    }

    /**
     * Send message to parent window
     */
    static sendToParent(message: Record<string, any>): void {
        if (this.isPreviewMode && window.parent && window.parent !== window) {
            window.parent.postMessage(message, '*');
        }
    }
}

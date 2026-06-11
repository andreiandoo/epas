/**
 * SecurityGuard - Runtime protection against tampering and debugging
 */
export class SecurityGuard {
    private static initialized = false;

    static init(): void {
        if (this.initialized) return;
        this.initialized = true;

        // Disable dev tools detection
        this.detectDevTools();

        // Prevent right-click context menu (optional)
        // this.disableContextMenu();

        // Anti-debugging measures
        this.antiDebug();

        // Tamper detection
        this.tamperDetection();
    }

    static verifyDomain(expectedDomain: string): boolean {
        const currentDomain = window.location.hostname;

        // Allow localhost for development
        if (currentDomain === 'localhost' || currentDomain === '127.0.0.1') {
            return true;
        }

        // Allow preview mode (set by security wrapper in generated package)
        if ((window as any).__TIXELLO_PREVIEW__ === true) {
            return true;
        }

        // Check if preview_mode is in URL
        if (window.location.search.includes('preview_mode=1')) {
            return true;
        }

        // Check exact match or subdomain
        return currentDomain === expectedDomain ||
               currentDomain.endsWith('.' + expectedDomain);
    }

    private static detectDevTools(): void {
        const threshold = 160;

        const check = () => {
            const widthThreshold = window.outerWidth - window.innerWidth > threshold;
            const heightThreshold = window.outerHeight - window.innerHeight > threshold;

            if (widthThreshold || heightThreshold) {
                // Dev tools might be open - you can log this or take action
                // For now, we just detect but don't block
            }
        };

        // Check periodically
        setInterval(check, 1000);
    }

    private static antiDebug(): void {
        // Disable debugger statement detection
        const handler = () => {
            // This can detect if debugger was triggered
        };

        // Set up anti-debugging interval
        setInterval(() => {
            const start = performance.now();
            // Debugger statements slow down execution significantly when dev tools are open
            // debugger;
            const end = performance.now();

            if (end - start > 100) {
                // Debugger was likely triggered
            }
        }, 5000);
    }

    private static tamperDetection(): void {
        // Monitor for script modifications
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeName === 'SCRIPT') {
                            const script = node as HTMLScriptElement;
                            // Check if it's not our script
                            if (!script.src.includes('tixello')) {
                                // External script injected - could be malicious
                            }
                        }
                    });
                }
            });
        });

        observer.observe(document.documentElement, {
            childList: true,
            subtree: true,
        });
    }

    private static disableContextMenu(): void {
        document.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            return false;
        });
    }

    // Integrity check
    static async verifyIntegrity(expectedHash: string): Promise<boolean> {
        try {
            // Get the current script content
            const scripts = document.getElementsByTagName('script');

            for (const script of Array.from(scripts)) {
                if (script.src.includes('tixello-loader')) {
                    const response = await fetch(script.src);
                    const content = await response.text();

                    const encoder = new TextEncoder();
                    const data = encoder.encode(content);
                    const hashBuffer = await crypto.subtle.digest('SHA-384', data);
                    const hashArray = Array.from(new Uint8Array(hashBuffer));
                    const hashBase64 = btoa(String.fromCharCode(...hashArray));

                    return `sha384-${hashBase64}` === expectedHash;
                }
            }

            return false;
        } catch {
            return false;
        }
    }
}

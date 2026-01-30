/**
 * Notification Sound Component
 * Uses Web Audio API to generate a pleasant notification sound
 */
const AmbiletNotificationSound = (function() {
    let audioContext = null;
    let isEnabled = true;
    const STORAGE_KEY = 'ambilet_notification_sound_enabled';

    // Initialize audio context (lazy loading for better performance)
    function getAudioContext() {
        if (!audioContext) {
            audioContext = new (window.AudioContext || window.webkitAudioContext)();
        }
        return audioContext;
    }

    // Load preference from localStorage
    function loadPreference() {
        const stored = localStorage.getItem(STORAGE_KEY);
        isEnabled = stored === null ? true : stored === 'true';
        return isEnabled;
    }

    // Save preference to localStorage
    function savePreference(enabled) {
        isEnabled = enabled;
        localStorage.setItem(STORAGE_KEY, String(enabled));
    }

    // Generate a pleasant notification sound using Web Audio API
    async function playSound(type = 'default', forcePlay = false) {
        if (!isEnabled && !forcePlay) return;

        try {
            const ctx = getAudioContext();

            // Resume audio context if suspended (required for autoplay policies)
            if (ctx.state === 'suspended') {
                await ctx.resume();
            }

            const now = ctx.currentTime;

            // Create oscillators for a pleasant two-tone notification
            const osc1 = ctx.createOscillator();
            const osc2 = ctx.createOscillator();
            const gainNode = ctx.createGain();

            // Configure oscillators based on notification type
            let freq1, freq2, duration;
            switch (type) {
                case 'sale':
                    // Cheerful ascending tone for sales
                    freq1 = 523.25; // C5
                    freq2 = 659.25; // E5
                    duration = 0.3;
                    break;
                case 'warning':
                    // Attention-grabbing tone
                    freq1 = 440; // A4
                    freq2 = 440;
                    duration = 0.4;
                    break;
                case 'success':
                    // Pleasant ascending tone
                    freq1 = 392; // G4
                    freq2 = 523.25; // C5
                    duration = 0.25;
                    break;
                default:
                    // Default pleasant notification tone
                    freq1 = 587.33; // D5
                    freq2 = 783.99; // G5
                    duration = 0.2;
            }

            osc1.type = 'sine';
            osc2.type = 'sine';
            osc1.frequency.value = freq1;
            osc2.frequency.value = freq2;

            // Connect oscillators through gain node
            osc1.connect(gainNode);
            osc2.connect(gainNode);
            gainNode.connect(ctx.destination);

            // Envelope: quick attack, smooth decay
            gainNode.gain.setValueAtTime(0, now);
            gainNode.gain.linearRampToValueAtTime(0.15, now + 0.01); // Attack
            gainNode.gain.exponentialRampToValueAtTime(0.01, now + duration); // Decay

            // Play first tone
            osc1.start(now);
            osc1.stop(now + duration * 0.6);

            // Play second tone slightly delayed for chime effect
            osc2.start(now + 0.08);
            osc2.stop(now + duration);

        } catch (e) {
            console.warn('Could not play notification sound:', e);
        }
    }

    // Play notification sound (public method)
    function play(type, forcePlay = false) {
        return playSound(type, forcePlay);
    }

    // Enable or disable sounds
    function setEnabled(enabled) {
        savePreference(enabled);
    }

    // Check if sounds are enabled
    function getEnabled() {
        return loadPreference();
    }

    // Toggle sound on/off
    function toggle() {
        setEnabled(!isEnabled);
        return isEnabled;
    }

    // Initialize
    loadPreference();

    // Public API
    return {
        play: play,
        setEnabled: setEnabled,
        isEnabled: getEnabled,
        toggle: toggle
    };
})();

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AmbiletNotificationSound;
}

/**
 * ePas Seating Widget
 *
 * Embeddable seating map with seat selection, hold management, and countdown timer
 *
 * Usage:
 *   <div id="seating-widget"></div>
 *   <script>
 *     const widget = new EPasSeating({
 *       apiBaseUrl: '/api/public',
 *       eventId: 123,
 *       containerId: 'seating-widget',
 *       onSeatsSelected: (seats) => console.log('Selected:', seats),
 *       onHoldExpired: () => console.log('Hold expired'),
 *     });
 *     widget.init();
 *   </script>
 */

class EPasSeating {
    constructor(config) {
        this.config = {
            apiBaseUrl: config.apiBaseUrl || '/api/public',
            eventId: config.eventId,
            containerId: config.containerId || 'seating-widget',
            maxSeats: config.maxSeats || 10,
            onSeatsSelected: config.onSeatsSelected || (() => {}),
            onHoldExpired: config.onHoldExpired || (() => {}),
            onError: config.onError || ((err) => console.error(err)),
            locale: config.locale || 'en',
        };

        this.state = {
            eventSeatingId: null,
            geometry: null,
            seats: [],
            selectedSeats: [],
            heldSeats: [],
            holdExpiresAt: null,
            priceTiers: {},
            sessionUid: this.getOrCreateSessionUid(),
        };

        this.timers = {
            countdown: null,
            statusRefresh: null,
        };

        this.container = null;
        this.canvas = null;
        this.ctx = null;

        this.COLORS = {
            available: '#10b981',
            selected: '#3b82f6',
            held: '#f59e0b',
            sold: '#ef4444',
            blocked: '#6b7280',
            disabled: '#d1d5db',
        };
    }

    /**
     * Initialize the widget
     */
    async init() {
        try {
            this.container = document.getElementById(this.config.containerId);
            if (!this.container) {
                throw new Error(`Container #${this.config.containerId} not found`);
            }

            await this.loadSeatingLayout();
            this.renderUI();
            this.attachEventHandlers();
            this.startStatusPolling();

            console.log('[EPasSeating] Widget initialized successfully');
        } catch (error) {
            this.config.onError(error);
        }
    }

    /**
     * Load seating layout from API
     */
    async loadSeatingLayout() {
        const response = await this.apiRequest(`/events/${this.config.eventId}/seating`);

        this.state.eventSeatingId = response.event_seating_id;
        this.state.geometry = {
            canvas: response.canvas,
            backgroundUrl: response.background_url,
            sections: response.sections,
        };
        this.state.priceTiers = response.price_tiers.reduce((acc, tier) => {
            acc[tier.id] = tier;
            return acc;
        }, {});

        console.log('[EPasSeating] Layout loaded:', response);
    }

    /**
     * Load current seat availability
     */
    async loadSeatAvailability() {
        const response = await this.apiRequest(
            `/events/${this.config.eventId}/seats?event_seating_id=${this.state.eventSeatingId}`
        );

        this.state.seats = response.seats;
        this.redrawCanvas();
    }

    /**
     * Render the UI
     */
    renderUI() {
        this.container.innerHTML = `
            <div class="epas-seating-widget" style="font-family: system-ui, sans-serif;">
                <!-- Header -->
                <div class="epas-header" style="margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="margin: 0; font-size: 1.25rem; font-weight: 600;">Select Your Seats</h3>
                    <div class="epas-countdown" id="countdown" style="font-size: 0.875rem; color: #ef4444; font-weight: 600;"></div>
                </div>

                <!-- Canvas Container -->
                <div class="epas-canvas-container" style="position: relative; border: 2px solid #e5e7eb; border-radius: 0.5rem; overflow: auto; max-height: 600px; background: #f9fafb;">
                    <canvas id="seating-canvas" style="display: block; cursor: pointer;"></canvas>
                </div>

                <!-- Legend -->
                <div class="epas-legend" style="margin-top: 1rem; display: flex; gap: 1rem; flex-wrap: wrap; font-size: 0.875rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <div style="width: 20px; height: 20px; background: ${this.COLORS.available}; border-radius: 4px;"></div>
                        <span>Available</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <div style="width: 20px; height: 20px; background: ${this.COLORS.selected}; border-radius: 4px;"></div>
                        <span>Selected</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <div style="width: 20px; height: 20px; background: ${this.COLORS.held}; border-radius: 4px;"></div>
                        <span>Held (Others)</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <div style="width: 20px; height: 20px; background: ${this.COLORS.sold}; border-radius: 4px;"></div>
                        <span>Sold</span>
                    </div>
                </div>

                <!-- Selected Seats Info -->
                <div class="epas-selection-info" id="selection-info" style="margin-top: 1rem; padding: 1rem; background: #f3f4f6; border-radius: 0.5rem;">
                    <p style="margin: 0; font-size: 0.875rem; color: #6b7280;">No seats selected</p>
                </div>

                <!-- Action Buttons -->
                <div class="epas-actions" style="margin-top: 1rem; display: flex; gap: 0.5rem;">
                    <button id="hold-btn" class="epas-btn" style="flex: 1; padding: 0.75rem; background: #3b82f6; color: white; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer;" disabled>
                        Hold Seats
                    </button>
                    <button id="release-btn" class="epas-btn" style="flex: 1; padding: 0.75rem; background: #6b7280; color: white; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer;" disabled>
                        Release Holds
                    </button>
                </div>
            </div>
        `;

        // Initialize canvas
        this.canvas = document.getElementById('seating-canvas');
        this.ctx = this.canvas.getContext('2d');

        // Set canvas dimensions
        this.canvas.width = this.state.geometry.canvas.width;
        this.canvas.height = this.state.geometry.canvas.height;

        // Initial draw
        this.drawSeatingMap();
    }

    /**
     * Draw the seating map on canvas
     */
    drawSeatingMap() {
        const { canvas, backgroundUrl, sections } = this.state.geometry;

        // Clear canvas
        this.ctx.clearRect(0, 0, canvas.width, canvas.height);

        // Draw background if available
        if (backgroundUrl) {
            const img = new Image();
            img.onload = () => {
                this.ctx.globalAlpha = 0.3;
                this.ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                this.ctx.globalAlpha = 1.0;
                this.drawSections();
            };
            img.src = backgroundUrl;
        } else {
            this.drawSections();
        }
    }

    /**
     * Draw all sections and seats
     */
    drawSections() {
        this.state.geometry.sections.forEach(section => {
            // Draw section boundary
            this.ctx.strokeStyle = '#9ca3af';
            this.ctx.lineWidth = 2;
            this.ctx.strokeRect(section.x_position, section.y_position, section.width, section.height);

            // Draw section label
            this.ctx.fillStyle = '#374151';
            this.ctx.font = 'bold 14px sans-serif';
            this.ctx.fillText(section.section_code, section.x_position + 5, section.y_position + 20);

            // Draw seats
            section.rows.forEach(row => {
                row.seats.forEach(seat => {
                    this.drawSeat(seat, section, row);
                });
            });
        });
    }

    /**
     * Draw a single seat
     */
    drawSeat(seat, section, row) {
        const x = section.x_position + seat.x_offset;
        const y = section.y_position + row.y_offset + seat.y_offset;
        const width = seat.width || 25;
        const height = seat.height || 25;

        // Determine seat color based on status
        let color = this.COLORS.available;

        const seatData = this.state.seats.find(s => s.seat_uid === seat.seat_uid);
        if (seatData) {
            color = this.COLORS[seatData.status] || this.COLORS.available;
        }

        // Check if selected
        if (this.state.selectedSeats.includes(seat.seat_uid)) {
            color = this.COLORS.selected;
        }

        // Draw seat rectangle
        this.ctx.fillStyle = color;
        this.ctx.fillRect(x, y, width, height);

        // Draw seat border
        this.ctx.strokeStyle = '#ffffff';
        this.ctx.lineWidth = 1;
        this.ctx.strokeRect(x, y, width, height);

        // Store seat position for click detection
        seat._renderX = x;
        seat._renderY = y;
        seat._renderWidth = width;
        seat._renderHeight = height;
    }

    /**
     * Redraw canvas (called after status updates)
     */
    redrawCanvas() {
        this.drawSeatingMap();
    }

    /**
     * Attach event handlers
     */
    attachEventHandlers() {
        // Canvas click for seat selection
        this.canvas.addEventListener('click', (e) => this.handleCanvasClick(e));

        // Action buttons
        document.getElementById('hold-btn').addEventListener('click', () => this.holdSelectedSeats());
        document.getElementById('release-btn').addEventListener('click', () => this.releaseHeldSeats());
    }

    /**
     * Handle canvas click
     */
    handleCanvasClick(e) {
        const rect = this.canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;

        // Find clicked seat
        let clickedSeat = null;

        this.state.geometry.sections.forEach(section => {
            section.rows.forEach(row => {
                row.seats.forEach(seat => {
                    if (
                        x >= seat._renderX &&
                        x <= seat._renderX + seat._renderWidth &&
                        y >= seat._renderY &&
                        y <= seat._renderY + seat._renderHeight
                    ) {
                        clickedSeat = seat;
                    }
                });
            });
        });

        if (clickedSeat) {
            this.toggleSeatSelection(clickedSeat.seat_uid);
        }
    }

    /**
     * Toggle seat selection
     */
    toggleSeatSelection(seatUid) {
        const index = this.state.selectedSeats.indexOf(seatUid);

        if (index > -1) {
            // Deselect
            this.state.selectedSeats.splice(index, 1);
        } else {
            // Select (check max limit)
            if (this.state.selectedSeats.length >= this.config.maxSeats) {
                alert(`Maximum ${this.config.maxSeats} seats allowed`);
                return;
            }

            // Check if seat is available
            const seatData = this.state.seats.find(s => s.seat_uid === seatUid);
            if (seatData && seatData.status !== 'available') {
                alert('This seat is not available');
                return;
            }

            this.state.selectedSeats.push(seatUid);
        }

        this.updateUI();
        this.config.onSeatsSelected(this.state.selectedSeats);
    }

    /**
     * Update UI after state changes
     */
    updateUI() {
        this.redrawCanvas();
        this.updateSelectionInfo();
        this.updateActionButtons();
    }

    /**
     * Update selection info panel
     */
    updateSelectionInfo() {
        const infoEl = document.getElementById('selection-info');

        if (this.state.selectedSeats.length === 0) {
            infoEl.innerHTML = '<p style="margin: 0; font-size: 0.875rem; color: #6b7280;">No seats selected</p>';
        } else {
            const total = this.state.selectedSeats.reduce((sum, seatUid) => {
                const seat = this.findSeatByUid(seatUid);
                return sum + (seat?.price_cents || 0);
            }, 0);

            infoEl.innerHTML = `
                <p style="margin: 0 0 0.5rem 0; font-weight: 600;">${this.state.selectedSeats.length} seat(s) selected</p>
                <p style="margin: 0; font-size: 0.875rem; color: #6b7280;">Total: $${(total / 100).toFixed(2)}</p>
            `;
        }
    }

    /**
     * Update action button states
     */
    updateActionButtons() {
        const holdBtn = document.getElementById('hold-btn');
        const releaseBtn = document.getElementById('release-btn');

        holdBtn.disabled = this.state.selectedSeats.length === 0;
        releaseBtn.disabled = this.state.heldSeats.length === 0;
    }

    /**
     * Hold selected seats
     */
    async holdSelectedSeats() {
        try {
            const response = await this.apiRequest('/seats/hold', 'POST', {
                event_seating_id: this.state.eventSeatingId,
                seat_uids: this.state.selectedSeats,
            });

            if (response.held && response.held.length > 0) {
                this.state.heldSeats = response.held;
                this.state.holdExpiresAt = new Date(response.expires_at);
                this.state.selectedSeats = [];
                this.startCountdown();
                await this.loadSeatAvailability();
                this.updateUI();
                alert(`Successfully held ${response.held.length} seat(s)`);
            }

            if (response.failed && response.failed.length > 0) {
                alert(`Failed to hold ${response.failed.length} seat(s) - they may have been taken`);
            }
        } catch (error) {
            this.config.onError(error);
            alert('Failed to hold seats. Please try again.');
        }
    }

    /**
     * Release held seats
     */
    async releaseHeldSeats() {
        try {
            await this.apiRequest('/seats/hold', 'DELETE', {
                event_seating_id: this.state.eventSeatingId,
                seat_uids: this.state.heldSeats,
            });

            this.state.heldSeats = [];
            this.state.holdExpiresAt = null;
            this.stopCountdown();
            await this.loadSeatAvailability();
            this.updateUI();
            alert('Seats released successfully');
        } catch (error) {
            this.config.onError(error);
        }
    }

    /**
     * Start countdown timer
     */
    startCountdown() {
        this.stopCountdown();

        this.timers.countdown = setInterval(() => {
            if (!this.state.holdExpiresAt) {
                this.stopCountdown();
                return;
            }

            const remaining = this.state.holdExpiresAt - new Date();

            if (remaining <= 0) {
                this.handleHoldExpired();
                return;
            }

            const minutes = Math.floor(remaining / 60000);
            const seconds = Math.floor((remaining % 60000) / 1000);

            document.getElementById('countdown').textContent =
                `Hold expires in ${minutes}:${seconds.toString().padStart(2, '0')}`;
        }, 1000);
    }

    /**
     * Stop countdown timer
     */
    stopCountdown() {
        if (this.timers.countdown) {
            clearInterval(this.timers.countdown);
            this.timers.countdown = null;
        }
        document.getElementById('countdown').textContent = '';
    }

    /**
     * Handle hold expiration
     */
    handleHoldExpired() {
        this.stopCountdown();
        this.state.heldSeats = [];
        this.state.holdExpiresAt = null;
        this.loadSeatAvailability();
        this.updateUI();
        this.config.onHoldExpired();
        alert('Your hold has expired. Please select seats again.');
    }

    /**
     * Start polling for seat status updates
     */
    startStatusPolling() {
        // Poll every 5 seconds
        this.timers.statusRefresh = setInterval(() => {
            this.loadSeatAvailability();
        }, 5000);
    }

    /**
     * Find seat by UID in geometry
     */
    findSeatByUid(seatUid) {
        for (const section of this.state.geometry.sections) {
            for (const row of section.rows) {
                const seat = row.seats.find(s => s.seat_uid === seatUid);
                if (seat) return seat;
            }
        }
        return null;
    }

    /**
     * Make API request
     */
    async apiRequest(endpoint, method = 'GET', body = null) {
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-Seating-Session': this.state.sessionUid,
            },
        };

        if (body) {
            options.body = JSON.stringify(body);
        }

        const response = await fetch(`${this.config.apiBaseUrl}${endpoint}`, options);

        if (!response.ok) {
            throw new Error(`API error: ${response.status} ${response.statusText}`);
        }

        return await response.json();
    }

    /**
     * Get or create session UID
     */
    getOrCreateSessionUid() {
        let uid = sessionStorage.getItem('epas_seating_session_uid');

        if (!uid) {
            uid = 'sess_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            sessionStorage.setItem('epas_seating_session_uid', uid);
        }

        return uid;
    }

    /**
     * Destroy widget and cleanup
     */
    destroy() {
        this.stopCountdown();
        if (this.timers.statusRefresh) {
            clearInterval(this.timers.statusRefresh);
        }
        if (this.container) {
            this.container.innerHTML = '';
        }
    }
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = EPasSeating;
}

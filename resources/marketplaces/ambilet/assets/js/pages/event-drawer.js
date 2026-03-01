// Mobile ticket drawer functions
function openTicketDrawer() {
    var backdrop = document.getElementById('ticketDrawerBackdrop');
    var drawer = document.getElementById('ticketDrawer');
    backdrop.style.visibility = '';
    drawer.style.visibility = '';
    backdrop.classList.add('open');
    drawer.classList.add('open');
    document.body.style.overflow = 'hidden';
    syncDrawerContent();
}

function closeTicketDrawer() {
    document.getElementById('ticketDrawerBackdrop').classList.remove('open');
    document.getElementById('ticketDrawer').classList.remove('open');
    document.body.style.overflow = '';
}

function toggleDrawerTerms() {
    var content = document.getElementById('drawer-ticket-terms-content');
    var chevron = document.getElementById('drawer-terms-chevron');
    if (!content) return;
    var isHidden = content.classList.contains('hidden');
    content.classList.toggle('hidden', !isHidden);
    if (chevron) chevron.style.transform = isHidden ? 'rotate(180deg)' : '';
}

// Sync drawer content with main ticket selection
function syncDrawerContent() {
    const mainContent = document.getElementById('ticket-types');
    const drawerContent = document.getElementById('drawerTicketTypes');
    if (mainContent && drawerContent) {
        // Clone the ticket cards for the drawer
        drawerContent.innerHTML = mainContent.innerHTML;
        // Update onclick handlers to work in drawer context
        drawerContent.querySelectorAll('[onclick*="EventPage.updateQuantity"]').forEach(btn => {
            const originalOnclick = btn.getAttribute('onclick');
            btn.setAttribute('onclick', originalOnclick + '; syncDrawerSummary();');
        });
    }
    syncDrawerSummary();
}

function syncDrawerSummary() {
    setTimeout(() => {
        const mainSummary = document.getElementById('cartSummary');
        const mainEmpty = document.getElementById('emptyCart');
        const drawerSummary = document.getElementById('drawerCartSummary');
        const drawerEmpty = document.getElementById('drawerEmptyCart');
        const mainTotal = document.getElementById('totalPrice');
        const drawerTotal = document.getElementById('drawerTotalPrice');
        const mainSubtotal = document.getElementById('subtotal');
        const drawerSubtotal = document.getElementById('drawerSubtotal');
        const mainTaxes = document.getElementById('taxesContainer');
        const drawerTaxes = document.getElementById('drawerTaxesContainer');
        const mainPoints = document.getElementById('pointsEarned');
        const drawerPoints = document.getElementById('drawerPointsEarned');
        const drawerPointsRow = document.getElementById('drawerPointsRow');

        if (mainSummary && !mainSummary.classList.contains('hidden')) {
            drawerSummary.style.display = 'block';
            drawerEmpty.style.display = 'none';
            if (mainTotal && drawerTotal) {
                drawerTotal.textContent = mainTotal.textContent;
            }
            // Sync subtotal
            if (mainSubtotal && drawerSubtotal) {
                drawerSubtotal.textContent = mainSubtotal.textContent;
            }
            // Sync taxes
            if (mainTaxes && drawerTaxes) {
                drawerTaxes.innerHTML = mainTaxes.innerHTML;
            }
            // Sync points
            if (mainPoints && drawerPoints) {
                drawerPoints.textContent = mainPoints.textContent;
                // Show points row if there are points
                const pointsValue = parseInt(mainPoints.textContent) || 0;
                if (drawerPointsRow) {
                    drawerPointsRow.style.display = pointsValue > 0 ? 'flex' : 'none';
                }
            }
        } else {
            drawerSummary.style.display = 'none';
            drawerEmpty.style.display = 'block';
        }

        // Also sync qty values from main to drawer
        document.querySelectorAll('#ticket-types [id^="qty-"]').forEach(qtyEl => {
            const drawerQty = document.querySelector('#drawerTicketTypes [id="' + qtyEl.id + '"]');
            if (drawerQty) {
                drawerQty.textContent = qtyEl.textContent;
            }
        });

        // Sync selected state
        document.querySelectorAll('#ticket-types .ticket-card').forEach(card => {
            const ticketId = card.dataset.ticket;
            const drawerCard = document.querySelector('#drawerTicketTypes [data-ticket="' + ticketId + '"]');
            if (drawerCard) {
                if (card.classList.contains('selected')) {
                    drawerCard.classList.add('selected');
                } else {
                    drawerCard.classList.remove('selected');
                }
            }
        });
    }, 50);
}

// Show mobile button after event loads and update min price
document.addEventListener('DOMContentLoaded', () => {
    // Poll for event load
    const checkLoaded = setInterval(() => {
        if (typeof EventPage !== 'undefined' && EventPage.event && EventPage.ticketTypes?.length) {
            clearInterval(checkLoaded);
            // Don't show mobile ticket button for ended events
            if (EventPage.eventEnded) return;
            const mobileBtn = document.getElementById('mobileTicketBtn');
            const minPriceEl = document.getElementById('mobileMinPrice');
            const mobileBtnText = mobileBtn?.querySelector('span:not(#mobileMinPrice)');
            if (mobileBtn) {
                // Check if event has seating
                const hasSeating = EventPage.seatingLayout && EventPage.ticketTypes.some(t => t.has_seating);

                // Update button text for seating events
                if (hasSeating && mobileBtnText) {
                    mobileBtnText.textContent = 'Alege locul';
                    // Override drawer open to open seating modal directly
                    mobileBtn.querySelector('button').setAttribute('onclick',
                        'if (EventPage.seatingLayout) { EventPage.openSeatSelection(); } else { openTicketDrawer(); }');
                }

                // Find minimum price (skip 0-price if paid tickets exist)
                const allPrices = EventPage.ticketTypes
                    .filter(t => !t.is_sold_out && t.available > 0)
                    .map(t => t.price);
                const paidPrices = allPrices.filter(p => p > 0);
                const prices = paidPrices.length > 0 ? paidPrices : allPrices;
                if (prices.length && minPriceEl) {
                    const minPrice = Math.min(...prices);
                    if (minPrice > 0) {
                        minPriceEl.textContent = 'De la ' + minPrice.toFixed(0) + ' lei';
                    } else {
                        minPriceEl.textContent = 'Gratuit';
                    }
                }
            }
        }
    }, 100);
});

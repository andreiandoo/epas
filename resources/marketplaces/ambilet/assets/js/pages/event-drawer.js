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
    // Force a re-render of the desktop ticket cards before cloning so the
    // drawer picks up the latest state (perks, prices, availability). Without
    // this, opening the drawer immediately after page load — before the API
    // response has re-triggered renderTicketTypes with enable_ticket_perks —
    // would leave the drawer stuck with the perk-less initial render.
    if (typeof EventPage !== 'undefined' && typeof EventPage.renderTicketTypes === 'function' && EventPage.ticketTypes?.length) {
        try { EventPage.renderTicketTypes(); } catch (_) {}
    }
    const mainContent = document.getElementById('ticket-types');
    const drawerContent = document.getElementById('drawerTicketTypes');
    if (mainContent && drawerContent) {
        // Clone the ticket cards for the drawer
        drawerContent.innerHTML = mainContent.innerHTML;

        // Fix duplicate IDs: rename ticket-group-X to drawer-ticket-group-X
        drawerContent.querySelectorAll('[id^="ticket-group-"]').forEach(el => {
            el.id = 'drawer-' + el.id;
        });

        // Re-wire the ticket-group accordion — innerHTML cloning drops the
        // listeners the desktop render bound, so the drawer headers were dead.
        wireDrawerTicketGroups(drawerContent);

        // Update onclick handlers to work in drawer context
        drawerContent.querySelectorAll('[onclick*="EventPage.updateQuantity"]').forEach(btn => {
            const originalOnclick = btn.getAttribute('onclick');
            btn.setAttribute('onclick', originalOnclick + '; syncDrawerSummary();');
        });
    }

    // Handle ticket terms visibility
    var termsSection = document.getElementById('drawer-ticket-terms-section');
    if (termsSection) {
        var termsContent = typeof EventPage !== 'undefined' && EventPage.event ? EventPage.event.ticket_terms : null;
        var hasTerms = termsContent && termsContent.trim() && termsContent !== '<p></p>' && termsContent !== '<p><br></p>';
        termsSection.style.display = hasTerms ? '' : 'none';
        if (hasTerms) {
            var termsEl = document.getElementById('drawer-ticket-terms-content');
            if (termsEl) termsEl.innerHTML = termsContent;
        }
    }

    syncDrawerSummary();
}

/**
 * Re-wire the exclusive-open ticket-group accordion inside the mobile drawer.
 * The desktop render binds click listeners in event-single.js, but the drawer
 * is built by cloning innerHTML (which drops listeners) and the content divs
 * were renamed ticket-group-X -> drawer-ticket-group-X. This:
 *   1. rewrites each button's data-group-id to the drawer id, and
 *   2. binds a click handler SCOPED to the drawer (the desktop version used
 *      document.getElementById, which would toggle the page behind the drawer).
 */
function wireDrawerTicketGroups(drawerContent) {
    if (!drawerContent) return;

    drawerContent.querySelectorAll('[data-ticket-group-btn]').forEach(function(btn) {
        var gid = btn.getAttribute('data-group-id') || '';
        if (gid && gid.indexOf('drawer-') !== 0) {
            btn.setAttribute('data-group-id', 'drawer-' + gid);
        }

        // The drawer container already uses space-y-2, so drop the group
        // wrapper's mb-4 (otherwise the gaps stack and look uneven).
        if (btn.parentElement) {
            btn.parentElement.classList.remove('mb-4');
        }

        btn.addEventListener('click', function() {
            var target = drawerContent.querySelector('#' + btn.dataset.groupId);
            if (!target) return;
            var wasHidden = target.classList.contains('hidden');

            drawerContent.querySelectorAll('.ticket-group-content').forEach(function(el) {
                el.classList.add('hidden');
            });
            drawerContent.querySelectorAll('[data-ticket-group-btn] .chevron-icon').forEach(function(icon) {
                icon.classList.remove('rotate-180');
            });
            // All groups closed now → fully-rounded headers.
            drawerContent.querySelectorAll('[data-ticket-group-btn]').forEach(function(b) {
                b.classList.remove('rounded-t-2xl');
                b.classList.add('rounded-2xl');
            });

            if (wasHidden) {
                target.classList.remove('hidden');
                var chevron = btn.querySelector('.chevron-icon');
                if (chevron) chevron.classList.add('rotate-180');
                btn.classList.remove('rounded-2xl');
                btn.classList.add('rounded-t-2xl');
            }
        });
    });
}

function syncDrawerSummary() {
    setTimeout(() => {
        // Re-clone ticket cards from desktop (which has already re-rendered with +/- buttons)
        const mainContent = document.getElementById('ticket-types');
        const drawerContent = document.getElementById('drawerTicketTypes');
        if (mainContent && drawerContent) {
            drawerContent.innerHTML = mainContent.innerHTML;

            // Fix duplicate IDs
            drawerContent.querySelectorAll('[id^="ticket-group-"]').forEach(el => {
                el.id = 'drawer-' + el.id;
            });

            // Re-wire the group accordion after every re-clone.
            wireDrawerTicketGroups(drawerContent);

            drawerContent.querySelectorAll('[onclick*="EventPage.updateQuantity"]').forEach(btn => {
                const originalOnclick = btn.getAttribute('onclick');
                btn.setAttribute('onclick', originalOnclick + '; syncDrawerSummary();');
            });
        }

        const mainSummary = document.getElementById('cartSummary');
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
            if (mainSubtotal && drawerSubtotal) {
                drawerSubtotal.textContent = mainSubtotal.textContent;
            }
            if (mainTaxes && drawerTaxes) {
                drawerTaxes.innerHTML = mainTaxes.innerHTML;
            }
            if (mainPoints && drawerPoints) {
                drawerPoints.textContent = mainPoints.textContent;
                const pointsValue = parseInt(mainPoints.textContent) || 0;
                if (drawerPointsRow) {
                    drawerPointsRow.style.display = pointsValue > 0 ? 'flex' : 'none';
                }
            }
        } else {
            drawerSummary.style.display = 'none';
            drawerEmpty.style.display = 'block';
        }
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

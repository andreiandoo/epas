/**
 * Artist-single page — claim CTA renderer
 * Decides what the "claim" container on the public artist page should
 * show based on:
 *   1. Whether the visitor is logged in as an artist owning this profile
 *      → "Editează profilul" deep-link.
 *   2. /artist/check-claim/{slug} response → either a "claimed/verified"
 *      badge, a "review pending" badge, or the call-to-action button to
 *      claim it.
 *
 * Designed to be a no-op silently if the API is down or the slug is
 * missing — never blocks the rest of the page.
 */
const ArtistClaim = {
    init() {
        const container = document.getElementById('claimCtaContainer');
        if (!container) return;

        const slug = window.ARTIST_SLUG || '';
        if (!slug) return;

        // Case 1: visitor is the owning artist account → quick edit link.
        if (typeof AmbiletAuth !== 'undefined' && AmbiletAuth.isArtist && AmbiletAuth.isArtist()) {
            const data = AmbiletAuth.getArtistData?.();
            const ownedSlug = data?.artist?.slug;
            if (ownedSlug && ownedSlug === slug) {
                container.innerHTML = ArtistClaim.renderEditCta();
                container.classList.remove('hidden');
                return;
            }
        }

        // Case 2: ask the server about the current claim state.
        if (typeof AmbiletAPI === 'undefined' || !AmbiletAPI.artist?.checkClaim) return;

        AmbiletAPI.artist.checkClaim(slug)
            .then((res) => {
                if (!res || !res.success || !res.data) return;
                const data = res.data;

                // CLAIMED — never render the "Revendică profilul" button.
                // Show the verified/pending badge as a trust signal.
                if (data.is_claimed || data.is_verified || data.is_pending) {
                    if (data.is_verified) {
                        container.innerHTML = ArtistClaim.renderVerifiedBadge();
                    } else if (data.is_pending) {
                        container.innerHTML = ArtistClaim.renderPendingBadge();
                    } else {
                        // is_claimed=true but neither verified nor pending —
                        // keep the section hidden entirely.
                        return;
                    }
                    container.classList.remove('hidden');
                    return;
                }

                // NOT CLAIMED — render the call-to-action button.
                if (data.exists !== false) {
                    container.innerHTML = ArtistClaim.renderClaimCta(slug);
                    container.classList.remove('hidden');
                }
            })
            .catch(() => { /* Silent fail — claim CTA simply never appears. */ });
    },

    renderClaimCta(slug) {
        return ''
            + '<a href="/artist/inregistrare?claim=' + encodeURIComponent(slug) + '"'
            + ' class="flex items-center justify-center w-full gap-2 px-5 py-3 text-sm font-semibold transition-all border rounded-xl border-primary text-primary hover:bg-primary hover:text-white">'
            + '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">'
            + '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>'
            + '</svg>'
            + 'Revendică profilul'
            + '</a>';
    },

    renderVerifiedBadge() {
        return ''
            + '<div class="flex items-center justify-center w-full gap-2 px-5 py-3 text-sm font-semibold rounded-xl bg-green-50 text-green-700 border border-green-200">'
            + '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">'
            + '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>'
            + '</svg>'
            + 'Profil verificat'
            + '</div>';
    },

    renderPendingBadge() {
        return ''
            + '<div class="flex items-center justify-center w-full gap-2 px-5 py-3 text-sm font-semibold rounded-xl bg-amber-50 text-amber-700 border border-amber-200">'
            + '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">'
            + '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>'
            + '</svg>'
            + 'Cerere de revendicare în review'
            + '</div>';
    },

    renderEditCta() {
        return ''
            + '<a href="/artist/cont/detalii"'
            + ' class="flex items-center justify-center w-full gap-2 px-5 py-3 text-sm font-semibold text-white transition-all bg-primary rounded-xl hover:bg-primary-dark">'
            + '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">'
            + '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>'
            + '</svg>'
            + 'Editează profilul'
            + '</a>';
    }
};

window.ArtistClaim = ArtistClaim;

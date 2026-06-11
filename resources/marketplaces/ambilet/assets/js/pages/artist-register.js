/**
 * Artist Account — Register page handler
 *
 * Two flows, branched on `window.ARTIST_CLAIM_SLUG`:
 *
 *   1. Claim flow (slug pre-filled): we run check-claim on load to show
 *      "already verified" / "review pending" banners, and require the
 *      claim_message textarea on submit.
 *
 *   2. Picker flow (no slug): the user types into a search input and
 *      picks an artist from the autocomplete dropdown. The picked
 *      artist's id is sent as `artist_id`. Already-claimed artists are
 *      shown as disabled in the dropdown.
 *
 * Either way the linked artist is REQUIRED — the form blocks submit
 * with a clear message if neither slug nor id is present.
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('artist-register-form');
    const slugInput = document.getElementById('artist_slug');
    const idInput = document.getElementById('artist_id');
    const claimStatus = document.getElementById('claim-status');

    const claimSlug = (window.ARTIST_CLAIM_SLUG || '').trim();
    const isClaimFlow = claimSlug !== '';

    if (isClaimFlow) {
        wireClaimStatusCheck(claimSlug, claimStatus, form);
    } else {
        wireArtistPicker(idInput);
    }

    wirePhoneInput();
    wireSubmit(form, slugInput, idInput, isClaimFlow);
});

// ============================================================================
// Claim flow: check current status of the slug to set expectations
// ============================================================================
function wireClaimStatusCheck(claimSlug, claimStatusEl, form) {
    if (!claimStatusEl) return;

    AmbiletAPI.artist.checkClaim(claimSlug)
        .then((res) => {
            if (!res.success || !res.data) return;
            const data = res.data;

            if (data.is_verified) {
                claimStatusEl.className = 'p-3 mb-5 text-sm text-center rounded-lg bg-red-50 text-red-700 border border-red-200';
                claimStatusEl.innerHTML = '<strong>Profil deja revendicat și verificat.</strong><br>Dacă tu ești titularul, contactează echipa la <a href="mailto:contact@ambilet.ro" class="underline">contact@ambilet.ro</a>.';
                claimStatusEl.classList.remove('hidden');
                disableForm(form);
            } else if (data.is_pending) {
                claimStatusEl.className = 'p-3 mb-5 text-sm text-center rounded-lg bg-amber-50 text-amber-700 border border-amber-200';
                claimStatusEl.innerHTML = '<strong>O cerere de revendicare este deja în review.</strong><br>Va trebui să aștepți rezultatul acelei cereri înainte de a aplica din nou.';
                claimStatusEl.classList.remove('hidden');
                disableForm(form);
            }
        })
        .catch(() => { /* non-blocking — let the user submit anyway */ });
}

function disableForm(form) {
    form.querySelectorAll('input,textarea,button[type="submit"]').forEach(el => el.disabled = true);
}

// ============================================================================
// Picker flow: searchable autocomplete dropdown
// ============================================================================
function wireArtistPicker(hiddenIdInput) {
    const search = document.getElementById('artist_search');
    const results = document.getElementById('artist_results');
    const selected = document.getElementById('artist_selected');
    const selectedLogo = document.getElementById('artist_selected_logo');
    const selectedName = document.getElementById('artist_selected_name');
    const selectedSlug = document.getElementById('artist_selected_slug');
    const clearBtn = document.getElementById('artist_clear_btn');

    if (!search || !results) return;

    let debounceTimer = null;

    const showResults = (artists) => {
        if (!artists || artists.length === 0) {
            results.innerHTML = '<div class="px-4 py-3 text-sm text-muted">Nicio potrivire.</div>';
            results.classList.remove('hidden');
            return;
        }

        results.innerHTML = artists.map(a => {
            const logoUrl = resolveStorageUrl(a.logo_url || a.main_image_url);
            const claimedBadge = a.is_claimed
                ? '<span class="ml-auto px-2 py-0.5 text-xs text-amber-700 rounded-full bg-amber-100">deja revendicat</span>'
                : '';
            return ''
                + '<button type="button" data-artist-id="' + a.id + '"'
                + ' data-artist-name="' + escapeAttr(a.name) + '"'
                + ' data-artist-slug="' + escapeAttr(a.slug) + '"'
                + ' data-artist-logo="' + escapeAttr(logoUrl || '') + '"'
                + ' data-claimed="' + (a.is_claimed ? '1' : '0') + '"'
                + ' class="flex items-center w-full gap-3 px-3 py-2 text-left transition-colors hover:bg-surface' + (a.is_claimed ? ' opacity-50 cursor-not-allowed' : '') + '">'
                + (logoUrl
                    ? '<img src="' + escapeAttr(logoUrl) + '" class="object-cover w-8 h-8 rounded-full bg-gray-200" alt="">'
                    : '<div class="flex items-center justify-center w-8 h-8 text-xs font-semibold text-white rounded-full bg-primary">' + escapeHtml((a.name || '?').charAt(0).toUpperCase()) + '</div>')
                + '<div class="min-w-0">'
                + '<p class="text-sm font-medium truncate text-secondary">' + escapeHtml(a.name) + '</p>'
                + '<p class="text-xs truncate text-muted">/' + escapeHtml(a.slug) + '</p>'
                + '</div>'
                + claimedBadge
                + '</button>';
        }).join('');

        // Wire each result button
        results.querySelectorAll('button[data-artist-id]').forEach(btn => {
            btn.addEventListener('click', () => {
                if (btn.dataset.claimed === '1') {
                    AmbiletNotifications.error('Acest profil este deja revendicat.');
                    return;
                }
                const id = btn.dataset.artistId;
                const name = btn.dataset.artistName;
                const slug = btn.dataset.artistSlug;
                const logo = btn.dataset.artistLogo;

                hiddenIdInput.value = id;
                search.value = '';
                results.classList.add('hidden');
                results.innerHTML = '';

                selected.classList.remove('hidden');
                selected.classList.add('flex');
                selectedName.textContent = name;
                selectedSlug.textContent = '/' + slug;
                if (logo) {
                    selectedLogo.src = logo;
                    selectedLogo.classList.remove('hidden');
                } else {
                    selectedLogo.classList.add('hidden');
                }
            });
        });

        results.classList.remove('hidden');
    };

    const runSearch = (query) => {
        AmbiletAPI.artist.searchArtists(query)
            .then((res) => {
                if (!res.success) return;
                showResults(res.data?.artists || []);
            })
            .catch(() => {
                results.innerHTML = '<div class="px-4 py-3 text-sm text-red-600">Eroare la căutare.</div>';
                results.classList.remove('hidden');
            });
    };

    search.addEventListener('input', () => {
        const query = search.value.trim();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => runSearch(query), 250);
    });

    search.addEventListener('focus', () => {
        // Show featured/all on focus when empty.
        if (search.value.trim() === '') runSearch('');
    });

    // Close dropdown when clicking outside.
    document.addEventListener('click', (e) => {
        if (!results.contains(e.target) && e.target !== search) {
            results.classList.add('hidden');
        }
    });

    clearBtn?.addEventListener('click', () => {
        hiddenIdInput.value = '';
        selected.classList.add('hidden');
        selected.classList.remove('flex');
        search.value = '';
        search.focus();
    });
}

// ============================================================================
// Phone input: digits + plus + spaces only
// ============================================================================
function wirePhoneInput() {
    const phoneInput = document.getElementById('phone');
    if (!phoneInput) return;
    phoneInput.setAttribute('inputmode', 'tel');
    phoneInput.addEventListener('input', function () {
        this.value = this.value.replace(/[^\d+\s]/g, '');
    });
}

// ============================================================================
// Submit handler
// ============================================================================
function wireSubmit(form, slugInput, idInput, isClaimFlow) {
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Se trimite cererea...';

        const password = document.getElementById('password').value;
        const passwordConfirm = document.getElementById('password_confirmation').value;
        if (password !== passwordConfirm) {
            AmbiletNotifications.error('Parolele nu coincid');
            btn.disabled = false;
            btn.textContent = originalText;
            return;
        }

        // Either slug OR id must be present — the backend enforces this too,
        // but we want a clean inline error before the round-trip.
        const artistSlug = slugInput.value.trim();
        const artistId = idInput.value.trim();
        if (!artistSlug && !artistId) {
            AmbiletNotifications.error('Selectează artistul pe care îl reprezinți.');
            btn.disabled = false;
            btn.textContent = originalText;
            return;
        }

        const formData = {
            first_name: document.getElementById('first_name').value.trim(),
            last_name: document.getElementById('last_name').value.trim(),
            email: document.getElementById('email').value.trim().toLowerCase(),
            phone: document.getElementById('phone').value.replace(/\s/g, '') || null,
            password: password,
            password_confirmation: passwordConfirm,
        };
        if (artistSlug) formData.artist_slug = artistSlug;
        if (artistId) formData.artist_id = parseInt(artistId, 10);

        if (isClaimFlow) {
            formData.claim_message = document.getElementById('claim_message')?.value.trim() || '';
        }

        // Phone format check (only when provided).
        if (formData.phone && !/^\+?\d{7,15}$/.test(formData.phone)) {
            AmbiletNotifications.error('Numărul de telefon trebuie să conțină doar cifre (7-15 cifre)');
            btn.disabled = false;
            btn.textContent = originalText;
            return;
        }

        try {
            const result = await AmbiletAuth.registerArtist(formData);

            if (result.success) {
                AmbiletNotifications.success('Cerere trimisă cu succes! Verifică-ți emailul.');
                setTimeout(() => {
                    window.location.href = '/artist/in-asteptare?email=' + encodeURIComponent(formData.email);
                }, 1200);
            } else {
                AmbiletNotifications.error(result.message || 'Eroare la trimiterea cererii');
                btn.disabled = false;
                btn.textContent = originalText;
            }
        } catch (error) {
            AmbiletNotifications.error('Eroare la trimitere. Încearcă din nou.');
            btn.disabled = false;
            btn.textContent = originalText;
        }
    });
}

// ============================================================================
// Helpers
// ============================================================================
function resolveStorageUrl(path) {
    if (!path) return '';
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    const base = (typeof window.AMBILET !== 'undefined' && window.AMBILET.storageUrl) || 'https://core.tixello.com/storage';
    return base.replace(/\/$/, '') + '/' + path.replace(/^\/+/, '');
}

function escapeHtml(s) {
    return String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function escapeAttr(s) {
    return escapeHtml(s);
}

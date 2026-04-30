/**
 * Artist Account — Profile Editor
 *
 * Strategy:
 *   1. Fetch GET /artist/profile + GET /artist/profile/taxonomies in parallel.
 *   2. If unlinked, show the amber notice and bail.
 *   3. Otherwise: hydrate the form from the artist record, render tab nav,
 *      bind change tracking. The form is one big HTML form with multiple
 *      `[data-tab]` sections — only one is visible at a time.
 *   4. Image uploads POST immediately to /profile/image and stage the
 *      returned `path` into the form's pending state. Nothing hits the
 *      Artist record until the user clicks Save.
 *   5. On Save: collect every `[data-field]`, repeater data, multi-select
 *      pills, plus nested `booking_agency.*` and `bio_html.*` keys, and
 *      PUT the whole payload. Server merges bio_html and validates each
 *      field against the strict whitelist.
 *
 * Caveats:
 *   - bio_html uses a plain textarea (server applies HtmlSanitizer).
 *   - Country/state/city are free-text inputs in V1 (cascading selects
 *     are a fast follow-up).
 */

const TABS = [
    ['identitate', 'Identitate', 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
    ['media', 'Media', 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'],
    ['biografie', 'Biografie', 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'],
    ['achievements', 'Realizări', 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z'],
    ['discografie', 'Discografie', 'M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z'],
    ['locatie', 'Locație', 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z'],
    ['categorii', 'Categorii', 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z'],
    ['social', 'Social Media', 'M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z'],
    ['videoclipuri', 'Videoclipuri', 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z'],
    ['tarife', 'Tarife', 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
    ['contact', 'Contact', 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z'],
    ['manager', 'Manager', 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
    ['agent', 'Agent', 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
    ['agentie', 'Agenție booking', 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
];

const State = {
    artist: null,           // server-supplied artist record
    taxonomies: { artist_types: [], artist_genres: [] },
    selectedTypeIds: new Set(),
    selectedGenreIds: new Set(),
    pendingImages: {},      // { main_image_url: 'storage/path' } until Save
    dirty: false,
};

window.addEventListener('ambilet:artist-cont:ready', () => {
    Promise.all([
        AmbiletAPI.artist.getProfile().catch(err => ({ _error: err })),
        AmbiletAPI.artist.getTaxonomies().catch(() => ({ success: false })),
    ]).then(([profileRes, taxRes]) => {
        const loading = document.getElementById('detalii-loading');
        loading?.classList.add('hidden');

        if (profileRes && profileRes._error) {
            const err = profileRes._error;
            // 403 with code='unlinked' means no artist_id linked yet.
            if (err.status === 403 && err.data?.errors?.code === 'unlinked') {
                document.getElementById('detalii-unlinked')?.classList.remove('hidden');
                return;
            }
            AmbiletNotifications.error('Eroare la încărcarea profilului.');
            return;
        }

        if (!profileRes?.success || !profileRes.data?.artist) {
            document.getElementById('detalii-unlinked')?.classList.remove('hidden');
            return;
        }

        State.artist = profileRes.data.artist;
        State.taxonomies = taxRes?.data || { artist_types: [], artist_genres: [] };
        State.selectedTypeIds = new Set((State.artist.artist_types || []).map(t => t.id));
        State.selectedGenreIds = new Set((State.artist.artist_genres || []).map(g => g.id));

        document.getElementById('detalii-editor')?.classList.remove('hidden');
        renderTabNav();
        hydrateForm();
        wireRepeaters();
        wireMultiSelects();
        wireImageUploaders();
        wireDirtyTracking();
        wireSave();
    });
});

// ============================================================================
// Tab nav
// ============================================================================
function renderTabNav() {
    const nav = document.getElementById('tab-nav');
    if (!nav) return;
    nav.innerHTML = TABS.map(([key, label, iconPath], i) => ''
        + '<li><button type="button" data-tab-target="' + key + '"'
        + ' class="tab-btn flex items-center w-full gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-colors '
        + (i === 0 ? 'bg-primary/10 text-primary' : 'text-secondary hover:bg-surface') + '">'
        + '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">'
        + '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="' + iconPath + '"/></svg>'
        + label + '</button></li>'
    ).join('');

    nav.querySelectorAll('button[data-tab-target]').forEach(btn => {
        btn.addEventListener('click', () => switchTab(btn.dataset.tabTarget));
    });
}

function switchTab(key) {
    document.querySelectorAll('.tab-btn').forEach(btn => {
        const active = btn.dataset.tabTarget === key;
        btn.classList.toggle('bg-primary/10', active);
        btn.classList.toggle('text-primary', active);
        btn.classList.toggle('text-secondary', !active);
        btn.classList.toggle('hover:bg-surface', !active);
    });
    document.querySelectorAll('.tab-section').forEach(sec => {
        sec.classList.toggle('hidden', sec.dataset.tab !== key);
    });
}

// ============================================================================
// Hydration: server data -> form inputs
// ============================================================================
function hydrateForm() {
    const a = State.artist;

    // Plain field inputs
    document.querySelectorAll('[data-field]').forEach(input => {
        const path = input.dataset.field;
        const value = getNested(a, path);
        if (input.type === 'checkbox') {
            input.checked = !!value;
        } else {
            input.value = value == null ? '' : value;
        }
    });

    // Image previews
    [['main_image_url', a.main_image_full_url],
     ['logo_url', a.logo_full_url],
     ['portrait_url', a.portrait_full_url]].forEach(([field, fullUrl]) => {
        if (fullUrl) showImagePreview(field, fullUrl);
    });

    // Repeaters
    renderRepeater('achievements', a.achievements || [], achievementRow);
    renderRepeater('discography', a.discography || [], discographyRow);
    renderRepeater('youtube_videos', a.youtube_videos || [], youtubeRow);

    // Multi-selects
    renderMultiSelect('artist_types', State.taxonomies.artist_types, State.selectedTypeIds);
    renderMultiSelect('artist_genres', State.taxonomies.artist_genres, State.selectedGenreIds);

    // Booking agency services checkboxes
    const services = (a.booking_agency && a.booking_agency.services) || [];
    document.querySelectorAll('[data-service]').forEach(cb => {
        cb.checked = services.includes(cb.dataset.service);
    });
}

function getNested(obj, path) {
    return path.split('.').reduce((acc, key) => acc == null ? acc : acc[key], obj);
}

// ============================================================================
// Repeaters
// ============================================================================
function wireRepeaters() {
    document.querySelectorAll('[data-add]').forEach(btn => {
        btn.addEventListener('click', () => {
            const key = btn.dataset.add;
            const max = key === 'youtube_videos' ? 5 : (key === 'achievements' ? 20 : 50);
            const container = document.querySelector('[data-repeater="' + key + '"]');
            const current = container.querySelectorAll('[data-row]').length;
            if (current >= max) {
                AmbiletNotifications.error('Maxim ' + max + ' intrări.');
                return;
            }
            const row = ({
                achievements: achievementRow,
                discography: discographyRow,
                youtube_videos: youtubeRow,
            }[key])({});
            container.insertAdjacentHTML('beforeend', row);
            wireRepeaterRow(container.lastElementChild);
            markDirty();
        });
    });
}

function renderRepeater(key, rows, rowFn) {
    const container = document.querySelector('[data-repeater="' + key + '"]');
    if (!container) return;
    container.innerHTML = rows.map(rowFn).join('') ||
        '<p class="text-sm text-muted">Niciun element încă.</p>';
    container.querySelectorAll('[data-row]').forEach(wireRepeaterRow);
}

function wireRepeaterRow(row) {
    row.querySelectorAll('[data-remove]').forEach(btn => {
        btn.addEventListener('click', () => { row.remove(); markDirty(); });
    });
}

function achievementRow(data) {
    return ''
        + '<div data-row class="flex gap-2 p-3 border rounded-lg border-border">'
        + '<input type="text" data-row-field="title" maxlength="14" placeholder="Titlu" value="' + escapeAttr(data.title || '') + '" class="flex-1 input">'
        + '<input type="text" data-row-field="subtitle" maxlength="24" placeholder="Subtitlu" value="' + escapeAttr(data.subtitle || '') + '" class="flex-1 input">'
        + '<button type="button" data-remove class="px-3 text-sm text-muted hover:text-red-600">×</button>'
        + '</div>';
}

function discographyRow(data) {
    return ''
        + '<div data-row class="grid grid-cols-1 gap-2 p-3 border rounded-lg border-border md:grid-cols-[1fr_1fr_120px_100px_40px]">'
        + '<input type="text" data-row-field="name" maxlength="255" placeholder="Nume" value="' + escapeAttr(data.name || '') + '" class="input">'
        + '<select data-row-field="type" class="input">'
        + ['album','ep','single','live','live_dvd','compilation','soundtrack','remix']
            .map(t => '<option value="' + t + '"' + (data.type === t ? ' selected' : '') + '>' + t + '</option>').join('')
        + '</select>'
        + '<input type="number" data-row-field="year" min="1900" max="2100" placeholder="An" value="' + escapeAttr(data.year || '') + '" class="input">'
        + '<input type="text" data-row-field="image" placeholder="cover URL" value="' + escapeAttr(data.image || '') + '" class="input">'
        + '<button type="button" data-remove class="px-2 text-sm text-muted hover:text-red-600">×</button>'
        + '</div>';
}

function youtubeRow(data) {
    return ''
        + '<div data-row class="flex gap-2 p-3 border rounded-lg border-border">'
        + '<input type="url" data-row-field="url" placeholder="https://www.youtube.com/watch?v=…" value="' + escapeAttr(data.url || '') + '" class="flex-1 input">'
        + '<button type="button" data-remove class="px-3 text-sm text-muted hover:text-red-600">×</button>'
        + '</div>';
}

function collectRepeater(key) {
    const container = document.querySelector('[data-repeater="' + key + '"]');
    if (!container) return [];
    return Array.from(container.querySelectorAll('[data-row]')).map(row => {
        const obj = {};
        row.querySelectorAll('[data-row-field]').forEach(input => {
            const f = input.dataset.rowField;
            const v = input.value.trim();
            if (v !== '') obj[f] = (input.type === 'number') ? Number(v) : v;
        });
        return obj;
    }).filter(obj => Object.keys(obj).length > 0);
}

// ============================================================================
// Multi-selects (artist_types / artist_genres)
// ============================================================================
function wireMultiSelects() {
    // Hover/click handlers are wired in renderMultiSelect after each render.
}

function renderMultiSelect(key, options, selectedSet) {
    const container = document.querySelector('[data-multi="' + key + '"]');
    if (!container) return;
    container.innerHTML = options.map(opt => {
        const selected = selectedSet.has(opt.id);
        return ''
            + '<button type="button" data-multi-id="' + opt.id + '"'
            + ' class="px-3 py-1 text-xs rounded-full transition-colors '
            + (selected ? 'bg-primary text-white' : 'bg-surface text-secondary hover:bg-primary/10') + '">'
            + escapeHtml(opt.name) + '</button>';
    }).join('') || '<p class="px-2 py-1 text-xs text-muted">Nicio opțiune disponibilă.</p>';

    container.querySelectorAll('button[data-multi-id]').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = parseInt(btn.dataset.multiId, 10);
            if (selectedSet.has(id)) {
                selectedSet.delete(id);
                btn.classList.remove('bg-primary', 'text-white');
                btn.classList.add('bg-surface', 'text-secondary', 'hover:bg-primary/10');
            } else {
                selectedSet.add(id);
                btn.classList.add('bg-primary', 'text-white');
                btn.classList.remove('bg-surface', 'text-secondary', 'hover:bg-primary/10');
            }
            markDirty();
        });
    });
}

// ============================================================================
// Image uploaders
// ============================================================================
function wireImageUploaders() {
    document.querySelectorAll('.image-uploader').forEach(uploader => {
        const field = uploader.dataset.field;
        const type = uploader.dataset.type;
        const fileInput = uploader.querySelector('input[type="file"]');
        const clearBtn = uploader.querySelector('.clear-btn');

        fileInput.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            // Show loading indicator on the preview frame
            const preview = uploader.querySelector('.preview');
            const previewImg = preview.querySelector('img');
            preview.classList.remove('hidden');
            previewImg.style.opacity = '0.4';

            try {
                const res = await AmbiletAPI.artist.uploadProfileImage(file, type);
                if (res.success && res.data) {
                    State.pendingImages[field] = res.data.path;
                    previewImg.src = res.data.url;
                    previewImg.style.opacity = '1';
                    clearBtn.classList.remove('hidden');
                    markDirty();
                } else {
                    AmbiletNotifications.error(res.message || 'Upload eșuat.');
                    preview.classList.add('hidden');
                }
            } catch (err) {
                AmbiletNotifications.error(err.message || 'Upload eșuat.');
                preview.classList.add('hidden');
            }
            fileInput.value = ''; // reset so re-selecting same file works
        });

        clearBtn?.addEventListener('click', () => {
            State.pendingImages[field] = null; // explicit "clear" marker for save
            uploader.querySelector('.preview').classList.add('hidden');
            clearBtn.classList.add('hidden');
            markDirty();
        });
    });
}

function showImagePreview(field, url) {
    const uploader = document.querySelector('.image-uploader[data-field="' + field + '"]');
    if (!uploader) return;
    const preview = uploader.querySelector('.preview');
    const img = preview.querySelector('img');
    img.src = url;
    preview.classList.remove('hidden');
    uploader.querySelector('.clear-btn').classList.remove('hidden');
}

// ============================================================================
// Dirty tracking + Save
// ============================================================================
function wireDirtyTracking() {
    document.getElementById('detalii-form').addEventListener('input', () => markDirty());
    document.getElementById('detalii-form').addEventListener('change', () => markDirty());

    document.getElementById('cancel-btn').addEventListener('click', () => {
        if (!State.dirty || confirm('Ești sigur? Modificările nesalvate vor fi pierdute.')) {
            window.location.reload();
        }
    });

    // Browser warning on tab close with unsaved changes.
    window.addEventListener('beforeunload', (e) => {
        if (State.dirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
}

function markDirty() {
    State.dirty = true;
    document.getElementById('dirty-indicator')?.classList.remove('hidden');
}

function clearDirty() {
    State.dirty = false;
    document.getElementById('dirty-indicator')?.classList.add('hidden');
}

function wireSave() {
    document.getElementById('detalii-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('save-btn');
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Se salvează...';

        const payload = collectPayload();

        try {
            const res = await AmbiletAPI.artist.updateProfile(payload);
            if (res.success) {
                AmbiletNotifications.success('Modificări salvate.');
                State.artist = res.data.artist;
                State.pendingImages = {};
                clearDirty();
                // Re-hydrate so server-merged values (bio, m2m) are reflected.
                hydrateForm();
            } else {
                AmbiletNotifications.error(res.message || 'Eroare la salvare.');
            }
        } catch (err) {
            const msg = err.errors
                ? Object.values(err.errors).flat().join(' ')
                : (err.message || 'Eroare la salvare.');
            AmbiletNotifications.error(msg);
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    });
}

function collectPayload() {
    const payload = { booking_agency: {}, bio_html: {} };

    // Plain fields, splitting nested keys on the dot.
    document.querySelectorAll('[data-field]').forEach(input => {
        const path = input.dataset.field;
        let value = input.type === 'checkbox' ? input.checked : input.value.trim();
        if (input.type === 'number' && value !== '') value = Number(value);
        if (value === '' || value == null) value = null;

        // Pending image override (uploads not yet committed live in
        // State.pendingImages and beat whatever's in the input).
        if (Object.prototype.hasOwnProperty.call(State.pendingImages, path)) {
            value = State.pendingImages[path];
        }

        setNested(payload, path, value);
    });

    // Repeaters
    payload.achievements = collectRepeater('achievements');
    payload.discography = collectRepeater('discography');
    payload.youtube_videos = collectRepeater('youtube_videos');

    // Multi-selects → ID arrays
    payload.artist_type_ids = Array.from(State.selectedTypeIds);
    payload.artist_genre_ids = Array.from(State.selectedGenreIds);

    // Booking agency services
    payload.booking_agency.services = Array.from(document.querySelectorAll('[data-service]:checked'))
        .map(cb => cb.dataset.service);

    return payload;
}

function setNested(obj, path, value) {
    const parts = path.split('.');
    let cur = obj;
    for (let i = 0; i < parts.length - 1; i++) {
        if (cur[parts[i]] == null || typeof cur[parts[i]] !== 'object') cur[parts[i]] = {};
        cur = cur[parts[i]];
    }
    cur[parts[parts.length - 1]] = value;
}

// ============================================================================
// Helpers
// ============================================================================
function escapeHtml(s) {
    return String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}
function escapeAttr(s) { return escapeHtml(s); }

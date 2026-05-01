/**
 * Artist Account — Profile Editor
 *
 * Strategy:
 *   1. Fetch GET /artist/profile + GET /artist/profile/taxonomies in parallel.
 *   2. If unlinked, show the amber notice and bail.
 *   3. Otherwise: hydrate the form from the artist record; render tab nav,
 *      image previews, repeaters, multi-select pills, rich-text bio editors.
 *   4. Image uploads are out-of-band: POST to /profile/image, returned path
 *      is staged in State.pendingImages and only persisted on Save.
 *   5. Save: collect every [data-field] (with dotted nesting for
 *      booking_agency.* and bio_html.*), repeater rows, multi-select ids,
 *      and PUT the whole payload.
 */

const State = {
    artist: null,
    taxonomies: { artist_types: [], artist_genres: [] },
    selectedTypeIds: new Set(),
    selectedGenreIds: new Set(),
    pendingImages: {}, // { main_image_url: 'storage/path', logo_url: ..., portrait_url: ... }
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

        const editor = document.getElementById('detalii-editor');
        if (editor) editor.classList.remove('hidden');

        // Public profile link (top-right of header)
        if (State.artist.slug) {
            const link = document.getElementById('public-profile-link');
            if (link) {
                link.href = '/artist/' + encodeURIComponent(State.artist.slug);
                link.classList.remove('hidden');
            }
        }

        wireTabNav();
        hydrateForm();
        wireRepeaters();
        wireImageUploaders();
        wireRichEditors();
        wireDirtyTracking();
        wireSave();
    });
});

// ============================================================================
// Tab nav
// ============================================================================
function wireTabNav() {
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => switchTab(btn.dataset.tabTarget));
    });
}

function switchTab(key) {
    document.querySelectorAll('.tab-btn').forEach(btn => {
        const active = btn.dataset.tabTarget === key;
        btn.classList.toggle('bg-primary', active);
        btn.classList.toggle('text-white', active);
        btn.classList.toggle('text-muted', !active);
        btn.classList.toggle('hover:bg-surface', !active);
    });
    document.querySelectorAll('.tab-section').forEach(sec => {
        sec.classList.toggle('hidden', sec.dataset.tab !== key);
    });
    // Scroll to top of editor on tab switch (long forms otherwise stay deep down)
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ============================================================================
// Hydration
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

    // Hero name + slug
    setText('hero-name', a.name || '—');
    setText('hero-slug', a.slug || '—');

    // Image previews (cover / logo / portrait)
    syncImagePreview('main_image_url', a.main_image_full_url, 'cover-preview');
    syncImagePreview('logo_url', a.logo_full_url, 'logo-preview', 'logo-placeholder');
    syncImagePreview('portrait_url', a.portrait_full_url, 'portrait-preview', 'portrait-placeholder');

    // Repeaters
    renderRepeater('achievements', a.achievements || [], achievementRow);
    renderRepeater('discography', a.discography || [], discographyRow);
    renderRepeater('youtube_videos', a.youtube_videos || [], youtubeRow);

    // Multi-selects
    renderMultiSelect('artist_types', State.taxonomies.artist_types, State.selectedTypeIds);
    renderMultiSelect('artist_genres', State.taxonomies.artist_genres, State.selectedGenreIds);

    // Booking agency services
    const services = (a.booking_agency && a.booking_agency.services) || [];
    document.querySelectorAll('[data-service]').forEach(cb => {
        cb.checked = services.includes(cb.dataset.service);
    });
}

function getNested(obj, path) {
    return path.split('.').reduce((acc, key) => acc == null ? acc : acc[key], obj);
}

function syncImagePreview(field, url, imgId, placeholderId = null) {
    const img = document.getElementById(imgId);
    const placeholder = placeholderId ? document.getElementById(placeholderId) : null;
    if (!img) return;

    if (url) {
        img.src = url;
        img.classList.remove('hidden');
        placeholder?.classList.add('hidden');
    } else {
        img.classList.add('hidden');
        placeholder?.classList.remove('hidden');
    }
}

// ============================================================================
// Repeaters (achievements / discography / youtube_videos)
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
            // Remove the empty placeholder if present
            const empty = container.querySelector('[data-empty]');
            if (empty) empty.remove();

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
    if (rows.length === 0) {
        container.innerHTML = '<p data-empty class="rounded-lg border border-dashed border-border py-6 text-center text-sm text-muted">Niciun element încă.</p>';
        return;
    }
    container.innerHTML = rows.map(rowFn).join('');
    container.querySelectorAll('[data-row]').forEach(wireRepeaterRow);
}

function wireRepeaterRow(row) {
    row.querySelectorAll('[data-remove]').forEach(btn => {
        btn.addEventListener('click', () => {
            row.remove();
            markDirty();
        });
    });
}

function achievementRow(data) {
    return ''
        + '<div data-row class="flex gap-2 rounded-lg border border-border p-3">'
        + '<input type="number" data-row-field="year" min="1900" max="2100" placeholder="Anul" value="' + escapeAttr(data.year || '') + '" class="form-input w-24">'
        + '<input type="text" data-row-field="title" maxlength="14" placeholder="Titlu (max 14)" value="' + escapeAttr(data.title || '') + '" class="form-input flex-1">'
        + '<input type="text" data-row-field="subtitle" maxlength="24" placeholder="Subtitlu" value="' + escapeAttr(data.subtitle || '') + '" class="form-input flex-1">'
        + '<button type="button" data-remove class="rounded-lg p-2 text-muted hover:bg-error/5 hover:text-error">'
        + '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>'
        + '</button>'
        + '</div>';
}

function discographyRow(data) {
    const types = ['album','ep','single','live','live_dvd','compilation','soundtrack','remix'];
    return ''
        + '<div data-row class="grid gap-2 rounded-lg border border-border p-3 md:grid-cols-[1fr_140px_100px_1fr_40px]">'
        + '<input type="text" data-row-field="name" maxlength="255" placeholder="Nume" value="' + escapeAttr(data.name || '') + '" class="form-input">'
        + '<select data-row-field="type" class="form-input">'
        + types.map(t => '<option value="' + t + '"' + (data.type === t ? ' selected' : '') + '>' + t + '</option>').join('')
        + '</select>'
        + '<input type="number" data-row-field="year" min="1900" max="2100" placeholder="An" value="' + escapeAttr(data.year || '') + '" class="form-input">'
        + '<input type="text" data-row-field="image" placeholder="URL cover" value="' + escapeAttr(data.image || '') + '" class="form-input">'
        + '<button type="button" data-remove class="rounded-lg p-2 text-muted hover:bg-error/5 hover:text-error">'
        + '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>'
        + '</button>'
        + '</div>';
}

function youtubeRow(data) {
    return ''
        + '<div data-row class="flex gap-2 rounded-lg border border-border p-3">'
        + '<div class="relative flex-1">'
        + '<svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-error" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>'
        + '<input type="url" data-row-field="url" placeholder="https://www.youtube.com/watch?v=…" value="' + escapeAttr(data.url || '') + '" class="form-input pl-10">'
        + '</div>'
        + '<button type="button" data-remove class="rounded-lg p-2 text-muted hover:bg-error/5 hover:text-error">'
        + '<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>'
        + '</button>'
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
// Multi-selects (artist types + genres)
// ============================================================================
function renderMultiSelect(key, options, selectedSet) {
    const container = document.querySelector('[data-multi="' + key + '"]');
    if (!container) return;
    if (!options || options.length === 0) {
        container.innerHTML = '<p class="px-2 py-1 text-xs text-muted">Nicio opțiune disponibilă.</p>';
        return;
    }
    container.innerHTML = options.map(opt => {
        const selected = selectedSet.has(opt.id);
        return ''
            + '<button type="button" data-multi-id="' + opt.id + '"'
            + ' class="rounded-full border px-3 py-1.5 text-xs font-medium transition-colors '
            + (selected ? 'border-primary bg-primary text-white' : 'border-border bg-white text-muted hover:border-primary/40 hover:text-secondary') + '">'
            + escapeHtml(opt.name) + '</button>';
    }).join('');

    container.querySelectorAll('button[data-multi-id]').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = parseInt(btn.dataset.multiId, 10);
            if (selectedSet.has(id)) {
                selectedSet.delete(id);
                btn.classList.remove('border-primary', 'bg-primary', 'text-white');
                btn.classList.add('border-border', 'bg-white', 'text-muted', 'hover:border-primary/40', 'hover:text-secondary');
            } else {
                selectedSet.add(id);
                btn.classList.add('border-primary', 'bg-primary', 'text-white');
                btn.classList.remove('border-border', 'bg-white', 'text-muted', 'hover:border-primary/40', 'hover:text-secondary');
            }
            markDirty();
        });
    });
}

// ============================================================================
// Image uploaders (cover / logo / portrait)
// ============================================================================
function wireImageUploaders() {
    document.querySelectorAll('[data-cover-upload]').forEach(btn => {
        btn.addEventListener('click', () => document.querySelector('[data-cover-input]')?.click());
    });
    document.querySelectorAll('[data-logo-upload]').forEach(btn => {
        btn.addEventListener('click', () => document.querySelector('[data-logo-input]')?.click());
    });
    document.querySelectorAll('[data-portrait-upload]').forEach(btn => {
        btn.addEventListener('click', () => document.querySelector('[data-portrait-input]')?.click());
    });

    document.querySelectorAll('input[type="file"][data-image-field]').forEach(input => {
        input.addEventListener('change', (e) => handleUpload(e, input));
    });
}

async function handleUpload(e, input) {
    const file = e.target.files[0];
    if (!file) return;

    const field = input.dataset.imageField;
    const type = input.dataset.imageType;
    const previewIds = {
        main_image_url: { img: 'cover-preview', placeholder: null },
        logo_url: { img: 'logo-preview', placeholder: 'logo-placeholder' },
        portrait_url: { img: 'portrait-preview', placeholder: 'portrait-placeholder' },
    }[field];
    if (!previewIds) return;

    const previewImg = document.getElementById(previewIds.img);
    const placeholder = previewIds.placeholder ? document.getElementById(previewIds.placeholder) : null;

    // Show local preview while uploading
    const localUrl = URL.createObjectURL(file);
    if (previewImg) {
        previewImg.src = localUrl;
        previewImg.classList.remove('hidden');
        previewImg.style.opacity = '0.5';
    }
    placeholder?.classList.add('hidden');

    try {
        const res = await AmbiletAPI.artist.uploadProfileImage(file, type);
        if (res.success && res.data) {
            State.pendingImages[field] = res.data.path;
            if (previewImg) {
                previewImg.src = res.data.url;
                previewImg.style.opacity = '1';
            }
            markDirty();
        } else {
            AmbiletNotifications.error(res.message || 'Upload eșuat.');
            // Revert preview
            if (previewImg) previewImg.style.opacity = '1';
        }
    } catch (err) {
        AmbiletNotifications.error(err.message || 'Upload eșuat.');
        if (previewImg) previewImg.style.opacity = '1';
    }
    input.value = ''; // allow re-selecting same file
}

// ============================================================================
// Rich-text bio editors (RO + EN)
// ============================================================================
function wireRichEditors() {
    document.querySelectorAll('textarea[data-rich-editor]').forEach(textarea => {
        if (textarea._editorAttached) {
            if (textarea._editor) textarea._editor.innerHTML = textarea.value || '';
            return;
        }
        attachRichEditor(textarea);
    });
}

function attachRichEditor(textarea) {
    const wrap = document.createElement('div');
    wrap.className = 'overflow-hidden rounded-lg border border-border bg-white';

    const toolbar = document.createElement('div');
    toolbar.className = 'flex flex-wrap items-center gap-1 border-b border-border bg-surface px-2 py-1';

    const editor = document.createElement('div');
    editor.contentEditable = 'true';
    editor.className = 'prose prose-sm max-h-[500px] min-h-[200px] max-w-none overflow-y-auto bg-white p-3 focus:outline-none';
    editor.innerHTML = textarea.value || '';

    const sync = () => {
        textarea.value = editor.innerHTML;
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    };

    const buttons = [
        { cmd: 'bold', label: 'B', title: 'Bold (Ctrl+B)', style: 'font-weight:700' },
        { cmd: 'italic', label: 'I', title: 'Italic (Ctrl+I)', style: 'font-style:italic' },
        { cmd: 'underline', label: 'U', title: 'Underline', style: 'text-decoration:underline' },
        { sep: true },
        { cmd: 'formatBlock', arg: '<h3>', label: 'H', title: 'Subtitlu', style: 'font-weight:700' },
        { cmd: 'formatBlock', arg: '<p>', label: 'P', title: 'Paragraf' },
        { sep: true },
        { cmd: 'insertUnorderedList', label: '•', title: 'Listă bullets' },
        { cmd: 'insertOrderedList', label: '1.', title: 'Listă numerotată' },
        { sep: true },
        { cmd: 'createLink', label: '🔗', title: 'Inserează link', prompt: 'URL:' },
        { cmd: 'unlink', label: '⛔', title: 'Șterge link' },
        { sep: true },
        { cmd: 'removeFormat', label: '⌫', title: 'Curăță formatare' },
    ];

    buttons.forEach(btn => {
        if (btn.sep) {
            const sep = document.createElement('span');
            sep.className = 'mx-1 w-px self-stretch bg-border';
            toolbar.appendChild(sep);
            return;
        }
        const b = document.createElement('button');
        b.type = 'button';
        b.title = btn.title;
        b.className = 'min-w-[28px] rounded px-2 py-1 text-sm text-secondary hover:bg-primary/10';
        if (btn.style) b.setAttribute('style', btn.style);
        b.textContent = btn.label;
        b.addEventListener('mousedown', e => e.preventDefault());
        b.addEventListener('click', () => {
            let arg = btn.arg;
            if (btn.prompt) {
                arg = window.prompt(btn.prompt, 'https://');
                if (!arg) return;
            }
            editor.focus();
            try { document.execCommand(btn.cmd, false, arg); } catch (e) { /* old browsers */ }
            sync();
        });
        toolbar.appendChild(b);
    });

    editor.addEventListener('input', sync);
    editor.addEventListener('blur', sync);
    editor.addEventListener('paste', e => {
        e.preventDefault();
        const text = (e.clipboardData || window.clipboardData).getData('text/plain');
        document.execCommand('insertText', false, text);
    });

    wrap.appendChild(toolbar);
    wrap.appendChild(editor);

    textarea.style.display = 'none';
    textarea.parentNode.insertBefore(wrap, textarea);

    textarea._editorAttached = true;
    textarea._editor = editor;
}

// ============================================================================
// Dirty tracking + Save
// ============================================================================
function wireDirtyTracking() {
    const form = document.getElementById('detalii-form');
    form?.addEventListener('input', () => markDirty());
    form?.addEventListener('change', () => markDirty());

    document.getElementById('cancel-btn')?.addEventListener('click', () => {
        if (!State.dirty || confirm('Ești sigur? Modificările nesalvate vor fi pierdute.')) {
            window.location.reload();
        }
    });

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
    document.getElementById('detalii-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('save-btn');
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<svg class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Se salvează…';

        const payload = collectPayload();

        try {
            const res = await AmbiletAPI.artist.updateProfile(payload);
            if (res.success) {
                AmbiletNotifications.success('Modificări salvate.');
                State.artist = res.data.artist;
                State.pendingImages = {};
                clearDirty();
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
            btn.innerHTML = originalHtml;
        }
    });
}

function collectPayload() {
    const payload = { booking_agency: {}, bio_html: {} };

    document.querySelectorAll('[data-field]').forEach(input => {
        const path = input.dataset.field;
        let value = input.type === 'checkbox' ? input.checked : input.value.trim();
        if (input.type === 'number' && value !== '') value = Number(value);
        if (value === '' || value == null) value = null;

        // Pending image overrides
        if (Object.prototype.hasOwnProperty.call(State.pendingImages, path)) {
            value = State.pendingImages[path];
        }
        setNested(payload, path, value);
    });

    payload.achievements = collectRepeater('achievements');
    payload.discography = collectRepeater('discography');
    payload.youtube_videos = collectRepeater('youtube_videos');

    payload.artist_type_ids = Array.from(State.selectedTypeIds);
    payload.artist_genre_ids = Array.from(State.selectedGenreIds);

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
function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
}

function escapeHtml(s) {
    return String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}
function escapeAttr(s) { return escapeHtml(s); }

/**
 * Artist Account — Register page handler
 * If a `?claim=<slug>` was supplied, we hit /artist/check-claim/<slug> on
 * load to show the user whether the profile is free, already pending, or
 * already verified. The form posts to /artist/register and on success
 * redirects to /artist/in-asteptare?email=... so the user knows what to
 * expect next (verify email -> wait for admin approval).
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('artist-register-form');
    const slugInput = document.getElementById('artist_slug');
    const claimStatus = document.getElementById('claim-status');
    const proofContainer = document.getElementById('proof-links');
    const addProofBtn = document.getElementById('add-proof-link');

    // ---- Claim status check (shows a banner if profile is taken) ----
    const claimSlug = slugInput?.value?.trim();
    if (claimSlug && claimStatus) {
        AmbiletAPI.artist.checkClaim(claimSlug)
            .then((res) => {
                if (!res.success || !res.data) return;

                if (res.data.is_verified) {
                    claimStatus.className = 'p-3 mb-5 text-sm text-center rounded-lg bg-red-50 text-red-700 border border-red-200';
                    claimStatus.innerHTML = '<strong>Profil deja revendicat și verificat.</strong><br>Dacă tu ești titularul, contactează echipa la <a href="mailto:contact@ambilet.ro" class="underline">contact@ambilet.ro</a>.';
                    claimStatus.classList.remove('hidden');
                    form.querySelectorAll('input,textarea,button[type="submit"]').forEach(el => el.disabled = true);
                } else if (res.data.is_pending) {
                    claimStatus.className = 'p-3 mb-5 text-sm text-center rounded-lg bg-amber-50 text-amber-700 border border-amber-200';
                    claimStatus.innerHTML = '<strong>O cerere de revendicare este deja în review.</strong><br>Va trebui să aștepți rezultatul acelei cereri înainte de a aplica din nou.';
                    claimStatus.classList.remove('hidden');
                    form.querySelectorAll('input,textarea,button[type="submit"]').forEach(el => el.disabled = true);
                }
                // If neither verified nor pending, do nothing — the form is open.
            })
            .catch(() => { /* non-blocking — ignore network errors */ });
    }

    // ---- Add another proof link input (cap at 5) ----
    if (addProofBtn) {
        addProofBtn.addEventListener('click', () => {
            const existing = proofContainer.querySelectorAll('input').length;
            if (existing >= 5) {
                AmbiletNotifications.error('Maxim 5 linkuri de dovadă');
                return;
            }
            const wrap = document.createElement('div');
            wrap.className = 'flex gap-2';
            wrap.innerHTML = '<input type="url" name="claim_proof[]" class="flex-1 input" placeholder="https://...">'
                + '<button type="button" class="px-3 text-sm rounded-lg text-muted hover:text-red-600" aria-label="Șterge">×</button>';
            wrap.querySelector('button').addEventListener('click', () => wrap.remove());
            proofContainer.appendChild(wrap);
        });
    }

    // ---- Phone field: digits + plus + spaces only ----
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        phoneInput.setAttribute('inputmode', 'tel');
        phoneInput.addEventListener('input', function () {
            this.value = this.value.replace(/[^\d+\s]/g, '');
        });
    }

    // ---- Form submit ----
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

        // Collect proof URLs (skip empties).
        const proofInputs = proofContainer.querySelectorAll('input[name="claim_proof[]"]');
        const claimProof = Array.from(proofInputs)
            .map(input => input.value.trim())
            .filter(v => v.length > 0);

        const formData = {
            first_name: document.getElementById('first_name').value.trim(),
            last_name: document.getElementById('last_name').value.trim(),
            email: document.getElementById('email').value.trim().toLowerCase(),
            phone: document.getElementById('phone').value.replace(/\s/g, '') || null,
            password: password,
            password_confirmation: passwordConfirm,
            artist_slug: slugInput.value.trim() || null,
            claim_message: document.getElementById('claim_message').value.trim() || null,
            claim_proof: claimProof.length > 0 ? claimProof : null
        };

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
});

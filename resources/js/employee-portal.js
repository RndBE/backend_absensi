// Umpan balik kirim-form di portal karyawan: tombol berubah jadi spinner dan form
// tidak bisa terkirim dua kali. Berlaku untuk semua <form>; lewati satu form dengan
// atribut data-no-loading.
//
// Tombol sengaja dinonaktifkan SETELAH satu putaran event loop, bukan langsung:
// tombol yang disabled saat submit tidak ikut mengirim name/value-nya.
const RESET_AFTER_MS = 15000;

function setLoading(button) {
    if (button.dataset.loading === '1') return;
    button.dataset.loading = '1';
    button.dataset.originalHtml = button.innerHTML;
    button.setAttribute('aria-busy', 'true');
    button.classList.add('opacity-70', 'cursor-wait');

    const spinner = document.createElement('span');
    spinner.className = 'inline-block h-4 w-4 shrink-0 rounded-full border-2 border-current border-t-transparent animate-spin';
    spinner.setAttribute('aria-hidden', 'true');

    // Ikon material diganti spinner supaya lebar tombol tidak berubah banyak.
    const icon = button.querySelector('.material-symbols-outlined');
    if (icon) {
        icon.replaceWith(spinner);
    } else {
        button.prepend(spinner);
    }
}

function clearLoading(button) {
    if (button.dataset.loading !== '1') return;
    button.innerHTML = button.dataset.originalHtml;
    button.disabled = false;
    button.removeAttribute('aria-busy');
    button.classList.remove('opacity-70', 'cursor-wait');
    delete button.dataset.loading;
    delete button.dataset.originalHtml;
}

function resetForm(form) {
    delete form.dataset.submitting;
    form.querySelectorAll('[data-loading="1"]').forEach(clearLoading);
}

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.hasAttribute('data-no-loading') || form.target === '_blank') return;

    // Handler lain (confirm(), validasi) sudah membatalkan pengiriman: jangan kunci apa-apa.
    if (event.defaultPrevented) return;

    if (form.dataset.submitting === '1') {
        event.preventDefault();
        return;
    }
    form.dataset.submitting = '1';

    const button = event.submitter || form.querySelector('button[type="submit"], button:not([type])');
    if (button instanceof HTMLButtonElement) {
        setLoading(button);
        setTimeout(() => { button.disabled = true; }, 0);
    }

    // Respons berupa unduhan tidak memuat ulang halaman; tanpa ini tombolnya terkunci selamanya.
    setTimeout(() => resetForm(form), RESET_AFTER_MS);
});

// Tombol "kembali" dari bfcache memulihkan halaman dalam keadaan terkunci.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        document.querySelectorAll('form[data-submitting="1"]').forEach(resetForm);
    }
});

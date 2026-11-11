// Font dibundel lokal lewat Vite (tanpa CDN) agar aplikasi tetap jalan offline.
import '@fontsource/ibm-plex-sans/400.css';
import '@fontsource/ibm-plex-sans/500.css';
import '@fontsource/ibm-plex-sans/600.css';
import '@fontsource/ibm-plex-mono/400.css';
import '@fontsource/ibm-plex-mono/500.css';

// Tombol cetak (ringkasan BLUF). Tanpa atribut onclick agar CSP script-src 'self' tetap berlaku.
document.addEventListener('click', (event) => {
    if (event.target.closest('[data-print]')) {
        window.print();
    }
});

import './graph';

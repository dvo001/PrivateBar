'use strict';
const navToggle = document.querySelector('#nav-toggle');
navToggle?.addEventListener('click', () => {
    const nav = document.querySelector('#mobile-nav');
    nav.hidden = !nav.hidden;
    navToggle.setAttribute('aria-expanded', String(!nav.hidden));
});
document.querySelector('#copy-link')?.addEventListener('click', async () => {
    const input = document.querySelector('#share-link');
    try { await navigator.clipboard.writeText(input.value); document.querySelector('#copy-status').textContent = 'Link kopiert.'; }
    catch { input.select(); document.querySelector('#copy-status').textContent = 'Bitte den markierten Link kopieren.'; }
});
const lines = document.querySelector('#recipe-lines');
let nextLine = lines ? lines.children.length : 0;
document.querySelector('#add-line')?.addEventListener('click', () => {
    if (lines.children.length >= 30) return;
    const clone = lines.firstElementChild.cloneNode(true);
    clone.querySelectorAll('input, select').forEach(field => {
        field.name = field.name.replace(/ingredients\[\d+\]/, `ingredients[${nextLine}]`);
        if (field.tagName === 'INPUT') field.value = '';
        else field.selectedIndex = 0;
    });
    nextLine += 1; lines.append(clone); clone.querySelector('select').focus();
});
lines?.addEventListener('click', event => {
    if (event.target.closest('.remove-line') && lines.children.length > 1) event.target.closest('.recipe-line').remove();
});

// Kamerazugriff nur nach Interaktion. Kein Bild verlässt den Browser.
const scannerButton = document.querySelector('#start-scanner');
let stream, scannerControls, scanTimer;
function stopScanner() {
    clearTimeout(scanTimer); scannerControls?.stop();
    stream?.getTracks().forEach(track => track.stop());
}
window.addEventListener('pagehide', stopScanner);
scannerButton?.addEventListener('click', async () => {
    const status = document.querySelector('#scanner-status');
    const video = document.querySelector('#scanner-video');
    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
        status.textContent = 'Die Kamera benötigt HTTPS und einen unterstützten Browser. Bitte den Barcode eingeben.'; return;
    }
    scannerButton.disabled = true;
    let submitted = false;
    const found = code => {
        if (submitted || !/^[0-9]{8,14}$/.test(code)) return;
        submitted = true; stopScanner();
        document.querySelector('#barcode-input').value = code;
        document.querySelector('#barcode-form').requestSubmit();
    };
    try {
        let nativeSupported = false;
        if ('BarcodeDetector' in window) {
            const formats = await BarcodeDetector.getSupportedFormats();
            nativeSupported = ['ean_13','ean_8','upc_a'].every(format => formats.includes(format));
        }
        video.hidden = false; document.querySelector('#scan-placeholder').hidden = true;
        if (nativeSupported) {
            const detector = new BarcodeDetector({ formats: ['ean_13','ean_8','upc_a'] });
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
            video.srcObject = stream; await video.play();
            const scan = async () => {
                if (submitted) return;
                try { for (const result of await detector.detect(video)) found(result.rawValue); }
                catch { /* Einzelne unscharfe Frames überspringen. */ }
                if (!submitted) scanTimer = setTimeout(scan, 220);
            };
            scan();
        } else {
            await new Promise((resolve, reject) => {
                const script = document.createElement('script'); script.src = '/assets/zxing-browser.min.js';
                script.onload = resolve; script.onerror = reject; document.head.append(script);
            });
            const reader = new ZXingBrowser.BrowserMultiFormatReader();
            scannerControls = await reader.decodeFromVideoDevice(undefined, video, result => { if (result) found(result.getText()); });
        }
        status.textContent = 'Kamera bereit. Barcode in die Bildmitte halten.';
    } catch {
        stopScanner(); scannerButton.disabled = false;
        status.textContent = 'Die Kamera konnte nicht geöffnet werden. Prüfe die Kamerafreigabe oder gib den Barcode ein.';
    }
});

const frame = document.querySelector('#photo-frame');
if (frame) {
    const a = document.querySelector('#frame-a'), b = document.querySelector('#frame-b');
    let lastActivity = Date.now(), previous = null, current = a, next = b, timer, generation = 0, savedFocus;
    const idle = Math.max(60, Number(document.body.dataset.frameIdle || 300)) * 1000;
    const critical = () => document.querySelector('[data-critical]') || document.querySelector('dialog[open]') || document.hidden;
    const load = async run => {
        try {
            const response = await fetch('/fotorahmen/naechstes' + (previous ? '?previous=' + encodeURIComponent(previous) : ''), { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('photo');
            const photo = await response.json();
            if (run !== generation || frame.hidden) return;
            if (!photo.url) { wake(); return; }
            next.src = photo.url;
            await next.decode();
            if (run !== generation || frame.hidden) return;
            previous = photo.id;
            const fade = matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : Math.min(3, Number(photo.fade));
            // CSSOM setzt nur die Dauer; keine dynamische HTML-Einfügung.
            next.style.transitionDuration = `${fade}s`; current.style.transitionDuration = `${fade}s`;
            next.classList.add('visible'); current.classList.remove('visible');
            [current, next] = [next, current];
            timer = setTimeout(() => load(run), Math.max(3, Number(photo.seconds)) * 1000);
        } catch { if (run === generation && !frame.hidden) timer = setTimeout(() => load(run), 10000); }
    };
    function wake() {
        generation += 1; clearTimeout(timer); frame.hidden = true;
        document.querySelector('main').inert = false;
        document.querySelector('.sidebar').inert = false;
        lastActivity = Date.now(); savedFocus?.focus({ preventScroll: true });
    }
    // Die gesamte erste Berührung einschliesslich des folgenden Klicks abfangen.
    let suppressUntil = 0;
    ['pointerdown','pointerup','click','touchstart','touchend','keydown'].forEach(type => {
        document.addEventListener(type, event => {
            if (!frame.hidden || Date.now() < suppressUntil) {
                event.preventDefault(); event.stopImmediatePropagation();
                if (!frame.hidden) { suppressUntil = Date.now() + 700; wake(); }
                return;
            }
            lastActivity = Date.now();
        }, { capture: true, passive: false });
    });
    ['pointermove','wheel','input'].forEach(type => document.addEventListener(type, () => { if (frame.hidden) lastActivity = Date.now(); }, { passive: true }));
    setInterval(() => {
        if (frame.hidden && !critical() && Date.now() - lastActivity >= idle) {
            savedFocus = document.activeElement; frame.hidden = false; frame.focus();
            document.querySelector('main').inert = true; document.querySelector('.sidebar').inert = true;
            load(++generation);
        }
    }, 1000);
    window.addEventListener('pagehide', () => { generation += 1; clearTimeout(timer); });
}

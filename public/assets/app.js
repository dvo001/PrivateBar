'use strict';

// Eigener Ziffernblock: Wayland-Tastaturen beachten inputmode bei Passwortfeldern nicht immer.
if (document.body.dataset.localDisplay === '1') {
    const pinDialog = document.querySelector('#local-pin-confirm');
    const confirmationForms = pinDialog && typeof pinDialog.showModal === 'function'
        ? [...document.querySelectorAll('form[data-pin-confirm]')] : [];
    for (const form of confirmationForms) {
        const pin = form.querySelector('input[name="pin"]');
        pin.closest('label').hidden = true;
        pin.type = 'hidden';
        pin.value = '';
    }
    const pinFields = [...document.querySelectorAll('input[type="password"][inputmode="numeric"]')]
        .filter(field => ['pin', 'new_pin'].includes(field.name));
    const pads = new Map();
    for (const field of pinFields) {
        const pad = document.createElement('div');
        pad.className = 'pin-keypad';
        pad.setAttribute('role', 'group');
        pad.setAttribute('aria-label', 'Ziffernblock für Kiosk-PIN');
        pad.hidden = true;
        // Schreibschutz verhindert die zusätzliche Betriebssystemtastatur.
        field.readOnly = true;
        field.dataset.pinKeypadField = '1';
        const edit = key => {
            if (key === 'clear') field.value = '';
            else if (key === 'erase') field.value = field.value.slice(0, -1);
            else if (field.value.length < 6) field.value += key;
            field.dispatchEvent(new Event('input', { bubbles: true }));
        };
        for (const key of ['1','2','3','4','5','6','7','8','9','clear','0','erase']) {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = key === 'clear' ? 'Leeren' : key === 'erase' ? '⌫' : key;
            if (key === 'erase') button.setAttribute('aria-label', 'Letzte Ziffer löschen');
            button.addEventListener('click', () => edit(key));
            pad.append(button);
        }
        field.closest('label').after(pad);
        pads.set(field, pad);
        field.addEventListener('keydown', event => {
            if (event.ctrlKey || event.metaKey || event.altKey) return;
            if (/^[0-9]$/.test(event.key)) { event.preventDefault(); edit(event.key); }
            else if (event.key === 'Backspace') { event.preventDefault(); edit('erase'); }
            else if (event.key === 'Delete') { event.preventDefault(); edit('clear'); }
        });
    }
    document.addEventListener('focusin', event => {
        if (!pads.has(event.target)) return;
        for (const [field, pad] of pads) pad.hidden = event.target !== field;
    });
    for (const [field, pad] of pads) if (document.activeElement === field) pad.hidden = false;
    for (const form of new Set(pinFields.map(field => field.form))) {
        if (!form) continue;
        form.addEventListener('submit', event => {
            const fields = pinFields.filter(field => field.form === form);
            // Readonly-Felder sind von HTML-Validierung ausgenommen: vor dem Senden prüfen.
            fields.forEach(field => { field.readOnly = false; });
            if (!form.checkValidity()) {
                event.preventDefault();
                form.reportValidity();
            }
            fields.forEach(field => { field.readOnly = true; });
        });
    }
    if (confirmationForms.length) {
        const dialogForm = pinDialog.querySelector('form');
        const dialogPin = dialogForm.querySelector('input[name="pin"]');
        let pending = null, approved = null;
        for (const form of confirmationForms) {
            form.addEventListener('submit', event => {
                if (event.defaultPrevented) return;
                if (approved === form) { approved = null; return; }
                event.preventDefault();
                pending = { form, submitter: event.submitter };
                dialogPin.value = '';
                pinDialog.querySelector('#local-pin-action').textContent = event.submitter?.textContent.trim() || 'Aktion ausführen';
                pinDialog.showModal();
                dialogPin.focus();
            });
        }
        dialogForm.addEventListener('submit', event => {
            if (event.defaultPrevented) return;
            event.preventDefault();
            if (!pending || !/^[0-9]{6}$/.test(dialogPin.value)) return;
            const { form, submitter } = pending;
            const targetPin = form.querySelector('input[name="pin"]');
            targetPin.value = dialogPin.value;
            pending = null;
            approved = form;
            pinDialog.close();
            dialogPin.value = '';
            try { form.requestSubmit(submitter || undefined); }
            finally { targetPin.value = ''; approved = null; }
        });
        pinDialog.querySelector('#local-pin-cancel').addEventListener('click', () => pinDialog.close());
        pinDialog.addEventListener('close', () => { pending = null; dialogPin.value = ''; });
    }
}
document.querySelectorAll('[data-ingredient-picker]').forEach(picker => {
    const select = picker.querySelector('[data-ingredient-select]');
    const category = picker.querySelector('[data-ingredient-category]');
    const search = picker.querySelector('[data-ingredient-search]');
    const groups = [...select.querySelectorAll('optgroup')].map(group => group.cloneNode(true));
    picker.querySelector('[data-ingredient-filters]').hidden = false;
    const normalize = value => value.toLocaleLowerCase('de-CH').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    const filter = () => {
        const selected = select.value;
        select.replaceChildren(new Option('Zuordnung wählen', ''));
        let count = 0;
        for (const original of groups) {
            if (category.value && category.value !== original.label) continue;
            const group = original.cloneNode(true);
            [...group.children].forEach(option => {
                if (!normalize(option.textContent).includes(normalize(search.value.trim()))) option.remove();
                else { option.selected = option.value === selected; count++; }
            });
            if (group.children.length) select.append(group);
        }
        // Ein ausgefilterter Vorschlag darf nicht unbemerkt durch die erste Zutat ersetzt werden.
        if (![...select.options].some(option => option.value === selected)) select.value = '';
        picker.querySelector('[data-ingredient-results]').textContent = count ? `${count} Zutaten zur Auswahl.` : 'Keine passende Zutat. Suche ändern oder eine neue Zutat ergänzen.';
    };
    category.addEventListener('change', filter);
    search.addEventListener('input', filter);
});
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
    const clock = document.querySelector('#off-clock');
    // Ruheanzeige nur am Pi selbst, nicht in Browsern im Heimnetz.
    const localDisplay = document.body.dataset.localDisplay === '1';
    let monitor = null, wakeUntil = 0;
    try { wakeUntil = Number(sessionStorage.getItem('monitor-wake-until')) || 0; } catch { /* Storage optional. */ }
    const activity = () => {
        lastActivity = Date.now();
        if (monitor) {
            wakeUntil = Date.now() + monitor.minutes * 60000;
            try { sessionStorage.setItem('monitor-wake-until', String(wakeUntil)); } catch { /* Storage optional. */ }
        }
    };
    const zurich = new Intl.DateTimeFormat('de-CH', { timeZone: 'Europe/Zurich', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' });
    const resting = () => {
        if (!monitor?.enabled) return false;
        const parts = zurich.formatToParts(new Date());
        const now = parts.find(part => part.type === 'hour').value + ':' + parts.find(part => part.type === 'minute').value;
        return monitor.off < monitor.on ? now >= monitor.off && now < monitor.on : now >= monitor.off || now < monitor.on;
    };
    const updateClock = () => {
        const parts = zurich.formatToParts(new Date());
        const hour = Number(parts.find(part => part.type === 'hour').value);
        const minute = Number(parts.find(part => part.type === 'minute').value);
        const digital = document.querySelector('#clock-digital');
        digital.textContent = zurich.format(new Date());
        digital.hidden = monitor.style === 'analog';
        document.querySelector('#clock-analog').hidden = monitor.style !== 'analog';
        document.querySelector('#clock-hour').setAttribute('transform', `rotate(${hour % 12 * 30 + minute / 2} 100 100)`);
        document.querySelector('#clock-minute').setAttribute('transform', `rotate(${minute * 6} 100 100)`);
        clock.style.color = monitor.color;
        clock.style.opacity = String(monitor.brightness / 100);
        frame.setAttribute('aria-label', `${digital.textContent} Uhr. Zum Öffnen der Bar berühren.`);
    };
    const refreshMonitor = async () => {
        try {
            const response = await fetch('/monitor/anzeige', { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' });
            if (response.ok) monitor = await response.json();
        } catch { /* Vorhandene lokale Einstellungen bei Verbindungsfehler behalten. */ }
    };
    if (localDisplay) { refreshMonitor(); setInterval(refreshMonitor, 30000); }
    let lastActivity = Date.now(), previous = null, current = a, next = b, timer, generation = 0, savedFocus;
    const idle = Math.max(60, Number(document.body.dataset.frameIdle || 300)) * 1000;
    const critical = () => document.querySelector('[data-critical]') || document.querySelector('dialog[open]') || document.hidden;
    const load = async run => {
        try {
            const response = await fetch('/fotorahmen/naechstes' + (previous ? '?previous=' + encodeURIComponent(previous) : ''), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error('photo');
            const photo = await response.json();
            if (run !== generation || frame.hidden || !clock.hidden) return;
            if (!photo.url) { wake(); return; }
            next.src = photo.url;
            await next.decode();
            if (run !== generation || frame.hidden || !clock.hidden) return;
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
        clock.hidden = true; a.hidden = false; b.hidden = false;
        frame.setAttribute('aria-label', 'Fotorahmen. Zum Zurückkehren berühren.');
        activity(); savedFocus?.focus({ preventScroll: true });
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
            activity();
        }, { capture: true, passive: false });
    });
    ['pointermove','wheel','input'].forEach(type => document.addEventListener(type, () => { if (frame.hidden) activity(); }, { passive: true }));
    setInterval(() => {
        const showClock = resting() && Date.now() >= wakeUntil;
        if (showClock && !document.hidden) {
            if (frame.hidden || clock.hidden) {
                savedFocus = frame.hidden ? document.activeElement : savedFocus;
                generation += 1; clearTimeout(timer);
                a.hidden = true; b.hidden = true; clock.hidden = false; frame.hidden = false;
                frame.focus();
                document.querySelector('main').inert = true; document.querySelector('.sidebar').inert = true;
            }
            updateClock();
            return;
        }
        if (!clock.hidden) wake();
        // Während der Weckzeit ist auch der Fotorahmen pausiert.
        if (resting()) return;
        if (frame.hidden && !critical() && Date.now() - lastActivity >= idle) {
            savedFocus = document.activeElement; frame.hidden = false; frame.focus();
            document.querySelector('main').inert = true; document.querySelector('.sidebar').inert = true;
            load(++generation);
        }
    }, 1000);
    window.addEventListener('pagehide', () => { generation += 1; clearTimeout(timer); });
}

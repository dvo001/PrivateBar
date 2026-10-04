const { chromium } = require('playwright');
const { AxeBuilder } = require('@axe-core/playwright');
const assert = require('node:assert/strict');
const url = process.env.PRIVATEBAR_TEST_URL;
const pin = process.env.PRIVATEBAR_TEST_PIN;
if (!url || !pin) throw new Error('PRIVATEBAR_TEST_URL und PRIVATEBAR_TEST_PIN für eine isolierte Pi-Testinstanz setzen.');
(async () => {
    const browser = await chromium.launch({headless: true});
    try {
        for (const width of [1920, 390, 320]) {
            const context = await browser.newContext({viewport: {width, height: 1200}});
            const page = await context.newPage();
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.goto(url + '/anmelden');
            await page.locator('[name="pin"]').fill(pin);
            await page.getByRole('button', {name: 'Bar öffnen'}).click();
            await page.waitForURL(url + '/');
            await page.goto(url + '/einstellungen');
            await page.locator('[name="product_lookup_enabled"][type="checkbox"]').check();
            await page.getByRole('button', {name: 'Externe Dienste speichern'}).click();
            await page.getByText('Externe Dienste gespeichert.', {exact: true}).waitFor();
            await page.reload();
            assert.equal(await page.locator('[name="product_lookup_enabled"][type="checkbox"]').isChecked(), true);
            await page.locator('[name="product_lookup_enabled"][type="checkbox"]').uncheck();
            await page.getByRole('button', {name: 'Externe Dienste speichern'}).click();
            await page.getByText('Externe Dienste gespeichert.', {exact: true}).waitFor();
            await page.reload();
            assert.equal(await page.locator('[name="product_lookup_enabled"][type="checkbox"]').isChecked(), false);
            await page.goto(url + '/einstellungen/lokal');
            await page.locator('[name="pin"]').fill(pin);
            await page.getByRole('button', {name: 'Einstellungen öffnen'}).click();
            await page.locator('[name="cloud_url"]').waitFor();
            assert.equal(await page.locator('[name="device_token"]').inputValue(), '');
            assert.equal(await page.locator('[name="device_token"]').getAttribute('type'), 'password');
            assert.equal(await page.locator('[name="monitor_clock_style"] option').count(), 2);
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
            const audit = await new AxeBuilder({page}).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
            assert.deepEqual(audit.violations.map(v => v.id), []);
            assert.deepEqual(errors, []);
            console.log(`Einstellungen: ${width}px, Speichern/Neuladen, Geheimnisfeld, Uhrfelder und axe erfolgreich.`);
            await context.close();
        }
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });

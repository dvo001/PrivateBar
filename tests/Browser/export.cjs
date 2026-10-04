const { chromium } = require('playwright');
const { AxeBuilder } = require('@axe-core/playwright');
const assert = require('node:assert/strict');
const url = process.env.PRIVATEBAR_TEST_URL;
const email = process.env.PRIVATEBAR_TEST_EMAIL;
const password = process.env.PRIVATEBAR_TEST_PASSWORD;
if (!url || !email || !password) throw new Error('URL, E-Mail und Passwort einer isolierten Cloud-Testinstanz setzen.');
(async () => {
    const browser = await chromium.launch({headless: true});
    try {
        for (const width of [1920, 390, 320]) {
            const context = await browser.newContext({viewport: {width, height: 1200}});
            const page = await context.newPage();
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.goto(url + '/anmelden');
            await page.locator('[name="email"]').fill(email);
            await page.locator('[name="password"]').fill(password);
            await page.getByRole('button', {name: 'Bar öffnen'}).click();
            await page.waitForURL(url + '/');
            await page.goto(url + '/einstellungen');
            const form = page.locator('form[action="/einstellungen/datenbank/export"]');
            await form.locator('[name="password"]').fill('incorrect-test-password');
            await form.getByRole('button', {name: 'Datenbankexport herunterladen'}).click();
            await page.getByText('Das Passwort ist nicht korrekt.', {exact: true}).waitFor();
            assert.equal(await form.locator('[name="password"]').inputValue(), '');
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
            const audit = await new AxeBuilder({page}).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
            assert.deepEqual(audit.violations.map(v => v.id), []);
            assert.deepEqual(errors, []);
            console.log(`Cyon-Export: ${width}px, Passwortprüfung, leeres Passwortfeld, Überlauf und axe erfolgreich.`);
            await context.close();
        }
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });

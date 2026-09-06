const { chromium } = require('playwright');
const { AxeBuilder } = require('@axe-core/playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const url = process.env.PRIVATEBAR_TEST_URL;
const pin = process.env.PRIVATEBAR_TEST_PIN;
if (!url || !pin) throw new Error('Isolierte Testinstanz und Test-PIN angeben.');

(async () => {
    const browser = await chromium.launch();
    const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    try {
        await page.goto(url + '/anmelden');
        await page.locator('[name=pin]').fill(pin);
        await page.getByRole('button', { name: 'Bar öffnen' }).click();
        await page.waitForURL(url + '/');
        await page.goto(url + '/meine-bar/neu');
        await page.locator('[name=name]').fill('Zutatenprüfung Flasche');
        await page.locator('[name=barcode]').fill('8001110016303');
        await page.locator('[data-ingredient-category]').selectOption({ label: 'Liköre' });
        await page.locator('[name=ingredient_id]').selectOption({ label: 'Amaretto' });
        await page.locator('[data-ingredient-search]').fill('zzzz');
        assert.equal(await page.locator('[name=ingredient_id]').inputValue(), '');
        await page.locator('[data-ingredient-search]').fill('');
        await page.locator('[name=ingredient_id]').selectOption({ label: 'Liköre – noch nicht zugeordnet' });
        await page.locator('[name=confirmed]').check();
        await page.getByRole('button', { name: 'Flasche bestätigen' }).click();
        await page.waitForURL(url + '/meine-bar');
        const bottle = page.locator('article').filter({ has: page.getByRole('heading', { name: 'Zutatenprüfung Flasche' }) });
        await bottle.getByRole('link', { name: 'Flasche bearbeiten' }).click();
        await page.locator('[data-ingredient-search]').fill('Amaretto');
        await page.locator('[name=ingredient_id]').selectOption({ label: 'Amaretto' });
        await page.locator('[name=abv]').fill('28');
        await page.locator('[name=confirmed]').check();
        await page.getByRole('button', { name: 'Flasche bestätigen' }).click();
        await page.waitForURL(url + '/meine-bar');
        assert.equal(await bottle.count(), 1);
        assert.match(await bottle.textContent(), /Amaretto/);

        await page.goto(url + '/einstellungen/zutaten');
        await page.getByText('Neue Cocktailzutat ergänzen', { exact: true }).click();
        const create = page.locator('form[action="/einstellungen/zutaten"]');
        await create.locator('[name=name]').fill('Browser-Haussirup');
        await create.locator('[name=category_id]').selectOption('syrup');
        await create.locator('[name=synonyms]').fill('browser syrup, remove me');
        await create.getByRole('button').click();
        await page.waitForURL('**/einstellungen/zutaten?q=*');
        const detail = page.locator('details').filter({ has: page.locator('summary', { hasText: 'Browser-Haussirup' }) });
        await detail.locator('summary').click();
        assert.match(await detail.locator('[name=synonyms]').inputValue(), /remove me/);
        await detail.locator('[name=name]').fill('Browser-Blütensirup');
        await detail.locator('[name=synonyms]').fill('browser syrup');
        await detail.getByRole('button', { name: 'Speichern', exact: true }).click();
        await page.waitForLoadState();
        await page.goto(url + '/einstellungen/zutaten?q=Browser-Bl%C3%BCtensirup');
        const edited = page.locator('details').filter({ has: page.locator('summary', { hasText: 'Browser-Blütensirup' }) });
        await edited.locator('summary').click();
        assert.doesNotMatch(await edited.locator('[name=synonyms]').inputValue(), /remove me/);
        assert.match(await edited.locator('[name=synonyms]').inputValue(), /browser-haussirup/);

        fs.mkdirSync('artifacts/browser', { recursive: true });
        const results = [];
        for (const width of [1920, 390, 320]) {
            await page.setViewportSize({ width, height: width === 1920 ? 1200 : 844 });
            for (const path of ['/meine-bar/neu', '/einstellungen/zutaten']) {
                await page.goto(url + path);
                const audit = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
                const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
                results.push({ width, path, overflow, violations: audit.violations.map(v => v.id) });
                assert.equal(overflow, false);
                assert.equal(audit.violations.length, 0, JSON.stringify(audit.violations));
                await page.screenshot({ path: `artifacts/browser/ingredients-${width}-${path.includes('einstellungen') ? 'settings' : 'bottle'}.png`, fullPage: true });
            }
        }
        const nojs = await browser.newContext({ javaScriptEnabled: false, storageState: await context.storageState() });
        const fallback = await nojs.newPage();
        await fallback.goto(url + '/meine-bar/neu');
        assert(await fallback.locator('[name=ingredient_id] option').count() > 100);
        assert.equal(await fallback.locator('[data-ingredient-filters]').isVisible(), false);
        assert.deepEqual(errors, []);
        fs.writeFileSync('artifacts/browser/ingredients.json', JSON.stringify({ results, errors, noJs: 'passed', editFlow: 'passed' }, null, 2));
        console.log(JSON.stringify(results));
        await nojs.close();
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });

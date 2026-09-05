const { chromium }=require('playwright');
const { AxeBuilder }=require('@axe-core/playwright');
const assert=require('node:assert/strict');
const url=process.env.PRIVATEBAR_TEST_URL; const pin=process.env.PRIVATEBAR_TEST_PIN;
if (!url || !pin) throw new Error('Testinstanz und Test-PIN setzen. Dieser Test erzeugt eigene Testdaten.');
(async()=>{
 const browser=await chromium.launch(); const context=await browser.newContext({viewport:{width:390,height:844}});const page=await context.newPage();
 try {
 await page.goto(url + '/anmelden');await page.locator('[name=pin]').fill(pin);await page.getByRole('button',{name:'Bar öffnen'}).click();
 await page.goto(url + '/meine-bar/neu');await page.locator('[name=name]').fill('Prüfung Gin');await page.locator('[name=ingredient_id]').selectOption({label:'Gin'});await page.locator('[name=abv]').fill('40');await page.locator('[name=confirmed]').check();await page.getByRole('button',{name:'Flasche bestätigen'}).click();await page.waitForURL('**/meine-bar');assert(await page.getByRole('heading',{name:'Prüfung Gin'}).isVisible());
 await page.goto(url + '/entdecken?q=Gin%20Tonic');await page.locator('.recipe-card a').first().click();assert(await page.getByRole('heading',{name:'Gin Tonic',exact:true}).isVisible());
 await page.getByRole('button',{name:'Alle fehlenden Zutaten einkaufen'}).click();await page.goto(url + '/einkaufsliste');await page.getByRole('button',{name:'✓ Gekauft'}).click();await page.goto(url + '/machbar');assert(await page.getByRole('heading',{name:'Gin Tonic',exact:true}).isVisible());
 await page.locator('.recipe-card a').first().click();await page.getByRole('button',{name:'♡ Als Favorit merken'}).click();await page.goto(url + '/favoriten');assert(await page.getByRole('heading',{name:'Gin Tonic',exact:true}).isVisible());
 await page.goto(url + '/rezepte/neu');await page.locator('[name=name]').fill('Prüfung Hausrezept');await page.locator('[name="ingredients[0][ingredient_id]"]').selectOption({label:'Gin'});await page.locator('[name="ingredients[0][amount]"]').fill('4');await page.getByRole('button',{name:'+ Weitere Zutat'}).click();await page.locator('[name="ingredients[1][ingredient_id]"]').selectOption({label:'Tonic Water'});await page.locator('[name="ingredients[1][amount]"]').fill('12');await page.locator('[name=instructions]').fill('Mit Eis ins Glas geben und vorsichtig umrühren.');await page.getByRole('button',{name:'Rezept speichern'}).click();assert(await page.getByRole('heading',{name:'Prüfung Hausrezept',exact:true}).isVisible());
 const a11y=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();assert.deepEqual(a11y.violations.map(v=>v.id),[]);
 console.log('Browserabläufe bestanden: Produkt → Rezept → Einkauf → Bestand → machbar → Favorit → eigenes Rezept.');
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1});

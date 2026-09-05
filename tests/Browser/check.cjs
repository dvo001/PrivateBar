const { chromium } = require('playwright');
const { AxeBuilder } = require('@axe-core/playwright');
const fs = require('fs');
const path = require('path');
const url = process.env.PRIVATEBAR_TEST_URL;
const pin = process.env.PRIVATEBAR_TEST_PIN;
if (!url || !pin) throw new Error('PRIVATEBAR_TEST_URL und PRIVATEBAR_TEST_PIN für eine Testinstanz setzen.');
const out = path.join(__dirname, '../../artifacts/browser');
fs.mkdirSync(out, {recursive:true});
(async () => {
 const browser = await chromium.launch({headless:true});
 const results=[];
 for (const viewport of [{width:1920,height:1200},{width:390,height:844},{width:320,height:740}]) {
  const context=await browser.newContext({viewport}); const page=await context.newPage(); const errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  await page.goto(url + '/anmelden');
  await page.locator('input[name="pin"]').fill(pin); await page.getByRole('button',{name:'Bar öffnen'}).click(); await page.waitForURL(url + '/');
  for (const path of ['/','/entdecken','/meine-bar','/meine-bar/neu','/einkaufsliste','/rezepte/neu','/einstellungen']) {
    const start=Date.now(); await page.goto(url + path); const elapsed=Date.now()-start;
    const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth);
    const audit=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa']).analyze();
    results.push({width:viewport.width,path,elapsed,overflow,violations:audit.violations.map(v=>({id:v.id,impact:v.impact,nodes:v.nodes.map(n=>({target:n.target,summary:n.failureSummary}))})),errors:[...errors]});
    if(path==='/') await page.screenshot({path:`${out}/start-${viewport.width}.png`,fullPage:true});
  }
  await context.close();
 }
 fs.writeFileSync(out + '/browser.json',JSON.stringify(results,null,2));
 console.log(JSON.stringify(results.map(r=>({width:r.width,path:r.path,ms:r.elapsed,overflow:r.overflow,violations:r.violations.map(v=>v.id),errors:r.errors})),null,2));
 await browser.close();
 if (results.some(r=>r.overflow || r.violations.length || r.errors.length)) process.exitCode=1;
})().catch(e=>{console.error(e);process.exitCode=1});

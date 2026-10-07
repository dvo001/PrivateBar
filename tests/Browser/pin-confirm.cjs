const { chromium } = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
(async () => {
 const browser = await chromium.launch({headless:true, executablePath:process.env.PRIVATEBAR_BROWSER_EXECUTABLE || undefined});
 try {
  const view = fs.readFileSync(path.join(root, 'resources/views/settings/local.blade.php'), 'utf8');
  const dialog = view.slice(view.indexOf('<dialog'), view.indexOf('</dialog>') + 9);
  for (const width of [1920,390,320]) {
   const page = await browser.newPage({viewport:{width,height:1200}});
   const errors=[];page.on('pageerror',e=>errors.push(e.message));
   await page.setContent(`<body data-local-display="1"><main>
    <form data-pin-confirm action="/einstellungen/lokal"><label>Wert<input name="value" required value="29"></label><label>Neue PIN<input name="new_pin" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"></label><label>PIN<input name="pin" type="password" inputmode="numeric" required pattern="[0-9]{6}" maxlength="6"></label><button>Speichern</button></form>
    <form data-pin-confirm action="/einstellungen/verbindung/test"><label>PIN<input name="pin" type="password" inputmode="numeric" required pattern="[0-9]{6}" maxlength="6"></label><button>Verbindung testen</button></form>
    ${dialog}</main></body>`);
   await page.addStyleTag({content:fs.readFileSync(path.join(root,'resources/css/app.css'),'utf8')});
   await page.addScriptTag({content:fs.readFileSync(path.join(root,'resources/js/app.js'),'utf8')});
   await page.evaluate(()=>{window.submissions=[];document.querySelectorAll('form[data-pin-confirm]').forEach(f=>f.addEventListener('submit',e=>{if(!e.defaultPrevented)submissions.push({action:f.getAttribute('action'),data:Object.fromEntries(new FormData(f))});e.preventDefault();}));});
   const modal=page.locator('#local-pin-confirm');
   const digits=modal.locator('.pin-keypad');
   const fillPin=async()=>{for(const n of '012345')await digits.getByRole('button',{name:n,exact:true}).click();};
   await page.getByRole('button',{name:'Speichern',exact:true}).click();
   assert.equal(await modal.evaluate(e=>e.open),true);
   assert.equal(await page.evaluate(()=>submissions.length),0);
   await digits.getByRole('button',{name:'1',exact:true}).click();
   await modal.getByRole('button',{name:'Bestätigen und ausführen'}).click();
   assert.equal(await page.evaluate(()=>submissions.length),0,'partial PIN must not execute');
   await modal.getByRole('button',{name:'Abbrechen',exact:true}).click();
   assert.equal(await modal.evaluate(e=>e.open),false);
   assert.equal(await page.locator('[name=value]').inputValue(),'29','cancel preserves settings');
   await page.getByRole('button',{name:'Verbindung testen',exact:true}).click();
   assert.equal(await modal.locator('[name=pin]').inputValue(),'','each action requires a fresh PIN');
   await fillPin();
   await modal.getByRole('button',{name:'Bestätigen und ausführen'}).click();
   assert.deepEqual(await page.evaluate(()=>submissions),[{action:'/einstellungen/verbindung/test',data:{pin:'012345'}}]);
   await page.getByRole('button',{name:'Speichern',exact:true}).click();
   await page.keyboard.press('Escape');
   assert.equal(await modal.evaluate(e=>e.open),false);
   await page.getByRole('button',{name:'Speichern',exact:true}).click();
   await fillPin();
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
   assert.ok(await modal.evaluate(e=>{const r=e.getBoundingClientRect();return r.left>=0 && r.right<=innerWidth && r.top>=0 && r.bottom<=innerHeight;}));
   await modal.getByRole('button',{name:'Bestätigen und ausführen'}).click();
   assert.deepEqual(await page.evaluate(()=>submissions[1]),{action:'/einstellungen/lokal',data:{value:'29',new_pin:'',pin:'012345'}});
   assert.equal(await modal.locator('[name=pin]').inputValue(),'');
   assert.deepEqual(errors,[]);
   console.log(`PIN-Popup: ${width}px, Abbrechen/Escape, neue PIN je Aktion, Validierung, korrekter Zielvorgang und Layout OK`);
   await page.close();
  }
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});

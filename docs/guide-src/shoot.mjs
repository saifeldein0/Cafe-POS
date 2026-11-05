// Headless Chrome screenshots via raw CDP. Usage: node shoot.mjs <fr|en> <outdir>
// Run after demo-data.php, with the dev server on :8000; then python crop.py <outdir> and python build.py.
import { spawn } from 'node:child_process';
import { mkdirSync, writeFileSync, rmSync } from 'node:fs';
import path from 'node:path';

const [, , L = 'fr', OUT = './shots'] = process.argv;
const BASE = 'http://localhost:8000';
const PORT = L === 'fr' ? 9333 : 9334;
const profile = path.resolve(OUT, `profile-${L}`);
mkdirSync(OUT, { recursive: true });
rmSync(profile, { recursive: true, force: true });

const chrome = spawn('C:/Program Files/Google/Chrome/Application/chrome.exe', [
  '--headless=new', `--remote-debugging-port=${PORT}`, `--user-data-dir=${profile}`,
  '--no-first-run', '--disable-gpu', `--lang=${L}`, '--hide-scrollbars', 'about:blank',
], { stdio: 'ignore' });

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
let targets;
for (let i = 0; i < 150; i++) {
  try { targets = await (await fetch(`http://127.0.0.1:${PORT}/json`)).json(); if (targets.find(t => t.type === 'page')) break; } catch {}
  await sleep(200);
}
const page = targets.find((t) => t.type === 'page');
const ws = new WebSocket(page.webSocketDebuggerUrl);
await new Promise((r) => ws.addEventListener('open', r));
let id = 0; const pending = new Map();
ws.addEventListener('message', (e) => { const m = JSON.parse(e.data); if (m.id && pending.has(m.id)) { pending.get(m.id)(m); pending.delete(m.id); } });
const send = (method, params = {}) => new Promise((res, rej) => { const i = ++id; pending.set(i, (m) => m.error ? rej(new Error(method + ': ' + m.error.message)) : res(m.result)); ws.send(JSON.stringify({ id: i, method, params })); });
const ev = async (expr) => (await send('Runtime.evaluate', { expression: expr, awaitPromise: true, returnByValue: true })).result.value;

const W = 1366, H = 768, DSF = 2;
await send('Page.enable'); await send('Runtime.enable');
await send('Emulation.setDeviceMetricsOverride', { width: W, height: H, deviceScaleFactor: DSF, mobile: false });

async function go(url, wait = 1800) { await send('Page.navigate', { url: BASE + url }); await sleep(wait); }
async function shot(name, full = false) {
  let params = { format: 'png' };
  if (full) {
    const h = await ev('Math.max(document.documentElement.scrollHeight, document.body.scrollHeight)');
    params = { format: 'png', captureBeyondViewport: true, clip: { x: 0, y: 0, width: W, height: Math.max(h, H), scale: 1 } };
  }
  const { data } = await send('Page.captureScreenshot', params);
  writeFileSync(path.join(OUT, `${L}-${name}.png`), Buffer.from(data, 'base64'));
  console.log('saved', name);
}
const click = (sel) => ev(`(()=>{const el=document.querySelector(${JSON.stringify(sel)}); if(!el) return 'missing '+${JSON.stringify(sel)}; el.click(); return 'ok';})()`);

// POS
await go(`/locale/${L}`, 2500);
await shot('pos-empty');
const cats = await ev(`[...document.querySelectorAll('[wire\\\\:click^="selectCategory"]')].map(b=>b.getAttribute('wire:click'))`);
console.log('cats', cats);
// add products: find in any category
async function addProduct(pid, times = 1) {
  for (const c of cats) {
    const has = await ev(`!!document.querySelector('[wire\\\\:click^="addItem(${pid},"]')`);
    if (has) break;
    await ev(`document.querySelector('[wire\\\\:click="${c}"]').click()`); await sleep(700);
  }
  for (let i = 0; i < times; i++) { console.log(await click(`[wire\\:click^="addItem(${pid},"]`)); await sleep(600); }
}
await addProduct(1, 2);  // Express x2
await addProduct(3, 1);  // Cappucine
await addProduct(7, 1);  // Eau 0.5
await addProduct(15, 2); // Croissant x2
// end on the hot drinks category for a nice look
await ev(`document.querySelector('[wire\\\\:click="${cats[0]}"]').click()`); await sleep(900);
await shot('pos-order');

// Admin login
await go('/admin/login', 2500);
await shot('login');
await ev(`(()=>{const i=document.querySelector('input[type=password]'); i.value='1234'; i.dispatchEvent(new Event('input',{bubbles:true}));})()`);
await sleep(400);
await click('form button[type=submit]');
await sleep(3000);
await go('/admin', 3000);
await shot('dashboard', true);
// user menu open
console.log(await ev(`(()=>{const t=document.querySelector('.fi-user-menu .fi-dropdown-trigger')||document.querySelector('.fi-user-menu-trigger').closest('.fi-dropdown-trigger'); t.dispatchEvent(new MouseEvent('mousedown',{bubbles:true,button:0})); return 'menu';})()`));
await sleep(900);
await shot('usermenu');

await go('/admin/changer-pin', 2500); await shot('changepin');
await go('/admin/purchases', 2500); await shot('purchases');
await go('/admin/purchases/create', 2500); await shot('purchase-form', true);
await go('/admin/products', 2500); await shot('products');
await go('/admin/products/3/edit', 3000); await shot('recipe', true);
console.log(await ev(`(()=>{const row=[...document.querySelectorAll('tr')].find(r=>/inconnu|unknown/.test(r.textContent)); if(!row) return 'norow'; const b=[...row.querySelectorAll('button,a')].find(x=>/Modifier|Edit/.test(x.textContent)); if(!b) return 'nobtn'; b.click(); return 'edit';})()`)); await sleep(2000); await shot('recipe-edit');
await go('/admin/rapport-mensuel?month=2026-10', 3000); await shot('report', true);

ws.close(); chrome.kill();
process.exit(0);

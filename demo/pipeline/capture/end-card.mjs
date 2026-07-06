// Render the closing end card to assets/end-card.png (1280x800), on-brand with the wiki look.
import { mkdirSync } from "node:fs";
import { join, dirname } from "node:path";
import { fileURLToPath } from "node:url";
import { loadPlaywright, BASE } from "./lib.mjs";

const OUT = join(dirname(fileURLToPath(import.meta.url)), "..", "assets");
mkdirSync(OUT, { recursive: true });

const html = `<!doctype html><html><head><meta charset="utf-8"><style>
  html,body{margin:0;padding:0;width:1280px;height:800px;
    font-family:-apple-system,'Segoe UI','Helvetica Neue',Arial,sans-serif;
    background:#f8f9fa;color:#202122;overflow:hidden}
  .wrap{width:100%;height:100%;display:flex;flex-direction:column;
    align-items:center;justify-content:center;text-align:center}
  .logo{width:120px;height:120px;border-radius:22px;background:#32739b;color:#fff;
    display:flex;align-items:center;justify-content:center;font-weight:800;font-size:66px;
    letter-spacing:-2px;box-shadow:0 6px 24px rgba(50,115,155,.28);margin-bottom:34px}
  h1{font-size:62px;font-weight:800;margin:0 0 14px;letter-spacing:-1px}
  .tag{font-size:29px;color:#54595d;margin:0 0 30px;max-width:900px}
  .rule{width:120px;height:3px;background:#a2a9b1;border-radius:2px;margin:0 0 26px}
  .co{font-size:24px;color:#54595d;margin:0 0 8px}
  .url{font-size:25px;color:#32739b;font-weight:600}
</style></head><body><div class="wrap">
  <div class="logo">M↓</div>
  <h1>Native Markdown</h1>
  <div class="tag">Markdown as a native content model for MediaWiki</div>
  <div class="rule"></div>
  <div class="co">Works alongside your existing wikitext pages</div>
  <div class="url">github.com/ProfessionalWiki/NativeMarkdown</div>
</div></body></html>`;

const { chromium } = await loadPlaywright();
const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 }, deviceScaleFactor: 2 });
const page = await ctx.newPage();
await page.setContent(html, { waitUntil: "networkidle" });
await page.waitForTimeout(200);
await page.screenshot({ path: join(OUT, "end-card.png") });
await browser.close();
console.log("end card ->", join(OUT, "end-card.png"), "| (BASE was", BASE + ")");

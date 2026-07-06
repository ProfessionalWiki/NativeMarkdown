// Composed split hero still: the rendered wiki page (left) and its Markdown source (right),
// with a divider, panel labels and a wordmark. Doubles as the mediawiki.org screenshot and the
// blog og:image. Writes assets/native-markdown-demo-hero.png (2560x1600) + .webp.
import { mkdirSync, readFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { mkdtempSync } from "node:fs";
import { join, dirname } from "node:path";
import { fileURLToPath } from "node:url";
import { execFileSync } from "node:child_process";
import { loadPlaywright, login, makeContext, resolveFfmpeg, INDEX } from "./lib.mjs";

const OUT = join(dirname(fileURLToPath(import.meta.url)), "..", "assets");
mkdirSync(OUT, { recursive: true });
const PAGE = "Platform_Architecture.md";
const dataUri = (p) => "data:image/png;base64," + readFileSync(p).toString("base64");

const { chromium } = await loadPlaywright();
const browser = await chromium.launch();
const work = mkdtempSync(join(tmpdir(), "nm-poster-"));
const statePath = join(work, "state.json");
await login(browser, { statePath });
const ctx = await makeContext(browser, { statePath });
const page = await ctx.newPage();

await page.goto(`${INDEX}/${PAGE}`, { waitUntil: "networkidle" });
await page.evaluate(() => window.scrollTo(0, 0));
await page.waitForTimeout(500);
await page.screenshot({ path: join(work, "r.png") });

await page.goto(`${INDEX}?title=${PAGE}&action=edit`, { waitUntil: "networkidle" });
await page.waitForSelector(".ace_editor .ace_content", { timeout: 15000 });
await page.waitForTimeout(1200);
await page.screenshot({ path: join(work, "e.png") });

const rendered = dataUri(join(work, "r.png"));
const editor = dataUri(join(work, "e.png"));

const html = `<!doctype html><html><head><meta charset="utf-8"><style>
  html,body{margin:0;padding:0;width:1280px;height:800px;overflow:hidden;
    font-family:-apple-system,'Segoe UI',Arial,sans-serif;background:#0b1622}
  .split{display:flex;width:100%;height:734px}
  .pane{width:50%;height:100%;overflow:hidden;position:relative;background:#fff}
  .pane img{position:absolute;width:200%;max-width:none}
  .left img{top:0;left:0}                 /* rendered: ToC + title + diagram */
  .right img{top:-150px;left:-150px}       /* editor: line numbers + Markdown source */
  .divider{width:4px;background:#32739b;height:734px}
  .label{position:absolute;top:14px;left:14px;background:rgba(11,22,34,.82);color:#fff;
    font-size:16px;font-weight:600;padding:5px 12px;border-radius:14px;letter-spacing:.2px}
  .bar{height:66px;background:#0b1622;color:#fff;display:flex;align-items:center;gap:14px;padding:0 22px}
  .mark{width:40px;height:40px;border-radius:9px;background:#32739b;color:#fff;display:flex;
    align-items:center;justify-content:center;font-weight:800;font-size:24px;letter-spacing:-1px}
  .name{font-weight:800;font-size:23px}
  .sub{color:#aeb8c4;font-size:19px;margin-left:2px}
</style></head><body>
  <div class="split">
    <div class="pane left"><img src="${rendered}"><div class="label">Rendered wiki page</div></div>
    <div class="divider"></div>
    <div class="pane right"><img src="${editor}"><div class="label">Its Markdown source</div></div>
  </div>
  <div class="bar">
    <div class="mark">M↓</div><div class="name">Native Markdown</div>
    <div class="sub">a first-class wiki page that's really Markdown</div>
  </div>
</body></html>`;

await page.setContent(html, { waitUntil: "networkidle" });
await page.waitForTimeout(200);
await page.screenshot({ path: join(OUT, "native-markdown-demo-hero.png") });
await browser.close();

const ff = resolveFfmpeg();
try {
  execFileSync("cwebp", ["-quiet", "-q", "90", join(OUT, "native-markdown-demo-hero.png"), "-o", join(OUT, "native-markdown-demo-hero.webp")]);
} catch {
  execFileSync(ff, ["-y", "-i", join(OUT, "native-markdown-demo-hero.png"), "-c:v", "libwebp", "-quality", "90", join(OUT, "native-markdown-demo-hero.webp")]);
}
console.log("hero ->", join(OUT, "native-markdown-demo-hero.png"));

// The Native Markdown hero demo, captured deterministically in the browser (no captions burned here;
// captions + end card are added in post by make-video.sh). Flow:
//   rendered page  ->  Edit (clean Markdown source)  ->  type a Monitoring section  ->  Save
//   ->  the page re-integrates (ToC grows, link works, category lands)  ->  action=raw
// Pre-state:  ./stage.sh record   (Platform Architecture.md without its leading H1; DB Operations link red)
//   NODE_PATH="$(npm root -g)" node edit-flow.mjs
import { mkdtempSync, mkdirSync } from "node:fs";
import { tmpdir } from "node:os";
import { join, dirname } from "node:path";
import { fileURLToPath } from "node:url";
import {
  loadPlaywright, login, makeContext, transcodeVideo, resolveFfmpeg, glideTo, glideClick, INDEX,
} from "./lib.mjs";

const OUT = join(dirname(fileURLToPath(import.meta.url)), "..", "assets");
mkdirSync(OUT, { recursive: true });
const PAGE = "Platform_Architecture.md";
const ffmpeg = resolveFfmpeg();

// The exact section the author types, inserted before "## Related pages".
const MONITORING =
  "## Monitoring\n\n" +
  "When an alert fires, follow the [[Incident Response Runbook.md|runbook]].\n\n" +
  "[[Category:Monitoring]]\n\n";

const aceScrollToLine = (page, needle) =>
  page.evaluate((n) => {
    const ed = document.querySelector(".ace_editor").env.editor;
    const row = ed.session.getDocument().getAllLines().findIndex((l) => l.includes(n));
    if (row >= 0) ed.renderer.scrollToLine(Math.max(0, row - 3), true, true, () => {});
  }, needle);

const smoothTo = (page, sel) =>
  page.evaluate((s) => {
    const el = document.querySelector(s);
    if (el) el.scrollIntoView({ behavior: "smooth", block: "center" });
  }, sel);

const { chromium } = await loadPlaywright();
const browser = await chromium.launch();
const work = mkdtempSync(join(tmpdir(), "nm-edit-"));
const statePath = join(work, "state.json");
await login(browser, { statePath });

const vdir = mkdtempSync(join(tmpdir(), "nm-vid-"));
const ctx = await makeContext(browser, { statePath, recordVideoDir: vdir });
const page = await ctx.newPage();
const video = page.video();

// ---- Beat 1: a normal, integrated wiki page ----
await page.goto(`${INDEX}/${PAGE}`, { waitUntil: "networkidle" });
await page.waitForTimeout(2100);                          // title, diagram, ToC
await smoothTo(page, "#Components, [id='Components']");   // reveal the styled table with real links (no cursor)
await page.waitForTimeout(1700);
await page.evaluate(() => window.scrollTo({ top: 0, behavior: "smooth" }));  // back to the top, no link detour
await page.waitForTimeout(900);

// ---- Beat 2: click Edit -> the source is plain Markdown ----
await glideClick(page, page.locator("#ca-edit a").first());
await page.waitForSelector(".ace_editor .ace_content", { timeout: 15000 });
await page.waitForTimeout(1500);
await aceScrollToLine(page, "## Components");             // reveal headings, table pipes, [[links]]
await page.waitForTimeout(2400);

// ---- Beat 3: author a Monitoring section (heading + link + category) ----
await page.evaluate((needle) => {
  const ed = document.querySelector(".ace_editor").env.editor;
  const row = ed.session.getDocument().getAllLines().findIndex((l) => l.startsWith(needle));
  ed.moveCursorTo(Math.max(0, row), 0);
  ed.clearSelection();
  ed.renderer.scrollCursorIntoView(null, 0.5);
}, "## Related pages");
await page.waitForTimeout(850);
// animate the insertion character by character so the Markdown editing is watchable
for (const ch of MONITORING) {
  await page.evaluate((t) => {
    const ed = document.querySelector(".ace_editor").env.editor;
    ed.insert(t);
    ed.renderer.scrollCursorIntoView(null, 0.5);
  }, ch);
  await page.waitForTimeout(34);
}
await page.waitForTimeout(1500);

// ---- Beat 4: Save ----
await glideClick(page, page.locator("#wpSave").first());
await page.waitForURL(`**/${PAGE}`, { timeout: 15000 }).catch(() => {});
await page.waitForLoadState("networkidle");
await page.waitForTimeout(1400);

// ---- Beat 5: the page re-integrated ----
await glideTo(page, page.locator("a[href='#Monitoring']").first());   // new ToC entry
await page.waitForTimeout(1500);
await smoothTo(page, "#Monitoring, [id='Monitoring']");               // the new section, blue runbook link
await page.waitForTimeout(1700);
await page.evaluate(() => window.scrollTo({ top: document.body.scrollHeight, behavior: "smooth" }));
await page.waitForTimeout(700);
await glideTo(page, page.locator("#catlinks a[title^='Category:Monitoring']").first());
await page.waitForTimeout(1500);

// ---- Beat 6: action=raw — the stored Markdown, byte for byte ----
await page.goto(`${INDEX}?title=${PAGE}&action=raw`, { waitUntil: "domcontentloaded" });
await page.waitForTimeout(2600);
await page.evaluate(() => window.scrollTo({ top: 300, behavior: "smooth" }));
await page.waitForTimeout(1800);

await page.close();
await ctx.close();
const raw = await video.path();
transcodeVideo(ffmpeg, raw, {
  mp4: join(OUT, "native-markdown-demo.nocaps.mp4"),   // uncaptioned master; make-video.sh burns captions
  startSec: 0.8,
});
console.log("raw edit-flow ->", join(OUT, "native-markdown-demo.nocaps.mp4"));
await browser.close();

// Deterministic browser reveal for the Native Markdown demo: the rendered Platform Architecture.md
// page (grown ToC, blue [[Database Operations.md]] link, Monitoring category, embedded diagram) and
// then the same page fetched as clean Markdown via action=raw. Produces webm + mp4 + webp poster and
// a set of stills. Assumes the wiki is in the demo "after" state: run  ./stage.sh demo  first.
//
//   NODE_PATH="$(npm root -g)" node reveal.mjs           # video + stills
//   NODE_PATH="$(npm root -g)" node reveal.mjs stills    # stills only
import { mkdtempSync, mkdirSync } from "node:fs";
import { tmpdir } from "node:os";
import { join, dirname } from "node:path";
import { fileURLToPath } from "node:url";
import {
  loadPlaywright, login, makeContext, transcodeVideo, pngToWebp, resolveFfmpeg,
  glideTo, glideClick, INDEX, API,
} from "./lib.mjs";

const OUT = join(dirname(fileURLToPath(import.meta.url)), "..", "assets");
mkdirSync(OUT, { recursive: true });
const PAGE = "Platform_Architecture.md";
const ffmpeg = resolveFfmpeg();
const onlyStills = process.argv[2] === "stills";

const smoothTo = (page, sel) =>
  page.evaluate((s) => {
    const el = document.querySelector(s);
    if (el) el.scrollIntoView({ behavior: "smooth", block: "center" });
  }, sel);
const toTop = (page) => page.evaluate(() => window.scrollTo({ top: 0, behavior: "smooth" }));

const { chromium } = await loadPlaywright();
const browser = await chromium.launch();
const work = mkdtempSync(join(tmpdir(), "nm-reveal-"));
const statePath = join(work, "state.json");
await login(browser, { statePath });

// ---- Stills pass (non-recording, crisp 2x, no cursor) ----
{
  const ctx = await makeContext(browser, { statePath });
  const page = await ctx.newPage();

  await page.goto(`${INDEX}/${PAGE}`, { waitUntil: "networkidle" });
  await page.waitForTimeout(600);
  await page.screenshot({ path: join(OUT, "rendered-top.png") }); // poster / hero candidate

  await smoothTo(page, "#Components, [id='Components']");
  await page.waitForTimeout(900);
  await page.screenshot({ path: join(OUT, "components-blue-link.png") });

  await smoothTo(page, "#Monitoring, [id='Monitoring']");
  await page.waitForTimeout(900);
  await page.screenshot({ path: join(OUT, "monitoring-section.png") });

  await page.evaluate(() => window.scrollTo({ top: document.body.scrollHeight, behavior: "smooth" }));
  await page.waitForTimeout(900);
  await page.screenshot({ path: join(OUT, "category-bar.png") });

  await page.goto(`${INDEX}?title=${PAGE}&action=raw`, { waitUntil: "domcontentloaded" });
  await page.waitForTimeout(400);
  await page.screenshot({ path: join(OUT, "raw-markdown.png") });

  await page.goto(`${API.replace("/api.php", "")}/rest.php/v1/page/Platform%20Architecture.md`,
    { waitUntil: "domcontentloaded" });
  await page.waitForTimeout(300);
  await page.screenshot({ path: join(OUT, "rest-json.png") });

  await ctx.close();
  console.log("stills ->", OUT);
}

if (onlyStills) { await browser.close(); process.exit(0); }

// ---- Video pass (recording context, synthetic cursor) ----
{
  const vdir = mkdtempSync(join(tmpdir(), "nm-vid-"));
  const ctx = await makeContext(browser, { statePath, recordVideoDir: vdir });
  const page = await ctx.newPage();
  const video = page.video();

  // 1) Land on the rendered page: title, diagram, ToC now showing Monitoring.
  await page.goto(`${INDEX}/${PAGE}`, { waitUntil: "networkidle" });
  await page.waitForTimeout(1600);
  await glideTo(page, page.locator("a[href='#Monitoring']").first());
  await page.waitForTimeout(1100);

  // 2) The Components table: the [[Database Operations.md]] link is now blue (was red).
  await smoothTo(page, "#Components, [id='Components']");
  await page.waitForTimeout(700);
  await glideTo(page, page.locator("a[href='/index.php/Database_Operations.md']").first());
  await page.waitForTimeout(1700);

  // 3) The new Monitoring section with its working [[runbook]] link.
  await smoothTo(page, "#Monitoring, [id='Monitoring']");
  await page.waitForTimeout(700);
  await glideTo(page, page.locator("#Monitoring, [id='Monitoring']").first());
  await page.waitForTimeout(1600);

  // 4) The Monitoring category at the foot of the page.
  await page.evaluate(() => window.scrollTo({ top: document.body.scrollHeight, behavior: "smooth" }));
  await page.waitForTimeout(700);
  await glideTo(page, page.locator("#catlinks a[title^='Category:Monitoring']").first());
  await page.waitForTimeout(1500);

  // 5) The same page as clean Markdown — action=raw.
  await page.goto(`${INDEX}?title=${PAGE}&action=raw`, { waitUntil: "domcontentloaded" });
  await page.waitForTimeout(2600);
  await page.evaluate(() => window.scrollTo({ top: 320, behavior: "smooth" }));
  await page.waitForTimeout(1600);

  await page.close();
  await ctx.close();
  const raw = await video.path();
  transcodeVideo(ffmpeg, raw, {
    webm: join(OUT, "native-markdown-rendered.webm"),
    mp4: join(OUT, "native-markdown-rendered.mp4"),
    poster: join(OUT, "native-markdown-rendered-poster.webp"),
    startSec: 1.0,
    posterSec: 1.2,
  });
  console.log("video ->", OUT);
}

await browser.close();

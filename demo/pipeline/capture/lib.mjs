// Playwright + ffmpeg capture scaffolding for the Native Markdown demo, adapted from the NeoWiki
// website's scripts/capture/lib.mjs. Differences from that original:
//   - targets the mediawiki-dev wiki at http://localhost:8484 (empty script path: /index.php, /api.php)
//   - drops the NeoWiki-only #p-redherb-sidebar hide; keeps the generic GlobalIdGenerator notice strip
//   - encode settings tuned for a 1280x800 text-heavy wiki page
// Requires a real ffmpeg on PATH (Playwright's bundled ffmpeg only does VP8) and global Playwright.
import { execFileSync, execSync } from "node:child_process";
import { unlinkSync } from "node:fs";
import { createRequire } from "node:module";
import { pathToFileURL } from "node:url";
import { join } from "node:path";

export const BASE = process.env.WIKI_BASE || "http://localhost:8484";
export const INDEX = `${BASE}/index.php`;
export const API = `${BASE}/api.php`;
export const VIEWPORT = { width: 1280, height: 800 };

// Resolve Playwright whether it's a local dep or a global (nvm) install.
// Playwright is CJS, so ESM interop lands its exports on `.default`; normalize either shape.
export async function loadPlaywright() {
  let mod;
  try {
    mod = await import("playwright");
  } catch {
    const globalRoot = execSync("npm root -g").toString().trim();
    const req = createRequire(import.meta.url);
    const entry = req.resolve(join(globalRoot, "playwright"));
    mod = await import(pathToFileURL(entry).href);
  }
  return mod.chromium ? mod : (mod.default ?? mod);
}

export function resolveFfmpeg() {
  return process.env.FFMPEG || "ffmpeg";
}

// Injected into every page: strip MediaWiki's intermittent "Clock was set back" /
// GlobalIdGenerator PHP notice that Dockerized dev wikis print under clock skew.
export function prepPage() {
  const stripNotice = () => {
    if (!document.body) return;
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    const dead = [];
    while (walker.nextNode()) {
      const v = walker.currentNode.nodeValue || "";
      if (/Clock was set back|GlobalIdGenerator|on line \d+/.test(v)) dead.push(walker.currentNode);
    }
    dead.forEach((n) => n.remove());
    document.querySelectorAll("body *").forEach((el) => {
      const t = el.textContent || "";
      if (t.length < 280 && (t.includes("GlobalIdGenerator") || t.includes("Clock was set back")))
        el.remove();
    });
  };
  document.addEventListener("DOMContentLoaded", stripNotice);
  for (const d of [200, 600, 1200, 2000]) setTimeout(stripNotice, d);
}

// Playwright video never captures the OS pointer, so paint a synthetic cursor + click pulse.
export function showCursor() {
  const TIP = 4;
  const ACCENT = "#3366cc"; // MediaWiki link blue
  const ensure = () => {
    const root = document.documentElement;
    if (!root || document.getElementById("nm-cursor")) return;
    const svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
    svg.id = "nm-cursor";
    svg.setAttribute("viewBox", "0 0 24 24");
    svg.innerHTML =
      '<path d="M4.037 4.688a.495.495 0 0 1 .651-.651l16 6.5a.5.5 0 0 1-.063.947l-6.124 1.58a2 2 0 0 0-1.438 1.435l-1.579 6.126a.5.5 0 0 1-.947.063z" fill="#1c1a17" stroke="#fff" stroke-width="1.3" stroke-linejoin="round"/>';
    Object.assign(svg.style, {
      position: "fixed", top: "0", left: "0", width: "24px", height: "24px",
      zIndex: "2147483647", pointerEvents: "none", transform: "translate(-100px, -100px)",
      transition: "transform 70ms linear", filter: "drop-shadow(0 1px 1.5px rgba(0,0,0,.4))",
    });
    root.appendChild(svg);
  };
  const moveTo = (x, y) => {
    const svg = document.getElementById("nm-cursor");
    if (svg) svg.style.transform = `translate(${x - TIP}px, ${y - TIP}px)`;
  };
  const pulse = (x, y) => {
    const root = document.documentElement;
    if (!root) return;
    const ring = document.createElement("div");
    Object.assign(ring.style, {
      position: "fixed", left: `${x}px`, top: `${y}px`, width: "16px", height: "16px",
      marginLeft: "-8px", marginTop: "-8px", borderRadius: "50%", border: `2px solid ${ACCENT}`,
      zIndex: "2147483646", pointerEvents: "none", opacity: "1", transform: "scale(0.35)",
      transition: "transform .45s ease-out, opacity .45s ease-out",
    });
    root.appendChild(ring);
    requestAnimationFrame(() => { ring.style.transform = "scale(2.6)"; ring.style.opacity = "0"; });
    setTimeout(() => ring.remove(), 500);
  };
  window.addEventListener("mousemove", (e) => moveTo(e.clientX, e.clientY), true);
  window.addEventListener("mousedown", (e) => pulse(e.clientX, e.clientY), true);
  ensure();
  document.addEventListener("DOMContentLoaded", ensure);
  for (const d of [150, 500, 1200]) setTimeout(ensure, d);
}

// Move the pointer along a visible path and click, so the recorded cursor travels.
export async function glideClick(page, locator, { steps = 24, settle = 180 } = {}) {
  await locator.scrollIntoViewIfNeeded();
  const box = await locator.boundingBox();
  if (!box) return locator.click();
  await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2, { steps });
  await page.waitForTimeout(settle);
  await page.mouse.down();
  await page.waitForTimeout(70);
  await page.mouse.up();
}

// Glide the pointer to an element without clicking (draw the eye).
export async function glideTo(page, locator, { steps = 24 } = {}) {
  await locator.scrollIntoViewIfNeeded();
  const box = await locator.boundingBox();
  if (box) await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2, { steps });
}

// Out-of-band UI login -> storageState file the recording context reuses, so the login
// screen never appears on camera. Our wiki has an empty script path (/index.php).
export async function login(browser, { statePath, user = "AdminName", pass = "AdminPassword" }) {
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  await page.goto(`${INDEX}?title=Special:UserLogin&returnto=Main_Page`, {
    waitUntil: "domcontentloaded",
  });
  await page.fill('input[name="wpName"]', user);
  await page.fill('input[name="wpPassword"]', pass);
  await page.getByRole("button", { name: "Log in" }).click();
  await page.waitForLoadState("networkidle");
  await ctx.storageState({ path: statePath });
  await ctx.close();
}

export async function makeContext(browser, { statePath, recordVideoDir } = {}) {
  const ctx = await browser.newContext({
    viewport: VIEWPORT,
    deviceScaleFactor: 2,
    ...(statePath ? { storageState: statePath } : {}),
    ...(recordVideoDir ? { recordVideo: { dir: recordVideoDir, size: VIEWPORT } } : {}),
  });
  await ctx.addInitScript(prepPage);
  if (recordVideoDir) await ctx.addInitScript(showCursor);
  return ctx;
}

// One raw capture -> webm (VP9) + mp4 (H.264) + webp poster. Trims the first startSec
// (settling) with an input seek and drops audio. CRFs tuned for crisp wiki text at 1280x800.
export function transcodeVideo(ffmpeg, raw, { webm, mp4, poster, startSec = 1.0, posterSec } = {}) {
  const run = (args) => execFileSync(ffmpeg, args, { stdio: "inherit" });
  const ss = String(startSec);
  if (webm) {
    run(["-y", "-ss", ss, "-i", raw, "-an", "-c:v", "libvpx-vp9", "-crf", "32", "-b:v", "0",
      "-row-mt", "1", "-pix_fmt", "yuv420p", webm]);
  }
  if (mp4) {
    run(["-y", "-ss", ss, "-i", raw, "-an", "-c:v", "libx264", "-crf", "22", "-preset", "slow",
      "-pix_fmt", "yuv420p", "-movflags", "+faststart", mp4]);
  }
  if (poster) {
    const framePng = `${poster}.frame.png`;
    run(["-y", "-ss", String(posterSec ?? startSec), "-i", raw, "-frames:v", "1", "-update", "1", framePng]);
    pngToWebp(ffmpeg, framePng, poster);
    unlinkSync(framePng);
  }
}

export function pngToWebp(ffmpeg, pngPath, webpPath, quality = 90) {
  try {
    execFileSync("cwebp", ["-quiet", "-q", String(quality), pngPath, "-o", webpPath], { stdio: "inherit" });
  } catch {
    execFileSync(ffmpeg, ["-y", "-i", pngPath, "-c:v", "libwebp", "-quality", String(quality), webpPath],
      { stdio: "inherit" });
  }
}

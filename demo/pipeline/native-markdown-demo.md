# Recording: Native Markdown demo video

Re-record recipe for the Native Markdown launch demo. Unlike the sibling "Use Semantic MediaWiki with
AI" clip (a hand-recorded terminal screencast), this one is **fully deterministic**: a Playwright script
drives the dev wiki in a browser and ffmpeg does the rest, so anyone can regenerate an identical clip with
a few commands — no human at the keyboard, no screen recorder. It is a dev artifact and is not served to
the web from here; the encoded files are copied to their destinations (see [Embedding](#embedding)).

## What it shows

One continuous, silent, captioned browser screencast (~33 s) that lands a single idea: **a fully
integrated MediaWiki page is plain Markdown underneath, and you author it in Markdown.**

| Time | On screen | Caption |
|------|-----------|---------|
| 0:00–04 | The rendered `Platform Architecture.md`: sidebar ToC, an embedded diagram, a styled table, blue **and red** `[[links]]` | "A normal MediaWiki page." → "There's no wikitext behind it." |
| 0:05–09 | Click **Edit**; the source is clean, syntax-highlighted Markdown | "The whole page is plain Markdown." → "A native content model, not a tag hack." |
| 0:09–16 | Type a `## Monitoring` section (a heading, a `[[runbook]]` link, a `[[Category:Monitoring]]`), then **Save** | "Add a heading, a link, a category…" → "…and save." |
| 0:17–24 | The page re-integrates: the ToC gains **Monitoring**, the link is live, the category lands at the foot | "The table of contents grows. The link works." → "Our new category is present." |
| 0:25–29 | `action=raw`: the stored Markdown | "action=raw: the stored Markdown." → "Exactly what LLMs read and write." |
| 0:29–33 | End card | wordmark · tagline · coexists-with-wikitext · repo URL |

The AI angle is a deliberate one-line closing nod (`action=raw` is what LLMs read/write), not the subject.
The demo drives the **local dev wiki only**; it never touches a live wiki.

## Assets produced

All land in `demo/pipeline/assets/`. Even pixel dimensions throughout.

| File | What | Size (ref) |
|------|------|-----------|
| `native-markdown-demo.webm` | VP9, primary web video, 1280×800, ~33 s | ~1.7 MB |
| `native-markdown-demo.mp4` | H.264, Safari/iOS fallback, 1280×800, ~33 s | ~2.2 MB |
| `native-markdown-demo-poster.webp` | video poster (clean rendered-page frame) | ~73 KB |
| `native-markdown-demo-hero.png` / `.webp` | composed split still (rendered ½ · Markdown source ½) — mediawiki.org screenshot + blog og:image | ~570 KB / ~230 KB |
| `native-markdown-demo.gif` | ~13 s captioned teaser (type → integrate), for mediawiki.org | ~2.2 MB |
| `end-card.png` | the closing card, 1280×800 | ~120 KB |
| `native-markdown-demo.nocaps.mp4` | uncaptioned master — re-burn captions from this without re-capturing | ~2.1 MB |
| `rendered-top.png`, `editor-source.png`, `monitoring-section.png`, `components-blue-link.png`, `category-bar.png`, `raw-markdown.png`, `rest-json.png` | supporting stills for blog body / mediawiki.org | — |

## Prerequisites

- The **mediawiki-dev env running**. From the env root (`/var/home/ccc/workspace/mediawiki-dev`):
  `docker compose ps` — confirm the `mediawiki` service is up and note the port. It has been served on
  **http://localhost:8484**, but a NeoWiki env can also hold 8484; if the port differs, export
  `WIKI_BASE=http://localhost:<port>` before the capture scripts and set the same in `make-video.sh`/stage.
  Admin login: `AdminName` / `AdminPassword`. Sitename "Platform Docs".
- **Playwright + Chromium** (global is fine): `npm i -g playwright && npx playwright install chromium`.
  The scripts resolve the global install; run them with `NODE_PATH="$(npm root -g)"` (as below).
- **A real ffmpeg** on PATH with libvpx-vp9, libx264, libwebp and libass (Fedora's `ffmpeg` package has all).
  `cwebp` is used for WebP if present, else ffmpeg's libwebp is the fallback.

## How to regenerate (all deterministic — just run these)

From `demo/pipeline/`:

```sh
# 1. Stage the exact "before" page state (idempotent; safe to re-run before every take).
#    Platform Architecture.md is loaded WITHOUT its leading "# H1" (see Notes) and the
#    Database Operations.md link is left red.
./stage.sh record

# 2. Capture the raw, uncaptioned edit-flow -> assets/native-markdown-demo.nocaps.mp4
NODE_PATH="$(npm root -g)" node capture/edit-flow.mjs

# 3. Render the closing card -> assets/end-card.png
NODE_PATH="$(npm root -g)" node capture/end-card.mjs

# 4. Burn captions + append the end card + encode the WebM/MP4/poster trio
./make-video.sh

# 5. Composed split hero still -> assets/native-markdown-demo-hero.{png,webp}
NODE_PATH="$(npm root -g)" node capture/poster.mjs

# 6. mediawiki.org teaser GIF (the type->integrate arc, captions burned in)
cd assets
ffmpeg -y -ss 9.0 -t 13.0 -i native-markdown-demo.mp4 \
  -vf "fps=11,scale=760:-1:flags=lanczos,palettegen=stats_mode=diff" _pal.png
ffmpeg -y -ss 9.0 -t 13.0 -i native-markdown-demo.mp4 -i _pal.png \
  -lavfi "fps=11,scale=760:-1:flags=lanczos[x];[x][1:v]paletteuse=dither=bayer:bayer_scale=4:diff_mode=rectangle" \
  native-markdown-demo.gif
rm -f _pal.png
```

The capture reads whatever is on the wiki, so **step 1 before step 2 every time** — the edit-flow saves a
revision, so a second run without re-staging would start from the already-edited page.

The supporting stills (`rendered-top.png`, `monitoring-section.png`, `rest-json.png`, …) are optional and
regenerated with `./stage.sh demo && NODE_PATH="$(npm root -g)" node capture/reveal.mjs stills`.

### Tweaking the captions without re-capturing

Caption text and timing live in `captions.txt`, one caption per line as `start|end|text` (seconds). Edit
that file and re-run only `./make-video.sh` — it re-burns onto the uncaptioned master
(`native-markdown-demo.nocaps.mp4`). No browser re-capture needed. Then regenerate the GIF (step 6) if the
timing of the 9–22 s window changed. Captions are drawn with ffmpeg `drawtext` (one clean box per line);
libass `BorderStyle=3` was avoided because it dips the box lower under space glyphs.

### Staging commands (`./stage.sh`)

| Command | State |
|---------|-------|
| `record` | The demo "before" state: `Platform Architecture.md` minus its leading H1, `Database Operations.md` absent (red link). Run before each capture. |
| `baseline` | Fixtures "before" state, H1 included. |
| `demo` | Applies the same edit the video makes, as a stand-in (used to shoot the supporting stills). |
| `seed` | Full fixtures seed for a fresh wiki (mirrors `demo/fixtures/README.md`). |

## Encoding

- **WebM (VP9):** `-crf 32 -b:v 0 -row-mt 1 -pix_fmt yuv420p`
- **MP4 (H.264):** `-crf 21 -preset slow -pix_fmt yuv420p -movflags +faststart`
- Captions are burned with ffmpeg `drawtext` (one clean box per caption, from `captions.txt`); the end card is a 4 s clip (`fade=t=in`) concatenated on.
- Poster: a clean rendered-page frame (~1.5 s in) → WebP q90.
- CRFs are tuned so wiki/editor text stays crisp at this size while each format lands ~1.7–2.2 MB.

## Embedding

**professional.wiki blog** — the post `templates/pages/news/native-markdown-released.html.twig` already
exists (currently image-only). Copy `native-markdown-demo.{webm,mp4,-poster.webp}` into BunnyFarm's
`public/video/` and add the shared partial (house style — click-to-play with a poster, no autoplay):

```twig
{% include 'parts/video.html.twig' with {
    webm: 'video/native-markdown-demo.webm',
    mp4: 'video/native-markdown-demo.mp4',
    poster: 'video/native-markdown-demo-poster.webp',
    width: 1280,
    height: 800,
    alt: 'Editing a MediaWiki page as plain Markdown: it renders with a table of contents, working links and a category',
    caption: 'The page is stored as Markdown. Edit it as Markdown, and it renders as a fully integrated wiki page.'
} %}
```

Pass the real `width`/`height` (1280×800) — the partial reserves the box from those attributes, so there is
no layout shift. The column renders it at up to ~925 CSS px; 1280-wide masters give HiDPI headroom.
`native-markdown-demo-hero.png` is the og:image.

**mediawiki.org `Extension:NativeMarkdown`** — self-hosted video is impractical there: TimedMediaHandler
**does not allow MP4** (MPEG-LA patent policy) and wikitext can't embed a raw `<video>`. So:

1. Upload `native-markdown-demo-hero.png` (or `rendered-top.png`) as the infobox / lead screenshot.
2. Upload `native-markdown-demo.gif` for an inline motion teaser (GIF needs no TMH; captions make it
   self-explanatory).
3. Link to the blog post for the full video.
4. *Optional, best quality:* upload `native-markdown-demo.webm` to **Wikimedia Commons** (royalty-free,
   reusable) and embed `[[File:Native-markdown-demo.webm|thumb|...]]` for an in-page VideoJS player.

## Notes / pitfalls

- **Fully deterministic:** every asset above is produced by the commands here; nothing needs a human screen
  recorder. (The sibling SMW clip does, because it films a real terminal session.)
- **Leading H1 removed for the recording.** The fixtures pages start with an `# H1` that repeats the page
  title, so the rendered page would show the title twice and the ToC would nest a level deeper. In a silent
  video that's an unanswerable distraction, so `stage.sh record` loads `demo-pages/Platform
  Architecture.recording.md` (fixtures content minus the H1). The H1 is optional in the product; this is
  staging, not a product change.
- **The `.md` in the page title is intentional** — it's suffix detection (`$wgNativeMarkdownSuffixDetection`)
  and quietly reinforces "this page is Markdown" in every frame. Leave it. (Namespace/everywhere activation
  modes give clean titles; that's a config choice to mention in the blog, not fix here.)
- **The red `[[Database Operations.md]]` link stays red** — it proves these are real wiki links, not
  autolinks. The "create the page, red turns blue" beat was deliberately cut to keep the hero about one
  page. To make that optional GIF: `stage.sh record`, create `Database Operations.md` (see
  `demo-pages/Database Operations.md`), and capture the Components table before/after.
- **Editor is Ace (CodeEditor), not a plain textarea.** `edit-flow.mjs` drives it through its editor
  instance (`.ace_editor.env.editor`) to place the cursor and animate the insertion; if a MediaWiki upgrade
  changes the editor, that's the part to revisit.
- **Legibility:** the editor beat is legible at full width and click-to-play; at the ~925 px blog width its
  monospace is small but the highlighted `#`/`|`/`[[ ]]` structure still reads as "Markdown source." To make
  it larger, record at a narrower viewport or bump the wiki's Appearance→Text size, then re-run from step 2.
- **Companion blog post:** the announcement (`docs/announce/blog.md`) does **not** exist yet — it's gated
  behind a checkpoint. The video is built to the README/SPEC story; the caption lines double as blog section
  headings ("There's no wikitext behind it", "Exactly what LLMs read and write") for one vocabulary across
  surfaces.

## Environment coupling (before running on a different setup)

These scripts target the ProfessionalWiki `mediawiki-dev` env specifically — they are internal tooling,
not a general-purpose demo kit, and will not run unchanged on an arbitrary MediaWiki setup. To adapt:

- `capture/lib.mjs` assumes an **empty script path** (`/index.php`, `/api.php`); a wiki using `/w/` + `/wiki/`
  must change these (or derive them from `action=query&meta=siteinfo`). `WIKI_BASE` overrides only the host.
- `stage.sh` assumes `docker compose` with a service named `mediawiki` (`MEDIAWIKI_DEV_ROOT` for the env root);
  `container-stage.sh` assumes MediaWiki at `/var/www/html` and an `AdminName`/`Admin` user.
- The capture assumes the **Vector 2022** skin and the **CodeEditor (Ace)** extension, and that the fixtures
  pages are seeded (`stage.sh seed`).
- `make-video.sh` / `make_logo.py` default to **Fedora** font paths; override `CAPTION_FONT` or edit them.

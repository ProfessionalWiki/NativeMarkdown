# NativeMarkdown

<!-- Badges (activate at publish, when the repo is public and the package is on Packagist):
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/ProfessionalWiki/NativeMarkdown/ci.yml?branch=master)](https://github.com/ProfessionalWiki/NativeMarkdown/actions?query=workflow%3ACI)
[![codecov](https://codecov.io/gh/ProfessionalWiki/NativeMarkdown/branch/master/graph/badge.svg)](https://codecov.io/gh/ProfessionalWiki/NativeMarkdown)
[![Type Coverage](https://shepherd.dev/github/ProfessionalWiki/NativeMarkdown/coverage.svg)](https://shepherd.dev/github/ProfessionalWiki/NativeMarkdown)
[![Psalm level](https://shepherd.dev/github/ProfessionalWiki/NativeMarkdown/level.svg)](psalm.xml)
[![Latest Stable Version](https://poser.pugx.org/professional-wiki/native-markdown/v/stable)](https://packagist.org/packages/professional-wiki/native-markdown)
[![Download count](https://poser.pugx.org/professional-wiki/native-markdown/downloads)](https://packagist.org/packages/professional-wiki/native-markdown)
[![License](https://poser.pugx.org/professional-wiki/native-markdown/license)](LICENSE)
-->

[MediaWiki] extension that makes Markdown a **native content model**: whole pages are stored and edited as
Markdown and rendered with first-class wiki integration — internal links, categories, table of contents and
search — coexisting with wikitext pages on the same wiki.

Because pages are stored as plain Markdown, they are directly consumable and writable by LLMs and agents:
`action=raw` returns clean Markdown, no wikitext conversion needed.
See [For AI agents and LLMs](#for-ai-agents-and-llms).

NativeMarkdown has been created and is maintained by [Professional Wiki].

**Status: pre-release.** Configuration and behavior can still change.

- [Usage](#usage)
- [Installation](#installation)
- [Configuration](#configuration)
- [For AI agents and LLMs](#for-ai-agents-and-llms)
- [Comparison with other Markdown extensions](#comparison-with-other-markdown-extensions)
- [Development](#development)
- [Release notes](#release-notes)

## Usage

A Markdown page renders as a normal wiki page: MediaWiki table of contents, blue and red internal links,
category assignment, working search — while `action=raw` returns the Markdown source.

<img
  src="docs/screenshots/rendered-page-with-toc.png"
  width="720"
  alt="A markdown page rendered with sidebar table of contents, an embedded diagram and a table with links"
/>

Editing uses the standard edit form. With the [CodeEditor extension] installed, Markdown pages get syntax
highlighting; Show preview renders through the full pipeline:

<img
  src="docs/screenshots/editor-markdown-highlighting.png"
  width="720"
  alt="The wiki edit form with Markdown syntax highlighting"
/>

### Markdown syntax

Pages use [CommonMark] plus GitHub Flavored Markdown: tables, strikethrough, task lists and autolinks, with
footnotes (`[^1]`) also enabled. Raw HTML is always escaped, which makes output XSS-safe by construction.

For readers coming from wikitext:

| Wikitext | Markdown |
|---|---|
| `'''bold'''`, `''italic''` | `**bold**`, `*italic*` |
| `== Heading ==` | `## Heading` |
| `* bullet` / `# numbered` | `- bullet` / `1. numbered` |
| `[https://example.org label]` | `[label](https://example.org)` |
| `{\| class="wikitable" ...` | GFM tables (`\| a \| b \|`), styled as wikitable automatically |
| `<syntaxhighlight lang="php">` | fenced code block: <code>```php</code> |
| `<ref>Source</ref>` | footnote: `[^1]` plus `[^1]: Source` |
| `[[Page]]`, `[[Category:X]]`, `[[File:X.png]]` | identical — see below |
| `{{Template}}`, parser functions, magic words | not available (rendered as literal text) |

MediaWiki already renders the page title as the top-level heading, so starting a page with a `# Heading` is
optional; when present it simply becomes the first entry of the table of contents.

### Wiki links in Markdown

Wikitext's link syntax works inside Markdown, with the same semantics:

| Syntax | Effect |
|---|---|
| `[[Page]]`, `[[Namespace:Page]]` | Internal link, blue or red by page existence, tracked in `Special:WhatLinksHere` |
| `[[Page\|label]]` | Internal link with label |
| `[[Page#Section]]`, `[[#Section]]` | Link to a section of another page or of the current page |
| `[[Category:X]]` | Assigns the category and renders nothing, like wikitext |
| `[[Category:X\|sort key]]` | Assigns with a sort key |
| `[[:Category:X]]` | Visible link to the category page |
| `[[File:X.png]]` | Embeds the file at full size; missing files render as an upload link |
| `[[File:X.png\|300px\|alt=Alt text\|Caption]]` | Embed with width, alt text and caption (tooltip); each parameter is optional |
| `[[:File:X.png]]` | Link to the file page instead of embedding |
| `[[wikipedia:Page]]` | Interwiki link, using the wiki's interwiki table |

Anything in `[[...]]` that is not a valid title renders as literal text. YAML front matter between `---`
markers is hidden from output and stored as page metadata.

### Differences from wikitext link behavior

- Inside GFM table cells, `|` separates columns before links are parsed, so write `[[Page\|label]]` with a
  backslash escape: `| [[Page\|label]] |`.
- Height-only image sizes (`x100px`) are ignored; only the width of `100px` / `100x200px` is used.
- `[[Media:X]]` links land on the file description page rather than the raw file.
- Relative subpage links (`[[/sub]]`) are not resolved; the text is treated as a literal page title.
- `[[Special:...]]` links render but are not recorded in `Special:WhatLinksHere`, as in wikitext.

### Page behavior notes

- **Search** indexes the rendered prose of Markdown pages, not the raw markup, so snippets are clean. The
  index updates through the job queue when a page is edited: pages saved before NativeMarkdown was installed
  or upgraded keep their previously indexed text until their next edit or a search index rebuild.
- **Moves:** Markdown pages do not support redirects, so moving one does not leave a redirect behind at the
  old title. Inbound links need updating manually, as with any redirect-less content model.
- **Diffs, history, undo and rollback** work as they do for wikitext pages: the stored Markdown is compared
  and reverted line by line.
- **Edit conflicts** are merged three-way at the Markdown-source level; genuinely conflicting edits surface
  the normal conflict screen.

### Explicit non-goals in v1

Templates and transclusion (`{{...}}` stays literal text), parser functions and magic words, wikitext
islands, VisualEditor support, section editing, redirects on Markdown pages, and mapping front matter to
structured data.

### Roadmap (post-v1 candidates)

Transclusion; front matter mapped to structured data (Semantic MediaWiki); live side-by-side preview and
WYSIWYG editing; Obsidian-vault and git-repository import tooling (export is already free via `action=raw`);
opt-in template expansion.

## Installation

Platform requirements:

* [PHP] 8.1 or later
* [MediaWiki] 1.43 or later

**Not yet published to Packagist.** Once released, installation uses [Composer] with
[MediaWiki's built-in support for Composer][Composer install].

On the command line, go to your wiki's root directory. Then run these two commands:

```shell script
COMPOSER=composer.local.json composer require --no-update professional-wiki/native-markdown:~1.0
```
```shell script
composer update professional-wiki/native-markdown --no-dev -o
```

Then enable the extension by adding the following to the bottom of your wiki's [LocalSettings.php] file:

```php
wfLoadExtension( 'NativeMarkdown' );
```

For Markdown syntax highlighting in the editor, also install the [CodeEditor extension].

## Configuration

New pages use the Markdown content model where the wiki's configuration says so. Defaults apply to page
creation only; existing pages never change model implicitly. Individual pages can be switched between
wikitext and Markdown (in both directions) via `Special:ChangeContentModel`.

| Setting | Default | Effect |
|---|---|---|
| `$wgNativeMarkdownNamespaces` | `[]` | Namespace IDs in which new pages default to Markdown, e.g. `[ NS_HELP ]` |
| `$wgNativeMarkdownEverywhere` | `false` | New pages everywhere default to Markdown — the "Markdown wiki" mode (see exclusions below) |
| `$wgNativeMarkdownSuffixDetection` | `false` | New pages whose title ends in `.md` default to Markdown, in every namespace |
| `$wgNativeMarkdownAllowExternalImages` | `false` | Embed external `![alt](url)` images; when off they render as plain links |

`$wgNativeMarkdownEverywhere` covers the whole prose wiki, but deliberately leaves some pages as wikitext: the
discussion (Talk) namespaces, where signatures and threading depend on wikitext; the Template and MediaWiki
namespaces; and any namespace whose content model is explicitly configured elsewhere (for example a Scribunto
or JSON namespace). Titles ending in `.css`, `.js` or `.json` never default to Markdown either, since MediaWiki
reserves those for code pages. External links honor the core `$wgNoFollowLinks` setting. Input size is bounded
by core's `$wgMaxArticleSize`.

## For AI agents and LLMs

Markdown is the native read/write format of today's language models, and NativeMarkdown stores pages as
exactly that — plain Markdown, no wikitext wrapper. That makes a Markdown page directly consumable and
directly writable by an agent, with no lossy conversion step in either direction:

- **Read the source** with `action=raw`:

  ```
  GET /index.php?title=Release_Notes.md&action=raw
  ```

  returns the raw Markdown, front matter and all — the same bytes an author typed.

- **Read via the REST API**, which also reports the model:

  ```
  GET /rest.php/v1/page/Release_Notes.md
  → { "content_model": "markdown", "source": "# Release Notes\n...", ... }
  ```

- **Read the rendered HTML** with `action=parse` (`?action=parse&page=Release_Notes.md&prop=text`), for when
  an agent wants the resolved links and table of contents rather than the source.

- **Write** through the ordinary editing APIs (`action=edit`, the REST update endpoint) or, more
  conveniently, through the [MediaWiki MCP Server] — an agent hands over Markdown and it is stored verbatim,
  rendered with full wiki integration on read.

Because the round trip is lossless, an agent can fetch a page as Markdown, edit it, and write it back without
the content drifting through a wikitext translation. Full-text search indexes the rendered prose (not the raw
markup), so an agent's keyword lookups match what a reader sees rather than `#` and `**` noise.

## Comparison with other Markdown extensions

NativeMarkdown exists because no maintained extension makes Markdown a first-class content model:

- **[Extension:WikiMarkdown]** embeds Markdown blocks inside wikitext pages via a tag, plus a shallow `.md`
  content handler on top of Parsedown. Inside the Markdown there are no working `[[wiki links]]`, no category
  assignment and no MediaWiki table of contents. NativeMarkdown makes the whole page Markdown, with links,
  categories, ToC, search and link tables behaving like they do on wikitext pages.
- **[Extension:Markdown]** is archived and points visitors to WikiMarkdown.
- **[MarkdownExtraParser]** has been unmaintained for over a decade.

Related but different: our [ExternalContent extension] embeds Markdown *files from external sources* (like
GitHub) into wikitext pages, while NativeMarkdown is for the wiki's own pages being Markdown. They compose
nicely.

## Development

The Application layer (the whole Markdown pipeline) has no MediaWiki dependencies, so the unit suite runs
standalone in the extension directory with no MediaWiki install:

```shell script
composer install
composer test
```

Style checks and static analysis, also standalone: `composer cs`, `composer phpstan`, `composer psalm`.

The full suite including integration tests runs inside a MediaWiki install, from the MediaWiki root:

```shell script
php tests/phpunit/phpunit.php extensions/NativeMarkdown/tests/phpunit/
```

## Release notes

### Version 1.0.0 — not yet released

Initial release for MediaWiki 1.43+ with these features:

* Markdown content model (`markdown`) rendering CommonMark + GitHub Flavored Markdown with footnotes
* Wikitext link syntax inside Markdown: internal links, section links, categories, file embeds, interwiki
* MediaWiki integration: table of contents, red/blue links, link tables, WhatLinksHere, WantedPages/Files
* Clean full-text search: rendered prose is indexed, not raw markup; front matter excluded
* YAML front matter parsed, hidden from output and stored as page metadata
* Per-page model switching via `Special:ChangeContentModel`, namespace/suffix/wiki-wide activation modes
* XSS-safe by construction: raw HTML escaped, unsafe links blocked, external images off by default
* `action=raw` / REST return the stored Markdown byte for byte — built for AI agents and git round-trips
* CodeEditor syntax highlighting on Markdown pages

[MediaWiki]: https://www.mediawiki.org
[Professional Wiki]: https://professional.wiki/en/mediawiki-development
[PHP]: https://www.php.net
[Composer]: https://getcomposer.org
[Composer install]: https://professional.wiki/en/articles/installing-mediawiki-extensions-with-composer
[LocalSettings.php]: https://www.mediawiki.org/wiki/Manual:LocalSettings.php
[CommonMark]: https://commonmark.org
[CodeEditor extension]: https://www.mediawiki.org/wiki/Extension:CodeEditor
[MediaWiki MCP Server]: https://github.com/ProfessionalWiki/MediaWiki-MCP-Server
[Extension:WikiMarkdown]: https://www.mediawiki.org/wiki/Extension:WikiMarkdown
[Extension:Markdown]: https://www.mediawiki.org/wiki/Extension:Markdown
[MarkdownExtraParser]: https://www.mediawiki.org/wiki/Extension:MarkdownExtraParser
[ExternalContent extension]: https://github.com/ProfessionalWiki/ExternalContent

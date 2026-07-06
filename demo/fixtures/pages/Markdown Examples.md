---
description: Tour of everything NativeMarkdown supports
audience: wiki editors
---

# Markdown on this wiki

This page is stored as **native Markdown** and rendered with full wiki integration. Edit it to see the
source — or fetch it as clean Markdown via [action=raw](/index.php?title=Markdown_Examples.md&action=raw).

## Text formatting

Regular CommonMark: **bold**, *italic*, `inline code`, ~~strikethrough~~, and [external links](https://commonmark.org).

> Blockquotes work too.

```python
def hello():
    return "fenced code blocks with language info"
```

## Wiki links

- Link to a page: [[Team Onboarding.md]]
- With a label: [[Team Onboarding.md|our onboarding guide]]
- To a section: [[Team Onboarding.md#First week]]
- A page that does not exist yet: [[Style Guide.md]]
- A visible category link: [[:Category:Markdown examples]]
- An interwiki link: [[wikipedia:Markdown]]

## Images and files

Embed an uploaded file with the familiar wikitext syntax, including width, alt text and caption:

[[File:Deployment_Pipeline.png|480px|alt=Four boxes from pull request to production|The deployment pipeline]]

Link to the file page instead of embedding: [[:File:Deployment_Pipeline.png]]. Missing files render
as an upload link and are listed on Special:WantedFiles.

## Tables

| Feature | Wikitext | Markdown |
|---|---|---|
| Internal links | `[[Page]]` | `[[Page]]` (same!) |
| Bold | `'''text'''` | `**text**` |
| Headings | `== Title ==` | `## Title` |

## Task lists

- [x] Native content model
- [x] Real red and blue links
- [ ] Template support (not in v1)

## Footnotes

MediaWiki's table of contents appears automatically for pages with enough headings[^toc].

[^toc]: Four or more, same threshold as wikitext.

## Front matter

This page has a hidden YAML front matter block — view the source to see it.

[[Category:Markdown examples]]
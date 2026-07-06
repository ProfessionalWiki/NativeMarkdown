# Dogfood content

The realistic pages used for Phase 4 dogfooding and the README/announcement screenshots. They form a
small intranet: a syntax tour, onboarding, a deployment guide, release notes, an incident runbook and an
architecture overview — mixing wiki links, categories, tables, file embeds, task lists and footnotes.

`pages/` holds the page sources (page title = file name without the extension; the `.wikitext` file is the
`Category:Documentation` description). `images/` holds the two diagrams the pages embed, the wiki logo used
for the screenshots, and the scripts that generated them.

Load everything into a dev wiki (suffix detection makes the `.md` titles markdown pages):

```bash
docker compose cp docs/dogfood/pages mediawiki:/tmp/pages
docker compose cp docs/dogfood/images mediawiki:/tmp/uploads
docker compose exec -T mediawiki bash -c '
  rm /tmp/uploads/*.py /tmp/uploads/markdown-wiki-icon.png
  php maintenance/importImages.php --user=Admin /tmp/uploads
  cd /tmp/pages
  for f in *.md; do php maintenance/edit.php --user Admin "$f" < "$f"; done
  php maintenance/edit.php --user Admin "Category:Documentation" < "Category Documentation.wikitext"
  php maintenance/runJobs.php --maxjobs 500
'
```

#!/usr/bin/env bash
# Runs INSIDE the mediawiki container. Stages the demo page state for the Native Markdown
# launch recording. Invoked via ./stage.sh <cmd> from the env root; not meant to be run directly.
set -euo pipefail

MW=/var/www/html
EXT="$MW/extensions/NativeMarkdown"
DOGFOOD="$EXT/docs/dogfood"
DEMO="$EXT/recordings/demo-pages"
USER=AdminName

edit()   { php "$MW/maintenance/edit.php" --user "$USER" --summary "$2" "$1"; }
jobs()   { php "$MW/maintenance/runJobs.php" --maxjobs 500 >/dev/null; }

# Reset the demo delta to the known "before" state: Platform Architecture.md back to its
# dogfood content (no Monitoring section, Database Operations link red) and the agent-created
# page removed. Run this before every terminal take so the recorded delta is identical.
baseline() {
  edit "Platform Architecture.md" "reset to dogfood baseline" < "$DOGFOOD/pages/Platform Architecture.md"
  printf 'Database Operations.md\n' > /tmp/nm-del.txt
  php "$MW/maintenance/deleteBatch.php" -u "$USER" -r "reset demo baseline" /tmp/nm-del.txt || true
  jobs
  echo "baseline: Platform Architecture.md restored; Database Operations.md deleted."
}

# Apply the exact edit the agent makes, as a deterministic stand-in for the live terminal take:
# add a ## Monitoring section (+ Category:Monitoring) to Platform Architecture.md and create the
# Database Operations.md page its Components table links to (red link -> blue).
demo() {
  edit "Platform Architecture.md" "Add Monitoring section and category" < "$DEMO/Platform Architecture.md"
  edit "Database Operations.md"   "Create Database Operations page"      < "$DEMO/Database Operations.md"
  jobs
  echo "demo: Monitoring section added; Database Operations.md created."
}

# Pre-recording state for the captioned edit-flow video: Platform Architecture.md WITHOUT its
# leading H1 (avoids the title-appears-twice distraction in a silent video and gives a clean ToC),
# and with the Database Operations.md link left red (a real red wiki link, created live only in the
# optional red->blue GIF, not the hero video).
record() {
  edit "Platform Architecture.md" "recording baseline (no leading H1)" < "$DEMO/Platform Architecture.recording.md"
  printf 'Database Operations.md\n' > /tmp/nm-del.txt
  php "$MW/maintenance/deleteBatch.php" -u "$USER" -r "recording baseline" /tmp/nm-del.txt || true
  jobs
  echo "record: Platform Architecture.md staged without H1; Database Operations.md absent (red)."
}

# Full dogfood seed from scratch (fresh wiki only). Mirrors docs/dogfood/README.md.
seed() {
  rm -rf /tmp/nm-uploads && cp -r "$DOGFOOD/images" /tmp/nm-uploads
  rm -f /tmp/nm-uploads/*.py /tmp/nm-uploads/markdown-wiki-icon.png
  php "$MW/maintenance/importImages.php" --user=Admin /tmp/nm-uploads
  for f in "$DOGFOOD"/pages/*.md; do
    php "$MW/maintenance/edit.php" --user Admin "$(basename "$f")" < "$f"
  done
  php "$MW/maintenance/edit.php" --user Admin "Category:Documentation" < "$DOGFOOD/pages/Category Documentation.wikitext"
  jobs
  echo "seed: dogfood pages and images imported."
}

cmd="${1:-}"
case "$cmd" in
  baseline|demo|record|seed) "$cmd" ;;
  *) echo "usage: stage.sh {baseline|demo|record|seed}" >&2; exit 2 ;;
esac

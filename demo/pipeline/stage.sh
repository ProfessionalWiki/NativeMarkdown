#!/usr/bin/env bash
# Stage the Native Markdown demo page state on the local dev wiki (http://localhost:8484).
#   ./stage.sh record      # pre-recording state for the captioned edit-flow video (H1 removed, link red)
#   ./stage.sh baseline    # fixtures "before" state (with the leading H1)
#   ./stage.sh demo        # apply the demo edit as a deterministic stand-in (used for stills)
#   ./stage.sh seed        # full fixtures seed, fresh wiki only
# Runs the container-side script over `docker compose exec`. Assumes the mediawiki-dev env is up.
set -euo pipefail

ENV_ROOT="${MEDIAWIKI_DEV_ROOT:-/var/home/ccc/workspace/mediawiki-dev}"
cd "$ENV_ROOT"
exec docker compose exec -T mediawiki \
  bash /var/www/html/extensions/NativeMarkdown/demo/pipeline/container-stage.sh "${1:-}"

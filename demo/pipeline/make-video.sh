#!/usr/bin/env bash
# Post-process the raw edit-flow capture into the final demo assets:
#   burn captions (drawtext, from captions.txt)  ->  append end-card.png  ->  encode WebM + MP4 + poster.
# Captions use ffmpeg drawtext (one clean box per line) rather than libass, which draws the opaque
# box per-glyph and dips lower under space characters. Run from demo/pipeline/ after:
#   node capture/edit-flow.mjs  and  node capture/end-card.mjs
set -euo pipefail
cd "$(dirname "$0")"

SRC="assets/native-markdown-demo.nocaps.mp4"
END="assets/end-card.png"
ENDSEC="${ENDSEC:-4.0}"
FF="${FFMPEG:-ffmpeg}"
FONT="${CAPTION_FONT:-/usr/share/fonts/liberation-sans-fonts/LiberationSans-Bold.ttf}"

# Build the caption drawtext chain from captions.txt (lines: start|end|text). Text goes through
# per-caption files so apostrophes/colons/ellipses need no escaping.
CAPDIR="assets/_caps"; rm -rf "$CAPDIR"; mkdir -p "$CAPDIR"
draw=""; i=0
while IFS='|' read -r start end text; do
  case "$start" in ''|'#'*) continue;; esac
  printf '%s' "$text" > "$CAPDIR/c$i.txt"
  df="drawtext=fontfile=${FONT}:textfile=${CAPDIR}/c${i}.txt:fontcolor=white:fontsize=33:box=1:boxcolor=black@0.60:boxborderw=16:x=(w-text_w)/2:y=h-text_h-54:enable=between(t\,${start}\,${end})"
  draw="${draw:+$draw,}$df"
  i=$((i+1))
done < captions.txt

graph="[0:v]${draw},fps=25,setsar=1[main];\
[1:v]scale=1280:800,fps=25,setsar=1,fade=t=in:st=0:d=0.4[end];\
[main][end]concat=n=2:v=1:a=0,format=yuv420p[v]"

echo "-> MP4 (H.264)"
"$FF" -y -v error -i "$SRC" -loop 1 -t "$ENDSEC" -i "$END" -filter_complex "$graph" -map "[v]" \
  -c:v libx264 -crf 21 -preset slow -pix_fmt yuv420p -movflags +faststart assets/native-markdown-demo.mp4

echo "-> WebM (VP9)"
"$FF" -y -v error -i "$SRC" -loop 1 -t "$ENDSEC" -i "$END" -filter_complex "$graph" -map "[v]" \
  -c:v libvpx-vp9 -crf 32 -b:v 0 -row-mt 1 -pix_fmt yuv420p assets/native-markdown-demo.webm

echo "-> poster (clean rendered-page frame, ~1.5s)"
"$FF" -y -v error -ss 1.5 -i "$SRC" -frames:v 1 -update 1 assets/_poster.frame.png
if command -v cwebp >/dev/null 2>&1; then
  cwebp -quiet -q 90 assets/_poster.frame.png -o assets/native-markdown-demo-poster.webp
else
  "$FF" -y -v error -i assets/_poster.frame.png -c:v libwebp -quality 90 assets/native-markdown-demo-poster.webp
fi
rm -f assets/_poster.frame.png
rm -rf "$CAPDIR"

echo "done:"
for f in assets/native-markdown-demo.mp4 assets/native-markdown-demo.webm assets/native-markdown-demo-poster.webp; do
  printf "  %-42s %s bytes\n" "$f" "$(stat -c%s "$f")"
done

#!/usr/bin/env bash
# Rasterise the crest (spec 8.3.5) into package 7's icons (spec 7.1).
#
# The four PNGs in public/ are not artwork. They are renders of the two SVG
# sources in resources/icons/, which are themselves the crest of D21 with its
# tokens written as sRGB because an icon has no stylesheet. Change the crest,
# run this, commit all of it together.
#
# Headless Chrome is the renderer because it is already on the machine and it
# is the one that agrees with what a browser will show. The SVG is wrapped in
# a margin-free page rather than loaded directly: a bare .svg document gets
# Chrome's 8px body margin, which offsets every pixel.
#
# Usage: scripts/icons.sh
set -euo pipefail

cd "$(dirname "$0")/.."
chrome="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
[ -x "$chrome" ] || { echo "Google Chrome not found at $chrome" >&2; exit 1; }
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

render() { # render <source.svg> <size> <out.png>
  local src="$1" size="$2" out="$3"
  cat > "$work/page.html" <<HTML
<body style="margin:0"><img src="file://$PWD/$src" style="width:${size}px;height:${size}px;display:block"></body>
HTML
  "$chrome" --headless --disable-gpu --hide-scrollbars \
    --screenshot="$work/shot.png" --window-size="$size,$size" \
    "file://$work/page.html" >/dev/null 2>&1
  mv "$work/shot.png" "$out"
  echo "  $out  ${size}x${size}"
}

echo "rasterising the crest:"
render resources/icons/icon.svg          192 public/icon-192.png
render resources/icons/icon.svg          512 public/icon-512.png
render resources/icons/icon.svg          180 public/apple-touch-icon.png
render resources/icons/icon-maskable.svg 512 public/icon-maskable-512.png

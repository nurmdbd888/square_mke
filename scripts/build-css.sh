#!/usr/bin/env bash
# Compiles resources/css/app.css into public/css/app.css with the Tailwind
# standalone CLI (no Node needed). The binary is downloaded once into .tools/.
#
#   scripts/build-css.sh          build minified CSS
#   scripts/build-css.sh --watch  rebuild on every change while developing
set -euo pipefail

TAILWIND_VERSION="v4.3.3"

cd "$(dirname "$0")/.."

case "$(uname -s)-$(uname -m)" in
    Linux-x86_64) asset="tailwindcss-linux-x64" ;;
    Linux-aarch64 | Linux-arm64) asset="tailwindcss-linux-arm64" ;;
    Darwin-arm64) asset="tailwindcss-macos-arm64" ;;
    Darwin-x86_64) asset="tailwindcss-macos-x64" ;;
    MINGW* | MSYS* | CYGWIN*) asset="tailwindcss-windows-x64.exe" ;;
    *) echo "Unsupported platform: $(uname -s)-$(uname -m)" >&2; exit 1 ;;
esac

bin=".tools/tailwindcss-${TAILWIND_VERSION}"
if [ ! -x "$bin" ]; then
    mkdir -p .tools
    echo "Downloading Tailwind CSS ${TAILWIND_VERSION} (${asset})..."
    curl -fsSL -o "$bin" "https://github.com/tailwindlabs/tailwindcss/releases/download/${TAILWIND_VERSION}/${asset}"
    chmod +x "$bin"
fi

if [ "${1:-}" = "--watch" ]; then
    exec "$bin" -i resources/css/app.css -o public/css/app.css --watch
fi

"$bin" -i resources/css/app.css -o public/css/app.css --minify

#!/usr/bin/env bash
# Builds timeguardian-<version>.zip (top level folder "timeguardian") ready to unpack into custom_apps/.
# Usage: bin/pack.sh [output-dir]   (default: ./dist)
set -euo pipefail
cd "$(dirname "$0")/.."
ROOT="$(pwd)"
OUT="${1:-$ROOT/dist}"
VERSION="$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' appinfo/info.xml | head -n1)"
[ -n "$VERSION" ] || { echo "cannot read version from appinfo/info.xml" >&2; exit 1; }

# refuse to pack broken PHP or failing tests
if command -v php >/dev/null 2>&1; then
	find lib appinfo -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
	php tests/run.php >/dev/null
fi

STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
DEST="$STAGE/timeguardian"
mkdir -p "$DEST" "$OUT"
for item in appinfo lib l10n js css img templates docs LICENSE README.md CHANGELOG.md; do
	[ -e "$item" ] && cp -R "$item" "$DEST/"
done
find "$DEST" -name '.DS_Store' -delete

# release hygiene: nothing from the development environment may leak into the package
if grep -rliE "$(printf 'dan%s|mich%s|mik%s' iele elin web)" "$DEST" >/dev/null 2>&1; then
	echo "forbidden reference found in package:" >&2
	grep -rliE "$(printf 'dan%s|mich%s|mik%s' iele elin web)" "$DEST" >&2
	exit 1
fi

ZIP="$OUT/timeguardian-$VERSION.zip"
rm -f "$ZIP"
(cd "$STAGE" && zip -qr -X "$ZIP" timeguardian)
echo "$ZIP"
unzip -l "$ZIP" | tail -n 1

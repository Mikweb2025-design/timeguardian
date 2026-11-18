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
rm -rf "$DEST/docs/screenshots"
find "$DEST" -name '.DS_Store' -delete

# release hygiene: nothing from the development environment may leak into the package
# (the public repository URL in info.xml and the README is allowed)
REPO_URL='https://github.com/Mikweb2025-design/timeguardian'
PATTERN="$(printf 'dan%s|mich%s|mik%s' iele elin web)"
LEAK=0
while IFS= read -r -d '' f; do
	if sed "s#$REPO_URL##g" "$f" | grep -qiE "$PATTERN"; then echo "forbidden reference in: $f" >&2; LEAK=1; fi
done < <(find "$DEST" -type f -print0)
[ "$LEAK" = 0 ] || exit 1

ZIP="$OUT/timeguardian-$VERSION.zip"
rm -f "$ZIP"
(cd "$STAGE" && zip -qr -X "$ZIP" timeguardian)
echo "$ZIP"
unzip -l "$ZIP" | tail -n 1

# App Store archive: tar.gz with exactly one top-level folder "timeguardian", no .git
TGZ="$OUT/timeguardian-$VERSION.tar.gz"
rm -f "$TGZ"
(cd "$STAGE" && COPYFILE_DISABLE=1 tar -czf "$TGZ" timeguardian)
echo "$TGZ"

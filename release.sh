#!/usr/bin/env bash
# Publish a HomeFlip Connector release.
#
#   bash homeflip-connector/release.sh            # version read from the plugin header
#
# 1. Builds dist/homeflip-connector-X.Y.Z.zip (top folder homeflip-connector/)
# 2. Pushes the homeflip-connector/ folder history to github.com/wbxprops/homeflip-connector
#    main, via `git subtree split` (the source of truth stays in ai-projects)
# 3. Creates GitHub release vX.Y.Z with homeflip-connector.zip + info.json, which is what
#    every site's updater reads (includes/updater.php)
# 4. Copies the zip to ~/Downloads for a manual install
#
# Commit the connector changes in ai-projects FIRST: subtree split publishes commits,
# not the working tree. The script refuses to run with uncommitted connector changes.
set -euo pipefail

REPO=wbxprops/homeflip-connector
ROOT=$(git -C "$(dirname "$0")" rev-parse --show-toplevel)
DIR="$ROOT/homeflip-connector"
cd "$ROOT"

VERSION=$(grep -m1 -E '^\s*\*\s*Version:' "$DIR/homeflip-connector.php" | awk '{print $NF}' | tr -d '\r')
TAG="v$VERSION"
echo "Releasing $TAG"

if [ -n "$(git status --porcelain -- homeflip-connector ':!homeflip-connector/dist')" ]; then
	echo "Uncommitted changes under homeflip-connector/ -- commit them first." >&2
	exit 1
fi
if gh release view "$TAG" --repo "$REPO" >/dev/null 2>&1; then
	echo "$TAG already released. Bump Version: in homeflip-connector.php." >&2
	exit 1
fi

# 1. zip (python: Git Bash on Windows has no zip)
mkdir -p "$DIR/dist"
ZIP="$DIR/dist/homeflip-connector-$VERSION.zip"
python - "$DIR" "$ZIP" <<'PY'
import os, sys, zipfile
src, out = sys.argv[1], sys.argv[2]
skip_dirs = {'dist', '.git', 'tools'}
skip_files = {'release.sh'}
with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
    for base, dirs, files in os.walk(src):
        dirs[:] = [d for d in dirs if d not in skip_dirs]
        for f in files:
            if f in skip_files:
                continue
            full = os.path.join(base, f)
            z.write(full, 'homeflip-connector/' + os.path.relpath(full, src).replace(os.sep, '/'))
print('built', out)
PY

# 2. code -> GitHub main
git subtree split --prefix=homeflip-connector -b connector-publish >/dev/null 2>&1
git push "https://github.com/$REPO.git" connector-publish:main
git branch -D connector-publish >/dev/null

# 3. release + update manifest
TMP=$(mktemp -d)
cp "$ZIP" "$TMP/homeflip-connector.zip"
cat > "$TMP/info.json" <<JSON
{
  "version": "$VERSION",
  "package": "https://github.com/$REPO/releases/download/$TAG/homeflip-connector.zip",
  "requires": "6.0",
  "requires_php": "7.4"
}
JSON
gh release create "$TAG" "$TMP/homeflip-connector.zip" "$TMP/info.json" \
	--repo "$REPO" --target main --title "$TAG" --notes "HomeFlip Connector $VERSION"

# 4. manual-install copy
cp "$ZIP" "$HOME/Downloads/"
echo "Done: $TAG published; $(basename "$ZIP") copied to Downloads."

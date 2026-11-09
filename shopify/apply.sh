#!/usr/bin/env bash
# Copies the rescue snippets and section into a Dawn-based theme folder and
# wires them into layout/theme.liquid and config/settings_schema.json.
# Safe to run twice. Review the diff (git diff) before pushing the theme.
#
#   ./shopify/apply.sh path/to/theme
set -euo pipefail

THEME="${1:?usage: apply.sh path/to/theme}"
HERE="$(cd "$(dirname "$0")" && pwd)"
LAYOUT="$THEME/layout/theme.liquid"
SCHEMA="$THEME/config/settings_schema.json"

[ -f "$LAYOUT" ] || { echo "Not a theme folder: $LAYOUT missing"; exit 1; }

cp "$HERE"/theme/snippets/*.liquid "$THEME/snippets/"
cp "$HERE"/theme/sections/*.liquid "$THEME/sections/"

# <head>: hero preload as early as possible, structured data before </head>.
if ! grep -q "render 'hv-hero-preload'" "$LAYOUT"; then
  perl -0pi -e "s|(<meta charset=\"utf-8\">)|\$1\n    {%- render 'hv-hero-preload' -%}|" "$LAYOUT"
fi
if ! grep -q "render 'hv-structured-data'" "$LAYOUT"; then
  perl -0pi -e "s|(\s*</head>)|\n    {% render 'hv-structured-data' %}\$1|" "$LAYOUT"
fi
# Before </body>: the support chat widget.
if ! grep -q "render 'hv-support-bot'" "$LAYOUT"; then
  perl -0pi -e "s|(\s*</body>)|\n    {% render 'hv-support-bot' %}\$1|" "$LAYOUT"
fi

# Theme settings: append the "Site rescue" group once.
if ! grep -q '"Site rescue"' "$SCHEMA"; then
  node -e '
    const fs = require("fs");
    const [schemaPath, extraPath] = process.argv.slice(1);
    const schema = JSON.parse(fs.readFileSync(schemaPath, "utf8"));
    const extra = JSON.parse(fs.readFileSync(extraPath, "utf8"));
    fs.writeFileSync(schemaPath, JSON.stringify(schema.concat(extra), null, 2) + "\n");
  ' "$SCHEMA" "$HERE/theme/settings_schema.hv.json"
fi

echo "Applied. Next: review 'git diff', then see PLAYBOOK.md for the per-section image fixes."

#!/bin/sh
# Builds the Harbourview Bakery site as the client handed it over.
# Runs inside the wp-cli container (see docker-compose.yml, service cli-*).
# Used for BOTH sites. The AFTER site then runs after/rescue.sh on top.
set -eu

: "${SITE_URL:?SITE_URL is required}"
ADMIN_PASSWORD="${WP_ADMIN_PASSWORD:-local-only-admin}"

echo "Waiting for WordPress files..."
i=0
until [ -f /var/www/html/wp-config.php ]; do
  i=$((i + 1)); [ "$i" -gt 60 ] && { echo "wp-config.php never appeared"; exit 1; }
  sleep 2
done
until wp db check --quiet >/dev/null 2>&1; do sleep 2; done

if wp core is-installed 2>/dev/null; then
  echo "Already installed. Run 'make reset' for a clean rebuild."
  exit 0
fi

wp core install \
  --url="$SITE_URL" \
  --title="Harbourview Bakery" \
  --admin_user=admin \
  --admin_password="$ADMIN_PASSWORD" \
  --admin_email=owner@harbourview-bakery.example \
  --skip-email

wp option update blogdescription "Fresh bread, pastries & custom cakes in Toronto"
wp option update timezone_string "America/Toronto"
wp option update date_format "F j, Y"
wp rewrite structure '/%postname%/' --hard
# Left on from the staging site: "Discourage search engines from indexing".
# This adds noindex to every page and switches off the XML sitemap.
# (The demo copy keeps it that way too: the business is fictional.)
wp option update blog_public 0

# Remove the default sample content.
wp post delete 1 2 3 --force >/dev/null 2>&1 || true

# --- Theme: a big multipurpose theme plus the old child theme --------------
wp theme install oceanwp --version=4.2.8
cp -R /work/before/theme/harbourview-child /var/www/html/wp-content/themes/harbourview-child
wp theme activate harbourview-child
wp theme delete twentytwentythree twentytwentyfour twentytwentyfive >/dev/null 2>&1 || true

# --- Plugins (all free, from wordpress.org, versions pinned) ---------------
# Page builder plus an add-on pack used for a single widget, long ago.
wp plugin install elementor --version=4.3.5 --activate
wp plugin install essential-addons-for-elementor-lite --version=6.8.8 --activate
# Forms.
wp plugin install contact-form-7 --version=6.2.4.1 --activate
# Newsletter popup.
wp plugin install popup-maker --version=1.25.0 --activate
# Social icons in the footer (icon font plus its own CSS and JS).
wp plugin install social-icons-widget-by-wpzoom --version=5.0.0 --activate
# A slider plugin that was tried once and never removed.
wp plugin install ml-slider --version=3.114.0 --activate
# A second copy of Font Awesome, loaded from a CDN.
wp plugin install font-awesome --version=5.2.1 --activate
# Header and footer code snippets plugin, installed "just in case".
wp plugin install insert-headers-and-footers --version=2.4.1 --activate
# Inactive leftovers that ship with WordPress.
wp plugin list --status=inactive --field=name

# Elementor settings as most sites have them: Font Awesome icon fonts,
# Google Fonts from Google's servers, default font-display.
wp option update elementor_disable_color_schemes yes >/dev/null
wp option update elementor_disable_typography_schemes yes >/dev/null
wp option update elementor_onboarded 1 >/dev/null

# OceanWP: icon font set (Simple Line Icons) and a gold accent colour.
wp theme mod set ocean_primary_color '#c9a96e'
wp theme mod set ocean_theme_default_icons sili

# --- Content -----------------------------------------------------------------
wp eval-file /work/seed/build-content.php

wp elementor flush-css >/dev/null 2>&1 || true
wp rewrite flush --hard
echo "Seed complete: $SITE_URL"

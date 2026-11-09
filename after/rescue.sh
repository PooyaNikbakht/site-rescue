#!/bin/sh
# The site rescue, as a script. Runs inside the wp-cli container on the AFTER
# site, after seed/seed.sh has built the same site the client handed over.
# Each step matches a section of CASE-STUDY.md.
set -eu
WP_ROOT=/var/www/html

if wp option get hv_rescue_done >/dev/null 2>&1; then
  echo "Rescue already applied. Run 'make reset' for a clean rebuild."
  exit 0
fi

echo "== 1. Plugins: remove what the site does not use =="
# Essential Addons: no widgets in use. Popup Maker: newsletter popup with no
# newsletter behind it. WPZOOM icons: replaced by two text links. MetaSlider:
# never used. Font Awesome plugin: second copy of an icon font, from a CDN.
# WPCode: empty. Akismet and Hello Dolly: inactive leftovers.
for p in essential-addons-for-elementor-lite popup-maker social-icons-widget-by-wpzoom ml-slider font-awesome insert-headers-and-footers; do
  wp plugin deactivate "$p" --quiet || true
  wp plugin delete "$p" --quiet || true
done
wp plugin delete akismet hello --quiet || true
wp post delete $(wp post list --post_type=popup,popup_theme --format=ids) --force --quiet 2>/dev/null || true

echo "== 2. Theme: the cleaned child theme and the parent theme's own performance switches =="
rm -rf "$WP_ROOT/wp-content/themes/harbourview-child"
cp -R /work/after/theme/harbourview-child "$WP_ROOT/wp-content/themes/harbourview-child"
wp theme mod set ocean_theme_default_icons svg            # inline SVG icons instead of an icon font
wp theme mod set ocean_performance_fontawesome disabled
wp theme mod set ocean_performance_simple_line_icons disabled
wp theme mod set ocean_performance_lightbox disabled
wp theme mod set ocean_performance_scroll_effect disabled
wp theme mod set ocean_performance_custom_select disabled
wp theme mod set ocean_performance_emoji disabled
wp theme mod set ocean_primary_color '#6b4423'
wp theme mod set ocean_top_bar 0 --quiet                  # empty top bar: 41px of blank space on phones

echo "== 3. Must-use plugins: performance, images, SEO, mail, embeds =="
mkdir -p "$WP_ROOT/wp-content/mu-plugins"
cp /work/after/mu-plugins/*.php "$WP_ROOT/wp-content/mu-plugins/"

echo "== 4. Elementor: no Google Fonts from Google, inline per-page CSS =="
wp option update elementor_google_font 0
wp option update elementor_font_display swap
wp option update elementor_css_print_method internal

echo "== 5. Images: scale to 2560px, convert to WebP, generate srcset sizes =="
wp eval-file /work/after/scripts/convert-images.php

echo "== 6. Content: hero, image sizes, alt text, headings, links, form =="
wp eval-file /work/after/scripts/fix-content.php

echo "== 7. SEO: let search engines in, XML sitemap on =="
wp option update blog_public 1

echo "== 8. Caching: page cache plugin + browser caching headers =="
wp plugin install cache-enabler --version=1.8.17 --activate
wp config set WP_CACHE true --raw --type=constant
wp option update cache_enabler '{"version":"1.8.17","use_trailing_slashes":1,"permalink_structure":"has_trailing_slash","cache_expires":0,"cache_expiry_time":0,"clear_site_cache_on_saved_post":1,"clear_site_cache_on_saved_comment":0,"clear_site_cache_on_saved_term":0,"clear_site_cache_on_saved_user":0,"clear_site_cache_on_changed_plugin":1,"convert_image_urls_to_webp":0,"mobile_cache":0,"compress_cache":1,"minify_html":1,"minify_inline_css_js":0,"excluded_post_ids":"","excluded_page_paths":"","excluded_query_strings":"","excluded_cookies":""}' --format=json
# Browser caching rules go above the WordPress block in .htaccess.
{ cat /work/after/htaccess-caching.conf; cat "$WP_ROOT/.htaccess"; } > /tmp/htaccess.new
cat /tmp/htaccess.new > "$WP_ROOT/.htaccess"

echo "== 9. Clean up =="
wp elementor flush-css >/dev/null 2>&1 || true
wp cache-enabler clear >/dev/null 2>&1 || true
wp rewrite flush --hard >/dev/null 2>&1 || true
# Demo copy only: the bakery is fictional, so keep it out of search engines.
# On a real client site, step 7 stays as it is. Measurements were taken with
# indexing on.
wp option update blog_public 0
wp option update hv_rescue_done "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
wp plugin list --fields=name,status,version
echo "Rescue complete: $SITE_URL"

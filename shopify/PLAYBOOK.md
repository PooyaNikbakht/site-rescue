# Shopify rescue playbook

The same job as the WordPress rescue, for a store on Dawn or a Dawn-based
theme. Shopify handles hosting, the CDN, image resizing and page caching, so
the problems are different: almost all of them come from **apps**, **theme
edits** and **images used at the wrong size**.

Status: the code in this folder is ready and has been wired into a copy of
Dawn 16 with `apply.sh` and checked with `shopify theme check` (0 errors;
the only warnings on our files are "snippet not referenced yet" for the two
snippets you wire into sections by hand, and a suggestion to use
`image_tag`'s `preload: true` instead of the hero preload snippet, which is a
good alternative when the hero section is fully under your control). Measuring it on a real store needs a free Shopify
development store, which needs the owner's Partner account. See
[SHOPIFY-TODO.md](SHOPIFY-TODO.md).

## 1. Audit (half a day)

1. **Measure first.** Lighthouse mobile and desktop, 3 runs each, on the home
   page, a collection page and a product page. Same script as the WordPress
   part: `MEASURE_SITES` and `MEASURE_PAGES` in `scripts/measure.mjs`
   (see SHOPIFY-TODO.md). Also pull the store's real-user Core Web Vitals
   from Online Store > Themes > "Web performance" (Shopify's report), which
   is field data and matters more than the lab numbers.
2. **List every script on the page.** Chrome DevTools > Network > JS, sorted
   by domain. For each third-party domain write down which app it belongs to
   and whether that app is still installed and still used.
3. **List installed apps** (Settings > Apps) and **app embeds** (Online
   Store > Themes > Customize > App embeds). Apps that were uninstalled often
   leave code behind in `layout/theme.liquid`, `snippets/` and
   `templates/*.json`. Search the theme for their domain names.
4. **Check images.** In DevTools, compare each image's natural size with its
   rendered size. Common findings: hero uploaded at 5000px and requested with
   `image_url` and no width, `img_url: 'master'` in older custom sections,
   missing `width`/`height`, lazy loading on the hero.
5. **Fonts.** Count families and weights in Theme settings > Typography plus
   any `@font-face` or Google Fonts links added by hand or by apps.

## 2. Images

- Use `snippets/hv-responsive-image.liquid` in any custom section that prints
  an `<img>` by hand. It gives `srcset`, `sizes`, `width`/`height`, lazy
  loading below the fold and `fetchpriority="high"` for the hero.
- Dawn's own sections already do most of this. The usual fixes are in
  sections a previous developer added, and in Dawn's `image-banner` /
  `slideshow` when the first section is not the banner (Dawn only marks the
  first section as eager).
- Re-export images that are huge (over 3000px wide) or PNG photos. Shopify
  converts to WebP/AVIF on the fly, but a 20 MB source still slows the admin
  and the first CDN transform.

## 3. Hero image preload

`snippets/hv-hero-preload.liquid` adds a `<link rel="preload">` for the
home hero with the same widths Dawn's banner uses. Pick the image in Theme
settings > Site rescue > Home hero image. Check the Network panel afterwards:
the hero should download **once**. If it downloads twice, the widths or
`imagesizes` do not match the section (we hit exactly this on the WordPress
site and removed the preload there).

## 4. Apps and third-party scripts

- **Remove unused apps** and then search the theme for their leftovers
  (`grep -ri "appdomain.com" .` on a pulled theme). Delete the snippets and
  the `render` tags that load them.
- **App embeds:** turn off the ones that are not used. Shopify loads every
  enabled app embed on every page.
- **Scripts pasted into theme.liquid** (review widgets, chat, popups) can be
  delayed until the visitor interacts with
  `snippets/hv-deferred-script.liquid`. Do not do this for scripts that must
  run before the first paint (A/B testing, consent banners required by law)
  or for analytics the client relies on without agreeing to it first.
- Prefer apps that use **theme app extensions** over apps that inject
  `ScriptTag`s: they can be switched off per theme without code.

## 5. Structured data

`snippets/hv-structured-data.liquid` prints `Organization` on every page and
optionally `Product` with offers per variant. Dawn 15+ already prints Product
data in `sections/main-product.liquid` (`{{ product | structured_data }}`),
so ours is off by default. Validate with Google's Rich Results Test after
the store is public.

## 6. Adding features without a rebuild

- **Support chat:** `snippets/hv-support-bot.liquid` loads the docs-chat
  widget with `defer`, configured from theme settings so the owner can turn
  it off. On a live store the widget and API must be on HTTPS. The cleaner
  long-term option is the **theme app embed** in `app-embed/blocks/`, which
  needs a Shopify app created from the owner's Partner account.
- **Order ahead / Book now:** `sections/hv-order-ahead.liquid` is a section
  the owner can add to any page in the theme editor. It shows a fallback
  until an ordering widget script is set, then takes the script URL from
  section settings. No code change needed to switch it on.

## 7. Apply and verify

```bash
shopify theme pull --store <store>.myshopify.com --path theme   # or start from Dawn
./shopify/apply.sh theme
shopify theme check --path theme
shopify theme dev --store <store>.myshopify.com --path theme     # http://127.0.0.1:9292
```

Then re-run the measurements from section 1 and compare.

## Typical scope and price

| Job | Time | Fixed price |
|-----|------|-------------|
| Audit with written report and numbers | 0.5 day | CAD 400 |
| Rescue (apps clean-up, images, preload, fonts, schema) | 1.5 to 2.5 days | CAD 1,200 to 2,000 |
| Support chat or ordering widget added | 0.5 day each | CAD 400 each |

Prices assume a single Dawn-based theme with up to about 15 apps. Heavily
customised themes or headless stores are quoted separately.

# Shopify: steps that need the owner

Everything that could be done without an account is done: the playbook, the
Liquid snippets and section, the app embed block, and `apply.sh`, which has
been run against a clean copy of Dawn 16 (public on GitHub). What is left
needs a Shopify login, so it was not attempted. No accounts were created.

## 1. Owner: create the accounts (about 10 minutes)

1. Sign up for a free **Shopify Partner** account at
   https://www.shopify.com/partners (use a personal email).
2. In the Partner Dashboard: **Stores > Add store > Create development
   store**. Purpose: "Create a store to test and build". Name it something
   like `harbourview-rescue-demo`. Development stores are free and have no
   time limit.
3. Note the store URL: `harbourview-rescue-demo.myshopify.com`.
4. Install the Shopify CLI on the laptop (needs Node 22, already set up):

   ```bash
   export PATH="$HOME/.nvm/versions/node/v22.21.0/bin:$PATH"
   npm install -g @shopify/cli --registry=https://registry.npmjs.org/
   shopify version
   ```

5. Log in once (opens a browser):

   ```bash
   shopify auth logout   # only if another account was used before
   shopify theme list --store harbourview-rescue-demo.myshopify.com
   ```

That is all the owner has to do by hand. The rest can be done in a later
session.

## 2. Later session: build the BEFORE store

1. In the dev store admin, add the bakery content as products (sourdough,
   croissant, cinnamon bun, cookies, celebration cake, baguette) with the
   full-size photos from `make photos` (`build/photos/`, credits in
   `CREDITS.md`). Product CSV import
   (Products > Import) is the fastest way; write the CSV from
   `seed/build-content.php` prices.
2. Recreate the usual problems on a duplicate of Dawn named "BEFORE":
   - a custom hero section that uses `image_url` without a width and no
     `width`/`height` attributes
   - three or four free apps with app embeds enabled (a reviews app, a
     popup/newsletter app, a social feed app, a chat app), then uninstall one
     and leave its snippet in `theme.liquid` (exactly what happens in real
     stores)
   - extra Google Fonts added through a custom `<link>` in `theme.liquid`
3. Write every step down in `shopify/BEFORE-SETUP.md` so it can be repeated.

## 3. Later session: apply the rescue to an AFTER theme

```bash
cd site-rescue
shopify theme pull --store harbourview-rescue-demo.myshopify.com --theme "AFTER" --path build/shopify-after
./shopify/apply.sh build/shopify-after
shopify theme check --path build/shopify-after
shopify theme push --store harbourview-rescue-demo.myshopify.com --theme "AFTER" --path build/shopify-after --unpublished
```

Then: pick the hero image in Theme settings > Site rescue, turn off unused
app embeds, uninstall unused apps, and remove their leftover snippets.

## 4. Later session: measure

Development stores always have a storefront password, which blocks
Lighthouse on the public URL. `shopify theme dev` serves a theme on a local
port through the owner's login, without the password page. Run two at once:

```bash
shopify theme dev --store harbourview-rescue-demo.myshopify.com --theme "BEFORE" --port 9292
shopify theme dev --store harbourview-rescue-demo.myshopify.com --theme "AFTER"  --port 9293
```

Theme dev injects its own hot-reload script, so both themes measure slightly
worse than they would in production. The comparison is still fair because
both carry the same overhead; say so in the write-up. For production
numbers, measure again after the client's real store goes live, and use
Shopify's Web performance report for field data.

Then run the same Lighthouse script as the WordPress part (3 runs, median):

```bash
export PATH="$HOME/.nvm/versions/node/v22.21.0/bin:$PATH"
MEASURE_SITES='{"before":"http://127.0.0.1:9292","after":"http://127.0.0.1:9293"}' \
MEASURE_PAGES='{"home":"/","collection":"/collections/all","product":"/products/country-sourdough"}' \
MEASURE_OUT=reports/shopify \
node scripts/measure.mjs
```

Ports 9292 and 9293 are not used by any other local project.

## 5. Later session: write it up

Add a Shopify section to CASE-STUDY.md with the before/after table from
`reports/shopify/SUMMARY.md`, screenshots of both themes, and the app list
before and after.

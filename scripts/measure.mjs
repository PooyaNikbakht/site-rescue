// Lighthouse measurements for BEFORE (8881) and AFTER (8882).
//
// Same pages, same machine, same Chrome, interleaved runs so both sites see
// the same conditions. 3 runs per page and form factor, the median run is
// reported. Lab data only: see CASE-STUDY.md for what that does and does not
// tell you.
//
//   node scripts/measure.mjs            full run (36 Lighthouse runs)
//   RUNS=1 node scripts/measure.mjs     quick check
//
// Output:
//   reports/lighthouse/raw/*.json       every run, trimmed (no screenshots)
//   reports/lighthouse/html/*.html      HTML report of each median run
//   reports/summary.json, reports/SUMMARY.md

import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import * as chromeLauncher from 'chrome-launcher';
import lighthouse, { desktopConfig } from 'lighthouse';
import { ReportGenerator } from 'lighthouse/report/generator/report-generator.js';

const ROOT = new URL('..', import.meta.url).pathname;
const OUT = process.env.MEASURE_OUT ? join(ROOT, process.env.MEASURE_OUT) : join(ROOT, 'reports');
const RUNS = Number(process.env.RUNS || 3);
const CHROME_PATH =
  process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';

// Defaults measure the two local WordPress sites. For other sites (e.g. the
// Shopify part) pass JSON maps, keys must be "before" and "after":
//   MEASURE_SITES='{"before":"https://a.example","after":"https://b.example"}'
//   MEASURE_PAGES='{"home":"/","product":"/products/x"}'
//   MEASURE_OUT=reports/shopify
const SITES = process.env.MEASURE_SITES
  ? JSON.parse(process.env.MEASURE_SITES)
  : { before: 'http://localhost:8881', after: 'http://localhost:8882' };
const PAGES = process.env.MEASURE_PAGES
  ? JSON.parse(process.env.MEASURE_PAGES)
  : { home: '/', menu: '/menu/', post: '/how-to-feed-a-sourdough-starter/' };
const IS_LOCAL_WP = !process.env.MEASURE_SITES;
const FORM_FACTORS = ['mobile', 'desktop'];

const METRICS = {
  lcp: 'largest-contentful-paint',
  fcp: 'first-contentful-paint',
  cls: 'cumulative-layout-shift',
  tbt: 'total-blocking-time',
  si: 'speed-index',
};

mkdirSync(join(OUT, 'lighthouse/raw'), { recursive: true });
mkdirSync(join(OUT, 'lighthouse/html'), { recursive: true });

const median = (xs) => {
  const s = [...xs].sort((a, b) => a - b);
  return s[Math.floor(s.length / 2)];
};

function summarise(lhr) {
  const a = lhr.audits;
  const resources = Object.fromEntries(
    (a['resource-summary']?.details?.items || []).map((i) => [i.resourceType, i]),
  );
  return {
    performance: Math.round(lhr.categories.performance.score * 100),
    accessibility: Math.round(lhr.categories.accessibility.score * 100),
    bestPractices: Math.round(lhr.categories['best-practices'].score * 100),
    seo: Math.round(lhr.categories.seo.score * 100),
    ...Object.fromEntries(Object.entries(METRICS).map(([k, id]) => [k, a[id]?.numericValue ?? null])),
    totalBytes: a['total-byte-weight']?.numericValue ?? null,
    requests: resources.total?.requestCount ?? null,
    imageBytes: resources.image?.transferSize ?? 0,
    imageRequests: resources.image?.requestCount ?? 0,
    scriptBytes: resources.script?.transferSize ?? 0,
    scriptRequests: resources.script?.requestCount ?? 0,
    stylesheetBytes: resources.stylesheet?.transferSize ?? 0,
    stylesheetRequests: resources.stylesheet?.requestCount ?? 0,
    fontBytes: resources.font?.transferSize ?? 0,
    fontRequests: resources.font?.requestCount ?? 0,
    thirdPartyRequests: resources['third-party']?.requestCount ?? 0,
    renderBlocking: a['render-blocking-insight']?.details?.items?.length ?? a['render-blocking-resources']?.details?.items?.length ?? null,
  };
}

// Drop the screenshots and other heavy fields so raw reports stay small.
function trim(lhr) {
  const copy = structuredClone(lhr);
  // Keep the audit entries (the HTML report expects them), drop the images.
  for (const id of ['final-screenshot', 'screenshot-thumbnails', 'full-page-screenshot']) {
    if (copy.audits[id]) copy.audits[id] = stubAudit(copy.audits[id]);
  }
  delete copy.fullPageScreenshot;
  // Keep the report UI strings (the HTML renderer needs them), drop the rest.
  if (copy.i18n) copy.i18n = { rendererFormattedStrings: copy.i18n.rendererFormattedStrings };
  delete copy.timing;
  return copy;
}

function stubAudit(a) {
  return { id: a.id, title: a.title, description: a.description || '', score: null, scoreDisplayMode: 'notApplicable', metricSavings: {} };
}

// Renders the HTML report for each median run from the trimmed raw JSON.
// `node scripts/measure.mjs --html-only` rebuilds them without re-measuring.
function renderHtml(lhr, key) {
  const copy = structuredClone(lhr);
  for (const ref of Object.values(copy.categories).flatMap((c) => c.auditRefs)) {
    if (!copy.audits[ref.id]) copy.audits[ref.id] = stubAudit({ id: ref.id, title: ref.id });
  }
  for (const a of Object.values(copy.audits)) a.metricSavings ||= {};
  writeFileSync(join(OUT, 'lighthouse/html', `${key}.html`), ReportGenerator.generateReportHtml(copy));
}

if (process.argv.includes('--html-only')) {
  const prev = JSON.parse(readFileSync(join(OUT, 'summary.json'), 'utf8'));
  for (const [key, p] of Object.entries(prev.pages)) {
    renderHtml(JSON.parse(readFileSync(join(OUT, 'lighthouse/raw', `${key}-run${p.medianRun}.json`), 'utf8')), key);
    console.log(`rendered ${key}`);
  }
  process.exit(0);
}

async function warm(url) {
  // Two plain requests with a browser Accept header: fills the AFTER page
  // cache and the PHP opcode cache on both sites. Fair to both.
  for (let i = 0; i < 2; i++) {
    await fetch(url, { headers: { Accept: 'text/html,application/xhtml+xml', 'Accept-Encoding': 'gzip' } });
  }
}

function wpCount(site, args) {
  try {
    const out = execFileSync('docker', ['compose', 'run', '--rm', '-T', `cli-${site}`, 'wp', ...args], {
      cwd: ROOT,
      stdio: ['ignore', 'pipe', 'ignore'],
    });
    return Number(String(out).trim());
  } catch {
    return null;
  }
}

const chrome = await chromeLauncher.launch({
  chromePath: CHROME_PATH,
  chromeFlags: ['--headless=new', '--no-first-run', '--disable-extensions'],
});

const results = {};
try {
  for (const [pageKey, path] of Object.entries(PAGES)) {
    for (const ff of FORM_FACTORS) {
      for (let run = 1; run <= RUNS; run++) {
        for (const [site, base] of Object.entries(SITES)) {
          const url = base + path;
          await warm(url);
          const flags = { port: chrome.port, output: 'json', logLevel: 'error' };
          const config = ff === 'desktop' ? desktopConfig : undefined;
          const { lhr } = await lighthouse(url, flags, config);
          const s = summarise(lhr);
          const key = `${site}-${pageKey}-${ff}`;
          (results[key] ||= { site, page: pageKey, formFactor: ff, url, runs: [] }).runs.push({ run, ...s, lhr });
          writeFileSync(join(OUT, 'lighthouse/raw', `${key}-run${run}.json`), JSON.stringify(trim(lhr)));
          console.log(
            `${key} run ${run}: perf ${s.performance}, LCP ${(s.lcp / 1000).toFixed(1)}s, CLS ${s.cls.toFixed(3)}, TBT ${Math.round(s.tbt)}ms, ${(s.totalBytes / 1e6).toFixed(2)} MB, ${s.requests} req`,
          );
        }
      }
    }
  }
} finally {
  await chrome.kill();
}

// Median by performance score (ties broken by LCP), as Lighthouse recommends.
const summary = { generatedAt: new Date().toISOString(), runsPerPage: RUNS, lighthouseVersion: null, chrome: null, pages: {} };
for (const [key, r] of Object.entries(results)) {
  const sorted = [...r.runs].sort((a, b) => a.performance - b.performance || b.lcp - a.lcp);
  const med = sorted[Math.floor(sorted.length / 2)];
  summary.lighthouseVersion = med.lhr.lighthouseVersion;
  summary.chrome = med.lhr.environment?.hostUserAgent;
  const { lhr, run, ...values } = med;
  // Each metric also gets its own median across runs.
  const perMetric = {};
  for (const m of Object.keys(values)) perMetric[m] = median(r.runs.map((x) => x[m]));
  summary.pages[key] = { site: r.site, page: r.page, formFactor: r.formFactor, url: r.url, medianRun: run, values, perMetricMedian: perMetric };
  renderHtml(trim(lhr), key);
}

summary.wordpress = {};
for (const site of IS_LOCAL_WP ? Object.keys(SITES) : []) {
  summary.wordpress[site] = {
    activePlugins: wpCount(site, ['plugin', 'list', '--status=active', '--format=count']),
    inactivePlugins: wpCount(site, ['plugin', 'list', '--status=inactive', '--format=count']),
    mustUsePlugins: wpCount(site, ['plugin', 'list', '--status=must-use', '--format=count']),
  };
}

const rel = OUT.replace(ROOT, '');
writeFileSync(join(OUT, 'summary.json'), JSON.stringify(summary, null, 2));
writeFileSync(join(OUT, 'SUMMARY.md'), renderMarkdown(summary));
console.log(`Wrote ${rel}/summary.json and ${rel}/SUMMARY.md`);

function renderMarkdown(s) {
  const sec = (ms) => (ms / 1000).toFixed(1) + ' s';
  const kb = (b) => (b >= 1e6 ? (b / 1e6).toFixed(1) + ' MB' : Math.round(b / 1024) + ' KB');
  const rows = [];
  for (const page of Object.keys(PAGES)) {
    for (const ff of FORM_FACTORS) {
      const b = s.pages[`before-${page}-${ff}`]?.perMetricMedian;
      const a = s.pages[`after-${page}-${ff}`]?.perMetricMedian;
      if (!a || !b) continue;
      rows.push(
        `| ${page} | ${ff} | ${b.performance} → ${a.performance} | ${sec(b.lcp)} → ${sec(a.lcp)} | ${b.cls.toFixed(3)} → ${a.cls.toFixed(3)} | ${Math.round(b.tbt)} → ${Math.round(a.tbt)} ms | ${kb(b.totalBytes)} → ${kb(a.totalBytes)} | ${b.requests} → ${a.requests} | ${kb(b.imageBytes)} → ${kb(a.imageBytes)} | ${b.accessibility} → ${a.accessibility} | ${b.seo} → ${a.seo} |`,
      );
    }
  }
  return `# Measurement summary

Generated ${s.generatedAt} with Lighthouse ${s.lighthouseVersion}, ${s.runsPerPage} runs per page and form factor, median of each metric.
Mobile uses Lighthouse's default simulated slow 4G and 4x CPU slowdown. Desktop uses the desktop preset.
${s.wordpress.before ? 'Both sites run in Docker on the same laptop, so there is no real network latency: these are lab numbers.' : 'Lab numbers from one machine and one network connection.'}

Values are BEFORE → AFTER.

| Page | Device | Performance | LCP | CLS | TBT | Page weight | Requests | Image weight | Accessibility | SEO |
|------|--------|-------------|-----|-----|-----|-------------|----------|--------------|---------------|-----|
${rows.join('\n')}

${s.wordpress.before ? `| | BEFORE | AFTER |
|-|--------|-------|
| Active plugins | ${s.wordpress.before.activePlugins} | ${s.wordpress.after.activePlugins} |
| Inactive plugins left installed | ${s.wordpress.before.inactivePlugins} | ${s.wordpress.after.inactivePlugins} |
| Must-use plugins (code in this repo) | ${s.wordpress.before.mustUsePlugins} | ${s.wordpress.after.mustUsePlugins} |
` : ''}
Raw reports (trimmed JSON for every run): \`lighthouse/raw/\`. HTML report of the median run: \`lighthouse/html/\`.
`;
}

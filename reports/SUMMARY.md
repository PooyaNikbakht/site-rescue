# Measurement summary

Generated 2026-11-09T21:11:25.540Z with Lighthouse 13.6.0, 3 runs per page and form factor, median of each metric.
Mobile uses Lighthouse's default simulated slow 4G and 4x CPU slowdown. Desktop uses the desktop preset.
Both sites run in Docker on the same laptop, so there is no real network latency: these are lab numbers.

Values are BEFORE → AFTER.

| Page | Device | Performance | LCP | CLS | TBT | Page weight | Requests | Image weight | Accessibility | SEO |
|------|--------|-------------|-----|-----|-----|-------------|----------|--------------|---------------|-----|
| home | mobile | 62 → 96 | 143.3 s → 2.7 s | 0.100 → 0.000 | 0 → 0 ms | 47.0 MB → 567 KB | 80 → 34 | 46.3 MB → 265 KB | 92 → 100 | 54 → 69 |
| home | desktop | 49 → 99 | 18.9 s → 0.8 s | 0.041 → 0.000 | 504 → 0 ms | 47.3 MB → 490 KB | 81 → 35 | 46.8 MB → 188 KB | 92 → 100 | 54 → 69 |
| menu | mobile | 69 → 92 | 6.4 s → 3.2 s | 0.100 → 0.025 | 0 → 0 ms | 2.3 MB → 484 KB | 73 → 31 | 1.6 MB → 184 KB | 92 → 100 | 54 → 69 |
| menu | desktop | 97 → 100 | 1.2 s → 0.6 s | 0.041 → 0.000 | 0 → 0 ms | 7.3 MB → 484 KB | 72 → 31 | 6.8 MB → 184 KB | 92 → 100 | 54 → 69 |
| post | mobile | 85 → 96 | 3.5 s → 2.6 s | 0.100 → 0.000 | 0 → 0 ms | 3.6 MB → 285 KB | 54 → 19 | 3.0 MB → 61 KB | 90 → 100 | 46 → 69 |
| post | desktop | 98 → 100 | 1.0 s → 0.7 s | 0.041 → 0.000 | 0 → 0 ms | 3.8 MB → 343 KB | 54 → 20 | 3.2 MB → 120 KB | 90 → 100 | 46 → 69 |

| | BEFORE | AFTER |
|-|--------|-------|
| Active plugins | 8 | 3 |
| Inactive plugins left installed | 2 | 0 |
| Must-use plugins (code in this repo) | 0 | 5 |

Raw reports (trimmed JSON for every run): `lighthouse/raw/`. HTML report of the median run: `lighthouse/html/`.

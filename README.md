# wp-public-data

A point-in-time mirror of publicly available WordPress.org metadata for **plugins**, **themes**, and **core releases**, refreshed hourly by GitHub Actions.

The goal is to make it easy to query, diff, and analyse the WordPress.org ecosystem without hammering the live API — and to keep a permanent git history of how plugins/themes have evolved over time.

## What's in here

### Per-slug current state

| Path | Contents |
| --- | --- |
| `plugins/<a>/<slug>.json` | Latest metadata for each plugin (sharded by first character of slug). Mirrors the response from `api.wordpress.org/plugins/info/1.2/`. |
| `themes/<a>/<slug>.json` | Latest metadata for each theme. Mirrors `api.wordpress.org/themes/info/1.2/`. |
| `core/<version>.json` | Per-version metadata for WordPress core releases. |

These files are overwritten in place each cycle, so `git log -- <path>` shows the full history of any given plugin/theme.

### Release history (monthly CSV chunks)

Whenever a plugin or theme's version changes, a row is appended to a monthly CSV bucketed by the **release date**:

```
releases/
  plugins/
    2024-09.csv
    2024-10.csv
    ...
    2026-05.csv
  themes/
    2024-09.csv
    ...
```

Each file shares the same header:

```
Slug, Name, Version, Previous Version, Download Link, Released,
WordPress.org URL, Required WP, Required PHP, Active Installs
```

For convenience, `plugin-releases.csv` and `theme-releases.csv` at the repo root are **symlinks** to the most recent month. The `github.com/.../blob/...` UI follows the link and so does `git clone`. (Note: `raw.githubusercontent.com` does **not** follow symlinks — it returns the target path as a string. Use the `blob/` URL, or pull the underlying `releases/plugins/YYYY-MM.csv` directly.)

To consume the full history, glob `releases/plugins/*.csv` (or `releases/themes/*.csv`) and concatenate — every file uses the same header row.

WordPress core releases live at `releases/core.csv` (a single file — volume is negligible, a handful of rows per year). `core-releases.csv` at the repo root is a symlink to it.

### A note on browsing

GitHub's web UI **does not render** these CSVs as a table: every monthly file is well over the 512 KB limit, so `github.com/.../blob/...` shows the raw text or refuses to display the file. To work with the data, clone the repo or pull a specific month's file via `curl` / `wget`.

### Other artifacts

- `latest-versions.json.gz` — compact `{ plugins: { slug: version }, themes: { slug: version } }` snapshot regenerated each run. Useful as a lightweight update-check feed.

## How it's updated

A GitHub Actions workflow (`.github/workflows/update.yml`) runs every hour and executes:

1. `bin/update-core.php` — refresh core release metadata.
2. `bin/update-plugins.php` — refresh per-plugin JSON.
3. `bin/update-themes.php` — refresh per-theme JSON.
4. `bin/update-releases.php` — diff against `HEAD`, append any version changes to the appropriate monthly CSV, and refresh the latest-versions snapshot.

The release-recorder bucket each new row by the release date in the source data, so a release dated `2026-04-30` detected on `2026-05-01` lands in `releases/plugins/2026-04.csv` — not the current month.

## Source

All data is sourced from public WordPress.org APIs. This repo is a community mirror and not affiliated with WordPress.org or Automattic.

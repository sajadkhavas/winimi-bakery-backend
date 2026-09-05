# F29S-D — Controlled SEO Guide Foundation

This release adds five source-controlled Winimi guides without automatically changing Production data.

## Source of truth

- Manifest: `database/content/winimi-seo-guides-v1.json`
- Validator: `App\Support\Content\SeoGuideManifest`
- Sync command: `content:sync-seo-guides`
- Acceptance seeder: `Database\Seeders\SeoGuideStagingSeeder`

## Safe audit

```bash
php artisan content:sync-seo-guides
```

Dry-run is the default and prints `DATABASE_MUTATIONS=0`.

## Controlled apply

First capture the exact manifest hash from dry-run output. Then:

```bash
php artisan content:sync-seo-guides \
  --apply \
  --expected-sha256='<EXACT_HASH>' \
  --confirm=SYNC_SEO_GUIDES
```

New guides remain drafts unless publication is explicitly requested.

## Controlled publication

Publication is reserved for the final production activation after server preflight and backup evidence:

```bash
php artisan content:sync-seo-guides \
  --apply \
  --publish \
  --expected-sha256='<EXACT_HASH>' \
  --confirm=SYNC_SEO_GUIDES
```

## Invariants

- exactly five canonical guide slugs
- canonical category: `راهنمای انتخاب و سفارش`
- no script/style/iframe/form/event-handler or `javascript:` content in the controlled manifest
- no automatic Production seed
- no fabricated product shelf-life, serving quantity, delivery zone, fee, stock or medical claim
- product-specific truth remains authoritative in the catalog/backend
- final Production publication must record the manifest SHA-256 in deployment evidence

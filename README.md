# HomeFlip Connector

WordPress plugin. Receives property data from the HomeFlip CRM and renders it through
shortcodes, so the subscriber owns their page layout and HomeFlip owns the data.

Plan: `reibase-landing/REI-BASE-WEBSITES-PROJECT.md` §7.

## The idea in one table

| `wp_postmeta` row | Owner | Written by |
| --- | --- | --- |
| `_elementor_data` (the layout) | **The subscriber, forever** | HomeFlip once, at page creation. Never again. |
| `homeflip_*` (the data) | **HomeFlip** | Republished any time |

Different rows, so neither writer can clobber the other. The subscriber drags blocks,
restyles, adds their own photos and sections; a HomeFlip republish still lands, because
it never touches the page.

## Shortcodes

| Tag | Renders | Example |
| --- | --- | --- |
| `[homeflip_price]` | purchase price | `$95,000` |
| `[homeflip_arv]` | ARV range | `$185k - $210k` |
| `[homeflip_rent]` | rent estimate | `$1,450` |
| `[homeflip_beds]` / `[homeflip_baths]` | beds / baths | `3` / `2.5` |
| `[homeflip_sqft]` / `[homeflip_year]` | size / year built | `1,450` / `1957` |
| `[homeflip_status]` | listing status | `available` |
| `[homeflip_field key="x" format="money"]` | escape hatch for a new CRM field | |

Formats mirror `wp-page-generator.py` (`money` / `money_k` / `num`) exactly, so moving a
live page from spliced widgets to shortcodes changes the mechanism without changing how
the page looks.

**Not shortcodes, on purpose:** comp blocks and galleries. They're set once when a page is
built and effectively never change, so they stay native Elementor widgets the subscriber
can edit. Only the values that actually get republished need republish safety — and it
keeps the plugin near its ~300 line estimate instead of owning comp-block markup.

## Empty fields are visible to editors

A shortcode that renders nothing looks exactly like one that rendered correctly, which is
how an unfilled field ships to buyers unnoticed. Logged-in editors see
`[Purchase price not set]`; the public page stays clean. The admin sidebar box lists every
field and flags the unset ones.

## Two REST gotchas baked in

1. A post type needs `'supports' => ['custom-fields']` or its registered meta is **silently
   absent** from the REST API. No error — the `meta` key just never appears.
2. Meta keys starting with `_` are protected and refuse REST writes without an explicit
   `auth_callback`. These keys deliberately don't start with `_`.

The `property` post type registration is **guarded** — whitebox.properties already has one
registered by something else (post 9086 predates this plugin), so if the type exists we only
add `custom-fields` support rather than re-registering and clobbering its rewrite rules.

## Status

**0.1.0** installed and active on the REI Base template site (`rei-template.tempurl.host`),
2026-09-22. Meta round trip verified over REST 2026-09-28 (post 21).

**0.2.0** (2026-09-28, installed + verified same day) adds:

- **Cache purge** (`includes/cache.php`). Measured 2026-09-28: a meta-only change left the
  public page on the OLD price with `X-Cache: HIT`, and two full post saves did not clear it.
  That is WPMU DEV hosting's Static Server Cache, not Elementor (the test page had no Elementor
  data). Every `homeflip_*` meta change or `property` save now purges it
  (`wpmudev_hosting_purge_static_cache()`) plus Elementor's cache, once per request.
  Manual backstop: `POST /wp-json/homeflip/v1/flush`.
- **Buyer forms** (`includes/forms.php`), created once through `Forminator_API::add_form` on
  the first wp-admin load: **Buyer Profile** (-> `contacts`) and **Next Purchase (Buyer
  Criteria)** (-> one `buyer_criteria_sets` row). Answer values are the CRM keys from
  `homeflip-crm/src/lib/wholesale/criteria.ts`. IDs are kept in the `homeflip_form_ids`
  option, which clones copy. `GET /wp-json/homeflip/v1/forms` lists IDs, shortcodes, fields.

Verified on the template 2026-09-28: forms created as **23** (Buyer Profile) and **24** (Next
Purchase), all fields, CRM answer keys, show/hide rules and page-URL field rendering; a price
change now shows on the very next page load (`X-Cache: MISS`), and `/flush` purges both layers.

**0.2.1** fixes the blank submit button: WPMU DEV's API example uses the pre-migration settings
format. Settings now come from Forminator 1.57.3's own blank template (`submitData`,
`submission-behaviour`). Existing forms are rebuilt **in place** (same IDs) when
`HOMEFLIP_FORMS_VERSION` moves, carrying over their settings and notifications, because
`Forminator_API::update_form` replaces both wholesale and would silently delete the admin
email notification.

**0.2.2** adds self-hosted updates (`includes/updater.php`): the header's
`Update URI: https://github.com/wbxprops/homeflip-connector` routes WordPress's update check to
`info.json` on the latest GitHub release, so sites get the normal "Update now" and can
auto-update. The repo is public on purpose (no credentials in the code; a private repo would
need a token cloned into every site). **Updates come from GitHub (Gary, 2026-09-28):** Dashboard -> Updates -> Check again ->
Update, or "Enable auto-updates" on the plugin row. No more zips in Downloads.

**0.3.0** adds page designs and business details:

- `includes/business.php`: the subscriber's name / phone / email / city / state / area /
  address in one option, printed by `[homeflip_business field="..."]` and
  `[homeflip_phone_button]`; `[homeflip_form name="..."]` places a form by name (HomeFlip's own
  by key, others by title match, e.g. `seller`). `GET/POST homeflip/v1/business` (POST merges).
  Empty on the template on purpose: it is cloned.
- `includes/templates.php`: `templates/*.json` installed into Elementor's local library
  ("HomeFlip - ..."), refreshed when a file's `homeflip_version` rises. On a FRESH site (5 pages
  or fewer) it also creates the starter pages once and sets Home as the front page; customers own
  those pages after that. `POST homeflip/v1/templates {"refresh_pages":true}` rebuilds them.
  Replaces Elementor's stock light-blue palette with a neutral one (navy / orange) only if the
  stock colors are still there.
- `tools/build_templates.py` generates the designs (free widgets only, global colors only, no
  typed-in business details, no invented guarantees or testimonials). Edit the generator, never
  the JSON. Not shipped in the zip.

Designs (v1): Home (we buy houses), Join Our Buyers List, Your Buy Box, Privacy Policy, Terms.
Modeled on whitebox.properties home 4929 + seller sections 1702 / 1270 / 1273. The property deal
page comes next, built from template 7860 with a real property.

No PHP available locally, so syntax is checked by installing on the template site.

## Releasing

1. Bump `Version:` in `homeflip-connector.php`
2. Commit in ai-projects (the script refuses uncommitted connector changes)
3. `bash homeflip-connector/release.sh` builds the zip, pushes this folder to
   `github.com/wbxprops/homeflip-connector` main (`git subtree split`; the source of truth stays
   here), creates release `vX.Y.Z` with `homeflip-connector.zip` + `info.json`, and copies the
   zip to Downloads

## Still to build

- Pairing + per-site token (§7 job 3) — for now, the app password works
- Form submissions -> CRM (n8n intake: contact upsert by email, criteria set insert, consent
  fields `marketing_consent_at/_url/_text`)
- Generator change: write `meta` instead of splicing `_elementor_data`

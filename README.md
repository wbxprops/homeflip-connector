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

Core written, **not yet installed or run anywhere.** No PHP available locally, so the
syntax is unlinted.

### The test that decides the next piece

The plan's §12 starts with the flush endpoint. Whether it's still needed depends on one
measurement — does Elementor's element cache also cache **shortcode output**?

1. Install and activate on whitebox.properties
2. On a scratch page, drop a Shortcode widget containing `[homeflip_price]`
3. Set `homeflip_price` on that post via REST, load the page, confirm it renders
4. Change the meta via REST **without touching the post**, reload
   - renders the new value → the cache doesn't reach shortcode output, and the flush
     endpoint may be unnecessary
   - renders the old value → flush endpoint stays job #1

Post **9024** is the known stale-cache case and post **9086** is the live Roselawn page
(CRM property 5022), so both are available as real comparisons.

## Still to build

- Pairing + per-site token (§7 job 3) — for now, the existing app password works
- Flush endpoint (§7 job 1) — pending the test above
- Forminator form shipping (§7 job 4)
- Generator change: write `meta` instead of splicing `_elementor_data`

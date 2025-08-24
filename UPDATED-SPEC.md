# 🌍 Elementor Geo Popup – UX & Logic Update Specification

This document defines how the Elementor Geo Popup plugin should handle **pages, popups, widgets, and sections** by country with **callback/fallback support**.  
Intended as implementation instructions for updating the existing plugin logic.

---

## 🧩 Problem

Currently, if a page (e.g., Homepage UK) is set to display only for **UK visitors**, then **US visitors see nothing**.  
We need a **fallback/callback mechanism** so every visitor always sees the correct version of the site:

- If their country matches a mapped variant → show that page/popup/widget/section.  
- If not → redirect or fall back to a **global version**.  
- If they override manually → remember preference.

---

## ✅ Expected UX & Conditions

### 1. Always Have a Default (Global) Version
- Default/global **page**, **popup**, **widget**, and **section** should be set per **Variant Group**.
- Loads when:
  - Visitor’s country is not mapped.
  - Geo lookup fails.
  - Bots/crawlers visit the site.

---

### 2. Country → Variant Mapping
Define a **routing table** per **Variant Group**:

| Priority | Condition          | Page to show    | Popup to show | Section/Widget to show |
|----------|--------------------|-----------------|---------------|-------------------------|
| 1        | GB (UK)            | `/home-uk`      | Popup_UK      | Section_UK, Widget_UK   |
| 2        | US                 | `/home-us`      | Popup_US      | Section_US, Widget_US   |
| 3        | CA                 | `/home-ca`      | Popup_CA      | Section_CA, Widget_CA   |
| 99       | Fallback (Global)  | `/home-global`  | Popup_Global  | Section_Global, Widget_Global |

Rules:
- If user lands on `/home-uk` but is not from GB → **302 redirect** to their country’s mapped page if available, else to global.
- If widget/section is country-specific but no match → fall back to **global widget/section**.

---

### 3. First Visit Behavior
- On first visit:
  - **Soft redirect** (302) to matched or fallback page.
  - Show a **region selector banner** (toast):  
    “We’ve shown you the United States site. Not your region? [Change]”
- Store choice in cookie (`region=XX`) for 30–90 days.

---

### 4. Manual Override
- Always provide a **“Region Selector”** in header/footer.
- When a visitor selects a region:
  - Store in cookie `region=XX`
  - Redirect to that region’s homepage
  - Override widget/section display rules accordingly
  - Suppress auto-redirects until cookie expires.

---

### 5. Popup, Widget & Section Behavior
- On page render:
  - Try to show **country-specific popup/section/widget**.
  - Else show **global version**.
  - Else show nothing.
- Add **frequency capping** for popups (once per session/day).
- Respect manual region override.

---

### 6. Preview & QA
- In admin/builder: **Preview as Country** dropdown.
- Query param override for QA:  
  `?force_country=US` (admins only).

---

### 7. Error & Edge Cases
- If MaxMind DB missing → load default/global page, popup, widget, section.
- If mapping points to missing entity → fallback to global and flag error in admin.

---

### 8. SEO & Performance
- Don’t redirect bots/crawlers. Serve **global** version.
- Add **hreflang** between page variants, canonicalize each to itself.
- Expose region selector links for crawlers.
- Use **302 redirects** (temporary).
- Cache variant pages by region key (`Vary: X-Geo-Country`) or use `/us/`, `/uk/` routes for simplicity.

---

## 🛠 Implementation Notes

### A) Variant Groups
- New entity: **Geo Variant Group**  
  - Group name: e.g., *Homepage* or *Promo Banner*  
  - Countries mapped to specific **Page + Popup + Section + Widget**  
  - Global fallback page/popup/section/widget  
  - Options:
    - [x] Enable soft redirects
    - [x] Show region selector banner
    - [x] Respect manual override cookie
    - [x] Skip bots/crawlers

### B) Elementor Controls
- **Popup settings**:  
  Add “Geo Targeting” tab with:
  - Enable toggle
  - Multi-select countries
  - Fallback = Global

- **Page settings**:  
  - “Assign to Variant Group”
  - Country tag

- **Section & Widget settings (Pro tier)**:  
  - “Assign to Variant Group”
  - Country tag
  - Fallback toggle (default → Global)

- **Global settings page**:  
  - Default/fallback page, popup, section, widget
  - Region selector toggle
  - Cookie duration (default 60 days)
  - QA param toggle

### C) Routing Decision Tree
1. If `?force_country=XX` (admin only) → use that.
2. Else if `region` cookie exists → use that.
3. Else run MaxMind lookup → get country.
4. Match variant group:
   - If current page not mapped for country → **302 redirect** to correct page.
   - Else render current page.
5. For **popups, widgets, sections**:
   - Show country variant → else global → else none.
6. Frequency cap popups by cookie/localStorage.

### D) Suggested Defaults
- Cookie: `rw_geo_region`
- Cookie TTL: 60 days
- Popup frequency cap key: `rw_popup_{id}_seen`
- QA param: `?force_country=XX`
- Redirect: 302 temporary

---

## Example Admin Copy

- **Geo Variant Group: Homepage**
  - GB → Page: *Home (UK)*, Popup: *UK Offer*, Section: *UK Hero*, Widget: *UK Promo Box*
  - US → Page: *Home (US)*, Popup: *US Offer*, Section: *US Hero*, Widget: *US Promo Box*
  - Fallback → Page: *Home (Global)*, Popup: *Global Newsletter*, Section: *Global Hero*, Widget: *Global Promo Box*
- **Options:**
  - [x] Soft redirects
  - [x] Region selector banner
  - [x] Respect manual region cookie
  - [x] Skip bots → serve Global

---
# 🌍 Elementor Geo Popup – DB Schema & Backend Spec (Pages, Popups, Sections, Widgets)

This document extends the UX & Logic spec with **database schema**, **data models**, **REST endpoints**, **capability model**, **Elementor control bindings**, and **migration plan**.  
Goal: enable Cursor to scaffold both backend (WP + MaxMind) and Elementor UI in one pass.

---

## 0) Naming Conventions

- **Plugin prefix**: `rw_geo_` (ReactWoo Geo)
- **DB table prefix**: `{$wpdb->prefix}rw_geo_`
- **Post meta keys** (Elementor entities): `_rw_geo_variant_group_id`, `_rw_geo_countries`, `_rw_geo_geo_enabled`, `_rw_geo_fallback_enabled`
- **Options**: `rw_geo_settings` (array)
- **Cookies**: `rw_geo_region` (ISO 3166-1 alpha-2), `rw_popup_{id}_seen`

---

## 1) Entities & Relationships (ER Overview)


+-------------------+ 1 : N +-------------------------+
| rw_geo_variant |-------------------->| rw_geo_variant_mapping |
| id (PK) | | id (PK) |
| name | | variant_id (FK) |
| slug | | country_iso2 |
| type_mask | | page_id (wp_posts.ID) |
| default_page_id | | popup_id (Elementor) |
| default_section | | section_ref |
| default_widget | | widget_ref |
| options (json) | | options (json) |
+-------------------+ +-------------------------+

Where type_mask bit-flags which entity types this Variant Group manages:
bit 1=Page, bit 2=Popup, bit 4=Section, bit 8=Widget (sum for mixed).


- **Variant Group** = a logical set (e.g., *Homepage*, *Promo Banner*) managing mappings per **country** for one or more **entity types** (page, popup, section, widget).
- **Variant Mapping** = country-specific target(s) for that Variant Group.

---

## 2) Database Schema (MySQL)

> Use `dbDelta` in activation for forward-compatible schema creation.

### 2.1 `rw_geo_variant`

```sql
CREATE TABLE IF NOT EXISTS {prefix}rw_geo_variant (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  type_mask TINYINT UNSIGNED NOT NULL DEFAULT 3, -- 1:page,2:popup,4:section,8:widget
  default_page_id BIGINT UNSIGNED NULL,
  default_popup_id BIGINT UNSIGNED NULL,
  default_section_ref VARCHAR(190) NULL, -- e.g., elementor section ID or custom ref
  default_widget_ref VARCHAR(190) NULL,  -- e.g., elementor widget ID or custom ref
  options JSON NULL,                     -- flags: soft_redirect, show_selector, respect_cookie, skip_bots, cookie_ttl, etc.
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY slug_unique (slug),
  KEY type_mask_idx (type_mask)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


2.2 rw_geo_variant_mapping
CREATE TABLE IF NOT EXISTS {prefix}rw_geo_variant_mapping (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  variant_id BIGINT UNSIGNED NOT NULL,
  country_iso2 CHAR(2) NOT NULL,        -- ISO 3166-1 alpha-2 uppercase
  page_id BIGINT UNSIGNED NULL,         -- wp_posts.ID
  popup_id BIGINT UNSIGNED NULL,        -- elementor popup post ID
  section_ref VARCHAR(190) NULL,        -- elementor section ID or custom ref
  widget_ref VARCHAR(190) NULL,         -- elementor widget ID or custom ref
  options JSON NULL,                    -- per-country overrides
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_variant_country (variant_id, country_iso2),
  KEY variant_id_idx (variant_id),
  CONSTRAINT fk_variant
    FOREIGN KEY (variant_id) REFERENCES {prefix}rw_geo_variant (id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

3) WordPress Integration (Meta & Options)
3.1 Post Meta (for Elementor entities)

For pages, popups, sections, widgets (where applicable):

_rw_geo_variant_group_id (int) – link the entity to a Variant Group (if entity is a country-specific variant).

_rw_geo_countries (array of ISO2) – if the entity itself declares target countries (optional; group mapping is canonical).

_rw_geo_geo_enabled (bool) – enable targeting at entity level (primarily for popups/sections/widgets).

_rw_geo_fallback_enabled (bool) – entity can serve as fallback where relevant.

Source of truth for routing is the Variant Group tables. Meta is for Elementor control UX and backward-compat UI.

3.2 Global Options (rw_geo_settings)

Stored via update_option('rw_geo_settings', array)

[
  'maxmind' => [
    'license_key'  => '',
    'last_updated' => '2025-08-10 10:00:00',
    'db_path'      => 'wp-content/uploads/geo-popup-db/GeoLite2-Country.mmdb',
    'auto_update'  => true,
    'update_freq'  => 'weekly' // daily|weekly|monthly
  ],
  'qa' => [
    'enable_force_param' => true,
    'param_name'         => 'force_country'
  ],
  'selector' => [
    'enabled'     => true,
    'cookie_name' => 'rw_geo_region',
    'ttl_days'    => 60
  ],
  'bots' => [
    'skip_redirect' => true
  ],
  'defaults' => [
    'variant_home_slug' => 'homepage', // default Variant Group slug for site root
  ]
];


4) Elementor Controls (Binding)
4.1 Page Template

Panel: Geo Targeting

Toggle: Enable Geo Targeting (syncs _rw_geo_geo_enabled)

Dropdown (select or create): Variant Group (sync _rw_geo_variant_group_id)

Read-only note: mapping controlled globally under Geo Variant Groups admin

Button: Preview as Country (opens preview with ?force_country=XX for admins)

4.2 Popup

Panel: Geo Targeting

Toggle: Enable

Multi-select: Countries (writes _rw_geo_countries for UI, but canonical mapping in group)

Dropdown: Variant Group

Toggle: Use as Global Fallback for this group

4.3 Sections & Widgets (Pro tier)

Panel: Geo Targeting

Toggle: Enable

Dropdown: Variant Group

Field: Entity Ref (auto-populated from Elementor node ID; read-only)

Toggle: Use as Global Fallback for this group

Cursor: Implement control registration via Elementor’s Controls API and save to post meta. Display a small tooltip linking to Geo Variant Groups admin screen.

5) Admin Screens
5.1 Geo Variant Groups (CPT-like UI or custom screen)

List table: Group Name, Slug, Types (icons), Default targets, Updated

Add/Edit Group:

Name, Slug, Type Mask (checkboxes: Page/Popup/Section/Widget)

Defaults:

Default Page (dropdown of pages)

Default Popup (dropdown of popups)

Default Section Ref (text)

Default Widget Ref (text)

Options:

Soft redirect (302) for mismatched pages

Show region selector banner (first visit)

Respect region cookie over IP

Skip bots/crawlers

Cookie TTL (days)

Country Mappings (inline table):

Country (ISO2) | Page | Popup | Section Ref | Widget Ref | Actions (Add/Remove)

Validation: ensure at least one default set for each enabled type

5.2 Settings (MaxMind & Global)

MaxMind license key

Download / Update DB (manual)

Auto-update toggle + frequency

QA: force param toggle + name

Region selector: enable + TTL

Bots: Skip redirect (toggle)

Default Variant Group for root (/)

6) Routing & Rendering Logic (Pseudocode)

function rw_geo_detect_country() {
  // 1) Admin QA override
  if (current_user_can('manage_options') && isset($_GET[$param = opt('qa.param_name')])) {
      return strtoupper(sanitize_text_field($_GET[$param]));
  }

  // 2) Region cookie override
  if (!empty($_COOKIE[opt('selector.cookie_name')])) {
      return strtoupper($_COOKIE[opt('selector.cookie_name')]);
  }

  // 3) MaxMind lookup
  try {
      $ip = rw_geo_get_ip();
      $mmdb = opt('maxmind.db_path');
      $reader = new GeoIp2\Database\Reader(ABSPATH . $mmdb);
      $record = $reader->country($ip);
      return strtoupper($record->country->isoCode);
  } catch (\Throwable $e) {
      return null; // lookup failed
  }
}

function rw_geo_route_current_request() {
  // Skip admin, AJAX, REST, or preview builders
  if (is_admin() || wp_doing_ajax() || defined('REST_REQUEST') || rw_geo_is_elementor_editor()) return;

  $country = rw_geo_detect_country();
  $variant = rw_geo_get_active_variant_group_for_route(); // e.g., "homepage" group for '/'
  if (!$variant) return;

  // Bot skip
  if (opt('bots.skip_redirect') && rw_geo_is_bot()) {
      rw_geo_set_context_country('GLOBAL');
      return;
  }

  $mapping = rw_geo_resolve_mapping($variant, $country); // returns obj with page/popup/section/widget resolved to either country or defaults
  rw_geo_set_context_country($country ?: 'GLOBAL');

  // Page redirect handling
  if ($variant->type_mask & TYPE_PAGE) {
     $current_id = get_queried_object_id();
     $target_id = $mapping->page_id ?: $variant->default_page_id;
     if ($target_id && $target_id != $current_id && $variant->options->soft_redirect) {
         wp_safe_redirect(get_permalink($target_id), 302);
         exit;
     }
  }

  // Popups/Sections/Widgets displayed at render time using resolved mapping from context
}

add_action('template_redirect', 'rw_geo_route_current_request', 0);


Frontend popup injection (respect frequency cap & resolved mapping):

add_action('wp_footer', function() {
  $ctx = rw_geo_get_render_context(); // contains resolved popup_id, section_ref, widget_ref
  if (!$ctx) return;

  if (!empty($ctx->popup_id) && !rw_geo_popup_seen($ctx->popup_id)) {
    echo "<script>
      document.addEventListener('DOMContentLoaded', function(){
        if (window.elementorProFrontend && elementorProFrontend.modules && elementorProFrontend.modules.popup) {
          elementorProFrontend.modules.popup.showPopup({ id: ".intval($ctx->popup_id)." });
        }
      });
    </script>";
  }
});

7) REST API (Admin-side, manage_options)

Base: /wp-json/rw-geo/v1/

GET /variant – list groups (with pagination & search)

POST /variant – create group

GET /variant/{id} – get group + mappings

PUT /variant/{id} – update group (name, slug, type_mask, defaults, options)

DELETE /variant/{id} – delete group

GET /variant/{id}/mapping – list mappings

POST /variant/{id}/mapping – upsert mapping (country_iso2, page_id, popup_id, section_ref, widget_ref, options)

DELETE /variant/{id}/mapping/{country} – delete mapping

POST /settings/maxmind/update – fetch & update DB (requires license key)

Security: nonce + capability check manage_options.

8) Capabilities & Roles

Base capability: manage_rw_geo (map to manage_options by default)

Filter to customize: rw_geo_capability

All REST routes & admin pages check this capability.

9) MaxMind Updater

Manual: Button triggers REST POST /settings/maxmind/update

Auto-update (WP-Cron):

Hook: rw_geo_mmdb_update_event

Frequency: per option (daily|weekly|monthly)

Steps:

Download tar.gz via license key

Extract GeoLite2-Country.mmdb

Replace existing atomically

Update rw_geo_settings.maxmind.last_updated

10) Caching & Performance

Cache country detection per session (cookie) for N minutes to reduce MMDB reads.

Cache Variant Group + Mappings in transients keyed by variant:{id}; bust on update.

For edge caching/CDN, either:

Use country-specific routes (/us/, /uk/) to avoid Vary

Or set Vary: X-Geo-Country header (advanced setups)

11) Multisite

Network-activated plugin stores settings per site by default.

Optionally add Network Settings page to set shared MaxMind path if desired (future enhancement).

12) Data Validation & Sanitization

country_iso2: ^[A-Z]{2}$

slug: WP sanitize_key

IDs: absint

options: wp_json_validate on save where available or strict schema

Enforce referential integrity in code (existence checks for post IDs)

13) Migrations

Activation:

Create tables (dbDelta)

Add default options rw_geo_settings if missing

Register cron for auto-update if enabled

Upgrade path:

Track rw_geo_db_version option (e.g., 1.0.0, 1.1.0)

upgrader_process_complete hook to run incremental migrations

14) Example Data

14.1 SQL Inserts (sample)

INSERT INTO {prefix}rw_geo_variant
(name, slug, type_mask, default_page_id, default_popup_id, default_section_ref, default_widget_ref, options)
VALUES
('Homepage', 'homepage', 15, 101, 201, 'global_hero', 'global_promo',
 '{"soft_redirect":true,"show_selector":true,"respect_cookie":true,"skip_bots":true,"cookie_ttl":60}');

INSERT INTO {prefix}rw_geo_variant_mapping
(variant_id, country_iso2, page_id, popup_id, section_ref, widget_ref, options)
VALUES
(1, 'GB', 102, 202, 'uk_hero', 'uk_promo', '{}'),
(1, 'US', 103, 203, 'us_hero', 'us_promo', '{}');

14.2 JSON Export (for REST seed)

{
  "variant": {
    "name": "Homepage",
    "slug": "homepage",
    "type_mask": 15,
    "defaults": {
      "page_id": 101,
      "popup_id": 201,
      "section_ref": "global_hero",
      "widget_ref": "global_promo"
    },
    "options": {
      "soft_redirect": true,
      "show_selector": true,
      "respect_cookie": true,
      "skip_bots": true,
      "cookie_ttl": 60
    }
  },
  "mappings": [
    {
      "country_iso2": "GB",
      "page_id": 102,
      "popup_id": 202,
      "section_ref": "uk_hero",
      "widget_ref": "uk_promo"
    },
    {
      "country_iso2": "US",
      "page_id": 103,
      "popup_id": 203,
      "section_ref": "us_hero",
      "widget_ref": "us_promo"
    }
  ]
}

15) Security Considerations

Strict capabilities + nonces on all admin & REST actions

Validate MaxMind download URL/params; use WP HTTP API; checksum if available

Escape outputs in admin (labels, refs)

Sanitize all inputs (ISO country codes, refs, IDs)

16) QA & Testing Checklist

 With and without MMDB file (fallback to global)

 VPN tests for GB/US/CA mappings

 Region selector cookie overrides IP

 Bot UA receives global without redirects

 Popups frequency cap respected across routes

 Elementor editor preview via ?force_country=XX

 REST CRUD for groups & mappings

 Cron auto-update path & error handling

 Multisite: per-site isolation of settings

17) Constants & Flags (optional)
define('RW_GEO_DEBUG', false);
define('RW_GEO_OPTION_KEY', 'rw_geo_settings');
define('RW_GEO_COOKIE_REGION', 'rw_geo_region');

define('RW_GEO_TYPE_PAGE',   1);
define('RW_GEO_TYPE_POPUP',  2);
define('RW_GEO_TYPE_SECTION',4);
define('RW_GEO_TYPE_WIDGET', 8);

18) Developer Hooks (Filters/Actions)

apply_filters('rw_geo_capability', 'manage_options')

apply_filters('rw_geo_detected_country', $iso2, $ip, $context)

apply_filters('rw_geo_variant_for_route', $variant, $wp_query)

apply_filters('rw_geo_mapping_resolved', $mapping, $country, $variant)

do_action('rw_geo_mmdb_updated', $meta) – after successful DB update

do_action('rw_geo_after_redirect', $target_id, $country, $variant)

19) Error Reporting

Non-fatal admin notices for:

Missing MMDB

Invalid MaxMind license

Mapping references to deleted posts

Log to error_log gated by RW_GEO_DEBUG

20) Deliverables for Cursor

DB layer: table creators + CRUD classes for Variant & Mapping

Settings page (MaxMind + global)

Admin screen for Variant Groups (list + add/edit + inline country mappings)

Elementor control registration on pages/popup/section/widget

Router: template_redirect logic + context resolver

Frontend popup injector (footer hook)

REST API routes & controllers

Cron updater for MaxMind

Activation/Deactivation hooks & migration



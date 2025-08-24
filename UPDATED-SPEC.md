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



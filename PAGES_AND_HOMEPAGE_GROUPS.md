# 📍 Pages Integration & Homepage Variant Groups

## ✅ Two Powerful Features Implemented

### Feature 1: Geo Columns for WordPress Pages
**Shows geo status and variant groups in Pages admin**

### Feature 2: Homepage Variant Groups  
**Use different homepages/blog pages based on visitor country**

---

## 📊 Feature 1: WordPress Pages Columns

### What You See: `Pages → All Pages`

```
Title              📍 Geo      Variant Group       Date
Homepage          ✓ Enabled   Homepage Group      Sep 30
                              (US, CA, GB)
About Us          Disabled    —                   Sep 29
Contact (Japan)   ✓ Enabled   Asia Pages          Sep 28
                              (JP, KR, CN)
Blog              Disabled    —                   Sep 27
```

**Two New Columns:**

**1. 📍 Geo Column**
- Shows: ✓ Enabled or Disabled
- Checks: Geo rules OR Elementor geo settings
- At a glance: See which pages have geo targeting

**2. Variant Group Column**
- Shows: Group name (clickable link)
- Shows: Countries in group (JP, KR, CN)
- Shows: "—" if page not in any group
- Click link: Opens group editor

### Geo Pages Tab

**New filter tab:**
```
All (45) | Published (40) | 📍 Geo Pages (8)
                           ↑ Click to filter
```

Shows only pages with geo targeting enabled.

---

## 🏠 Feature 2: Homepage Variant Groups

### What It Does

**Normal WordPress:**
```
Settings → Reading
Homepage displays: A static page
Homepage: [Select One Page ▼]
         └─ Shows same page to everyone
```

**With Variant Groups:**
```
Settings → Reading
Homepage displays: A static page
Homepage: [US Homepage ▼]

🌍 Geo-Targeted Homepage
Use Variant Group for Homepage: [Homepage Variants ▼]
                                  ↑ Select a group!
```

**Result:**
- US visitors → See US Homepage
- UK visitors → See UK Homepage  
- Japan visitors → See Japan Homepage
- Others → See default homepage

**All from ONE setting!** 🎯

### How to Set It Up

**Step 1: Create Variant Group**
```
1. Go to: Geo Elementor → Variant Groups
2. Create new group: "Homepage Variants"
3. Add mappings:
   - US, CA → US Homepage (page ID 123)
   - GB, IE → UK Homepage (page ID 456)
   - JP, KR → Asia Homepage (page ID 789)
4. Set default: Global Homepage (page ID 100)
5. Save
```

**Step 2: Enable in WordPress Settings**
```
1. Go to: Settings → Reading
2. Scroll to "Geo-Targeted Homepage" section
3. "Use Variant Group for Homepage": Select "Homepage Variants"
4. Save Changes
```

**Step 3: Test!**
```
- Visit from US → See US Homepage
- Visit from UK → See UK Homepage
- Visit from Japan → See Asia Homepage
- Visit from France → See Global Homepage (default)
```

**Perfect geo-targeted homepage!** ✨

---

## 📖 Technical Details

### How Homepage Groups Work

**WordPress normally does:**
```php
$homepage_id = get_option('page_on_front'); // Returns single page ID
show_page($homepage_id);
```

**With our integration:**
```php
// Filter the option
add_filter('option_page_on_front', function($page_id) {
    $group_id = get_option('egp_homepage_variant_group');
    
    if ($group_id) {
        $user_country = get_visitor_country();
        $country_page = get_page_from_group($group_id, $user_country);
        
        if ($country_page) {
            return $country_page; // WordPress shows this page instead!
        }
    }
    
    return $page_id; // Fallback to original
});
```

**WordPress is none the wiser!** It just gets a different page ID based on country.

### Safety Features

**1. Graceful Fallback**
```php
if (geo_detection_fails || group_not_found || no_matching_country) {
    return original_page_id; // WordPress default behavior
}
```

**2. Admin Bypass**
```php
if (is_admin()) {
    return original_page_id; // Don't filter in admin
}
```

**3. Validation**
```php
// Only return valid, published pages
if (!get_post($country_page) || get_post_status($country_page) !== 'publish') {
    return original_page_id; // Fallback
}
```

**WordPress never breaks!** ✅

---

## 🎯 Use Cases

### Use Case 1: Country-Specific Homepages

**Setup:**
```
Homepage Variants Group:
├─ US, CA → "Welcome USA" page
├─ GB, IE, AU → "Welcome UK/Commonwealth" page
├─ EU → "Welcome Europe" page
└─ Default → "Welcome Global" page
```

**Result:**
- Each region sees customized messaging
- Single point of management (variant group)
- No code, no complexity

### Use Case 2: Localized Blog Pages

**Setup:**
```
Blog Variants Group:
├─ JP, KR, CN → "Asia News" page
├─ DE, FR, IT → "EU News" page
├─ US, CA → "Americas News" page
└─ Default → "Global News" page
```

**Result:**
- Each region sees relevant blog content
- Visitors auto-routed to their regional blog
- Seamless UX

### Use Case 3: Mixed Approach

**Setup:**
```
Homepage: Use variant group (country-specific)
Blog: Use single page (same for everyone)
```

**Flexibility**: Mix and match as needed!

---

## 📊 Pages Admin Columns

### What Each Column Tells You

**📍 Geo Column:**
- ✓ Enabled = Page has geo rule OR is in variant group
- Disabled = No geo targeting

**Variant Group Column:**
- Shows group name = Page is part of a variant group
- Shows countries = Which countries see this page
- Click link = Edit the group
- "—" = Not in any group

---

## 🧪 Testing

### Test 1: Check Page Columns

```
1. Go to: Pages → All Pages
2. Look at columns
```

**Expected:**
- ✅ See "📍 Geo" column
- ✅ See "Variant Group" column
- ✅ Pages in groups show group name
- ✅ Group name is clickable

### Test 2: Check Geo Pages Tab

```
1. In Pages list
2. Look at top tabs
3. Should see: "📍 Geo Pages (X)"
4. Click it
```

**Expected:**
- ✅ Tab appears with count
- ✅ Click shows only geo-targeted pages
- ✅ Includes pages in variant groups

### Test 3: Create Homepage Group

```
1. Geo Elementor → Variant Groups
2. Create new group: "Test Homepage"
3. Add mapping: US → Some page
4. Save
```

**Expected:**
- ✅ Group created successfully

### Test 4: Enable in WordPress Settings

```
1. Settings → Reading
2. Scroll to "Geo-Targeted Homepage" section
3. Select group in "Use Variant Group for Homepage"
4. Save Changes
```

**Expected:**
- ✅ Section appears
- ✅ Dropdown shows your groups
- ✅ Saves successfully

### Test 5: Test Frontend

```
1. Visit homepage (not logged in)
2. Check which page shows
3. Use VPN to change country
4. Visit again
```

**Expected:**
- ✅ Different page shows based on country
- ✅ Default page shows if no match
- ✅ No errors if geo detection fails

---

## 🔒 Safety & Fallbacks

### What Happens If...

**Geo detection fails?**
→ Shows WordPress default page (original behavior)

**Group deleted?**
→ Shows WordPress default page (graceful fallback)

**Page in group deleted?**
→ Shows default page from group or WordPress default

**Visitor from unmapped country?**
→ Shows group's default page or WordPress default

**Admin viewing pages?**
→ Original WordPress settings (no filtering in admin)

**WordPress updated?**
→ Still works (we use option filters, not core modifications)

---

## 📋 Complete Feature Matrix

| Location | Geo Column | Group Column | Tab Filter | Settings |
|----------|-----------|--------------|------------|----------|
| Elementor Templates | ✅ | — | ✅ | ✅ |
| WordPress Pages | ✅ | ✅ | ✅ | — |
| Homepage Setting | — | — | — | ✅ |
| Blog Page Setting | — | — | — | ✅ |

---

## 🎯 Benefits

### For Pages Admin
- ✅ See geo status at a glance
- ✅ See which group each page belongs to
- ✅ Click to edit group
- ✅ Filter to only geo pages

### For Homepage Settings
- ✅ Country-specific homepages from one setting
- ✅ No code required
- ✅ Managed via variant groups
- ✅ Safe fallbacks
- ✅ WordPress compatibility

### For Users
- ✅ Visitors automatically see their regional content
- ✅ Seamless experience
- ✅ No redirects needed
- ✅ Fast loading

---

## 🚀 Files Created

1. ✅ `includes/page-columns-integration.php` - Pages admin columns
2. ✅ `assets/css/page-columns.css` - Column styling
3. ✅ `includes/homepage-variant-group.php` - Homepage group support

**All loaded in main plugin!** ✅

---

## 📖 User Guide

### Set Up Country-Specific Homepage

**Quick Start:**
```
1. Create pages:
   - US Homepage
   - UK Homepage
   - Asia Homepage
   - Global Homepage

2. Create variant group:
   - Name: "Homepage Variants"
   - Mappings:
     • US, CA → US Homepage
     • GB, IE, AU → UK Homepage
     • JP, KR, CN → Asia Homepage
   - Default: Global Homepage

3. Enable in WordPress:
   - Settings → Reading
   - "Use Variant Group for Homepage": Select "Homepage Variants"
   - Save

4. Test:
   - Visit from different countries
   - See different homepages!
```

**Same process for Blog page!**

---

## ✅ Summary

**WordPress Pages Admin:**
- ✅ Shows geo status
- ✅ Shows variant group membership
- ✅ Clickable group links
- ✅ Geo pages filter tab

**Homepage/Blog Settings:**
- ✅ Variant group selection
- ✅ Country-based page routing
- ✅ Safe fallbacks
- ✅ WordPress compatibility

**All with your brand icon (dashicons-location-alt)!** 📍

---

**Ready to test!** 🚀

Check:
1. `Pages → All Pages` for new columns
2. `Settings → Reading` for new geo section


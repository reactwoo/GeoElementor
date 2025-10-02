# Hybrid Geo-Targeting System - Testing Guide

## 🧪 Complete Testing Checklist

### Pre-Test Setup

1. **Clear All Caches**
   ```bash
   # WordPress
   wp cache flush
   
   # Elementor
   wp elementor flush-css
   ```
   
   Or manually:
   - WordPress Admin → Elementor → Tools → Regenerate Files
   - Clear browser cache (Ctrl+Shift+R)

2. **Verify Plugin is Active**
   - Check: Plugins → Geo Elementor (should be active)
   - Check: Geo Elementor menu appears in admin sidebar

3. **Verify Elementor is Active**
   - Check: Elementor plugin is installed and active
   - Check: You can edit pages with Elementor

---

## Test Suite 1: Template System

### Test 1.1: Create Template

**Steps:**
1. Go to: `wp-admin → Geo Elementor → Geo Templates`
2. Click "Add New"
3. Fill in:
   - Name: `Japan Test Banner`
   - Type: `Section`
   - Countries: `Japan, Italy`
   - Fallback: `Hide`
4. Click "Save Template"

**Expected:**
- ✅ Modal closes
- ✅ Success message appears
- ✅ Page refreshes
- ✅ New template appears in list
- ✅ Prompt to edit with Elementor

**Troubleshoot:**
- If modal doesn't close: Check browser console for JavaScript errors
- If save fails: Check error log in `wp-content/debug.log`

### Test 1.2: Edit Template Content

**Steps:**
1. In templates list, click "Edit with Elementor"
2. Add a Heading widget: "Hello from Japan!"
3. Add a Text widget: "This content is only for Japanese visitors"
4. Style it (optional)
5. Click "Update"

**Expected:**
- ✅ Elementor editor opens
- ✅ Can add/edit widgets normally
- ✅ Save works
- ✅ Content is stored

**Troubleshoot:**
- If Elementor doesn't open: Check that post type supports Elementor
- If save fails: Check file permissions

### Test 1.3: Insert Widget on Page

**Steps:**
1. Edit any page with Elementor
2. Search widgets for "Geo Section"
3. Drag widget to page
4. In widget settings, select "Japan Test Banner"
5. Update page

**Expected:**
- ✅ Widget appears in sidebar
- ✅ Can drag to page
- ✅ Template selector shows your template
- ✅ In editor, shows template content with badge
- ✅ Save works

**Troubleshoot:**
- If widget not found: Clear Elementor cache, hard refresh
- If template not in dropdown: Check template is published

---

## Test Suite 2: Frontend Rendering

### Test 2.1: View from Allowed Country

**Simulate being in Japan:**

**Option A: Using Browser Console**
```javascript
// Override detection for testing
localStorage.setItem('egp_test_country', 'JP');
location.reload();
```

**Option B: Using VPN/Proxy**
- Connect to Japan VPN
- Visit page

**Expected:**
- ✅ Template content is VISIBLE
- ✅ Shows "Hello from Japan!" heading
- ✅ Console log: `[EGP Frontend] ✓ User country allowed - SHOWING`

### Test 2.2: View from Non-Allowed Country

**Simulate being in UK:**
```javascript
localStorage.setItem('egp_test_country', 'GB');
location.reload();
```

**Expected:**
- ✅ Template content is HIDDEN
- ✅ Nothing shows (or fallback if configured)
- ✅ Console log: `[EGP Frontend] ❌ User country NOT allowed - HIDING`

### Test 2.3: Check Console Logs

**Open browser console (F12) and look for:**
```
[EGP Frontend] Loaded X geo targeting rules
[EGP Frontend] Available Elementor elements (data-id): [...]
[EGP Frontend] User country: GB
[EGP Frontend] Checking rule for: Japan Test Banner
[EGP Frontend] Found by data-id: 123abc
```

---

## Test Suite 3: Multiple Widgets

### Test 3.1: Test All Three Widget Types

1. **Create 3 Templates:**
   - "US Section" (Type: Section, Country: US)
   - "EU Container" (Type: Container, Countries: DE, FR, IT)
   - "Asia Form" (Type: Form, Countries: JP, CN, KR)

2. **Edit each template content** with Elementor

3. **Insert all three widgets** on same page:
   - Geo Section → "US Section"
   - Geo Container → "EU Container"
   - Geo Form → "Asia Form"

4. **Test from different countries:**
   - From US: See Section only
   - From Germany: See Container only
   - From Japan: See Form only
   - From UK: See nothing

**Expected:**
- ✅ Each widget shows only for its countries
- ✅ Multiple widgets can coexist on same page
- ✅ No conflicts between widgets

---

## Test Suite 4: Direct Element Targeting (Old System)

### Test 4.1: Mix Both Systems

1. On the same page with template widgets:
2. Add a regular Elementor section
3. Click it → Advanced → Geo Targeting
4. Enable and select countries: GB, AU
5. Save page

**Expected:**
- ✅ Template widgets work (new system)
- ✅ Direct element targeting works (old system)
- ✅ Both show correctly based on country
- ✅ No conflicts

---

## Test Suite 5: Template Management

### Test 5.1: Edit Template Settings

1. In Geo Templates admin
2. Click "Settings" on template
3. Change countries from JP, IT to JP, US, IT
4. Save

**Expected:**
- ✅ Modal opens with current settings
- ✅ Can modify countries
- ✅ Saves successfully
- ✅ Changes reflected immediately

### Test 5.2: Delete Template

1. Create a test template
2. Insert it somewhere (note location)
3. Delete template from admin
4. Visit page where it was inserted

**Expected:**
- ✅ Template deleted from admin
- ✅ Widget on page shows "Template not found" in editor
- ✅ Widget shows nothing on frontend

### Test 5.3: Template Usage Tracking

1. Create a template
2. Insert it on 3 different pages
3. Check template admin page

**Expected:**
- ✅ Usage count shows "3 pages"
- ✅ Counter updates as you add/remove widgets

---

## Test Suite 6: Edge Cases

### Test 6.1: No Countries Selected

1. Create template without selecting any countries
2. Try to save

**Expected:**
- ✅ Validation error
- ✅ Doesn't save
- ✅ Shows error message

### Test 6.2: Empty Template

1. Create template
2. Don't add any content in Elementor
3. Insert widget on page

**Expected:**
- ✅ Widget inserts
- ✅ Shows empty (no errors)
- ✅ Badge shows in editor

### Test 6.3: Delete Widget, Template Remains

1. Insert widget on page
2. Delete widget from page
3. Check template still exists in admin

**Expected:**
- ✅ Widget removed from page
- ✅ Template still in admin
- ✅ Can reuse template elsewhere

---

## Test Suite 7: Performance

### Test 7.1: Multiple Templates on Page

1. Create 10 templates
2. Insert all 10 widgets on one page
3. Measure page load time

**Expected:**
- ✅ Page loads in reasonable time (< 3s)
- ✅ No memory errors
- ✅ All widgets render correctly

### Test 7.2: Large Template Content

1. Create template with lots of content:
   - 20 widgets
   - Images
   - Complex layouts
2. Insert on page
3. Test load time

**Expected:**
- ✅ Loads normally
- ✅ No timeout errors
- ✅ Content displays correctly

---

## Troubleshooting Guide

### Issue: Widget Not Appearing in Elementor

**Solutions:**
1. Clear Elementor cache: Tools → Regenerate Files
2. Deactivate/reactivate plugin
3. Check error log for widget registration errors
4. Verify widget files exist in `includes/widgets/`

### Issue: Template Content Not Showing

**Check:**
1. Is user from allowed country? (check console)
2. Is template published?
3. Is "Show in Editor" enabled?
4. Check console for `[EGP Frontend]` errors

### Issue: Countries Not Saving

**Check:**
1. Browser console for JavaScript errors
2. AJAX request in Network tab
3. Nonce validation in server response
4. Database: `wp_postmeta` table for `egp_countries`

### Issue: "Edit with Elementor" Not Working

**Check:**
1. Elementor plugin is active
2. User has edit permissions
3. Template post type supports Elementor
4. Try direct URL: `post.php?post=TEMPLATE_ID&action=elementor`

---

## Success Criteria

### ✅ System is Working When:

1. **Templates:**
   - [ ] Can create templates in admin
   - [ ] Can edit content with Elementor
   - [ ] Can set countries and fallback
   - [ ] Templates list shows correctly

2. **Widgets:**
   - [ ] All 3 widgets appear in Elementor
   - [ ] Can insert on pages
   - [ ] Template selector works
   - [ ] Override settings work

3. **Frontend:**
   - [ ] Content shows for allowed countries
   - [ ] Content hides for non-allowed countries
   - [ ] Console logs are clear
   - [ ] No JavaScript errors

4. **Integration:**
   - [ ] Works with element rules (old system)
   - [ ] No conflicts
   - [ ] Both systems coexist

5. **Performance:**
   - [ ] Page loads quickly
   - [ ] No memory issues
   - [ ] Multiple widgets work

---

## Quick Test Script

**5-Minute Smoke Test:**

```bash
# 1. Create template
# Go to: Geo Templates → Add New
# Name: "Quick Test"
# Countries: US
# Save

# 2. Edit content
# Click "Edit with Elementor"
# Add heading: "Test Content"
# Save

# 3. Insert widget
# Edit any page
# Add "Geo Section" widget
# Select "Quick Test"
# Save

# 4. View frontend
# Open browser console
# Check logs show template loading
# Verify content visible/hidden based on country

# ✅ If all work = System is ready!
```

---

## Report Issues

When reporting issues, include:
1. Steps to reproduce
2. Expected vs actual behavior
3. Browser console logs (F12)
4. Server error logs
5. Screenshots

**You're ready to test!** 🚀

Start with Test Suite 1 and work through each test systematically.


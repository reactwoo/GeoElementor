# Browser Console Test Instructions

## The Problem
The geo rule is being saved correctly, but elements are not hiding on the frontend.

## What to Check

### Step 1: Open Browser Console
1. Go to the page with "Japan Header" element
2. Press **F12** to open Developer Tools
3. Go to the **Console** tab

### Step 2: Look for These Messages

You should see messages like:
```
[EGP Frontend] Loaded 1 geo targeting rules: [...]
[EGP Frontend] Available Elementor elements (data-id): [...]
[EGP Frontend] User country: GB
[EGP Frontend] Processing geo rules for country: GB
[EGP Frontend] Checking rule for: Japan Header | Allowed: [IT, JP] | User: GB
[EGP Frontend] ❌ User country NOT allowed - HIDING: Japan Header
[EGP Frontend] Searching for element: Japan Header
```

### Step 3: Key Questions

**Question 1**: What does "Available Elementor elements (data-id)" show?
- This lists ALL elements with data-id attributes on the page
- Is "Japan Header" in this list?
- If not, what IS in the list?

**Question 2**: What does "Searching for element: Japan Header" say?
- Does it say "Found by data-id: Japan Header"?
- Or does it say "Could not find element: Japan Header"?

**Question 3**: What is the ACTUAL element ID in Elementor?
1. In Elementor editor, click the container/section
2. Go to **Advanced** tab
3. Look for **CSS ID** field
4. What value is there? (might be empty)

### Step 4: Check the HTML

In browser console, run this command:
```javascript
// Find all elements with data-id attribute
document.querySelectorAll('[data-id]').forEach(el => {
    if (el.getAttribute('data-id').includes('Japan')) {
        console.log('Found Japan element:', el.getAttribute('data-id'), el);
    }
});
```

This will show if there's an element with "Japan" in its data-id.

## Expected Results

### If Working Correctly:
```
[EGP Frontend] Loaded 1 geo targeting rules
[EGP Frontend] Available Elementor elements (data-id): [..., "Japan Header", ...]
[EGP Frontend] User country: GB
[EGP Frontend] Checking rule for: Japan Header | Allowed: [IT, JP] | User: GB
[EGP Frontend] ❌ User country NOT allowed - HIDING: Japan Header
[EGP Frontend] Searching for element: Japan Header
[EGP Frontend] Found by data-id: Japan Header
[EGP Frontend] ✓ Hidden element: Japan Header
```

### If Element Not Found:
```
[EGP Frontend] Loaded 1 geo targeting rules
[EGP Frontend] Available Elementor elements (data-id): [... no "Japan Header" ...]
[EGP Frontend] User country: GB
[EGP Frontend] Checking rule for: Japan Header | Allowed: [IT, JP] | User: GB
[EGP Frontend] ❌ User country NOT allowed - HIDING: Japan Header
[EGP Frontend] Searching for element: Japan Header
[EGP Frontend] Could not find element: Japan Header
[EGP Frontend] ⚠️ Element not found to hide: Japan Header
```

## What to Report Back

Please copy and paste:
1. **All console messages** that start with `[EGP Frontend]`
2. **The CSS ID** from Elementor Advanced settings (if any)
3. **The results** of the JavaScript command above

This will tell us exactly why the element isn't being found!

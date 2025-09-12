# Geo Elementor Dashboard

A lightweight vanilla JavaScript dashboard for the Geo Elementor plugin, featuring Google Analytics-style UI with comprehensive analytics and reporting.

## Features

- **Overview Cards**: Total rules, active rules, clicks, countries, conversion rates
- **Interactive Charts**: Country performance, trends over time
- **Rules Performance Table**: Sortable table with detailed rule analytics
- **Real-time Data**: Live updates from WordPress REST API
- **Responsive Design**: Works on desktop and mobile devices
- **Ultra Lightweight**: Minimal bundle size (~15KB total)

## Tech Stack

- **Frontend**: Vanilla JavaScript (ES6+)
- **Charts**: CSS-based visualizations (no external libraries)
- **Styling**: Custom CSS with modern design
- **Build System**: esbuild for ultra-fast building
- **Bundle Size**: ~10KB JS + ~5KB CSS

## Setup & Development

### Prerequisites

- Node.js 16+ 
- npm or yarn
- WordPress with Geo Elementor plugin installed

### Installation

1. Navigate to the plugin directory:
   ```bash
   cd /path/to/geo-elementor-plugin
   ```

2. Install dependencies:
   ```bash
   npm install
   ```

3. Start development server:
   ```bash
   npm run dev
   ```

4. Build for production:
   ```bash
   npm run build
   ```

### Development Workflow

1. **Start Development**: Run `npm run dev` to start the Vite dev server
2. **Make Changes**: Edit files in the `src/` directory
3. **Build Production**: Run `npm run build` to create production assets
4. **Test in WordPress**: The built files will be automatically loaded in the WordPress admin

## File Structure

```
src/
├── dashboard.js          # Main dashboard JavaScript
└── dashboard.css         # Dashboard styles
```

## API Endpoints

The dashboard consumes these WordPress REST API endpoints:

- `GET /wp-json/geo-elementor/v1/analytics/overview` - Overview metrics
- `GET /wp-json/geo-elementor/v1/analytics/countries` - Country analytics
- `GET /wp-json/geo-elementor/v1/analytics/rules` - Rules performance data
- `GET /wp-json/geo-elementor/v1/analytics/trends` - Trends over time

## Customization

### Adding New Charts

1. Add new chart method to the `GeoElementorDashboard` class in `src/dashboard.js`
2. Add corresponding API endpoint in `includes/dashboard-api.php`
3. Add chart styles to `src/dashboard.css`

### Styling

The dashboard uses custom CSS. Customize the design by:

1. Modifying styles in `src/dashboard.css`
2. Using CSS custom properties for theming
3. Adding responsive breakpoints as needed

### Data Sources

The dashboard pulls data from:

- `geo_rule` post type (rules, clicks, views)
- `egp_countries` meta field (country targeting)
- `egp_active` meta field (rule status)
- Variant groups (if available)

## Production Deployment

1. Run `npm run build` to create production assets
2. The built files will be in `assets/js/dashboard/`
3. WordPress will automatically load these files in the admin

## How Automatic Tracking Works

The Geo Elementor plugin automatically handles all tracking without requiring any manual HTML modifications. Here's how it works:

### 🎯 **Automatic Element Detection**

When you enable geo targeting on any Elementor element:

1. **Sections & Containers**: Automatically get impression tracking when they enter the viewport
2. **Widgets**: Automatically get click tracking when users interact with them
3. **Forms**: Automatically get submission and field interaction tracking

### 🔧 **No Manual Setup Required**

**Before (Manual):**
```html
<div onclick="egpTrackClick(123)" data-rule-id="123">
    Your content
</div>
```

**Now (Automatic):**
```html
<!-- Elementor automatically adds tracking -->
<div data-rule-id="auto-generated" data-element-type="widget" onclick="egpTrackClick(auto-id)">
    Your content
</div>
```

### 📊 **Smart Tracking by Element Type**

| Element Type | Tracking Method | What Gets Tracked |
|-------------|----------------|-------------------|
| **Sections** | Impression | When section becomes visible |
| **Containers** | Impression | When container becomes visible |
| **Widgets** | Click | When users click widget content |
| **Forms** | Submission + Fields | Form completions and field interactions |

### 🎨 **For Content Creators**

1. **Add any Elementor element** (section, container, widget)
2. **Enable geo targeting** in the element settings
3. **Select target countries** using the native dropdown
4. **Publish your page** - tracking works automatically!

### 📈 **Analytics Dashboard**

The dashboard automatically shows:
- **Impressions**: How many times geo-targeted elements were viewed
- **Clicks**: How many times users interacted with elements
- **Form Submissions**: Successful form completions
- **CTR**: Click-through rates
- **Conversion Rates**: Form submission rates

## Troubleshooting

### Dashboard Not Loading

1. Ensure you've run `npm run build`
2. Check that files exist in `assets/js/dashboard/`
3. Verify WordPress REST API is enabled
4. Check browser console for errors

### Build Errors

1. Ensure Node.js 16+ is installed
2. Delete `node_modules` and run `npm install` again
3. Check for syntax errors in React components

### API Errors

1. Verify the plugin is active
2. Check that REST API endpoints are accessible
3. Ensure user has proper permissions

### Tracking Not Working

1. **Verify geo targeting is enabled** on the element
2. **Check that countries are selected** in the element settings
3. **Ensure the element is published** and not in draft mode
4. **Test with different countries** using the admin country switcher
5. **Check browser developer tools** for JavaScript errors

## Contributing

1. Make changes in the `src/` directory
2. Test with `npm run dev`
3. Build with `npm run build`
4. Test in WordPress admin

## License

Same as the Geo Elementor plugin.
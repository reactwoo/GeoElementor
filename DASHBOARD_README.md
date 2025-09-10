# Geo Elementor Dashboard

A modern React.js dashboard for the Geo Elementor plugin, featuring Google Analytics-style UI with comprehensive analytics and reporting.

## Features

- **Overview Cards**: Total rules, active rules, clicks, countries, conversion rates
- **Interactive Charts**: Country performance, trends over time
- **Rules Performance Table**: Sortable table with detailed rule analytics
- **Real-time Data**: Live updates from WordPress REST API
- **Responsive Design**: Works on desktop and mobile devices

## Tech Stack

- **Frontend**: React 18 with modern hooks
- **Charts**: Recharts for beautiful visualizations
- **Styling**: Tailwind CSS for Google Analytics-like design
- **Build System**: Vite for fast development and building
- **Icons**: Lucide React for consistent iconography

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
├── main.jsx              # React app entry point
├── App.jsx               # Main dashboard component
├── index.css             # Global styles with Tailwind
└── components/
    ├── OverviewCards.jsx     # Overview metrics cards
    ├── CountryChart.jsx      # Country performance charts
    ├── TrendsChart.jsx       # Performance trends over time
    ├── RulesTable.jsx        # Rules performance table
    └── LoadingSpinner.jsx    # Loading state component
```

## API Endpoints

The dashboard consumes these WordPress REST API endpoints:

- `GET /wp-json/geo-elementor/v1/analytics/overview` - Overview metrics
- `GET /wp-json/geo-elementor/v1/analytics/countries` - Country analytics
- `GET /wp-json/geo-elementor/v1/analytics/rules` - Rules performance data
- `GET /wp-json/geo-elementor/v1/analytics/trends` - Trends over time

## Customization

### Adding New Charts

1. Create a new component in `src/components/`
2. Import and use in `App.jsx`
3. Add corresponding API endpoint in `includes/dashboard-api.php`

### Styling

The dashboard uses Tailwind CSS. Customize the design by:

1. Modifying `tailwind.config.js` for theme changes
2. Adding custom styles in `src/index.css`
3. Using Tailwind utility classes in components

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

## Contributing

1. Make changes in the `src/` directory
2. Test with `npm run dev`
3. Build with `npm run build`
4. Test in WordPress admin

## License

Same as the Geo Elementor plugin.
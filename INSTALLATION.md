# Geo Elementor Dashboard Installation

## Quick Start

1. **Install Dependencies**:
   ```bash
   npm install
   ```

2. **Build Dashboard**:
   ```bash
   npm run build
   ```
   
   Or use the build script:
   ```bash
   ./build-dashboard.sh
   ```

3. **Access Dashboard**:
   - Go to WordPress Admin
   - Navigate to "Geo Elementor" → "Dashboard"
   - Enjoy your modern analytics dashboard!

## What's Included

✅ **Submenu Renamed**: "Geo Elementor" → "Dashboard"  
✅ **Modern React UI**: Google Analytics-style interface  
✅ **Interactive Charts**: Country performance, trends, rules analytics  
✅ **Real-time Data**: Live updates from your WordPress data  
✅ **Responsive Design**: Works on all devices  
✅ **Comprehensive Analytics**: Rules, clicks, views, countries, conversion rates  

## Features Overview

### 📊 Overview Cards
- Total Rules Count
- Active Rules Count  
- Total Clicks
- Countries Targeted
- Conversion Rate
- Variant Groups

### 📈 Interactive Charts
- **Country Performance**: Bar chart and pie chart of top countries
- **Trends Over Time**: 30-day performance trends
- **Rules Performance**: Sortable table with detailed metrics

### 🎯 Analytics Data
- Real-time clicks and views tracking
- Country-based performance analysis
- Rule conversion rates
- Geographic targeting insights

## Troubleshooting

### Dashboard Shows "Loading..." Message
- Run `npm run build` to build the React dashboard
- Check that files exist in `assets/js/dashboard/`

### Charts Not Loading
- Verify WordPress REST API is enabled
- Check browser console for API errors
- Ensure user has admin permissions

### Build Errors
- Ensure Node.js 16+ is installed
- Delete `node_modules` and run `npm install` again
- Check for syntax errors in the console

## Development

To make changes to the dashboard:

1. Edit files in `src/` directory
2. Run `npm run dev` for development
3. Run `npm run build` for production
4. Refresh WordPress admin to see changes

## Support

For issues or questions:
1. Check the browser console for errors
2. Verify all dependencies are installed
3. Ensure WordPress REST API is working
4. Check file permissions in `assets/js/dashboard/`
# Changelog

All notable changes to the Geo Elementor plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.2] - 2024-01-XX

### Added
- **Modern Analytics Dashboard**: Complete rewrite of the admin dashboard with Google Analytics-style interface
- **Lightweight Architecture**: Ultra-lightweight vanilla JavaScript dashboard (~15KB total bundle size)
- **Comprehensive Analytics API**: New REST API endpoints for real-time analytics data
- **Overview Metrics Cards**: Display total rules, active rules, clicks, countries, conversion rates, and variant groups
- **Interactive Country Charts**: Visual representation of top performing countries with bar and pie charts
- **Performance Trends**: 30-day performance trends with visual timeline charts
- **Rules Performance Table**: Sortable table showing detailed rule analytics with conversion rates
- **Real-time Data Updates**: Live data fetching from WordPress REST API
- **Responsive Design**: Mobile-first responsive design that works on all devices
- **Zero Dependencies**: Pure vanilla JavaScript implementation with no external libraries
- **Build System**: Optimized esbuild-based build system for minimal bundle size

### Changed
- **Admin Menu Structure**: Renamed "Geo Elementor" submenu to "Dashboard" for better UX
- **Dashboard Performance**: Significantly improved loading times and reduced memory usage
- **Code Architecture**: Refactored dashboard to use modern ES6+ JavaScript patterns
- **Bundle Optimization**: Implemented tree shaking and aggressive minification

### Technical Improvements
- **API Endpoints**: Added 4 new REST API endpoints:
  - `/wp-json/geo-elementor/v1/analytics/overview` - Overview metrics
  - `/wp-json/geo-elementor/v1/analytics/countries` - Country performance data
  - `/wp-json/geo-elementor/v1/analytics/rules` - Rules performance data
  - `/wp-json/geo-elementor/v1/analytics/trends` - Trends over time
- **Data Sources**: Enhanced data collection from existing WordPress meta fields
- **Performance**: Optimized database queries for faster data retrieval
- **Security**: Improved nonce verification and permission checks

### Features
- **Geographic Analytics**: Track performance by country with visual heatmaps
- **Conversion Tracking**: Monitor click-through rates and conversion metrics
- **Rule Management**: Visual overview of all geo rules with performance metrics
- **Trend Analysis**: Historical performance data with 30-day trends
- **Export Ready**: Data structure prepared for future export functionality

### Developer Experience
- **Build Tools**: Simple npm-based build system with esbuild
- **Documentation**: Comprehensive documentation for dashboard customization
- **Code Quality**: Clean, maintainable vanilla JavaScript code
- **Performance Monitoring**: Built-in bundle size tracking and optimization

## [1.0.1] - 2024-01-XX

### Added
- Initial release with core geo-targeting functionality
- Elementor integration for popups and pages
- MaxMind GeoLite2 database integration
- Basic admin interface for rule management
- Country-based targeting system
- WordPress admin menu structure

### Features
- Geo rules creation and management
- Popup and page targeting
- Country selection interface
- Basic analytics tracking
- Elementor widget integration

## [1.0.0] - 2024-01-XX

### Added
- Initial plugin release
- Core geo-targeting functionality
- Basic WordPress integration
=== Geo Elementor ===
Contributors: reactwoo
Tags: elementor, popup, geolocation, geo-targeting, maxmind, country-specific, widget, globals, geo-rules, location-based
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.5.29
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Advanced geo-targeting solution for Elementor. Create location-based rules for popups, pages, and content with comprehensive geo rules management system.

== Description ==

**Geo Elementor** is a comprehensive WordPress plugin that provides advanced geo-targeting capabilities for Elementor websites. Using MaxMind's GeoLite2 database, the plugin enables you to create sophisticated location-based rules for popups, pages, and content, with a powerful geo rules management system.

= Key Features =

* **Built on ReactWoo Geo Core**: Reuses shared IP/country engine and MaxMind management from Geo Core
* **City targeting (Geo Elementor)**: City-level matching and Elementor routing are provided here (City Targeting add-on). Geo Core may expose a city string in visitor data for display/API, but **city-based routing rules** are implemented in Geo Elementor — not in Geo Core’s free country routing
* **Clear free vs advanced split**: Geo Core handles page/popup baseline, GeoElementor adds advanced targeting depth
* **Geo Rules Management**: Create and manage complex geo-targeting rules from a dedicated admin interface
* **Multi-Target Support**: Advanced targeting for sections, containers, widgets, and grouped experiences
* **Geo-Targeted Popups**: Show specific popups to visitors from selected countries
* **Geo Widget**: Dedicated Elementor widget for geo-targeting any content
* **Global Settings Integration**: Apply geo-targeting to Elementor's global widgets, colors, and typography
* **Preferred Countries**: Set default target countries in admin settings for consistent geo-targeting
* **Deep Elementor Integration**: Seamlessly integrated into Elementor Pro popup editor
* **MaxMind GeoLite2 Support**: Uses industry-standard geolocation database
* **Flexible Fallback Options**: Configure what happens when no country match is found
* **Real-time Country Detection**: Automatic IP-to-country resolution
* **Performance Optimized**: Cached geolocation results for better performance
* **Analytics & Tracking**: Monitor popup performance by country
* **Professional Licensing**: Integrated with reactwoo.com license server
* **Two-Way Synchronization**: Manual rules sync with Elementor popup settings automatically

= How It Works =

1. **Geo Core baseline**: Configure MaxMind and base geo engine in ReactWoo Geo Core
2. **Free Elementor baseline**: Use document-level geo visibility on Elementor pages/popups in Geo Core
3. **Free variant routing baseline**: Use Geo Core page-level server-side routing (1 default + 1 country mapping per page)
4. **Create advanced rules**: Use GeoElementor to add section/container/widget targeting and advanced multi-variant logic
4. **Configure Popups**: Add advanced geo targeting and behavior controls in Elementor
5. **Use Geo Widget**: Drag the "Geo" widget into Elementor for dynamic geo-aware content
6. **Global Settings**: Apply geo-targeting to Elementor's global widgets, colors, and typography
7. **Automatic Detection**: Shared engine detects visitor countries and applies matching rules
8. **Analytics**: Track performance and engagement by country

= Use Cases =

* **E-commerce**: Show country-specific promotions and shipping information
* **Local Businesses**: Display location-relevant offers and contact details
* **Multilingual Sites**: Show language-appropriate content based on location
* **Regional Marketing**: Target campaigns to specific geographic markets
* **Compliance**: Display region-specific legal notices and terms
* **Content Personalization**: Use the Geo Widget to show different content blocks based on visitor location
* **Global Branding**: Apply different colors, fonts, and styles to global elements based on geography
* **Regional Navigation**: Show different navigation elements or CTAs based on visitor country
* **Complex Targeting**: Create sophisticated rules that target multiple content types based on visitor location

= Requirements =

* WordPress 5.0 or higher
* Elementor 3.0.0 or higher
* Elementor Pro (required for popup functionality)
* PHP 7.4 or higher
* MaxMind GeoLite2 license key (free)

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/geo-elementor` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to **Settings > Geo Elementor** to configure your MaxMind license key
4. Download the GeoLite2 database using the "Update Database" button
5. Create geo rules using the **Geo Elementor > Geo Rules** menu or edit your Elementor popups and configure geo-targeting rules in the new "Geo Targeting" section

== Frequently Asked Questions ==

= Do I need Elementor Pro? =

Yes, Elementor Pro is required as this plugin extends Elementor's popup functionality.

= Is MaxMind GeoLite2 free? =

Yes, MaxMind GeoLite2 offers a free tier with up to 1000 requests per day. You can sign up at [maxmind.com](https://www.maxmind.com/en/geolite2/signup).

= How accurate is the geolocation? =

MaxMind GeoLite2 provides country-level accuracy with approximately 99.5% accuracy for country detection.

= Can I target specific cities or regions? =

Currently, the plugin supports country-level targeting. City-level targeting may be added in future versions.

= Does the plugin work with caching plugins? =

Yes, the plugin is designed to work with caching plugins. Geolocation results are cached for 1 hour to improve performance.

= Can I exclude certain countries? =

Yes, you can configure fallback behavior to show popups to all visitors except those from specific countries.

= Is the plugin GDPR compliant? =

The plugin only processes IP addresses for geolocation purposes and does not store personal data. IP addresses are not logged or stored permanently.

= How often should I update the database? =

MaxMind updates their database weekly. The plugin can be configured to automatically update the database, or you can update it manually.

= Can I use multiple popups for different countries? =

Yes, you can configure different popups for different countries. The plugin will show the first matching popup it finds.

= What is the Geo Widget? =

The Geo Widget is a dedicated Elementor widget that allows you to add geo-targeting to any content. Simply drag it into your design, configure target countries, and add your content. It's perfect for showing different content blocks based on visitor location.

= How do Global Settings work? =

Global Settings integration allows you to apply geo-targeting to Elementor's global widgets, colors, and typography. This means you can have different global styles and content for different countries, perfect for international brands.

= Can I set default target countries? =

Yes, you can configure preferred countries in the admin settings. These will be used as defaults when creating new geo-targeted content, ensuring consistency across your site.

= Does the plugin work on mobile devices? =

Yes, the plugin works on all devices and automatically detects mobile IP addresses.

== Screenshots ==

1. Plugin settings page with MaxMind configuration
2. Elementor popup editor with geo-targeting controls
3. Country selection interface
4. License management page
5. Database update interface
6. Analytics dashboard
7. Geo Widget in Elementor editor
8. Global Settings with geo-targeting controls
9. Preferred Countries configuration

== Changelog ==

= 1.0.5.26 =
* Fixed city targeting controls registration hook for Elementor editor visibility.

= 1.0.5.25 =
* Added time targeting addon implementation with optional runtime debug badge.
* Improved city targeting UX with search-and-add city options and selection-list controls.
* Fixed addon manager activation order so active addons initialize reliably.

= 1.0.5.23 =
* Version bump for release verification.

= 1.0.5.22 =
* Version bump for ReactWoo API update pipeline and release testing.

= 1.0.5.5 =
* Removed MaxMind/DB management actions from GeoElementor settings (Geo Core is now the single owner).
* Added prerequisite admin notice after activation to guide users to install/activate ReactWoo Geo Core first.

= 1.0.5.2 =
* Added compatibility guard for specific Elementor dependency-order debug notices on newer WordPress versions.
* Kept notice suppression narrowly scoped to known false positives to avoid hiding real warnings.

= 1.0.5.1 =
* Added WordPress-safe inner section navigation tabs across key GeoElementor admin screens.
* Refined admin card spacing and visual hierarchy to align with approved dashboard styling direction.

= 1.0.5 =
* Added Geo Core routing extension contract integration (`rwgc_route_variant_decision`) for Pro users.
* Clarified capability split: Geo Core baseline routing vs GeoElementor advanced multi-variant routing.
* Updated compatibility metadata.

= 1.0.4 =
* Aligned with ReactWoo Geo Core readiness checks and setup messaging.
* Clarified free baseline vs advanced capability split in admin and documentation.
* Prevented duplicate non-pro Elementor control sections when Geo Core baseline is active.

= 1.0.0 =
* Initial release
* MaxMind GeoLite2 integration
* Elementor Pro popup editor integration
* Country-based popup targeting
* Fallback behavior configuration
* Performance optimization with caching
* Analytics and tracking
* Professional licensing system
* Comprehensive admin interface

== Upgrade Notice ==

= 1.0.0 =
Initial release of Geo Elementor. This plugin requires Elementor Pro and a MaxMind GeoLite2 license key to function.

== Support ==

For support, please visit [reactwoo.com](https://reactwoo.com) or contact us at support@reactwoo.com.

== License ==

This plugin is licensed under the GPL v2 or later.

== Credits ==

* **MaxMind**: For providing the GeoLite2 geolocation database
* **Elementor**: For the excellent page builder platform
* **WordPress**: For the robust content management system

== Developer Notes ==

The plugin is built with modern WordPress development practices:

* Object-oriented PHP architecture
* WordPress coding standards compliance
* Comprehensive error handling
* Security best practices
* Performance optimization
* Extensible architecture with hooks and filters

For developers, the plugin provides several action and filter hooks for customization:

* `egp_before_popup_display` - Fired before popup display logic
* `egp_after_popup_display` - Fired after popup display logic
* `egp_popup_country_match` - Filter for custom country matching logic
* `egp_visitor_country_detected` - Action when visitor country is detected

== Roadmap ==

== Changelog ==

= 1.0.5.29 =
* **Dependencies:** Plugin header **`Requires Plugins: elementor, reactwoo-geocore`** (canonical Geo Core slug). **`package.json`** documents **`reactwooBuild.geoCoreDependencySlug`** for release parity.
* **Build:** Zip packaging reads **`reactwooBuild`** (`pluginFolder`, `zipFile`, `geoCoreDependencySlug`).

= 1.0.5.28 =
* **ReactWoo Geo Core:** Admin notice link when Core is not ready now points to **`admin.php?page=rwgc-settings`** (Geo Core Settings), matching the live menu slug.
* **Documentation:** `EGP_Pro_Migration` docblock clarifies that free→Pro variant migration reads **`RWGC_Routing::get_page_route_config()`**; Geo Core owns legacy meta and internal **`RWGC_Legacy_Route_Mapper`** / resolver plumbing — Geo Elementor only maps into Pro variant groups.
* **Compatibility:** Use with **ReactWoo Geo Core 0.1.10.x** or newer for the current routing engine and REST/capabilities surface.

= 1.0.2 =
* **NEW: Modern Analytics Dashboard** - Complete rewrite with Google Analytics-style interface
* **NEW: Lightweight Architecture** - Ultra-lightweight vanilla JavaScript dashboard (~15KB total)
* **NEW: Comprehensive Analytics API** - 4 new REST API endpoints for real-time data
* **NEW: Overview Metrics Cards** - Total rules, active rules, clicks, countries, conversion rates
* **NEW: Interactive Country Charts** - Visual representation of top performing countries
* **NEW: Performance Trends** - 30-day performance trends with visual timeline
* **NEW: Rules Performance Table** - Sortable table with detailed rule analytics
* **NEW: Real-time Data Updates** - Live data fetching from WordPress REST API
* **NEW: Responsive Design** - Mobile-first design that works on all devices
* **NEW: Zero Dependencies** - Pure vanilla JavaScript with no external libraries
* **IMPROVED: Admin Menu** - Renamed "Geo Elementor" submenu to "Dashboard"
* **IMPROVED: Performance** - Significantly faster loading and reduced memory usage
* **IMPROVED: Code Architecture** - Modern ES6+ JavaScript patterns
* **IMPROVED: Bundle Optimization** - Tree shaking and aggressive minification

= 1.0.1 =
* Initial release with core geo-targeting functionality
* Elementor integration for popups and pages
* MaxMind GeoLite2 database integration
* Basic admin interface for rule management
* Country-based targeting system

= 1.0.0 =
* Initial plugin release
* Core geo-targeting functionality
* Basic WordPress integration

== Upgrade Notice ==

= 1.0.2 =
Major update with new analytics dashboard! The admin interface has been completely redesigned with a modern Google Analytics-style dashboard featuring comprehensive analytics, interactive charts, and real-time data updates. The new dashboard is ultra-lightweight (~15KB) and requires no external dependencies.

= 1.0.1 =
Initial release with core geo-targeting functionality for Elementor.

== Future Roadmap ==

* City-level geolocation targeting
* A/B testing for geo-targeted popups
* Integration with popular analytics platforms
* Enhanced caching and performance optimization
* Multi-language support for country names
* Bulk import/export of geo-targeting rules


=== Geo Elementor ===
Contributors: reactwoo
Tags: elementor, popup, geolocation, geo-targeting, maxmind, country-specific, widget, globals, geo-rules, location-based
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Advanced geo-targeting solution for Elementor. Create location-based rules for popups, pages, and content with comprehensive geo rules management system.

== Description ==

**Geo Elementor** is a comprehensive WordPress plugin that provides advanced geo-targeting capabilities for Elementor websites. Using MaxMind's GeoLite2 database, the plugin enables you to create sophisticated location-based rules for popups, pages, and content, with a powerful geo rules management system.

= Key Features =

* **Geo Rules Management**: Create and manage complex geo-targeting rules from a dedicated admin interface
* **Multi-Target Support**: Target popups, pages, sections, and widgets with geo rules
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

1. **Setup**: Configure your MaxMind license key and preferred countries in the plugin settings
2. **Database**: Download the latest GeoLite2 country database
3. **Create Geo Rules**: Use the Geo Rules admin interface to create targeting rules for popups, pages, or content
4. **Configure Popups**: Add geo-targeting rules to your Elementor popups (manual or automatic sync)
5. **Use Geo Widget**: Drag the "Geo" widget into any Elementor design for content geo-targeting
6. **Global Settings**: Apply geo-targeting to Elementor's global widgets, colors, and typography
7. **Automatic Detection**: Plugin detects visitor countries and shows appropriate content
8. **Analytics**: Track popup performance and visitor engagement by country

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

Future versions may include:

* City-level geolocation targeting
* Advanced analytics and reporting
* A/B testing for geo-targeted popups
* Integration with popular analytics platforms
* Enhanced caching and performance optimization
* Multi-language support for country names
* Bulk import/export of geo-targeting rules


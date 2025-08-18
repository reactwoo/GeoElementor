# Elementor Geo Popup - Installation Guide

## Prerequisites

Before installing the plugin, ensure you have:

- WordPress 5.0 or higher
- Elementor 3.0.0 or higher
- Elementor Pro (required for popup functionality)
- PHP 7.4 or higher
- A MaxMind GeoLite2 license key (free)

## Installation Steps

### 1. Install the Plugin

#### Option A: Upload via WordPress Admin
1. Download the plugin ZIP file
2. Go to **WordPress Admin > Plugins > Add New**
3. Click **Upload Plugin**
4. Choose the ZIP file and click **Install Now**
5. Click **Activate Plugin**

#### Option B: Upload via FTP
1. Extract the plugin ZIP file
2. Upload the `elementor-geo-popup` folder to `/wp-content/plugins/`
3. Go to **WordPress Admin > Plugins**
4. Find "Elementor Geo Popup" and click **Activate**

### 2. Install Dependencies

The plugin requires Composer dependencies. After activation:

1. Navigate to the plugin directory: `/wp-content/plugins/elementor-geo-popup/`
2. Run: `composer install --no-dev`
3. Ensure the `vendor/` directory is created

### 3. Configure MaxMind Integration

1. Go to **Settings > Elementor Geo Popup**
2. Enter your MaxMind license key
   - Get a free key at [maxmind.com](https://www.maxmind.com/en/geolite2/signup)
3. Click **Test Connection** to verify
4. Click **Update Database** to download the latest GeoLite2 database

### 4. Configure Global Settings

In the same settings page:

1. **Auto Update Database**: Enable for automatic weekly updates
2. **Default Popup ID**: Set a fallback popup (optional)
3. **Fallback Behavior**: Choose what happens when no country match is found
4. **Debug Mode**: Enable for troubleshooting (optional)

### 5. Configure Popup Geo-Targeting

1. Go to **Templates > Popups** in Elementor
2. Edit an existing popup or create a new one
3. In the left panel, find the **Geo Targeting** section
4. Enable geo targeting for the popup
5. Select target countries
6. Choose fallback behavior
7. Save the popup

## Configuration Options

### Global Settings

- **MaxMind License Key**: Your MaxMind API key
- **Database Path**: Location of the downloaded database file
- **Auto Update**: Weekly automatic database updates
- **Debug Mode**: Log geolocation data for troubleshooting

### Popup-Level Settings

- **Enable Geo Targeting**: Turn on/off for individual popups
- **Target Countries**: Select specific countries for each popup
- **Fallback Behavior**: What to show when no country match is found

### Fallback Behaviors

- **Inherit**: Use global settings
- **Show to All**: Display popup regardless of country
- **Show to None**: Hide popup when no country match
- **Show Default**: Display the default popup

## Testing Your Setup

### 1. Test Country Detection

1. Enable debug mode in settings
2. Visit your site from different locations
3. Check the error log for geolocation data
4. Verify the detected countries are correct

### 2. Test Popup Display

1. Create test popups for different countries
2. Use a VPN or proxy to test from different locations
3. Verify the correct popup displays for each country
4. Test fallback behavior with unmatched countries

### 3. Test Performance

1. Monitor page load times
2. Check database query performance
3. Verify caching is working correctly
4. Test with multiple concurrent visitors

## Troubleshooting

### Common Issues

#### Plugin Not Activating
- Ensure Elementor and Elementor Pro are active
- Check PHP version compatibility
- Verify file permissions

#### MaxMind Connection Failed
- Verify license key is correct
- Check server can reach maxmind.com
- Ensure cURL is enabled on server

#### Database Download Failed
- Check server has write permissions
- Verify sufficient disk space
- Check PHP memory limits

#### Popups Not Showing
- Verify geo targeting is enabled
- Check country selection is correct
- Ensure popup is published
- Check fallback behavior settings

#### Performance Issues
- Enable caching
- Check database size
- Monitor server resources
- Optimize database queries

### Debug Mode

When enabled, debug mode will log:

- Visitor IP addresses
- Detected countries
- Popup matching results
- Database lookup errors

Check your WordPress error log for debug information.

### Support

For additional support:

- Visit [reactwoo.com](https://reactwoo.com)
- Contact support@reactwoo.com
- Check the plugin documentation
- Review WordPress error logs

## Security Considerations

### Data Privacy

- The plugin only processes IP addresses for geolocation
- No personal data is stored permanently
- IP addresses are cached temporarily for performance
- GDPR compliant data handling

### Server Security

- Database files are protected with .htaccess
- Admin functions require proper permissions
- AJAX requests are secured with nonces
- Input validation and sanitization

### Best Practices

- Keep the GeoLite2 database updated
- Monitor plugin performance
- Regular security updates
- Backup configuration settings

## Performance Optimization

### Caching

- Geolocation results are cached for 1 hour
- Database queries are optimized
- Minimal impact on page load times

### Database Management

- Regular database updates
- Automatic cleanup of old data
- Efficient country lookup queries

### Server Resources

- Minimal memory usage
- Optimized file operations
- Efficient IP address handling

## Updating the Plugin

### Automatic Updates

1. Go to **WordPress Admin > Updates**
2. Check for plugin updates
3. Update when available

### Manual Updates

1. Download the latest version
2. Deactivate the plugin
3. Replace plugin files
4. Reactivate the plugin
5. Update database if prompted

### Database Updates

- Plugin will prompt for database updates
- Backup existing data before updating
- Test functionality after updates

## License Management

### Activating Your License

1. Go to **Settings > EGP License**
2. Enter your license key from reactwoo.com
3. Click **Activate License**
4. Verify license status

### License Status

- **Valid**: Full functionality available
- **Invalid**: Check license key
- **Expired**: Renew your license
- **Inactive**: Activate your license

### Support and Updates

- Valid licenses include support
- Access to plugin updates
- Priority customer service
- Feature requests consideration

## Advanced Configuration

### Custom Hooks

The plugin provides several hooks for developers:

```php
// Custom country matching logic
add_filter('egp_popup_country_match', 'custom_country_match', 10, 3);

// Custom popup display logic
add_action('egp_before_popup_display', 'custom_display_logic', 10, 2);

// Custom geolocation handling
add_filter('egp_visitor_country', 'custom_country_detection', 10, 1);
```

### Database Customization

- Custom country lists
- Extended geolocation data
- Custom fallback behaviors
- Advanced targeting rules

### Integration Examples

- WooCommerce integration
- Custom analytics platforms
- Third-party geolocation services
- Advanced caching systems

## Maintenance

### Regular Tasks

- Update GeoLite2 database weekly
- Monitor plugin performance
- Check error logs
- Backup configuration

### Monitoring

- Popup display rates
- Country detection accuracy
- Performance metrics
- Error rates

### Optimization

- Database cleanup
- Cache management
- Performance tuning
- Resource optimization

## Conclusion

The Elementor Geo Popup plugin provides powerful geolocation-based popup targeting for WordPress sites using Elementor Pro. With proper setup and maintenance, it can significantly improve user engagement and conversion rates through location-specific content delivery.

For ongoing support and updates, ensure your license is active and regularly check for plugin updates and database refreshes.




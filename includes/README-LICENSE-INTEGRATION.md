# Centralized License Manager Integration

## Overview

The Geo Elementor plugin includes a centralized license manager that prevents conflicts between multiple plugins making license calls to the same server (`license.reactwoo.com`). This system provides:

- **Request Deduplication**: Prevents multiple simultaneous requests for the same license
- **Intelligent Caching**: 15-minute cache for license data to reduce server load
- **Shared Cron Jobs**: Single hourly license check across all plugins
- **Conflict Prevention**: Ensures plugins don't flood the license server

## How It Works

### 1. Automatic Initialization

The centralized manager automatically initializes when the Geo Elementor plugin loads. It sets up:
- Shared cron job for license checks
- Cache management system
- Request locking mechanism

### 2. Request Deduplication

When multiple plugins request license data simultaneously:
- First request proceeds normally
- Subsequent requests wait for the first to complete
- Results are cached and shared
- Maximum wait time: 10 seconds

### 3. Intelligent Caching

- License data cached for 15 minutes
- Cache automatically cleared on license activation/deactivation
- WordPress handles cache expiration

## Integration for Other Plugins

### Basic Usage

```php
// Get the centralized license manager instance
$license_manager = EGP_Centralized_License_Manager::get_instance();

// Get license data (with caching and deduplication)
$license_data = $license_manager->get_license_data('your-plugin-slug');

// Activate license (with deduplication)
$result = $license_manager->activate_license(
    'your-plugin-slug',
    $license_key,
    $domain,
    $plugin_version,
    'your-product-type'
);
```

### Plugin Integration Example

```php
class Your_Plugin_Licensing {
    
    private $license_manager;
    private $plugin_slug = 'your-plugin';
    
    public function __construct() {
        // Get centralized manager instance
        $this->license_manager = EGP_Centralized_License_Manager::get_instance();
        
        // Your plugin's license logic here
        add_action('admin_init', array($this, 'check_license'));
    }
    
    public function check_license() {
        // This will use caching and deduplication automatically
        $license_data = $this->license_manager->get_license_data($this->plugin_slug);
        
        if (isset($license_data['valid']) && $license_data['valid']) {
            // License is valid
            update_option('your_plugin_license_status', 'valid');
        } else {
            // License is invalid
            update_option('your_plugin_license_status', 'invalid');
        }
    }
    
    public function activate_license($license_key) {
        $domain = wp_parse_url(get_site_url(), PHP_URL_HOST);
        
        $result = $this->license_manager->activate_license(
            $this->plugin_slug,
            $license_key,
            $domain,
            '1.0.0',
            'your-product-type'
        );
        
        if (!is_wp_error($result)) {
            // Store tokens
            update_option('your_plugin_license_access_token', $result['accessToken']);
            update_option('your_plugin_license_refresh_token', $result['refreshToken']);
            return true;
        }
        
        return $result;
    }
}
```

## Benefits

### For Plugin Developers
- **No More Conflicts**: Multiple plugins can safely use the same license server
- **Reduced Code**: No need to implement caching, deduplication, or cron management
- **Better Performance**: Shared license checks reduce overall server load
- **Consistent Behavior**: All plugins use the same license validation logic

### For Site Owners
- **Faster Loading**: Reduced duplicate license checks
- **Better Reliability**: No more license conflicts between plugins
- **Lower Server Load**: Fewer requests to license server
- **Consistent Licensing**: All plugins use the same license validation system

## Configuration

### Cache Duration
- License data: 15 minutes
- Request locks: 30 seconds
- Activation locks: 60 seconds

### Cron Schedule
- Shared license check: Hourly
- Plugin-specific checks: Daily (if needed)

### Supported Product Types
- `geo` - Geo Elementor plugin
- `aliexpress` - Ali2Woo plugin
- `other-product` - Other plugins

## Troubleshooting

### Common Issues

1. **License conflicts**: Ensure each plugin uses a unique `plugin_slug`
2. **Cache issues**: Clear WordPress cache if license status seems stuck
3. **Performance**: Monitor server logs for excessive license requests

### Debug Mode

Enable WordPress debug mode to see license manager activity:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Manual Cache Clear

```php
// Clear specific plugin's license cache
wp_cache_delete('license_data_your-plugin', 'egp_license_cache');

// Clear all license cache
wp_cache_flush_group('egp_license_cache');
```

## Best Practices

1. **Unique Plugin Slugs**: Always use unique identifiers for your plugin
2. **Error Handling**: Always check for WP_Error returns
3. **Token Storage**: Store access and refresh tokens securely
4. **Graceful Degradation**: Handle license failures gracefully
5. **Regular Updates**: Use the centralized manager's cron for regular checks

## Support

For issues with the centralized license manager:
- Check WordPress debug logs
- Verify plugin slug uniqueness
- Ensure proper integration following examples above
- Contact support if issues persist


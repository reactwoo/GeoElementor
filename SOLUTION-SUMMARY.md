# Solution: Centralized License Manager for Multi-Plugin Conflict Prevention

## Problem Solved

**Issue**: Multiple plugins (Geo Elementor + Ali2Woo) making separate license calls to the same server (`license.reactwoo.com`) causing:
- Network flooding with duplicate requests
- Potential conflicts between plugins
- Inefficient resource usage
- Poor user experience

**Root Cause**: Each plugin independently calling the license server without coordination or caching.

## Solution Implemented

### 1. Centralized License Manager (`includes/centralized-license-manager.php`)

A singleton class that provides:
- **Request Deduplication**: Prevents multiple simultaneous requests for the same license
- **Intelligent Caching**: 15-minute cache for license data
- **Shared Cron Jobs**: Single hourly license check across all plugins
- **Conflict Prevention**: Ensures plugins don't flood the license server

### 2. Key Features

#### Request Deduplication
```php
// When multiple plugins request the same license simultaneously:
// - First request proceeds normally
// - Subsequent requests wait for the first to complete
// - Results are cached and shared
// - Maximum wait time: 10 seconds
```

#### Intelligent Caching
```php
// License data cached for 15 minutes
// Cache automatically cleared on license activation/deactivation
// WordPress handles cache expiration
```

#### Shared Cron Jobs
```php
// Single hourly license check across all plugins
// Replaces individual plugin cron jobs
// Reduces server load and improves performance
```

### 3. Integration Methods

#### Method 1: Full Integration (Recommended)
```php
class Your_Plugin_Licensing {
    private $license_manager;
    
    public function __construct() {
        $this->license_manager = EGP_Centralized_License_Manager::get_instance();
    }
    
    public function check_license() {
        $license_data = $this->license_manager->get_license_data('your-plugin-slug');
        // Use cached/deduplicated data
    }
}
```

#### Method 2: Direct Method Calls
```php
$license_manager = EGP_Centralized_License_Manager::get_instance();
$license_data = $license_manager->get_license_data('your-plugin-slug');
```

### 4. Benefits

#### For Plugin Developers
- **No More Conflicts**: Multiple plugins can safely use the same license server
- **Reduced Code**: No need to implement caching, deduplication, or cron management
- **Better Performance**: Shared license checks reduce overall server load
- **Consistent Behavior**: All plugins use the same license validation logic

#### For Site Owners
- **Faster Loading**: Reduced duplicate license checks
- **Better Reliability**: No more license conflicts between plugins
- **Lower Server Load**: Fewer requests to license server
- **Consistent Licensing**: All plugins use the same license validation system

## Implementation Details

### Files Created/Modified

1. **`includes/centralized-license-manager.php`** - New centralized manager class
2. **`includes/licensing.php`** - Updated to use centralized manager
3. **`includes/README-LICENSE-INTEGRATION.md`** - Integration documentation
4. **`includes/ali2woo-integration-example.php`** - Example integration for Ali2Woo
5. **`SOLUTION-SUMMARY.md`** - This summary document

### Configuration

- **Cache Duration**: 15 minutes for license data
- **Request Locks**: 30 seconds for license checks, 60 seconds for activation
- **Cron Schedule**: Hourly shared license check
- **Supported Product Types**: `geo`, `aliexpress`, `other-product`

### Error Handling

- **Graceful Degradation**: Falls back to direct calls if centralized manager unavailable
- **Request Timeouts**: Maximum 10-second wait for concurrent requests
- **Cache Failures**: Automatic fallback to direct server calls
- **Lock Expiration**: Automatic cleanup of expired request locks

## Usage Examples

### Basic License Check
```php
$license_manager = EGP_Centralized_License_Manager::get_instance();
$license_data = $license_manager->get_license_data('your-plugin-slug');

if (isset($license_data['valid']) && $license_data['valid']) {
    // License is valid
} else {
    // License is invalid
}
```

### License Activation
```php
$result = $license_manager->activate_license(
    'your-plugin-slug',
    $license_key,
    $domain,
    $plugin_version,
    'your-product-type'
);

if (!is_wp_error($result)) {
    // Store tokens
    update_option('your_plugin_access_token', $result['accessToken']);
    update_option('your_plugin_refresh_token', $result['refreshToken']);
}
```

## Migration Guide

### For Existing Plugins

1. **Remove Direct License Calls**: Replace `wp_remote_post/get` calls with centralized manager
2. **Update Cron Jobs**: Remove individual license check cron jobs
3. **Modify AJAX Handlers**: Use centralized activation/verification methods
4. **Update Storage**: Continue using existing option names for compatibility

### For New Plugins

1. **Include Manager**: Ensure centralized manager is available
2. **Use Unique Slugs**: Each plugin must have a unique identifier
3. **Follow Integration Examples**: Use provided integration patterns
4. **Handle Errors**: Always check for WP_Error returns

## Troubleshooting

### Common Issues

1. **License Conflicts**: Ensure each plugin uses a unique `plugin_slug`
2. **Cache Issues**: Clear WordPress cache if license status seems stuck
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

## Future Enhancements

### Planned Features

1. **Redis Support**: Optional Redis backend for better performance
2. **Rate Limiting**: Per-domain rate limiting for license requests
3. **Metrics Dashboard**: License usage analytics and monitoring
4. **Webhook Support**: Real-time license status updates

### Extension Points

1. **Custom Cache Backends**: Support for custom caching systems
2. **Plugin-Specific Hooks**: Allow plugins to customize behavior
3. **Multi-Server Support**: Support for multiple license servers
4. **Advanced Caching**: Configurable cache durations per plugin

## Conclusion

The centralized license manager provides a robust, efficient solution for preventing conflicts between multiple plugins using the same license server. It eliminates network flooding, improves performance, and ensures consistent behavior across all integrated plugins.

By implementing this solution, both Geo Elementor and Ali2Woo (and future plugins) can coexist without conflicts, while providing better performance and reliability for end users.

## Support

For implementation questions or issues:
1. Review the integration examples
2. Check WordPress debug logs
3. Verify plugin slug uniqueness
4. Contact support if issues persist


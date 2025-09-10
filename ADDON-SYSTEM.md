# Geo Elementor Add-On System

## Overview

The Geo Elementor Add-On System provides a framework for extending the core geo-targeting functionality with additional targeting methods and features. This system allows developers to create modular add-ons that can be installed, activated, and managed through the WordPress admin interface.

## Architecture

### Core Components

1. **EGP_Addon_Manager** - Central manager for all add-ons
2. **EGP_Base_Addon** - Base class that all add-ons must extend
3. **Add-on Registry** - System for registering and discovering add-ons
4. **Admin Interface** - UI for managing add-ons

### File Structure

```
/workspace/
├── includes/
│   ├── addon-manager.php      # Core add-on manager
│   └── addon-base.php         # Base add-on class
├── addons/
│   └── city-targeting/
│       └── city-targeting.php # City targeting add-on
├── assets/
│   ├── css/
│   │   └── addon-manager.css  # Admin styles
│   └── js/
│       └── addon-manager.js   # Admin JavaScript
└── test-addon-system.php      # Test script
```

## Creating an Add-On

### 1. Basic Structure

All add-ons must extend the `EGP_Base_Addon` class and implement required methods:

```php
<?php
class EGP_My_Addon extends EGP_Base_Addon {
    
    protected function get_addon_id() {
        return 'my-addon';
    }
    
    protected function get_addon_data() {
        return array(
            'id' => 'my-addon',
            'name' => 'My Add-on',
            'description' => 'Description of my add-on',
            'version' => '1.0.0',
            'author' => 'Your Name',
            'author_uri' => 'https://yourwebsite.com',
            'plugin_uri' => 'https://yourwebsite.com',
            'requires' => '1.0.0',
            'tested' => '1.0.1',
            'file' => 'my-addon/my-addon.php',
            'class' => 'EGP_My_Addon',
            'category' => 'targeting',
            'tags' => array('custom', 'targeting'),
            'screenshot' => '',
            'icon' => 'eicon-cog',
            'premium' => false,
            'status' => 'available'
        );
    }
    
    protected function init_hooks() {
        // Add your hooks here
    }
    
    protected function init_elementor_integration() {
        // Add Elementor integration here
    }
    
    protected function init_frontend() {
        // Add frontend functionality here
    }
}
```

### 2. Registration

Add-ons are automatically registered when the add-on manager initializes. The registration happens in the `register_core_addons()` method of `EGP_Addon_Manager`.

### 3. Elementor Integration

To add controls to Elementor widgets and containers:

```php
protected function init_elementor_integration() {
    add_action('elementor/widgets/widgets_registered', array($this, 'add_controls_to_widgets'));
}

public function add_controls_to_widgets($widgets_manager) {
    $widget_types = $widgets_manager->get_widget_types();
    
    foreach ($widget_types as $widget_type => $widget) {
        $this->add_controls_to_element($widget);
    }
}

private function add_controls_to_element($element) {
    // Check if element already has our controls
    $controls = $element->get_controls();
    if (isset($controls['egp_my_addon'])) {
        return;
    }
    
    // Add controls section
    $element->start_controls_section(
        'egp_my_addon',
        array(
            'label' => __('My Add-on', 'elementor-geo-popup'),
            'tab' => \Elementor\Controls_Manager::TAB_ADVANCED,
        )
    );
    
    // Add controls
    $element->add_control(
        'egp_my_addon_enabled',
        array(
            'label' => __('Enable My Add-on', 'elementor-geo-popup'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'label_on' => __('On', 'elementor-geo-popup'),
            'label_off' => __('Off', 'elementor-geo-popup'),
            'return_value' => 'yes',
            'default' => '',
        )
    );
    
    $element->end_controls_section();
}
```

### 4. Frontend Functionality

To add frontend functionality:

```php
protected function init_frontend() {
    add_action('wp_head', array($this, 'inject_frontend_script'));
}

public function inject_frontend_script() {
    if (is_admin() || wp_doing_ajax()) {
        return;
    }
    
    ?>
    <script type="text/javascript">
    // Your frontend JavaScript here
    </script>
    <?php
}
```

### 5. Settings Management

The base class provides methods for managing add-on settings:

```php
// Get a setting
$value = $this->get_setting('my_setting', 'default_value');

// Set a setting
$this->set_setting('my_setting', 'new_value');

// Get all settings
$all_settings = $this->get_settings();

// Save settings
$this->save_settings($settings_array);
```

### 6. Admin Interface

To add an admin settings page:

```php
protected function init_hooks() {
    add_action('admin_menu', array($this, 'add_admin_menu'));
}

public function add_admin_menu() {
    add_submenu_page(
        'elementor-geo-popup',
        __('My Add-on Settings', 'elementor-geo-popup'),
        __('My Add-on', 'elementor-geo-popup'),
        'manage_options',
        'egp-my-addon-settings',
        array($this, 'render_admin_settings')
    );
}

public function render_admin_settings() {
    // Render your admin settings page
}
```

## City Targeting Add-On

The City Targeting add-on serves as a complete example of how to implement an add-on. It includes:

### Features

- **City Detection**: Uses OpenWeatherMap API to detect visitor city
- **Elementor Integration**: Adds city targeting controls to widgets and containers
- **Admin Interface**: Settings page for API configuration
- **Frontend Scripts**: JavaScript for city detection and targeting
- **Caching**: Caches city detection results for performance
- **Fallback Support**: Falls back to country targeting when city detection fails

### Configuration

1. Get a free API key from [OpenWeatherMap](https://openweathermap.org/api)
2. Go to Geo Elementor → City Settings
3. Enter your API key
4. Configure fallback behavior and cache duration

### Usage

1. Install and activate the City Targeting add-on
2. Edit any Elementor widget or container
3. Go to Advanced tab → City Targeting
4. Enable city targeting and specify target cities
5. Set fallback behavior (hide, show, or use country targeting)

## API Reference

### EGP_Addon_Manager

#### Methods

- `get_instance()` - Get singleton instance
- `register_addon($addon_data)` - Register an add-on
- `get_registered_addons()` - Get all registered add-ons
- `get_installed_addons()` - Get all installed add-ons
- `get_addon($addon_id)` - Get specific add-on data
- `is_addon_installed($addon_id)` - Check if add-on is installed
- `is_addon_active($addon_id)` - Check if add-on is active
- `install_addon($addon_id)` - Install an add-on
- `activate_addon($addon_id)` - Activate an add-on
- `deactivate_addon($addon_id)` - Deactivate an add-on
- `uninstall_addon($addon_id)` - Uninstall an add-on

### EGP_Base_Addon

#### Abstract Methods (Must Implement)

- `get_addon_id()` - Return add-on ID
- `get_addon_data()` - Return add-on data array

#### Protected Methods (Override as Needed)

- `init_hooks()` - Initialize WordPress hooks
- `init_elementor_integration()` - Initialize Elementor integration
- `init_frontend()` - Initialize frontend functionality
- `is_targeting_condition_met($settings, $context)` - Check targeting condition
- `get_targeting_data($context)` - Get targeting data for frontend

#### Public Methods

- `get_version()` - Get add-on version
- `is_active()` - Check if add-on is active
- `get_setting($key, $default)` - Get setting value
- `set_setting($key, $value)` - Set setting value
- `get_settings()` - Get all settings
- `save_settings($settings)` - Save settings
- `render_admin_settings()` - Render admin settings page
- `handle_ajax_request($action, $data)` - Handle AJAX requests

#### Utility Methods

- `log_debug($message)` - Log debug message
- `get_visitor_data()` - Get visitor data
- `get_visitor_ip()` - Get visitor IP
- `make_api_request($url, $args)` - Make API request
- `cache_set($key, $data, $expiration)` - Cache data
- `cache_get($key)` - Get cached data
- `cache_delete($key)` - Delete cached data
- `add_admin_notice($message, $type)` - Add admin notice
- `get_addon_url($path)` - Get add-on URL
- `get_addon_path($path)` - Get add-on path
- `enqueue_script($handle, $src, $deps, $ver, $in_footer)` - Enqueue script
- `enqueue_style($handle, $src, $deps, $ver)` - Enqueue style
- `register_ajax_handler($action, $callback)` - Register AJAX handler
- `create_nonce($action)` - Create nonce
- `verify_nonce($nonce, $action)` - Verify nonce

## Hooks and Filters

### Actions

- `egp_addon_activated` - Fired when add-on is activated
- `egp_addon_deactivated` - Fired when add-on is deactivated
- `egp_addon_uninstalled` - Fired when add-on is uninstalled

### Filters

- `egp_visitor_data` - Filter visitor data
- `egp_popup_guard_data` - Filter popup guard data

## Testing

Use the included test script to verify the add-on system:

```bash
php test-addon-system.php
```

This will test:
- Add-on manager initialization
- Add-on registration
- Add-on installation
- Add-on activation
- Add-on functionality

## Best Practices

1. **Always check if add-on is active** before executing functionality
2. **Use caching** for API requests to improve performance
3. **Handle errors gracefully** with proper fallbacks
4. **Follow WordPress coding standards**
5. **Use proper sanitization** for all user inputs
6. **Include proper documentation** and comments
7. **Test thoroughly** before releasing

## Future Add-Ons

The system is designed to support various types of add-ons:

- **Weather Targeting** - Target based on weather conditions
- **Time-Based Targeting** - Target based on time, timezone, business hours
- **Device Targeting** - Target based on device type, browser, OS
- **Behavioral Targeting** - Target based on user behavior
- **E-commerce Integration** - Target based on cart value, purchase history
- **Social Media Integration** - Target based on social platform, referral source
- **Google AdWords Integration** - Target based on active campaigns
- **Facebook Advertising Integration** - Target based on Facebook campaigns

## Support

For questions or issues with the add-on system, please refer to the main Geo Elementor documentation or contact support.
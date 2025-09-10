# Geo Elementor Development Tools

This directory contains internal development tools for creating and managing Geo Elementor add-ons. These tools are **NOT** included in the distributable plugin.

## Tools Available

### 1. Add-On Zip Creator (`create-addon-zip.php`)

Creates distributable zip files for add-ons.

**Usage:**
```bash
# CLI
php create-addon-zip.php city-targeting

# Web interface
http://yoursite.com/wp-content/plugins/geo-elementor/dev-tools/create-addon-zip.php
```

**Features:**
- Validates add-on structure
- Creates zip files with proper metadata
- Both CLI and web interfaces
- Validates required files and JSON structure

### 2. Add-On Structure

Each add-on should have:
```
addon-name/
├── addon-name.php          # Main add-on file
├── addon-info.json         # Metadata file
├── assets/                 # Optional: CSS, JS, images
│   ├── css/
│   ├── js/
│   └── images/
└── README.md              # Optional: Documentation
```

### 3. Addon-Info.json Format

```json
{
    "id": "addon-name",
    "name": "Add-On Display Name",
    "description": "Description of the add-on",
    "version": "1.0.0",
    "author": "Your Name",
    "author_uri": "https://yourwebsite.com",
    "plugin_uri": "https://yourwebsite.com",
    "requires": "1.0.0",
    "tested": "1.0.1",
    "file": "addon-name.php",
    "class": "EGP_Addon_Name_Addon",
    "category": "targeting",
    "tags": ["tag1", "tag2"],
    "screenshot": "",
    "icon": "eicon-icon-name",
    "premium": true,
    "status": "available"
}
```

## Development Workflow

1. **Create Add-On** - Develop add-on in `/addons/addon-name/`
2. **Add Metadata** - Create `addon-info.json` file
3. **Test Locally** - Test add-on functionality
4. **Create Zip** - Use `create-addon-zip.php` to create distributable zip
5. **Distribute** - Provide zip file to customers for installation

## Security Note

These tools are for internal development only and should never be included in the distributable plugin. They allow creation of add-ons that can be sold to customers.
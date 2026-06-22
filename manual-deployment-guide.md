# Manual Deployment Guide for Aplenty

## Method 1: cPanel File Manager

1. **Log into cPanel**
2. **Go to File Manager**
3. **Navigate to:** `/home/aplenty/staging.aplenty.co.uk/wp-content/plugins/geo-elementor`
4. **Upload your files** (zip and extract, or upload individual files)
5. **Set proper permissions** (755 for directories, 644 for files)

## Method 2: FTP/SFTP Client

### Using FileZilla or similar:
1. **Host:** aplenty.co.uk
2. **Username:** aplenty
3. **Password:** [your cPanel password]
4. **Port:** 21 (FTP) or 22 (SFTP)
5. **Remote directory:** `/staging.aplenty.co.uk/wp-content/plugins/geo-elementor`

### Using command line:
```bash
# SFTP
sftp aplenty@aplenty.co.uk
cd staging.aplenty.co.uk/wp-content/plugins/geo-elementor
put -r ./*

# Or use the FTP script
./ftp-deploy.sh
```

## Method 3: Git Clone via cPanel Terminal

If Terminal is available in cPanel:

1. **Go to Terminal in cPanel**
2. **Navigate to the plugin directory:**
   ```bash
   cd /home/aplenty/staging.aplenty.co.uk/wp-content/plugins/geo-elementor
   ```
3. **Clone your repository:**
   ```bash
   git clone https://github.com/your-username/your-repo.git .
   ```
4. **Set up automatic updates:**
   ```bash
   git remote add origin https://github.com/your-username/your-repo.git
   ```

## Method 4: WordPress Admin Upload

1. **Log into WordPress admin**
2. **Go to Plugins → Add New → Upload Plugin**
3. **Upload your plugin zip file**
4. **Activate the plugin**

## Post-Deployment Steps

1. **Check file permissions:**
   ```bash
   chmod -R 755 /home/aplenty/staging.aplenty.co.uk/wp-content/plugins/geo-elementor
   ```

2. **Activate plugin in WordPress admin**

3. **Test functionality**

4. **Check WordPress error logs** if there are issues

## Troubleshooting

- **Permission denied:** Check file permissions
- **Plugin not loading:** Verify plugin header in main PHP file
- **Database errors:** Check WordPress error logs
- **Cache issues:** Clear WordPress cache after deployment

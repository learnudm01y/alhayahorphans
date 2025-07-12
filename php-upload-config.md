# PHP Upload Configuration Guide

## Problem
Excel files are failing to upload due to PHP configuration limits.

## Current Limits
- `upload_max_filesize` = 2M (too small)
- `post_max_size` = 8M (too small)

## Required Changes

### Option 1: Update php.ini (Recommended)
1. Find your php.ini file:
   ```bash
   php --ini
   ```

2. Edit php.ini and update these values:
   ```ini
   ; File upload settings
   upload_max_filesize = 100M
   post_max_size = 120M
   max_file_uploads = 50
   memory_limit = 512M
   max_execution_time = 300
   max_input_time = 300
   max_input_vars = 10000
   file_uploads = On
   ```

3. Restart your web server (Apache/Nginx)

### Option 2: .htaccess (Apache only)
Create/update `.htaccess` in your Laravel public folder:
```apache
# PHP Upload Settings
php_value upload_max_filesize 100M
php_value post_max_size 120M
php_value max_file_uploads 50
php_value memory_limit 512M
php_value max_execution_time 300
php_value max_input_time 300
php_value max_input_vars 10000
```

### Option 3: Virtual Host Configuration
Add to your Apache virtual host:
```apache
<VirtualHost *:80>
    # ... other config ...
    
    php_admin_value upload_max_filesize 100M
    php_admin_value post_max_size 120M
    php_admin_value max_file_uploads 50
    php_admin_value memory_limit 512M
    php_admin_value max_execution_time 300
</VirtualHost>
```

### Option 4: Local Development (XAMPP/WAMP)
1. Open XAMPP Control Panel
2. Click "Config" next to Apache
3. Select "PHP (php.ini)"
4. Find and update the values above
5. Save and restart Apache

## Verification
After making changes, check if they worked:
1. Visit: `http://your-domain/admin/file/php-diagnostic`
2. Or create a PHP info page:
   ```php
   <?php phpinfo(); ?>
   ```

## Laravel Specific Settings
Add to your `.env` file:
```env
# File upload settings
UPLOAD_MAX_SIZE=104857600
POST_MAX_SIZE=125829120
```

## nginx Configuration (if using nginx)
Add to your nginx server block:
```nginx
server {
    # ... other config ...
    
    client_max_body_size 120M;
    
    location ~ \.php$ {
        # ... other config ...
        
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
    }
}
```

## Test Upload
After configuration:
1. Restart web server
2. Try uploading your Excel file again
3. Check the response for any remaining errors

## Common Issues
1. **Changes not taking effect**: Restart web server
2. **Still getting errors**: Check if there are multiple php.ini files
3. **Permission denied**: Ensure web server has write permissions to upload directories
4. **Memory issues**: Increase `memory_limit` further if needed

## For Production Servers
Contact your hosting provider or system administrator to increase these limits system-wide.

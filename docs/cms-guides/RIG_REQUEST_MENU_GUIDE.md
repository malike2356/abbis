# Rig Request Page - Location & Menu Setup Guide

## 📍 File Location

**Physical File:**
```
/opt/lampp/htdocs/abbis3.2/cms/public/rig-request.php
```

**URL Path:**
```
http://localhost:8080/abbis3.2/cms/rig-request
```

## 🎯 Adding to CMS Menu (cPanel Compatible)

### Why Links Won't Break on cPanel

The CMS menu system uses **relative URLs with automatic base URL detection**, which means:

1. **No Hardcoded Paths**: The system automatically detects your domain/subdomain
2. **Works on Any Domain**: Whether it's `localhost`, `yourdomain.com`, or `subdomain.yourdomain.com`
3. **Uses Base URL Detection**: The `base-url.php` helper automatically detects the correct path

### How to Add to Menu

#### Method 1: Via CMS Admin (Recommended)

1. **Login to CMS Admin:**
   - Go to: `http://yourdomain.com/cms/admin/`
   - Login with your admin credentials

2. **Navigate to Menus:**
   - Click on **"Menus"** in the admin sidebar
   - Select or create a menu (e.g., "Primary Menu")

3. **Add Rig Request:**
   - In the "Add Menu Items" section, click the **"🚛 Request Rig"** quick button
   - OR select **"Request Rig"** from the dropdown
   - Click **"Add to Menu"**
   - The system will automatically:
     - Set the label to "Request Rig"
     - Set the URL to `/cms/rig-request` (relative path)
     - Use the correct base URL for your domain

4. **Save Menu:**
   - Drag items to reorder if needed
   - Click **"Save Menu"**
   - Assign the menu to "Primary Menu" location if not already assigned

#### Method 2: Direct Database (Advanced)

If you need to add it directly via SQL:

```sql
INSERT INTO cms_menu_items 
(menu_name, label, url, object_type, object_id, menu_order, menu_type, menu_location) 
VALUES 
('Primary Menu', 'Request Rig', '/cms/rig-request', 'rig-request', NULL, 10, 'primary', 'primary');
```

**Note:** Replace `'Primary Menu'` with your actual menu name.

## 🔧 How Base URL Detection Works

The CMS uses `base-url.php` which:

1. **Checks APP_URL constant** (from `config/app.php`)
2. **Detects from SCRIPT_NAME** (if APP_URL not set)
3. **Falls back to `/abbis3.2`** (development only)

**For cPanel deployment:**
- Set `APP_URL` in `config/app.php` to your production domain
- Example: `define('APP_URL', 'https://yourdomain.com');`
- The menu URLs will automatically use the correct base path

## ✅ Verification

After adding to menu:

1. **Check Frontend:**
   - Visit your site homepage
   - Look for "Request Rig" in the navigation menu
   - Click it - should go to `/cms/rig-request`

2. **Check URL:**
   - The link should be: `https://yourdomain.com/cms/rig-request`
   - NOT: `https://yourdomain.com/abbis3.2/cms/rig-request` (unless that's your actual path)

## 🚀 Deployment Checklist

Before deploying to cPanel:

- [ ] Set `APP_URL` in `config/app.php` to production domain
- [ ] Test menu links work on localhost
- [ ] Verify `.htaccess` is uploaded (for URL routing)
- [ ] Check that `mod_rewrite` is enabled on cPanel
- [ ] Test the rig-request page loads correctly

## 📝 Notes

- The menu system stores **relative URLs** (`/cms/rig-request`)
- The `baseUrl` variable is dynamically generated per request
- This ensures links work on any domain/subdomain automatically
- No need to update menu URLs when moving between environments


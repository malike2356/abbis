# Homepage Location & Menu Integration

## Where is Your Homepage?

Your homepage file is located at:
```
/opt/lampp/htdocs/abbis3.2/cms/public/index.php
```

### How It Works:

1. **Homepage File**: `cms/public/index.php`
   - This is the main entry point for your homepage
   - It loads data from the database (pages, posts, settings)
   - It then includes the active theme template

2. **Theme Template**: `cms/themes/default/index.php` (or your active theme)
   - This is the actual HTML/CSS template for your homepage
   - You can customize the design here

3. **URL**: Your homepage is accessible at:
   - `http://localhost:8080/abbis3.2/` (root URL)
   - `http://localhost:8080/abbis3.2/cms/public/index.php` (direct access)

---

## How to Add Homepage to Menu

### Method 1: Using the Menu Admin (Recommended)

1. Go to **CMS Admin → Menus** (`/cms/admin/menus.php`)

2. **Create or select a menu** (if you don't have one, click "Create New Menu")

3. **Add Home to Menu:**
   - Click the **"🏠 Home"** button in the quick-add section
   - Or select **"Home"** from the dropdown
   - Click **"Add to Menu"**

4. **Save the Menu:**
   - Drag items to reorder if needed
   - Click **"Save Menu"**

5. **Assign to Location:**
   - In the right panel, under "Menu Locations"
   - Select your menu for **"Primary Menu"** (header navigation)
   - The menu will automatically appear in your site header

### Method 2: Manual Addition

If you prefer to add it manually:

1. In the menu form, select **"Home"** from the dropdown
2. Click **"Add to Menu"**
3. The homepage link will be added with URL: `/` (root URL)

---

## What Gets Added?

When you add "Home" to your menu:
- **Label**: "Home" (you can edit this later)
- **URL**: `/` (root URL - your homepage)
- **Type**: `home` (special type that points to root)

---

## Editing the Homepage

### To Change Homepage Content:

1. **Via CMS Admin:**
   - Go to **CMS Admin → Pages**
   - Create or edit a page with slug `"home"`
   - Set status to "published"
   - The homepage will display this content

2. **Via Theme Template:**
   - Edit `cms/themes/default/index.php`
   - Modify the HTML/CSS structure
   - This controls how the homepage looks

3. **Via Homepage File:**
   - Edit `cms/public/index.php`
   - Modify the logic/data loading
   - This controls what data is available

---

## Quick Reference

| File | Purpose | Location |
|------|---------|----------|
| **Homepage Entry** | Main PHP logic | `cms/public/index.php` |
| **Theme Template** | HTML/CSS design | `cms/themes/default/index.php` |
| **Homepage Content** | CMS page content | Database: `cms_pages` (slug='home') |

---

## Example: Adding Home to Menu via Admin

1. Navigate to: `http://localhost:8080/abbis3.2/cms/admin/menus.php`
2. Click **"🏠 Home"** button
3. Click **"Add to Menu"**
4. Click **"Save Menu"**
5. Assign menu to **"Primary Menu"** location
6. Done! Home will appear in your site navigation

---

## Troubleshooting

**Home not showing in menu?**
- Make sure you assigned the menu to "Primary Menu" location
- Check that the menu is saved
- Clear browser cache

**Homepage not loading?**
- Check `cms/public/index.php` exists
- Verify theme file exists: `cms/themes/default/index.php`
- Check database connection

**Home link goes to wrong URL?**
- Edit the menu item and update the URL to `/`
- Make sure base URL is correctly configured


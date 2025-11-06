# Homepage Editing Guide

## Where is the Homepage Content?

The homepage you see on your website is composed of **two parts**:

### 1. **Hero Section** (Top Banner)
- **Location**: `cms/themes/default/index.php` (lines 54-63)
- **Content**: 
  - Site Title (from CMS Settings)
  - Site Tagline (from CMS Settings)
  - "Get Free Quote" and "Browse Products" buttons
- **Status**: Currently hardcoded in the theme template
- **How to Edit**: You can modify the theme file directly, or edit via CMS Settings

### 2. **Main Content Section** (Editable via CMS)
- **Location**: Database (`cms_pages` table with `slug='home'`)
- **Content**: Custom content you add through the CMS admin
- **Status**: Fully editable through the CMS admin panel
- **Default**: If empty, shows a default "Services" section with 6 service cards

### 3. **Trust Badges Section** (Why Choose Us)
- **Location**: `cms/themes/default/index.php` (lines 66-89)
- **Status**: Currently hardcoded in the theme template

### 4. **Latest News Section** (Recent Posts)
- **Location**: `cms/themes/default/index.php` (lines 143-156)
- **Content**: Automatically displays your 3 most recent published blog posts
- **Status**: Auto-generated from your blog posts

---

## How to Edit the Homepage

### Method 1: Edit via CMS Admin (Recommended)

1. **Log into CMS Admin**
   - Go to: `http://localhost:8080/abbis3.2/cms/admin/`
   - Login with your admin credentials

2. **Navigate to Pages**
   - Click "Pages" in the left sidebar
   - You'll see "Homepage" listed at the top with a blue badge

3. **Edit Homepage**
   - Click "Create/Edit Homepage" link
   - You'll see two editor options:
     - **Rich Text Editor** (CKEditor) - For formatted text
     - **Visual Builder** (GrapesJS) - For drag-and-drop page building (like Elementor)

4. **Add Your Content**
   - **Option A**: Use Rich Text Editor
     - Type your content
     - Format with headings, lists, links, etc.
     - Add images using the image button
   
   - **Option B**: Use Visual Builder (Elementor-like)
     - Click "Switch to Visual Builder"
     - Drag and drop components
     - Build your page visually
     - Style each element

5. **Save Your Changes**
   - Click "Save Page"
   - Your changes will appear on the homepage immediately

### Method 2: Edit Theme Template (Advanced)

If you want to edit the hero section, trust badges, or other hardcoded parts:

1. **Edit Theme File**
   - Navigate to: `cms/themes/default/index.php`
   - Edit the HTML/CSS directly
   - Lines 54-63: Hero section
   - Lines 66-89: Trust badges
   - Lines 96-140: Default services (shown if homepage content is empty)

2. **Customize Settings**
   - Go to CMS Admin → Settings
   - Edit "Site Title" and "Site Tagline"
   - These appear in the hero section automatically

---

## What Gets Displayed?

### If Homepage Content Exists in Database:
```
[Header Navigation]
  ↓
[Hero Section - Site Title, Tagline, Buttons]
  ↓
[Trust Badges - Why Choose Us]
  ↓
[YOUR CUSTOM CONTENT - From Database]
  ↓
[Latest News - Recent Blog Posts]
  ↓
[Footer]
```

### If Homepage Content is Empty:
```
[Header Navigation]
  ↓
[Hero Section - Site Title, Tagline, Buttons]
  ↓
[Trust Badges - Why Choose Us]
  ↓
[Default Services Section - 6 Service Cards]
  ↓
[Latest News - Recent Blog Posts]
  ↓
[Footer]
```

---

## Tips for Editing

1. **Use Visual Builder for Complex Layouts**
   - Perfect for creating multi-column layouts
   - Add buttons, images, cards, sections
   - Responsive design built-in

2. **Use Rich Text Editor for Simple Content**
   - Good for blog-style content
   - Quick formatting
   - Better for SEO-friendly content

3. **Preview Your Changes**
   - Click "View" link after saving
   - Opens homepage in a new tab
   - See changes immediately

4. **Edit Hero Section**
   - Currently requires editing theme file
   - Or change Site Title/Tagline in Settings
   - Future: We can make this editable via CMS

---

## Quick Access

- **Edit Homepage**: `http://localhost:8080/abbis3.2/cms/admin/pages.php?action=edit&id=home`
- **View Homepage**: `http://localhost:8080/abbis3.2/`
- **CMS Admin**: `http://localhost:8080/abbis3.2/cms/admin/`

---

## Need Help?

If you want to make the hero section editable via CMS (like the content section), we can add that feature. Just let me know!


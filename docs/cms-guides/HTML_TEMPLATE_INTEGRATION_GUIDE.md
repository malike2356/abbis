# HTML Template Integration Guide

## 🎯 Best Options for Integrating Your HTML Template

You have **4 main approaches**, each with different complexity and flexibility:

---

## **Option 1: Use the Theme Converter (Recommended for Quick Start)** ⚡

**Best for:** Complete HTML templates (HTML, CSS, JS, images)

### How It Works:
1. Your CMS already has a **Theme Converter** tool
2. Zip your HTML template files
3. Upload via CMS Admin → Appearance → Theme Converter
4. The system automatically converts it to a CMS theme

### Steps:
1. **Prepare your template:**
   ```
   your-template/
   ├── index.html (or index.php)
   ├── style.css
   ├── assets/
   │   ├── css/
   │   ├── js/
   │   └── images/
   └── other files...
   ```

2. **Zip the template folder**

3. **Upload via Admin:**
   - Go to: `http://localhost:8080/abbis3.2/cms/admin/appearance.php`
   - Use the Theme Converter section
   - Upload your ZIP file
   - The system will:
     - Extract files
     - Detect structure
     - Convert HTML to PHP templates
     - Create theme directory
     - Register theme in database

4. **Activate the theme:**
   - Go to Appearance → Themes
   - Activate your new theme

### ✅ Pros:
- Fastest method
- Automatic conversion
- Handles file structure
- Preserves assets (CSS, JS, images)

### ❌ Cons:
- May need manual adjustments
- Some dynamic content needs manual integration

---

## **Option 2: Manual Theme Creation (Best Control)** 🎨

**Best for:** Custom integration, full control, learning the system

### Steps:

1. **Create theme directory:**
   ```bash
   mkdir -p cms/themes/your-theme-name/assets/{css,js,images}
   ```

2. **Create `style.css` with theme header:**
   ```css
   /*
   Theme Name: Your Template Name
   Description: Description of your template
   Version: 1.0
   Author: Your Name
   */
   
   /* Your existing CSS here */
   ```

3. **Create `index.php` (Homepage template):**
   ```php
   <?php
   // Get CMS data (already loaded by index.php)
   // Variables available:
   // - $homepage (page content)
   // - $recentPosts (blog posts)
   // - $menuItems (navigation)
   // - $baseUrl (for links)
   // - $companyName (site name)
   ?>
   
   <!DOCTYPE html>
   <html lang="en">
   <head>
       <meta charset="UTF-8">
       <meta name="viewport" content="width=device-width, initial-scale=1.0">
       <title><?php echo htmlspecialchars($companyName ?? 'Site'); ?></title>
       
       <!-- Your CSS -->
       <link rel="stylesheet" href="<?php echo $baseUrl; ?>/cms/themes/your-theme-name/assets/css/style.css">
       
       <!-- Or use existing header -->
       <?php include __DIR__ . '/../../public/header.php'; ?>
   </head>
   <body>
       
       <!-- Your HTML template structure -->
       <header>
           <!-- Navigation -->
           <nav>
               <?php foreach ($menuItems as $item): ?>
                   <a href="<?php echo htmlspecialchars($item['url']); ?>">
                       <?php echo htmlspecialchars($item['label']); ?>
                   </a>
               <?php endforeach; ?>
           </nav>
       </header>
       
       <main>
           <!-- Hero Section -->
           <section class="hero">
               <h1><?php echo htmlspecialchars($homepage['title'] ?? 'Welcome'); ?></h1>
               <p><?php echo nl2br(htmlspecialchars($homepage['content'] ?? '')); ?></p>
           </section>
           
           <!-- Your template sections -->
           <!-- Replace static content with PHP variables -->
       </main>
       
       <!-- Footer -->
       <?php include __DIR__ . '/../../public/footer.php'; ?>
       
       <!-- Your JS -->
       <script src="<?php echo $baseUrl; ?>/cms/themes/your-theme-name/assets/js/main.js"></script>
   </body>
   </html>
   ```

4. **Create other templates:**
   - `page.php` - For CMS pages
   - `post.php` - For blog posts
   - `single.php` - For single post/page
   - `shop.php` - For e-commerce (if needed)

5. **Register theme in database:**
   ```sql
   INSERT INTO cms_themes (name, slug, description, is_active, config)
   VALUES ('Your Template Name', 'your-theme-name', 'Description', 0, '{}');
   ```

6. **Activate in Admin:**
   - Appearance → Themes → Activate

### ✅ Pros:
- Full control
- Understand the system
- Custom integrations
- Optimized code

### ❌ Cons:
- More time-consuming
- Requires PHP knowledge
- Manual work

---

## **Option 3: Hybrid Approach (Recommended for Complex Templates)** 🔄

**Best for:** Large templates with many pages, complex layouts

### Strategy:
1. **Use Theme Converter** for initial setup
2. **Manually enhance** specific templates
3. **Create custom page templates** for special layouts

### Steps:

1. **Convert base template** (Option 1)

2. **Create custom page templates:**
   ```php
   // cms/themes/your-theme/page-custom.php
   <?php
   // Custom template for specific page layouts
   // Use in CMS Admin when editing pages
   ?>
   ```

3. **Break template into components:**
   ```
   your-theme/
   ├── templates/
   │   ├── header.php
   │   ├── footer.php
   │   ├── sidebar.php
   │   └── components/
   │       ├── hero.php
   │       ├── services.php
   │       └── testimonials.php
   ├── index.php
   └── page.php
   ```

4. **Use includes for reusability:**
   ```php
   <?php include __DIR__ . '/templates/components/hero.php'; ?>
   ```

### ✅ Pros:
- Best of both worlds
- Organized structure
- Reusable components
- Easy maintenance

---

## **Option 4: Direct File Replacement (Quick but Limited)** ⚠️

**Best for:** Simple templates, quick testing

### Steps:
1. Replace files in `cms/themes/default/`
2. Or replace `cms/public/index.php` directly

### ⚠️ Warning:
- Not recommended for production
- Updates may overwrite changes
- No version control
- Hard to maintain

---

## 📋 Step-by-Step: Recommended Workflow

### Phase 1: Preparation
1. **Analyze your template:**
   - List all HTML files
   - Identify static vs dynamic content
   - Note CSS/JS dependencies
   - Check image paths

2. **Plan integration:**
   - Map template pages to CMS pages
   - Identify dynamic sections (menus, content, etc.)
   - Plan component structure

### Phase 2: Integration

#### For Simple Templates:
```bash
# 1. Create theme directory
mkdir -p cms/themes/my-template/assets/{css,js,images}

# 2. Copy files
cp -r your-template/* cms/themes/my-template/

# 3. Convert HTML to PHP
# Replace static content with PHP variables
```

#### For Complex Templates:
1. Use Theme Converter first
2. Then manually enhance templates
3. Create custom page templates

### Phase 3: Dynamic Content Integration

**Replace static content with CMS data:**

```php
<!-- Before (Static) -->
<h1>Welcome to Our Company</h1>
<p>Static content here...</p>

<!-- After (Dynamic) -->
<h1><?php echo htmlspecialchars($homepage['title'] ?? 'Welcome'); ?></h1>
<div><?php echo $homepage['content'] ?? ''; ?></div>
```

**Navigation:**
```php
<!-- Before -->
<nav>
    <a href="/about">About</a>
    <a href="/services">Services</a>
</nav>

<!-- After -->
<nav>
    <?php foreach ($menuItems as $item): ?>
        <a href="<?php echo htmlspecialchars($item['url']); ?>">
            <?php echo htmlspecialchars($item['label']); ?>
        </a>
    <?php endforeach; ?>
</nav>
```

**Blog/Posts:**
```php
<?php foreach ($recentPosts as $post): ?>
    <article>
        <h2><?php echo htmlspecialchars($post['title']); ?></h2>
        <p><?php echo htmlspecialchars($post['excerpt'] ?? substr($post['content'], 0, 150)); ?></p>
        <a href="<?php echo $baseUrl; ?>/cms/post.php?slug=<?php echo $post['slug']; ?>">Read More</a>
    </article>
<?php endforeach; ?>
```

### Phase 4: Asset Paths

**Fix CSS/JS/Image paths:**

```php
<!-- Before -->
<link rel="stylesheet" href="assets/css/style.css">
<img src="images/logo.png">

<!-- After -->
<link rel="stylesheet" href="<?php echo $baseUrl; ?>/cms/themes/your-theme/assets/css/style.css">
<img src="<?php echo $baseUrl; ?>/cms/themes/your-theme/assets/images/logo.png">
```

---

## 🛠️ Common Integration Tasks

### 1. **Header/Footer Integration**

**Option A: Use existing CMS header/footer**
```php
<?php include __DIR__ . '/../../public/header.php'; ?>
<!-- Your content -->
<?php include __DIR__ . '/../../public/footer.php'; ?>
```

**Option B: Custom header/footer**
```php
<?php include __DIR__ . '/templates/header.php'; ?>
```

### 2. **Menu Integration**

```php
<?php
require_once __DIR__ . '/../../public/menu-functions.php';
$menuItems = getMenuItemsForLocation('primary', $pdo);
?>
<nav>
    <?php foreach ($menuItems as $item): ?>
        <a href="<?php echo htmlspecialchars($item['url']); ?>">
            <?php echo htmlspecialchars($item['label']); ?>
        </a>
    <?php endforeach; ?>
</nav>
```

### 3. **Widget Integration**

```php
<?php
require_once __DIR__ . '/../../public/widget-functions.php';
$widgets = getWidgetsForLocation('sidebar', $pdo);
foreach ($widgets as $widget) {
    echo $widget['content'];
}
?>
```

### 4. **E-commerce Integration**

```php
<?php
// Products
$products = $pdo->query("SELECT * FROM catalog_items WHERE status='active' LIMIT 8")->fetchAll();
foreach ($products as $product) {
    // Display product
}
?>
```

---

## 📁 Template File Structure

```
cms/themes/your-theme/
├── style.css              (Required - Theme info header)
├── index.php              (Homepage template)
├── page.php               (Page template)
├── post.php               (Blog post template)
├── single.php             (Single post/page)
├── shop.php               (Shop page - optional)
├── product.php            (Product page - optional)
├── functions.php          (Theme functions - optional)
├── assets/
│   ├── css/
│   │   ├── style.css
│   │   ├── responsive.css
│   │   └── custom.css
│   ├── js/
│   │   ├── main.js
│   │   └── custom.js
│   └── images/
│       ├── logo.png
│       └── ...
└── templates/             (Optional - component templates)
    ├── header.php
    ├── footer.php
    └── components/
        └── ...
```

---

## 🎯 Quick Start Checklist

- [ ] Choose integration method (Theme Converter recommended)
- [ ] Prepare template files (organize, clean up)
- [ ] Create theme directory structure
- [ ] Convert HTML to PHP templates
- [ ] Replace static content with CMS variables
- [ ] Fix asset paths (CSS, JS, images)
- [ ] Integrate navigation menu
- [ ] Test on local server
- [ ] Register theme in database
- [ ] Activate theme in admin
- [ ] Test all pages
- [ ] Optimize and refine

---

## 💡 Pro Tips

1. **Start Simple:** Begin with homepage, then expand
2. **Use Includes:** Break template into reusable components
3. **Test Incrementally:** Test each section as you integrate
4. **Keep Original:** Always keep a backup of original template
5. **Version Control:** Use Git to track changes
6. **Document Changes:** Note what you changed and why
7. **Mobile First:** Ensure responsive design works
8. **Performance:** Optimize images, minify CSS/JS

---

## 🆘 Troubleshooting

### Issue: Images not loading
**Solution:** Use absolute paths with `$baseUrl`
```php
<img src="<?php echo $baseUrl; ?>/cms/themes/your-theme/assets/images/logo.png">
```

### Issue: CSS not applying
**Solution:** Check path and ensure style.css has theme header
```php
<link rel="stylesheet" href="<?php echo $baseUrl; ?>/cms/themes/your-theme/assets/css/style.css">
```

### Issue: Menu not showing
**Solution:** Ensure menu items exist in database and use correct location
```php
$menuItems = getMenuItemsForLocation('primary', $pdo);
```

### Issue: Template not found
**Solution:** Check theme slug matches directory name and is registered in `cms_themes` table

---

## 📚 Additional Resources

- Theme Development Guide: `cms/THEME_DEVELOPMENT_GUIDE.md`
- CMS Integration Guide: `docs/CMS_INTEGRATION_GUIDE.md`
- Theme Converter: `cms/admin/theme-converter.php`

---

## 🎉 Next Steps

1. **Choose your method** based on template complexity
2. **Start with homepage** template
3. **Test thoroughly** before going live
4. **Iterate and improve** based on needs

**Need help?** Check the existing `construction` theme in `cms/themes/construction/` as a reference!


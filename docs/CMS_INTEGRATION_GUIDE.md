# CMS (Content Management System) Integration Guide

## Overview

ABBIS now includes a fully integrated CMS website system that can serve as your public-facing website, complete with ecommerce, blog, quote requests, and more. The CMS can replace the dashboard as the landing page or work alongside it.

## Features

### 🌐 Public Website
- **Homepage**: Customizable landing page with services showcase
- **Blog**: Content management with posts, categories, and publishing workflow
- **Pages**: Static pages (About, Services, Contact, etc.)
- **Theme System**: Multiple themes with customization options
- **Mobile Responsive**: All pages are fully responsive

### 🛒 Ecommerce Integration
- **Shop Page**: Browse products from ABBIS Catalog
- **Shopping Cart**: Session-based cart management
- **Checkout**: Order processing with customer details
- **Order Management**: View and manage orders in CMS Admin
- **Inventory Link**: Products linked to ABBIS inventory system

### 📋 Quote Request System
- **Public Form**: Visitors can request quotes/estimates
- **CRM Integration**: Automatically creates leads in ABBIS CRM
- **Client Conversion**: Quote requests convert to CRM clients
- **Follow-up Automation**: Creates scheduled follow-ups in CRM

### 🔗 ABBIS Integration Points
- **Catalog**: Ecommerce products tied to ABBIS catalog
- **CRM**: Quote requests create CRM entries
- **Inventory**: Shop inventory reflects ABBIS stock levels
- **Clients**: Orders can link to existing clients
- **Field Reports**: Orders can link to field reports

## Database Structure

The CMS uses the following tables (created via `database/cms_migration.sql`):

- `cms_pages` - Static pages
- `cms_posts` - Blog posts
- `cms_categories` - Post categories
- `cms_themes` - Theme configurations
- `cms_settings` - Site-wide settings
- `cms_menu_items` - Navigation menu items
- `cms_quote_requests` - Quote request submissions
- `cms_cart_items` - Shopping cart items
- `cms_orders` - Ecommerce orders
- `cms_order_items` - Order line items

## Setup & Configuration

### 1. Enable CMS Feature

1. Go to **System → Feature Management**
2. Find **"CMS Website"** under Business Intelligence
3. Toggle it **ON**
4. Save

### 2. Run Database Migration

If CMS tables don't exist automatically, run:
```sql
source database/cms_migration.sql
```

Or via web interface (if available):
- Navigate to CMS Admin
- Tables will auto-initialize if missing

### 3. Access CMS Admin

1. Go to **System → CMS Admin**
2. Or navigate to: `modules/cms-admin.php`

## CMS Admin Features

### Dashboard
- Overview statistics (pages, posts, quotes, orders)
- Quick links to manage content

### Pages Management
- Create/edit static pages
- Set page slugs (URLs)
- SEO titles and descriptions
- Draft/Published workflow

### Posts Management
- Create/edit blog posts
- Assign categories
- Featured images
- Publishing dates
- SEO optimization

### Categories
- Organize posts by category
- Hierarchical categories (parent/child)

### Themes
- Multiple theme support
- Custom color schemes
- Theme activation/deactivation
- Theme configurations

### Menu Builder
- Primary navigation menu
- Custom links
- Menu ordering
- External links support

### Quote Requests
- View all quote submissions
- Status management (new, contacted, quoted, converted)
- Link to CRM client records
- Export capabilities

### Orders
- View ecommerce orders
- Order status management
- Customer information
- Link to ABBIS clients/field reports

### Settings
- Site title and tagline
- Homepage type (CMS or Dashboard)
- Blog visibility
- Posts per page

## Public Pages & URLs

When CMS is enabled, these public pages are available:

- `/` - Homepage (replaces dashboard landing)
- `/cms/shop` - Ecommerce shop
- `/cms/quote` - Quote request form
- `/cms/blog` - Blog listing
- `/cms/post/[slug]` - Individual blog post
- `/cms/cart` - Shopping cart & checkout
- `/cms/[page-slug]` - Custom CMS pages

## Switching Between CMS and Dashboard

### CMS as Landing Page (Default)
When CMS is enabled, the homepage (`/`) shows the CMS website.
- **Public users**: See the website
- **Logged-in users**: Can access dashboard via "Dashboard" link in header

### Dashboard as Landing Page
If CMS is disabled in Feature Management:
- **All users**: Redirected to dashboard or login (original behavior)

### Admin Access
When CMS is active, administrators can:
1. Access ABBIS system via "Dashboard" link in website header
2. Access CMS Admin via System → CMS Admin
3. Switch between website and system seamlessly

## Ecommerce Workflow

### Customer Journey
1. **Browse**: Visit `/cms/shop`
2. **Add to Cart**: Select products, add quantities
3. **Checkout**: Provide customer details
4. **Order Placed**: Order created in system

### Admin Workflow
1. **View Orders**: CMS Admin → Orders tab
2. **Process Order**: Update status (pending → processing → completed)
3. **Link to Client**: Optionally link order to ABBIS client
4. **Link to Field Report**: If order becomes a job, link to field report
5. **Inventory Update**: Orders can trigger inventory adjustments

## Quote Request Workflow

### Customer Journey
1. **Submit Quote**: Fill form at `/cms/quote`
2. **Auto-Create**: System creates CRM entry automatically
3. **Follow-up**: Scheduled follow-up created in CRM

### Admin Workflow
1. **View Requests**: CMS Admin → Quote Requests tab
2. **View in CRM**: Click "View in CRM" to see full client record
3. **Convert**: Mark as contacted/quoted/converted
4. **Create Job**: Convert to field report when ready

## Theme Customization

### Default Theme
Located in: `cms/themes/default/`

### Creating Custom Themes
1. Create new folder in `cms/themes/[theme-name]/`
2. Copy `index.php` from default theme
3. Customize colors, layout, styles
4. Activate theme in CMS Admin → Themes

### Theme Configuration
Themes support JSON configuration:
```json
{
  "primary_color": "#0ea5e9",
  "secondary_color": "#64748b"
}
```

## Integration with ABBIS Modules

### Catalog Module
- Shop products pulled from `catalog_items`
- Only active, sellable products shown
- Prices from catalog sell_price
- Inventory quantities respected

### CRM Module
- Quote requests create client records
- Auto-follow-ups scheduled
- Client status updates
- Email integration

### Materials/Inventory
- Products linked to inventory
- Stock levels can affect shop availability
- Orders can trigger inventory transactions

### Financial System
- Orders can create financial entries
- Revenue tracking
- Payment processing (future)

## Security Considerations

- **Public Access**: Website is public (no login required)
- **Admin Protection**: CMS Admin requires ABBIS authentication
- **Session Security**: Cart uses secure session IDs
- **Input Validation**: All forms validated and sanitized
- **SQL Injection**: Prepared statements used throughout

## Best Practices

1. **Content Creation**: Create pages/posts in CMS Admin before making live
2. **SEO**: Fill SEO title and description for all pages/posts
3. **Quote Management**: Review quote requests daily and link to CRM promptly
4. **Order Processing**: Link orders to clients/field reports for complete tracking
5. **Theme Testing**: Test themes on mobile devices before activation
6. **Regular Backups**: Backup CMS content regularly

## Troubleshooting

### CMS Not Showing
- Check Feature Management: CMS must be enabled
- Check database: Run `cms_migration.sql` if tables missing
- Check file permissions: Ensure `cms/` directory is readable

### Shop Products Not Showing
- Verify products in Catalog are marked "is_sellable=1"
- Check category assignments
- Verify product status is "active"

### Quote Requests Not Creating CRM Entries
- Ensure CRM tables exist (`client_followups`, `clients`)
- Check for PHP errors in logs
- Verify database permissions

### Theme Not Loading
- Check theme exists in `cms/themes/[slug]/`
- Verify theme is active in database
- Check file permissions

## Future Enhancements

- [ ] Multi-language support
- [ ] Advanced theme editor
- [ ] Payment gateway integration
- [ ] Email notifications for orders/quotes
- [ ] Product reviews and ratings
- [ ] Advanced search functionality
- [ ] Newsletter subscription
- [ ] Social media integration
- [ ] Analytics tracking
- [ ] SEO sitemap generation

## Support

For issues or questions:
1. Check this documentation
2. Review CMS Admin for settings
3. Check ABBIS logs for errors
4. Ensure all integrations are properly configured


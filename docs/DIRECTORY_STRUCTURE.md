# ABBIS Directory Structure

This document describes the organized structure of the ABBIS project.

## Root Directory

The root directory contains only essential entry point files and configuration:

### Core Files
- `index.php` - Main application entry point
- `login.php` - Authentication page
- `logout.php` - Logout handler
- `forgot-password.php` - Password recovery
- `reset-password.php` - Password reset handler
- `sso.php` - Single Sign-On handler
- `config.php` - Legacy configuration (deprecated, use `config/`)

### Application Configuration
- `config/` - Application configuration files
  - `app.php` - Application settings
  - `database.php` - Database configuration
  - `constants.php` - Application constants
  - `paths.php` - Path definitions
  - `security.php` - Security settings

### PWA & Offline
- `manifest.webmanifest` - Progressive Web App manifest
- `sw.js` - Service Worker for offline functionality
- `offline/` - Offline field report system

### Git & Deployment
- `.gitignore` - Git ignore rules
- `.github/` - GitHub workflows and templates
- `push-to-github.sh` - Helper script for GitHub deployment

## Documentation (`docs/`)

All documentation is organized into subdirectories:

### `docs/reports/`
- Implementation and completion reports
- System analysis summaries
- Testing reports
- Fix documentation

### `docs/guides/`
- User guides and tutorials
- Setup instructions
- Integration guides

### `docs/setup/`
- Quick start guides
- Setup completion reports
- Implementation guides

### `docs/cms-guides/`
- CMS-specific documentation
- Theme development guides
- Page editing guides
- WordPress integration

### `docs/analysis/`
- System analysis reports
- Audit reports
- Performance assessments

### `docs/implementation/`
- Implementation status reports
- Completion documentation
- Feature implementation notes

### `docs/status/`
- Deployment status
- Fix status reports
- Implementation status

## Application Structure

### `api/` - API Endpoints
All REST API endpoints for data operations

### `modules/` - Application Modules
Main application modules (Dashboard, Field Reports, Clients, HR, Resources, Finance, System)

### `includes/` - Core Libraries
Shared PHP libraries and helper functions

### `assets/` - Static Assets
- `css/` - Stylesheets
- `js/` - JavaScript files
- `images/` - Image files

### `database/` - Database Files
- SQL migration scripts
- Database schema files
- Migration helpers

### `scripts/` - Utility Scripts
Command-line utility scripts for maintenance and data processing

### `storage/` - Storage Directory
- `cache/` - Application cache
- `logs/` - Application logs
- `temp/` - Temporary files

### `uploads/` - User Uploads
- `logos/` - Company logos
- `media/` - Media library files
- `payslips/` - Generated payslips
- `products/` - Product images
- `qrcodes/` - Generated QR codes
- `site/` - Site-specific uploads

## CMS Structure (`cms/`)

### `cms/admin/` - Admin Panel
Content management system administration interface

### `cms/public/` - Public Pages
Public-facing CMS pages (blog, shop, portfolio, etc.)

### `cms/themes/` - Themes
Theme files and templates

### `cms/includes/` - CMS Libraries
CMS-specific helper functions and classes

### `cms/plugins/` - CMS Plugins
Extension plugins for the CMS

## Best Practices

1. **Documentation**: Always place documentation in the appropriate `docs/` subdirectory
2. **Scripts**: Utility scripts go in `scripts/`
3. **API**: All API endpoints belong in `api/`
4. **Modules**: Application modules stay in `modules/`
5. **Root Directory**: Keep root directory clean with only essential files

## File Naming Conventions

- **Documentation**: `UPPERCASE_WITH_UNDERSCORES.md`
- **PHP Files**: `kebab-case.php` or `snake_case.php`
- **JavaScript**: `kebab-case.js`
- **CSS**: `kebab-case.css`


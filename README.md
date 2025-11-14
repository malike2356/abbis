# ABBIS v3.2 - Advanced Borehole Business Intelligence System

A comprehensive borehole drilling operations management system with field reporting, payroll, materials tracking, financial analytics, client portal, POS system, and CMS integration.

## 📁 Directory Structure

```
abbis3.2/
├── api/                    # API endpoints
│   ├── config-crud.php
│   ├── save-report.php
│   ├── analytics-api.php
│   ├── accounting-api.php
│   ├── crm-api.php
│   ├── social-auth.php
│   └── ...
│
├── assets/                 # Frontend assets
│   ├── css/               # Stylesheets
│   ├── js/                # JavaScript files
│   └── images/            # Images and icons
│
├── client-portal/          # Client Portal (SSO-enabled)
│   ├── dashboard.php
│   ├── login.php
│   ├── quotes.php
│   ├── invoices.php
│   ├── payments.php
│   └── ...
│
├── cms/                    # Content Management System
│   ├── admin/             # CMS admin panel
│   ├── plugins/           # CMS plugins
│   ├── themes/            # CMS themes
│   ├── public/            # Public-facing CMS pages
│   └── includes/          # CMS helper functions
│
├── config/                 # Configuration files
│   ├── app.php            # Main application config
│   ├── database.php       # Database configuration
│   ├── security.php       # Security settings
│   ├── constants.php      # Application constants
│   ├── environment.php    # Environment detection
│   ├── deployment.php.example  # Deployment template
│   └── ...
│
├── database/               # Database scripts and migrations
│   ├── schema.sql         # Main database schema
│   ├── migrations/        # Database migrations
│   │   ├── pos/          # POS system migrations
│   │   └── phase5/       # Phase 5 migrations
│   └── ...
│
├── docs/                   # Comprehensive documentation
│   ├── guides/            # User and developer guides
│   ├── setup/             # Setup and installation guides
│   ├── cms-guides/        # CMS-specific documentation
│   ├── reports/           # Status and analysis reports
│   ├── DEPLOYMENT_STEPS.md      # Deployment guide
│   ├── DEPLOYMENT_QUICK_START.md # Quick deployment
│   └── README.md          # Documentation index
│
├── includes/               # Core PHP classes and functions
│   ├── auth.php           # Authentication
│   ├── functions.php       # Core functions
│   ├── url-manager.php    # URL helper functions
│   ├── sso.php            # Single Sign-On
│   ├── AI/                # AI assistant system
│   ├── ClientPortal/      # Client portal services
│   ├── pos/               # POS integration services
│   └── ...
│
├── modules/                # Application modules
│   ├── dashboard.php       # Main dashboard
│   ├── field-reports.php  # Field reporting
│   ├── payroll.php        # Payroll management
│   ├── financial.php      # Financial analytics
│   ├── crm.php            # Customer relationship management
│   ├── pos.php            # Point of Sale system
│   ├── ai-assistant.php   # AI assistant
│   └── ...
│
├── offline/                # Offline field reporting
│   └── index.html         # PWA offline form
│
├── pos/                    # Point of Sale System
│   ├── api/               # POS API endpoints
│   ├── admin/             # POS admin panel
│   ├── terminal.php       # POS terminal interface
│   ├── index.php          # POS main interface
│   └── assets/            # POS assets
│
├── scripts/                # Utility scripts
│   ├── test-links.php     # Link verification
│   ├── test-url-changes.php # URL system testing
│   ├── find-hardcoded-urls.php # URL audit
│   └── ...
│
├── storage/                # Storage directory
│   ├── logs/              # Application logs
│   ├── cache/             # Cache files
│   ├── temp/              # Temporary files
│   └── regulatory/        # Regulatory form storage
│
├── tools/                  # Development tools
│   ├── deploy/            # Deployment scripts
│   └── ...
│
├── uploads/                # User-uploaded files
│   ├── logos/             # Company logos
│   ├── media/             # Media files
│   ├── payslips/          # Payslip PDFs
│   ├── products/          # Product images
│   ├── profiles/          # User profile photos
│   ├── qrcodes/           # QR code images
│   └── site/              # Site uploads
│
├── index.php               # Main entry point
├── login.php               # Login page
├── logout.php              # Logout handler
├── .htaccess               # Apache rewrite rules
├── .gitignore              # Git ignore rules
└── README.md               # This file
```

## 🚀 Quick Start

### Requirements

- PHP 7.4 or higher (PHP 8.2+ recommended)
- MySQL 5.7+ or MariaDB 10.3+
- Apache/Nginx web server with mod_rewrite
- Modern web browser

### Installation

1. **Clone Repository**
   ```bash
   git clone https://github.com/malike2356/abbis.git
   cd abbis
   ```

2. **Database Setup**
   ```bash
   mysql -u root -p -e "CREATE DATABASE abbis_3_2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p abbis_3_2 < database/schema.sql
   ```

3. **Configuration**
   
   Copy the deployment example:
   ```bash
   cp config/deployment.php.example config/deployment.php
   ```
   
   Edit `config/deployment.php`:
   ```php
   define('APP_URL', 'http://localhost:8080/abbis3.2');
   define('APP_ENV', 'development');
   define('DEBUG', true);
   ```
   
   Update `config/database.php` with your database credentials.

4. **Permissions**
   ```bash
   chmod 755 config/
   chmod 644 config/database.php
   chmod -R 755 uploads/
   chmod -R 755 storage/
   ```

5. **Access**
   - URL: `http://localhost:8080/abbis3.2`
   - Default Login: `admin` / `password`
   - **Important:** Change default password after first login

## 🌐 Deployment

### Quick Deployment (cPanel/Hostinger)

1. **Upload Files**
   - Upload project ZIP to `public_html/`
   - Extract files

2. **Import Database**
   - Create database in phpMyAdmin
   - Import SQL file

3. **Configure**
   - Create `config/deployment.php`
   - Set `APP_URL` to your domain
   - Update database credentials

4. **Set Permissions**
   - Set `uploads/` to 755

**See `docs/DEPLOYMENT_STEPS.md` for complete deployment guide.**

## 📚 Documentation

All documentation is centralized in the `docs/` directory:

- **Quick Start**: `docs/QUICK_START.md`
- **Deployment**: `docs/DEPLOYMENT_STEPS.md` and `docs/DEPLOYMENT_QUICK_START.md`
- **URL System**: `docs/DEPLOYMENT_URL_GUIDE.md`
- **Complete Features**: `docs/COMPLETE_SYSTEM_FEATURES.md`
- **Documentation Index**: `docs/INDEX.md`

### Documentation Categories

- **Guides** (`docs/guides/`) - User and developer guides
- **Setup** (`docs/setup/`) - Installation and migration guides
- **CMS Guides** (`docs/cms-guides/`) - CMS-specific documentation
- **Reports** (`docs/reports/`) - Status and analysis reports
- **Implementation** - Feature implementation documentation

## 🔧 Key Features

### Core Systems

- **Field Operations Reporting** - Complete drilling and construction data capture
- **Payroll Management** - Worker wages, benefits, and loan deductions
- **Materials Inventory** - Track screen pipes, plain pipes, and gravel
- **Financial Analytics** - Real-time profit/loss calculations and reporting
- **Loan Management** - Worker loans and repayment tracking
- **Dashboard** - Comprehensive KPI dashboard with recent activity

### Advanced Features

- **Client Portal** - SSO-enabled client portal for quotes, invoices, and payments
- **Point of Sale (POS)** - Complete POS system with inventory, sales, and accounting sync
- **Content Management System (CMS)** - Full-featured CMS with themes, plugins, and e-commerce
- **AI Assistant** - AI-powered assistant with multiple provider support
- **Offline Reporting** - PWA-enabled offline field reporting
- **Rig Tracking** - GPS tracking and telemetry for drilling rigs
- **Regulatory Forms** - Automated regulatory form generation

### System Features

- **Multi-user Support** - Role-based access control (Admin, Manager, Supervisor, Clerk)
- **Single Sign-On (SSO)** - Secure SSO between main system and client portal
- **URL Management** - Centralized URL system for easy deployment
- **Responsive Design** - Works on desktop, tablet, and mobile devices
- **Dark/Light Theme** - User-selectable theme preference
- **API Integration** - RESTful APIs for external integrations

## 🏗️ System Architecture

### Main Systems (All Direct Children of Root)

- **CMS** (`cms/`) - Content Management System
- **Client Portal** (`client-portal/`) - Client-facing portal
- **POS** (`pos/`) - Point of Sale system
- **API** (`api/`) - RESTful API endpoints
- **Modules** (`modules/`) - Application modules

### URL Management

All URLs are managed centrally through `includes/url-manager.php`:

- `api_url($file, $params)` - Generate API URLs
- `module_url($file, $params)` - Generate module URLs
- `cms_url($file, $params)` - Generate CMS URLs
- `client_portal_url($file, $params)` - Generate client portal URLs
- `pos_url($file, $params)` - Generate POS URLs
- `site_url($path)` - Generate site URLs

**Configuration**: Set `APP_URL` in `config/deployment.php` for production.

## 🛠️ Development

### Running Utility Scripts

```bash
# Test URL system
php scripts/test-url-changes.php

# Find hardcoded URLs
php scripts/fix-rpm-data.php

# Fix duplicate rig assets
php scripts/fix-duplicate-rig-assets.php

# Organize directory structure
php scripts/organize-directory.php
```

### API Endpoints

All API endpoints are located in the `api/` directory and follow RESTful conventions.

### Modules

Application modules are in the `modules/` directory. Each module is self-contained with its own functionality.

## 🔒 Security

- ✅ API keys excluded from version control
- ✅ Sensitive config files in `.gitignore`
- ✅ Password hashing with bcrypt
- ✅ CSRF protection
- ✅ XSS and SQL injection protection
- ✅ Session management
- ✅ Role-based access control

**Important:**
- Never commit `config/deployment.php` with real credentials
- Change default passwords immediately
- Keep database credentials secure
- Regularly update dependencies

## 📝 Notes

- All critical PHP files remain in the root directory for web server access
- Documentation is organized in `docs/` subdirectories
- Logs are stored in `storage/logs/` or `logs/`
- User uploads are stored in `uploads/` with appropriate subdirectories
- Cache files are stored in `storage/cache/`
- URL system uses centralized helpers for easy deployment

## 🚀 Deployment Checklist

Before deploying to production:

- [ ] Create `config/deployment.php` from example
- [ ] Set `APP_URL` to production domain
- [ ] Update database credentials
- [ ] Set file permissions (755 for dirs, 644 for files)
- [ ] Set `uploads/` directory to writable (755 or 777)
- [ ] Enable HTTPS and update `APP_URL` to `https://`
- [ ] Configure email settings
- [ ] Set up payment gateway credentials
- [ ] Configure backups

See `docs/DEPLOYMENT_STEPS.md` for detailed instructions.

## 📞 Support

For issues, questions, or contributions:
- Check documentation in `docs/` directory
- Review `docs/INDEX.md` for documentation index
- See `docs/DEPLOYMENT_STEPS.md` for deployment help

---

**Version:** 3.2.0  
**Last Updated:** November 2025  
**Repository:** https://github.com/malike2356/abbis


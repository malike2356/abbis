# ABBIS v3.2 - Advanced Borehole Business Intelligence System

A comprehensive borehole drilling operations management system with field reporting, payroll, materials tracking, financial analytics, and loan management.

## 📁 Directory Structure

```
abbis3.2/
├── api/                    # API endpoints
│   ├── config-crud.php
│   ├── save-report.php
│   ├── analytics-api.php
│   └── ...
│
├── assets/                 # Frontend assets
│   ├── css/               # Stylesheets
│   ├── js/                # JavaScript files
│   └── images/            # Images and icons
│
├── config/                 # Configuration files
│   ├── app.php
│   ├── database.php
│   ├── security.php
│   └── constants.php
│
├── cms/                    # Content Management System
│   ├── admin/
│   ├── plugins/
│   ├── themes/
│   └── public/
│
├── database/               # Database scripts and migrations
│   ├── schema.sql
│   ├── migrations/
│   └── ...
│
├── docs/                   # Documentation
│   ├── implementation/    # Implementation completion docs
│   ├── analysis/          # System analysis reports
│   ├── guides/            # User and developer guides
│   ├── status/            # Status and deployment docs
│   └── README.md          # Main documentation
│
├── includes/               # Core PHP classes and functions
│   ├── auth.php
│   ├── functions.php
│   ├── config-manager.php
│   └── ...
│
├── modules/                # Application modules
│   ├── dashboard.php
│   ├── field-reports.php
│   ├── clients.php
│   ├── hr.php
│   ├── resources.php
│   ├── financial.php
│   └── ...
│
├── scripts/                # Utility scripts
│   ├── fix-rpm-data.php
│   ├── fix-duplicate-rig-assets.php
│   └── organize-directory.php
│
├── storage/                # Storage directory
│   ├── logs/              # Application logs
│   ├── cache/             # Cache files
│   └── temp/              # Temporary files
│
├── uploads/                # User-uploaded files
│   ├── logos/
│   ├── media/
│   ├── payslips/
│   ├── products/
│   ├── qrcodes/
│   └── site/
│
├── index.php               # Main entry point
├── login.php               # Login page
├── logout.php              # Logout handler
├── config.php              # Configuration module
└── README.md               # This file
```

## 🚀 Quick Start

### Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Modern web browser

### Installation

1. **Database Setup**

   ```bash
   mysql -u root -p -e "CREATE DATABASE abbis_3_2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p abbis_3_2 < database/schema.sql
   ```

2. **Configuration**
   Update `config/database.php` with your database credentials:

   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   define('DB_NAME', 'abbis_3_2');
   ```

3. **Permissions**

   ```bash
   chmod 755 config/
   chmod 644 config/database.php
   chmod -R 755 uploads/
   chmod -R 755 storage/
   ```

4. **Access**
   - URL: `http://localhost:8080/abbis3.2`
   - Default Login: `admin` / `password`
   - **Important:** Change default password after first login

## 📚 Documentation

**All documentation is now centralized in the `docs/` directory.**

### Access Documentation

**Web Interface (Recommended):**

- Navigate to **Documentation** in the main menu
- Or visit: `modules/documentation.php`
- Features: Searchable, categorized, easy navigation

**File System:**

- All documentation: `docs/` directory
- Main index: `docs/INDEX.md`
- Organized by category (guides, setup, implementation, analysis, reports, etc.)

### Documentation Categories

- **Guides**: User and developer guides (`docs/guides/`)
- **Setup**: Installation and migration guides (`docs/setup/`)
- **Implementation**: Feature implementation reports (`docs/implementation/`)
- **Analysis**: System analysis and audit reports (`docs/analysis/`)
- **Reports**: Status and deployment reports (`docs/reports/`)
- **Status**: Deployment status documentation (`docs/status/`)
- **CMS Guides**: CMS-specific documentation (`docs/cms-guides/`)
- **General**: Main documentation files (root of `docs/`)

For a complete list, see `docs/INDEX.md`.

## 🔧 Key Features

- **Field Operations Reporting** - Complete drilling and construction data capture
- **Payroll Management** - Worker wages, benefits, and loan deductions
- **Materials Inventory** - Track screen pipes, plain pipes, and gravel
- **Financial Analytics** - Real-time profit/loss calculations and reporting
- **Loan Management** - Worker loans and repayment tracking
- **Dashboard** - Comprehensive KPI dashboard with recent activity
- **Multi-user Support** - Role-based access control (Admin, Manager, Supervisor, Clerk)
- **Responsive Design** - Works on desktop, tablet, and mobile devices
- **Dark/Light Theme** - User-selectable theme preference

## 🛠️ Development

### Running Utility Scripts

```bash
# Fix RPM data issues
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

## 📝 Notes

- All critical PHP files remain in the root directory for web server access
- Documentation has been organized into `docs/` subdirectories
- Logs are stored in `storage/logs/`
- User uploads are stored in `uploads/` with appropriate subdirectories
- Cache files are stored in `storage/cache/`

## 🔒 Security

- Change default passwords immediately
- Keep `config/database.php` secure (not in version control)
- Regularly update dependencies
- Review and apply security patches

## 📞 Support

For issues, questions, or contributions, please refer to the documentation in the `docs/` directory.

---

**Version:** 3.2.0  
**Last Updated:** 2025

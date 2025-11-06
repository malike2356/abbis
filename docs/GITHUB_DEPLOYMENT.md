# GitHub Deployment Guide - ABBIS System

## 🚀 **Deploying to Private GitHub Repository**

This guide walks you through deploying ABBIS to your private GitHub repository.

---

## 📋 **Prerequisites**

1. GitHub account (you mentioned you have this)
2. Git installed on your system
3. Repository created: `ABBIS` (private)

---

## 🔧 **Step-by-Step Instructions**

### **Step 1: Initialize Git (if not already done)**

```bash
cd /opt/lampp/htdocs/abbis3.2

# Check if git is already initialized
if [ ! -d .git ]; then
    git init
fi
```

### **Step 2: Create .gitignore**

Create `.gitignore` to exclude sensitive files:

```bash
cat > .gitignore << 'EOF'
# Configuration files with sensitive data
config/database.php
config/security.php
config/app.php

# Uploads directory
uploads/*
!uploads/.gitkeep

# Logs
*.log
logs/

# Temporary files
*.tmp
*.temp
*.cache

# IDE files
.vscode/
.idea/
*.swp
*.swo
*~

# OS files
.DS_Store
Thumbs.db
desktop.ini

# Environment files
.env
.env.local

# Database backups
*.sql
!database/schema.sql

# Composer
vendor/
composer.lock

# Node modules (if any)
node_modules/

# Cache
cache/
*.cache
EOF
```

### **Step 3: Add Remote Repository**

```bash
# Check current remotes
git remote -v

# Add your GitHub repository (replace with your actual URL)
git remote add origin https://github.com/YOUR-USERNAME/ABBIS.git

# Or if using SSH:
# git remote add origin git@github.com:YOUR-USERNAME/ABBIS.git
```

**If remote already exists and points to old location:**
```bash
git remote set-url origin https://github.com/YOUR-USERNAME/ABBIS.git
```

### **Step 4: Stage All Files**

```bash
# Add all files
git add .

# Check what will be committed
git status
```

### **Step 5: Commit Changes**

```bash
git commit -m "ABBIS v3.2.0 - Production Ready

Features:
- Advanced user management with profiles
- Social login (Google, Facebook, Phone)
- Password recovery
- Financial management hub
- Google Maps location picker
- QR code generation for reports
- Data protection compliance
- Enhanced security measures"
```

### **Step 6: Push to GitHub**

```bash
# If replacing existing version, force push (use with caution)
git push -f origin main

# Or if pushing to new branch:
git push -u origin main
```

**Note:** `-f` (force) is used to replace existing content. Only use if you're sure you want to overwrite.

---

## 🔐 **Handling Sensitive Data**

### **Option 1: Exclude Config Files (Recommended)**

Keep sensitive files out of Git:

```bash
# These should be in .gitignore:
# - config/database.php
# - config/security.php
# - config/app.php

# Create template files instead:
cp config/database.php config/database.php.template
cp config/security.php config/security.php.template
cp config/app.php config/app.php.template

# Edit templates to remove actual credentials
# Add templates to git, exclude actual configs
```

### **Option 2: Use Environment Variables**

For production, consider using `.env` files (not in Git):

```bash
# Install vlucas/phpdotenv
composer require vlucas/phpdotenv

# Create .env file (add to .gitignore)
# Update config files to read from .env
```

---

## 🌿 **Branch Strategy**

### **Recommended Structure:**

```
main (production-ready code)
├── develop (development branch)
├── feature/feature-name (feature branches)
└── hotfix/fix-name (quick fixes)
```

### **Create Development Branch:**

```bash
git checkout -b develop
git push -u origin develop
```

---

## 📝 **Commit Best Practices**

### **Good Commit Messages:**

```bash
git commit -m "Add Google Maps location picker to field reports

- Integrated Google Maps JavaScript API
- Added location search with autocomplete
- Auto-generates Plus codes and coordinates
- Improves UX for location input"
```

### **Avoid:**

```bash
# Bad:
git commit -m "fix"
git commit -m "update"
git commit -m "changes"
```

---

## 🔄 **Future Updates**

When making changes:

```bash
# 1. Check status
git status

# 2. Add changes
git add .

# 3. Commit with descriptive message
git commit -m "Description of changes"

# 4. Push to repository
git push origin main
```

---

## 🛡️ **Security Checklist**

Before pushing:

- [ ] No passwords in code
- [ ] No API keys hardcoded
- [ ] Database credentials excluded
- [ ] `.gitignore` properly configured
- [ ] Repository set to **Private**
- [ ] Sensitive files not committed
- [ ] Templates created for configs

---

## 📦 **Repository Structure**

Your repository should contain:

```
ABBIS/
├── api/
├── assets/
├── config/          (templates only, not actual configs)
├── database/
├── includes/
├── modules/
├── uploads/         (empty, .gitkeep only)
├── .gitignore
├── .htaccess
├── README.md
├── LICENSE          (if applicable)
└── ...
```

---

## 🚨 **Troubleshooting**

### **Error: Repository not found**
```bash
# Verify remote URL
git remote -v

# Update if needed
git remote set-url origin https://github.com/YOUR-USERNAME/ABBIS.git
```

### **Error: Authentication failed**
```bash
# Use personal access token instead of password
# GitHub Settings > Developer settings > Personal access tokens

# Or use SSH:
git remote set-url origin git@github.com:YOUR-USERNAME/ABBIS.git
```

### **Error: Large files**
```bash
# If you accidentally committed large files:
git rm --cached large-file.zip
git commit -m "Remove large file"
git push
```

---

## ✅ **Post-Deployment**

1. **Verify Repository Contents**
   - Check GitHub repository
   - Ensure sensitive files are not visible
   - Verify all necessary files are present

2. **Create README.md**
   ```bash
   # Add comprehensive README.md to repository
   ```

3. **Tag Release**
   ```bash
   git tag -a v3.2.0 -m "ABBIS Version 3.2.0 - Production Ready"
   git push origin v3.2.0
   ```

4. **Documentation**
   - Keep documentation up to date
   - Include setup instructions in README
   - Document configuration requirements

---

**Ready to Deploy!** 🚀

For questions or issues, refer to [GitHub Documentation](https://docs.github.com/)


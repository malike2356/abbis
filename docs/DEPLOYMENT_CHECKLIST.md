# ABBIS v3.2 - Deployment Checklist

Use this checklist to ensure everything is set up correctly before going live.

## ✅ Pre-Deployment Checklist

### Local XAMPP Setup
- [ ] XAMPP/LAMPP is installed and accessible
- [ ] Apache service is running
- [ ] MySQL service is running
- [ ] Database `abbis_3_2` has been created
- [ ] Database schema has been imported successfully
- [ ] Configuration file `config/database.php` is correct
- [ ] `uploads/` directory exists and is writable (755)
- [ ] `logs/` directory exists and is writable (755)
- [ ] Can access login page at `http://localhost/abbis3.2/login.php`
- [ ] Can login with default credentials
- [ ] Default password has been changed

### cPanel Production Setup
- [ ] All files uploaded to server
- [ ] Database created in cPanel
- [ ] Database user created and granted privileges
- [ ] Schema imported via phpMyAdmin
- [ ] Configuration updated with production credentials
- [ ] `APP_URL` in `config/database.php` updated to production domain
- [ ] Directory permissions set correctly (755/775)
- [ ] `.htaccess` file is present
- [ ] SSL certificate installed and active
- [ ] Can access login page via HTTPS
- [ ] Can login with credentials
- [ ] Default password changed
- [ ] Tested creating a field report
- [ ] Tested key functionality

## 🔒 Security Checklist

- [ ] Default admin password changed
- [ ] Strong passwords used for database and admin
- [ ] File permissions set correctly (644 for files, 755 for dirs)
- [ ] Sensitive files protected (config files)
- [ ] Error display disabled in production (`config/app.php`)
- [ ] HTTPS enabled (production)
- [ ] Regular backup strategy in place
- [ ] `setup-xampp.sh` removed or protected in production

## ⚙️ Configuration Checklist

- [ ] Company information updated
- [ ] Contact details configured
- [ ] Rigs added/configured
- [ ] Workers added/configured
- [ ] Clients added/configured (or tested client creation)
- [ ] Materials pricing set
- [ ] System settings reviewed

## 🧪 Functionality Testing

- [ ] Login/Logout works
- [ ] Dashboard displays correctly
- [ ] Can create a new field report
- [ ] Can view field reports list
- [ ] Financial calculations are correct
- [ ] Materials inventory tracking works
- [ ] Payroll calculations work
- [ ] Loan management functions
- [ ] Reports can be generated
- [ ] User management (if admin)
- [ ] Configuration module accessible

## 📊 Performance Checklist

- [ ] Page load times acceptable
- [ ] Database queries optimized
- [ ] No PHP errors in logs
- [ ] No JavaScript console errors
- [ ] Images and assets load correctly
- [ ] Mobile/responsive view works

## 🔄 Backup & Maintenance

- [ ] Backup procedure documented
- [ ] Database backup automated (if possible)
- [ ] File backup strategy in place
- [ ] Recovery procedure tested
- [ ] Update procedure documented

---

**Date Completed:** _______________

**Deployed By:** _______________

**Environment:** [ ] Local Development  [ ] Production

**Notes:**
________________________________________________________
________________________________________________________
________________________________________________________


# Super Admin Bypass - Quick Start Guide

## ⚠️ WARNING

**DEVELOPMENT MODE ONLY** - This feature is automatically disabled in production.

## Quick Access

### Default Credentials
- **Username:** `superadmin`
- **Password:** `dev123456`

### Login Methods

#### Method 1: Super Admin Login Page (Recommended)
1. Navigate to: `http://localhost:8080/abbis3.2/super-admin-login.php`
2. Enter credentials and click "Login as Super Admin"

#### Method 2: Regular Login Page
1. Navigate to: `http://localhost:8080/abbis3.2/login.php`
2. Enter Super Admin credentials
3. System will automatically detect and grant Super Admin access

### What Super Admin Can Do

✅ **Full System Access**
- Access all modules and pages
- Bypass all permission checks
- Bypass all role restrictions
- Bypass account lockout
- Access Client Portal via SSO

✅ **Development Features**
- Debug system issues
- Perform maintenance operations
- Test system features
- Access all system settings

## Configuration

### Change Credentials

**Option 1: Environment Variables**
```bash
export SUPER_ADMIN_USERNAME="your_username"
export SUPER_ADMIN_PASSWORD="your_secure_password"
```

**Option 2: Edit Config File**
Edit `config/super-admin.php`:
```php
define('SUPER_ADMIN_USERNAME', 'your_username');
define('SUPER_ADMIN_PASSWORD', 'your_secure_password');
```

### IP Whitelist (Optional)

Restrict access to specific IPs:
```php
// In config/super-admin.php
define('SUPER_ADMIN_IP_WHITELIST', [
    '127.0.0.1',
    '192.168.1.100'
]);
```

## UI Indicators

### Header Display
- **Profile Name:** "Super Admin (Dev)"
- **Role Badge:** "⚠️ Dev Mode" (yellow/orange)

### Login Page
- Development mode banner with link to Super Admin login

## Production Safety

### Automatic Disabling
- Super Admin bypass is **automatically disabled** in production
- Only works when `APP_ENV === 'development'`
- Cannot be enabled in production

### Verification
```php
// Check if Super Admin bypass is enabled
if (isSuperAdminBypassEnabled()) {
    echo "Super Admin bypass is ENABLED (Development Mode)";
} else {
    echo "Super Admin bypass is DISABLED (Production Mode)";
}
```

## Troubleshooting

### Super Admin Login Not Working

1. **Check Environment:**
   ```php
   echo APP_ENV; // Should be 'development'
   ```

2. **Check Configuration:**
   ```php
   echo isSuperAdminBYPASS_ENABLED() ? 'Enabled' : 'Disabled';
   ```

3. **Check Credentials:**
   - Verify username: `superadmin`
   - Verify password: `dev123456`
   - Check for typos or case sensitivity

4. **Check IP Whitelist:**
   - If IP whitelist is set, verify your IP is included
   - Check logs for IP restriction messages

### Access Denied

1. **Environment Check:**
   - Verify `APP_ENV === 'development'`
   - Check `config/environment.php` or `.env` file

2. **IP Whitelist:**
   - Check if your IP is whitelisted
   - Review `SUPER_ADMIN_IP_WHITELIST` configuration

3. **Logs:**
   - Check error logs: `storage/logs/`
   - Review Apache/PHP error logs

## Security Best Practices

1. **Use Strong Passwords**
   - Change default credentials
   - Use environment variables for credentials
   - Never commit credentials to version control

2. **Restrict IP Access**
   - Use IP whitelist in development
   - Limit access to trusted IPs only

3. **Monitor Access**
   - Review access logs regularly
   - Monitor for suspicious activity
   - Alert on unauthorized access attempts

4. **Production Deployment**
   - Verify Super Admin is disabled in production
   - Test authentication in production
   - Monitor for Super Admin access attempts

## Related Documentation

- `docs/SUPER_ADMIN_BYPASS.md` - Complete documentation
- `config/super-admin.php` - Configuration file
- `includes/auth.php` - Authentication system
- `includes/access-control.php` - Access control system

## Support

For issues or questions:
1. Check this documentation
2. Review error logs
3. Verify environment configuration
4. Check Super Admin configuration
5. Contact system administrator

---

**Remember:** Super Admin bypass is for **DEVELOPMENT ONLY**. Never enable in production!


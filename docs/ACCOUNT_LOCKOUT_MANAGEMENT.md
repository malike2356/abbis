# Account Lockout Management Guide

## Overview

ABBIS implements an account lockout system to prevent brute-force attacks. After 5 failed login attempts within 15 minutes, an account is automatically locked for 15 minutes.

## Lockout Settings

- **Max Login Attempts**: 5 failed attempts
- **Lockout Duration**: 15 minutes (900 seconds)
- **Lockout Window**: Failed attempts are tracked within a 15-minute rolling window

## Unlocking Accounts

### Method 1: Command Line Script (Recommended)

Use the unlock script to immediately unlock any account:

```bash
php scripts/unlock-account.php <username>
```

**Example:**
```bash
php scripts/unlock-account.php admin
```

**Output:**
```
Account unlock for user: admin
Status: WAS LOCKED
Deleted login attempts: 7
Remaining attempts (last 15 min): 0
Account status: UNLOCKED

User can now login.
```

### Method 2: Web API (Admin Only)

Admins can unlock accounts via the API endpoint:

**Endpoint:** `POST /api/unlock-account.php`

**Parameters:**
- `username` (required): Username to unlock
- `csrf_token` (required): CSRF token

**Example using cURL:**
```bash
curl -X POST "http://localhost/abbis3.2/api/unlock-account.php" \
  -d "username=admin" \
  -d "csrf_token=YOUR_CSRF_TOKEN"
```

### Method 3: User Management Interface

1. Log in as an administrator
2. Navigate to **User Management** (`modules/users.php`)
3. Find the locked user in the user list
4. The "Account Status" column will show:
   - 🔒 **Locked** (with unlock timer)
   - ⚠️ **Warning** (failed attempts but not locked)
   - ✅ **Unlocked** (no issues)
5. Click the **🔓 Unlock** button next to the locked user
6. Confirm the unlock action

### Method 4: Wait for Automatic Unlock

Accounts automatically unlock after 15 minutes from the last failed login attempt. The lockout status expires naturally.

## Checking Lockout Status

### Via API

**Endpoint:** `GET /api/check-lockout-status.php?username=<username>`

**Response:**
```json
{
  "success": true,
  "is_locked": true,
  "attempts": 5,
  "max_attempts": 5,
  "remaining_attempts": 0,
  "time_until_unlock": 450,
  "unlock_time": "2025-01-15 14:30:00",
  "last_attempt": "2025-01-15 14:15:00"
}
```

### Via Code

```php
require_once 'includes/auth.php';
$auth = new Auth();
$lockoutStatus = $auth->getLockoutStatus('username');

if ($lockoutStatus['is_locked']) {
    echo "Account is locked. Unlocks in: " . gmdate('H:i:s', $lockoutStatus['time_until_unlock']);
}
```

## Login Page Behavior

The login page automatically:

1. **Shows lockout warnings** when a username has failed attempts but is not yet locked
2. **Displays lockout details** when an account is locked, including:
   - Number of failed attempts
   - Time until automatic unlock
   - Unlock options
3. **Provides real-time feedback** when typing a username (via AJAX check)

## Programmatic Unlock

### PHP Code

```php
require_once 'includes/auth.php';
$auth = new Auth();

// Unlock account
$result = $auth->unlockAccount('username');

if ($result['success']) {
    echo "Account unlocked. Deleted " . $result['deleted_count'] . " login attempts.";
} else {
    echo "Error: " . $result['message'];
}
```

## Security Considerations

1. **Admin Only**: Unlocking accounts should only be performed by administrators
2. **Audit Logging**: Consider logging unlock actions for security auditing
3. **Rate Limiting**: The unlock script itself doesn't have rate limiting, but the login system does
4. **CSRF Protection**: Web-based unlock requires valid CSRF tokens

## Troubleshooting

### Account Still Locked After Unlock

1. Verify the unlock was successful:
   ```bash
   php scripts/unlock-account.php <username>
   ```

2. Check database directly:
   ```sql
   SELECT * FROM login_attempts WHERE username = 'admin' 
   AND attempt_time > DATE_SUB(NOW(), INTERVAL 900 SECOND);
   ```

3. Clear all attempts manually:
   ```sql
   DELETE FROM login_attempts WHERE username = 'admin';
   ```

### Lockout Status Not Updating

1. Check if `login_attempts` table exists:
   ```sql
   SHOW TABLES LIKE 'login_attempts';
   ```

2. Verify table structure:
   ```sql
   DESCRIBE login_attempts;
   ```

3. Check for database connection issues in logs

## Best Practices

1. **Monitor Lockouts**: Regularly check for locked accounts in the user management interface
2. **User Education**: Inform users about the lockout policy (5 attempts, 15-minute lockout)
3. **Password Reset**: Consider implementing a password reset feature for locked accounts
4. **Admin Alerts**: Set up alerts for multiple lockouts (potential attack)
5. **Regular Reviews**: Review lockout logs to identify patterns or issues

## Configuration

Lockout settings can be modified in `includes/auth.php`:

```php
class Auth {
    private $maxLoginAttempts = 5;        // Change this
    private $lockoutDuration = 900;       // Change this (in seconds)
    // ...
}
```

**Note:** Changing these values requires code modification and should be done carefully to maintain security.

## Related Files

- `includes/auth.php` - Authentication and lockout logic
- `scripts/unlock-account.php` - Command-line unlock script
- `api/unlock-account.php` - Web API for unlocking accounts
- `api/check-lockout-status.php` - API for checking lockout status
- `modules/users.php` - User management interface with unlock feature
- `login.php` - Login page with lockout status display

## Support

For issues or questions about account lockout:
1. Check the logs: `storage/logs/`
2. Review this documentation
3. Contact system administrator
4. Check ABBIS documentation: `docs/SECURITY_ASSESSMENT.md`


# Password Reset Functionality - Fix Documentation

## Issues Fixed

### 1. **cURL Dependency Removed**
- **Problem**: `forgot-password.php` and `reset-password.php` were using cURL to call the API, which could fail due to:
  - cURL not being available
  - Session/CSRF token issues
  - Network configuration problems
  - Error handling complexity

- **Solution**: Both files now directly handle password reset logic without cURL calls:
  - `forgot-password.php` directly creates tokens and sends emails
  - `reset-password.php` directly verifies tokens and resets passwords

### 2. **Database Table Auto-Creation**
- **Problem**: `password_reset_tokens` table might not exist, causing failures
- **Solution**: Added automatic table creation if it doesn't exist in:
  - `forgot-password.php`
  - `reset-password.php`
  - `api/password-recovery.php`

### 3. **Improved Error Handling**
- **Problem**: Errors were not properly caught and displayed
- **Solution**: Added comprehensive try-catch blocks with:
  - Detailed error logging
  - User-friendly error messages
  - Development mode support (shows reset link if email fails)

### 4. **Email Failure Handling**
- **Problem**: If email fails, users couldn't reset passwords
- **Solution**: 
  - Added development mode that shows reset link even if email fails
  - Better error messages
  - Token logging for manual recovery
  - Graceful fallback messages

## How It Works Now

### 1. Request Password Reset (`forgot-password.php`)
1. User enters email address
2. System validates email format
3. System checks if user exists (without revealing if email exists)
4. Creates `password_reset_tokens` table if it doesn't exist
5. Generates secure 64-character token
6. Saves token to database with 1-hour expiration
7. Sends email with reset link
8. Shows success message (or error if email fails)

### 2. Reset Password (`reset-password.php`)
1. User clicks reset link with token
2. System verifies token:
   - Token exists
   - Token not expired
   - Token not already used
3. If valid, shows password reset form
4. User enters new password
5. System validates password:
   - Minimum 8 characters
   - Passwords match
6. Updates password hash in database
7. Marks token as used
8. Invalidates all other tokens for the user
9. Shows success message with login link

## Database Schema

```sql
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY token (token),
    KEY user_id (user_id),
    KEY expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Security Features

1. **Secure Token Generation**: Uses `random_bytes(32)` for cryptographically secure tokens
2. **Token Expiration**: Tokens expire after 1 hour
3. **One-Time Use**: Tokens are marked as used after password reset
4. **CSRF Protection**: All forms include CSRF tokens
5. **Email Privacy**: System doesn't reveal if email exists in database
6. **Password Validation**: Minimum 8 characters required
7. **Token Cleanup**: Old expired tokens are automatically cleaned up

## Testing

### Test Password Reset Flow

1. **Request Reset**:
   ```
   Navigate to: /forgot-password.php
   Enter: valid email address
   Click: "Send Reset Link"
   ```

2. **Check Email** (or check logs in development mode):
   - Look for email with reset link
   - Or check error logs for token if email fails

3. **Reset Password**:
   ```
   Click reset link from email
   Enter: new password (min 8 characters)
   Confirm: new password
   Click: "Reset Password"
   ```

4. **Login**:
   ```
   Navigate to: /login.php
   Enter: email and new password
   Click: "Sign In"
   ```

## Troubleshooting

### Email Not Sending

1. **Check Email Configuration**:
   - Review `includes/email.php` configuration
   - Check SMTP settings if using SMTP
   - Verify email driver settings

2. **Development Mode**:
   - If `APP_ENV === 'development'`, reset link will be shown on screen
   - Check error logs for token if needed

3. **Check Logs**:
   ```bash
   tail -f storage/logs/*.log
   # Or
   tail -f /var/log/apache2/error.log
   ```

### Token Invalid/Expired

1. **Check Token in Database**:
   ```sql
   SELECT * FROM password_reset_tokens 
   WHERE token = 'your_token_here';
   ```

2. **Verify Expiration**:
   - Tokens expire after 1 hour
   - Request new reset link if expired

3. **Check if Used**:
   - Tokens can only be used once
   - Request new reset link if already used

### Database Errors

1. **Check Table Exists**:
   ```sql
   SHOW TABLES LIKE 'password_reset_tokens';
   ```

2. **Create Table Manually** (if needed):
   ```sql
   -- See schema above
   ```

3. **Check Permissions**:
   - Ensure database user has CREATE TABLE permissions
   - Check database connection settings

## Files Modified

1. **forgot-password.php**
   - Removed cURL dependency
   - Added direct password reset handling
   - Added table auto-creation
   - Improved error handling

2. **reset-password.php**
   - Removed cURL dependency
   - Added direct token verification
   - Added direct password reset
   - Added client-side validation
   - Improved error handling

3. **api/password-recovery.php**
   - Added table auto-creation
   - Improved error handling
   - Added token cleanup

## Next Steps

1. **Test the flow** with a valid user email
2. **Check email configuration** if emails aren't sending
3. **Monitor logs** for any errors
4. **Configure email system** if needed (SMTP, API, etc.)

## Support

If password reset still doesn't work:
1. Check error logs: `storage/logs/` or Apache error logs
2. Verify database connection
3. Check email configuration
4. Review this documentation
5. Contact system administrator


# Client Portal SSO Implementation

## Overview

This document describes the Single Sign-On (SSO) implementation that enables ABBIS administrators to access the Client Portal using their ABBIS admin credentials without needing separate login.

## Features

### ✅ Admin SSO Access
- ABBIS admins can access the client portal using their ABBIS credentials
- No separate login required if already logged into ABBIS
- Automatic admin mode activation
- SSO token-based authentication for secure access

### ✅ Admin Mode
- Admins can view all clients' data or a specific client's data
- Admin badge indicator in the portal header
- Admin indicator banner showing current view mode
- Link back to ABBIS dashboard

### ✅ Security
- SSO tokens expire after 5 minutes
- Token signature verification using HMAC-SHA256
- Only admin users can generate SSO tokens
- CSRF protection on login forms

## Implementation Details

### 1. SSO Token Generation

**Location:** `includes/sso.php`

The SSO class includes methods for client portal SSO:

```php
// Generate SSO token for admin to access client portal
$sso = new SSO();
$result = $sso->generateClientPortalSSOToken($abbisUserId, $abbisUsername, $abbisRole);

// Get client portal login URL with SSO token
$clientPortalUrl = $sso->getClientPortalLoginURL($abbisUserId, $abbisUsername, $abbisRole);
```

### 2. Client Portal Login

**Location:** `client-portal/login.php`

The login page now supports:
- Direct SSO token authentication (via URL parameter)
- Automatic admin mode activation if already logged into ABBIS
- Admin login using ABBIS credentials
- CSRF token validation

**Flow:**
1. Admin clicks "Client Portal" link in ABBIS header
2. SSO token is generated and appended to URL
3. Client portal login page verifies token
4. Admin mode is activated
5. Admin is redirected to client portal dashboard

### 3. Admin Mode Authentication

**Location:** `client-portal/auth-check.php`

The authentication check now:
- Allows access for clients (ROLE_CLIENT)
- Allows access for admins in admin mode (ROLE_ADMIN + client_portal_admin_mode)
- Supports viewing specific client data via `?client_id=X` parameter
- Supports viewing all clients overview (no client_id parameter)
- Logs admin activities with [ADMIN] prefix

### 4. Client Portal Header

**Location:** `client-portal/header.php`

The header now displays:
- Admin badge when in admin mode
- Admin indicator banner showing current view mode
- Link back to ABBIS dashboard
- Client name or admin name in user menu

### 5. Dashboard

**Location:** `client-portal/dashboard.php`

The dashboard now supports:
- Admin view: Shows all clients' statistics (when no client selected)
- Client-specific view: Shows specific client's statistics (when client_id provided)
- Admin mode indicator
- Different header text for admin vs client

## Usage

### For Admins

#### Access from ABBIS
1. Log into ABBIS as admin
2. Click "Client Portal" link in header (next to CMS link)
3. Automatically logged into client portal in admin mode
4. View all clients' data or select specific client

#### Direct Login
1. Navigate to `client-portal/login.php`
2. Enter ABBIS admin credentials
3. Admin mode is automatically activated
4. Access client portal features

#### Viewing Specific Client
- Add `?client_id=X` to any client portal URL
- Example: `client-portal/dashboard.php?client_id=5`
- Shows data for that specific client

#### Logout
- Click "Logout" - returns to ABBIS dashboard (if still logged in)
- Or fully log out to return to login page

### For Clients

- Normal client login flow remains unchanged
- Clients see only their own data
- No admin mode indicator
- Standard client portal experience

## Security Considerations

### 1. Token Security
- Tokens are cryptographically signed using HMAC-SHA256
- Tokens expire after 5 minutes
- Token signature is verified before authentication
- Only admin users can generate SSO tokens

### 2. Access Control
- Only admins can access client portal in admin mode
- Client data access is restricted to their own records
- Admin activities are logged with [ADMIN] prefix
- Activity logging includes IP address and user agent

### 3. Session Management
- Separate session management for client portal
- Admin mode flag stored in session
- Session timeout respects ABBIS session lifetime
- Secure session configuration (HttpOnly, Secure, SameSite)

## Database Changes

No database changes required. The SSO implementation uses existing:
- `users` table for authentication
- `clients` table for client data
- `client_portal_activities` table for activity logging

## Files Modified

### 1. `includes/sso.php`
- Added `generateClientPortalSSOToken()` method
- Added `verifyClientPortalSSOToken()` method
- Added `getClientPortalLoginURL()` method

### 2. `client-portal/login.php`
- Added SSO token verification
- Added admin mode activation
- Added CSRF token validation
- Added admin login support

### 3. `client-portal/auth-check.php`
- Added admin mode support
- Added client ID parameter support
- Added admin activity logging
- Updated access control logic

### 4. `client-portal/header.php`
- Added admin badge indicator
- Added admin indicator banner
- Added link back to ABBIS
- Added admin mode styling

### 5. `client-portal/dashboard.php`
- Added admin mode support
- Added all clients overview
- Added client-specific view
- Updated statistics queries

### 6. `client-portal/logout.php`
- Added admin mode logout handling
- Added redirect to ABBIS for admins
- Added full logout option

### 7. `includes/header.php`
- Added Client Portal link for admins
- Added SSO token generation
- Added icon and styling

## Testing

### Test Cases

1. **Admin SSO from ABBIS**
   - Log into ABBIS as admin
   - Click "Client Portal" link
   - Verify admin mode is activated
   - Verify admin badge is displayed
   - Verify all clients data is shown

2. **Admin Direct Login**
   - Navigate to client portal login
   - Enter ABBIS admin credentials
   - Verify admin mode is activated
   - Verify admin badge is displayed

3. **View Specific Client**
   - Access client portal as admin
   - Add `?client_id=X` to URL
   - Verify specific client data is shown
   - Verify client name is displayed

4. **Client Login**
   - Navigate to client portal login
   - Enter client credentials
   - Verify client mode (no admin badge)
   - Verify only client's data is shown

5. **Logout**
   - Log out as admin from client portal
   - Verify redirect to ABBIS (if still logged in)
   - Verify admin mode is cleared

## Future Enhancements

### Recommended Improvements

1. **Client Selector**
   - Add dropdown to select client in admin mode
   - Save selected client in session
   - Remember last viewed client

2. **Admin Permissions**
   - Add permission to view client portal
   - Restrict admin access by permission
   - Log permission checks

3. **Impersonation**
   - Add "View as Client" feature
   - Show client view exactly as client sees it
   - Clear admin indicators in impersonation mode

4. **Bulk Operations**
   - Add bulk actions for admins
   - Export all clients' data
   - Generate reports for all clients

5. **Audit Trail**
   - Enhanced activity logging
   - Track admin actions separately
   - Export audit logs

## Troubleshooting

### Common Issues

1. **SSO Token Expired**
   - Tokens expire after 5 minutes
   - Generate new token by clicking Client Portal link again
   - Or login directly with admin credentials

2. **Admin Mode Not Activated**
   - Verify user is admin (ROLE_ADMIN)
   - Check session variables
   - Clear browser cache and cookies
   - Try direct login

3. **Cannot View Client Data**
   - Verify client_id parameter is correct
   - Check client exists in database
   - Verify admin mode is active
   - Check database permissions

4. **Redirect Issues**
   - Verify app_url() function works correctly
   - Check session is active
   - Verify redirect_after_login is set
   - Check browser console for errors

## Support

For issues or questions:
- Check activity logs in `client_portal_activities` table
- Review error logs in `storage/logs/`
- Verify SSO token generation and verification
- Check session variables in browser developer tools

## Related Documents

- [Client Portal - Milestone 1](CLIENT_PORTAL_MILESTONE1.md)
- [Client Portal - Milestone 2](CLIENT_PORTAL_MILESTONE2.md)
- [SSO Implementation](SSO_IMPLEMENTATION.md)
- [Security Assessment](SECURITY_ASSESSMENT.md)

---

**Last Updated:** 2025-01-27  
**Version:** 3.2.0


# Client Portal SSO - Quick Start Guide

## ✅ Implementation Complete

SSO (Single Sign-On) has been successfully integrated into the Client Portal, allowing ABBIS administrators to access the portal using their ABBIS admin credentials.

## 🚀 How to Use

### For Admins

#### Method 1: From ABBIS Header (Recommended)
1. Log into ABBIS as admin
2. Click the **"Client Portal"** icon in the header (next to CMS icon)
3. You'll be automatically logged into the client portal in admin mode
4. View all clients' data or select a specific client

#### Method 2: Direct Login
1. Navigate to: `http://your-domain/abbis3.2/client-portal/login.php`
2. Enter your ABBIS admin username and password
3. Admin mode is automatically activated
4. Access client portal features

#### Method 3: View Specific Client
1. Access client portal as admin
2. Add `?client_id=X` to any URL
3. Example: `client-portal/dashboard.php?client_id=5`
4. View that specific client's data

### For Clients

- Normal client login remains unchanged
- Clients see only their own data
- No admin mode indicator
- Standard client portal experience

## 🔑 Features

### Admin Mode
- ✅ View all clients' statistics
- ✅ View specific client's data
- ✅ Admin badge indicator
- ✅ Admin indicator banner
- ✅ Link back to ABBIS dashboard
- ✅ Activity logging with [ADMIN] prefix

### Security
- ✅ SSO token-based authentication
- ✅ Token expiration (5 minutes)
- ✅ HMAC-SHA256 signature verification
- ✅ CSRF protection
- ✅ Role-based access control

## 📋 Access URLs

### Client Portal Login
```
http://your-domain/abbis3.2/client-portal/login.php
```

### Client Portal Dashboard
```
http://your-domain/abbis3.2/client-portal/dashboard.php
```

### View Specific Client (Admin Only)
```
http://your-domain/abbis3.2/client-portal/dashboard.php?client_id=5
```

## 🔍 Visual Indicators

### Admin Mode
- **Admin Badge:** Orange badge in header showing "Admin Mode"
- **Admin Banner:** Yellow banner below header showing current view mode
- **Link to ABBIS:** "← ABBIS" link in user menu

### Client Mode
- No admin badge
- No admin banner
- Standard client portal experience

## 🛠️ Technical Details

### Files Modified
1. `includes/sso.php` - Added client portal SSO methods
2. `client-portal/login.php` - Added SSO token verification and admin login
3. `client-portal/auth-check.php` - Added admin mode support
4. `client-portal/header.php` - Added admin indicators
5. `client-portal/dashboard.php` - Added admin view support
6. `client-portal/logout.php` - Added admin mode logout handling
7. `includes/header.php` - Added Client Portal link for admins

### Session Variables
- `client_portal_admin_mode` - Boolean flag for admin mode
- `client_portal_admin_user_id` - Admin user ID
- `client_portal_admin_username` - Admin username

### SSO Token Structure
```json
{
  "abbis_user_id": 1,
  "abbis_username": "admin",
  "target": "client_portal",
  "timestamp": 1234567890,
  "expires": 1234568190
}
```

## 🧪 Testing

### Test Admin SSO
1. Log into ABBIS as admin
2. Click "Client Portal" link in header
3. Verify admin mode is activated
4. Verify admin badge is displayed
5. Verify all clients data is shown

### Test Admin Direct Login
1. Navigate to client portal login
2. Enter ABBIS admin credentials
3. Verify admin mode is activated
4. Verify admin badge is displayed

### Test Client Login
1. Navigate to client portal login
2. Enter client credentials
3. Verify client mode (no admin badge)
4. Verify only client's data is shown

## 📝 Notes

- Admin mode is automatically activated when admin logs in
- Admin can view all clients or specific client's data
- Activity logging includes [ADMIN] prefix for admin actions
- SSO tokens expire after 5 minutes
- Admin can return to ABBIS by clicking "← ABBIS" link

## 🔗 Related Documentation

- [Client Portal SSO Implementation](CLIENT_PORTAL_SSO_IMPLEMENTATION.md)
- [Client Portal - Milestone 1](CLIENT_PORTAL_MILESTONE1.md)
- [Client Portal - Milestone 2](CLIENT_PORTAL_MILESTONE2.md)
- [SSO Implementation](SSO_IMPLEMENTATION.md)

---

**Last Updated:** 2025-01-27  
**Version:** 3.2.0


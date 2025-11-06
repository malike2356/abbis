# SSO Implementation - ABBIS & CMS Integration

## Overview
This document describes the Single Sign-On (SSO) implementation that enables seamless authentication between the ABBIS system and the CMS.

## Implementation Flow

### 1. User Visits Domain
- When a user visits the domain, they are automatically shown the **CMS public website** (as configured in `index.php`)
- The CMS is the default landing page for all visitors

### 2. Admin Access to ABBIS

#### For CMS Admins:
- **CMS Admin** users can see an "Access ABBIS System" link in:
  - CMS Admin Dashboard (`cms/admin/index.php`)
  - CMS Admin Header (top navigation bar)
  
- When clicking the link:
  - **SSO Login**: If CMS username matches ABBIS admin username, SSO token is generated and user is automatically logged into ABBIS
  - **Direct Access**: If already logged into ABBIS, direct link to dashboard is shown
  - **Separate Login**: If no matching ABBIS user found, user is redirected to ABBIS login page

#### For Non-Admin CMS Users:
- Non-admin CMS users **cannot** see the ABBIS link
- They only have access to CMS features

#### For Users with Intermediate Access Levels:
- Users with access levels between "ordinary user" and "admin" (e.g., editor, author) must use separate ABBIS login
- They will see a message indicating they need to log in with ABBIS credentials

### 3. Seamless Navigation

#### When Admin is Logged into ABBIS:
- Admin can see a **CMS** link in the ABBIS header (top right)
- Clicking the CMS link takes them directly to CMS admin dashboard
- No re-authentication required

#### When Admin is Logged into CMS:
- Admin can see an **ABBIS System** link in the CMS header
- If already logged into ABBIS, direct link is shown
- If not logged into ABBIS, SSO token is generated for automatic login

## Technical Implementation

### Files Created/Modified

1. **`includes/sso.php`** - SSO class handling token generation and verification
2. **`sso.php`** - SSO endpoint that processes tokens and logs users into ABBIS
3. **`cms/admin/index.php`** - Added ABBIS link for admins
4. **`cms/admin/header.php`** - Updated to use SSO for ABBIS link
5. **`includes/header.php`** - Added CMS link for ABBIS admins
6. **`cms/admin/pages.php`** - Replaced TinyMCE with CKEditor
7. **`cms/admin/posts.php`** - Replaced TinyMCE with CKEditor

### SSO Token Security

- Tokens are signed with HMAC-SHA256
- Tokens expire after 5 minutes
- Tokens include user ID verification
- Only admin users can generate SSO tokens
- Token signature is verified before authentication

### Rich Text Editor

- **CKEditor 5** (free, open-source) replaces TinyMCE
- Used in both Pages and Posts editing
- Features: headings, formatting, lists, links, tables, images, media embeds, source editing

## User Roles

### CMS Roles:
- `admin` - Full access, can see ABBIS link
- `editor` - Content editing, cannot see ABBIS link
- `author` - Content authoring, cannot see ABBIS link

### ABBIS Roles:
- `admin` - Full system access, can see CMS link
- `manager` - Management access
- `supervisor` - Supervisory access
- `clerk` - Data entry access

## Security Considerations

1. **Token Expiration**: SSO tokens expire after 5 minutes
2. **Role Verification**: Only admin users can access cross-system features
3. **User Matching**: CMS username must match ABBIS username for SSO
4. **Session Management**: Separate sessions for CMS and ABBIS, but SSO allows seamless transition
5. **Signature Verification**: All tokens are cryptographically signed

## Usage Examples

### Admin accessing ABBIS from CMS:
1. Log into CMS as admin
2. Click "Access ABBIS System" in dashboard or header
3. If username matches ABBIS admin, automatically logged in
4. If no match, redirected to ABBIS login

### Admin accessing CMS from ABBIS:
1. Log into ABBIS as admin
2. Click "CMS" link in header
3. Redirected to CMS admin dashboard
4. If not logged into CMS, redirected to CMS login

## Future Enhancements

- Support for password synchronization
- Remember me functionality
- Two-factor authentication integration
- Session timeout synchronization
- User profile synchronization


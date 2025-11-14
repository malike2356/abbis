# Client Portal - How It Works & Client Access

## 🔐 How the Client Portal Works

### **Authentication & Access Control**

The client portal uses **role-based access control** with three access methods:

#### 1. **Direct Client Login**
- Clients must have a user account with role `ROLE_CLIENT`
- Login URL: `/client-portal/login.php`
- Uses standard username/password authentication
- Only users with `ROLE_CLIENT`, `ROLE_ADMIN`, or `ROLE_SUPER_ADMIN` can access

#### 2. **SSO from ABBIS (Admin Access)**
- Admins logged into ABBIS can access client portal via SSO
- Admin mode allows viewing any client's data or all clients overview
- SSO token is generated automatically when clicking "Client Portal" link in ABBIS header
- No separate login required for admins already in ABBIS

#### 3. **Session-Based Access**
- If a client/admin is already logged into ABBIS, they're automatically redirected to client portal dashboard
- No re-authentication needed if session is active

### **Client Data Linking**

The system links users to clients in two ways:

1. **Direct Link**: `users.client_id` field directly links user to client record
2. **Email Match**: If no direct link, system matches user's email with `clients.email` and auto-links them

### **Admin Mode Features**

When admins access the client portal:
- Can view all clients' data (overview mode)
- Can view specific client by adding `?client_id=X` to URL
- All actions are logged with `[ADMIN]` prefix in activity logs
- Admin badge displayed in portal header

---

## 🔍 Can Clients Discover the Portal?

### **Current Discovery Methods:**

#### ✅ **1. Visible to Logged-In Users (ABBIS)**
- **Location**: ABBIS header (top right icon)
- **Who Sees It**: Users with roles: `CLIENT`, `ADMIN`, or `SUPER_ADMIN`
- **Link**: Opens client portal in new tab with SSO token
- **Visibility**: Only visible when logged into ABBIS system

#### ✅ **2. Direct URL Access**
- **URL**: `http://yourdomain.com/client-portal/login.php`
- **Access**: Anyone can visit, but only authorized users can log in
- **Discovery**: Clients would need to know the URL or be told about it

#### ✅ **3. System Module Link**
- **Location**: System → Client Portal (for admins)
- **Purpose**: Admin access point, not for client discovery

### **❌ What's NOT Currently Available:**

1. **No Public-Facing Links**
   - No link on CMS/public website
   - No mention in public pages
   - No email notifications with portal links

2. **No Automated Client Notifications**
   - No automatic email when client account is created
   - No welcome email with portal access instructions
   - No quote/invoice emails with portal links

3. **No Self-Registration**
   - Clients cannot sign themselves up
   - Must be created by admin in ABBIS system
   - No "Create Account" option on login page

---

## 📋 **Client Portal Access Workflow**

### **For New Clients:**

1. **Admin Creates Client Record**
   - Admin adds client in CRM/Client Management
   - Client record created in `clients` table

2. **Admin Creates User Account** (Manual Step)
   - Admin must manually create user account in User Management
   - Set role to `CLIENT`
   - Link user to client via `client_id` field
   - Set username/password

3. **Client Notification** (Manual Step - Not Automated)
   - Admin must manually inform client about portal
   - Provide login URL: `/client-portal/login.php`
   - Provide username and password
   - **Currently no automated email sent**

4. **Client Access**
   - Client visits `/client-portal/login.php`
   - Enters credentials
   - Accesses their dashboard, quotes, invoices, payments

### **For Existing ABBIS Users:**

- If user already has ABBIS account with `CLIENT` role:
  - Can click "Client Portal" icon in ABBIS header
  - Automatically logged in via SSO
  - No separate login needed

---

## 🔒 **Security Features**

1. **Role-Based Access**
   - Only `CLIENT`, `ADMIN`, `SUPER_ADMIN` roles can access
   - Other roles (worker, manager, etc.) are denied

2. **Client Data Isolation**
   - Clients can only see their own data
   - Cannot access other clients' information
   - Admin mode allows viewing all clients (with logging)

3. **CSRF Protection**
   - All forms protected with CSRF tokens
   - Prevents cross-site request forgery

4. **Activity Logging**
   - All portal actions logged in `client_portal_activities` table
   - Tracks: page views, downloads, payments, approvals
   - Includes IP address and user agent

5. **Session Management**
   - Secure session handling
   - Auto-logout on inactivity
   - SSO tokens expire after 5 minutes

---

## 💡 **Recommendations for Better Client Discovery**

### **1. Add Email Notifications**
When a client account is created or quote/invoice is sent:
- Include client portal login link
- Provide username and temporary password
- Add "View in Portal" button in email templates

### **2. Add Public Link (Optional)**
If you want clients to discover it:
- Add "Client Login" link to CMS public website footer
- Add to contact page
- Mention in quote/invoice emails

### **3. Add Welcome Email**
When client account is created:
- Send welcome email with portal access instructions
- Include login URL and credentials
- Explain portal features

### **4. Add Password Reset**
Currently clients can reset password via main ABBIS login, but could add:
- Dedicated password reset for client portal
- Email-based recovery

---

## 📊 **Current Portal Features**

Once clients access the portal, they can:

1. **Dashboard**
   - View statistics (quotes, invoices, payments, projects)
   - Recent quotes and invoices
   - Quick actions

2. **Quotes**
   - View all quotes
   - View quote details
   - Approve/reject quotes with signature
   - Download quote PDF

3. **Invoices**
   - View all invoices
   - View invoice details
   - See payment history
   - Download invoice PDF

4. **Payments**
   - Make online payments (Paystack, Flutterwave)
   - View payment history
   - Track payment status

5. **Projects**
   - View field reports/projects linked to client
   - Project status and details

6. **Profile**
   - View/update client information
   - Change password

---

## 🎯 **Summary**

**How It Works:**
- Role-based access (CLIENT, ADMIN, SUPER_ADMIN)
- SSO integration with ABBIS
- Client data isolation
- Admin mode for viewing all clients

**Client Discovery:**
- ✅ Visible to logged-in ABBIS users (header icon)
- ✅ Direct URL access (if they know the URL)
- ❌ **Not discoverable by public/guessing**
- ❌ No automated notifications
- ❌ No public-facing links

**Recommendation:**
The portal is **secure by design** - clients cannot discover it without being told. This is good for security but means you must:
1. Manually create client user accounts
2. Manually provide login credentials
3. Manually inform clients about the portal URL

Consider adding automated email notifications to streamline the onboarding process.

---

*Last Updated: January 2025*
*ABBIS Version: 3.2.0*


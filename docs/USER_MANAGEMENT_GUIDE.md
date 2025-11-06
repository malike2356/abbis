# Advanced User Management Guide

## 🎯 **Overview**

ABBIS now includes advanced user management with social login, phone authentication, password recovery, and comprehensive profile management.

---

## ✅ **Implemented Features**

### **1. Financial Menu Grouping** ✅
- Finance, Payroll, and Loans grouped under **"Financial"** menu
- Central hub at `/modules/financial.php`
- Quick financial overview with key metrics

### **2. Social Login** ✅
- **Google OAuth2** - Login with Google account
- **Facebook OAuth** - Login with Facebook (framework ready)
- **Phone Number Login** - SMS-based verification

### **3. Password Recovery** ✅
- Forgot password functionality
- Email-based password reset
- Secure token system (1-hour expiry)
- Reset password page

### **4. Advanced User Profiles** ✅
- Profile photo upload
- Personal information (DOB, bio, address)
- Contact details (phone, email, emergency contacts)
- Change password
- Connected accounts management

---

## 📋 **Database Migration**

### **Run Migration:**

```sql
-- Execute the migration script
source database/user_profiles_migration.sql;
```

Or manually run in phpMyAdmin/MySQL client.

### **New Tables Created:**
- `user_social_auth` - Social login connections
- `password_reset_tokens` - Password recovery tokens
- `email_verification_tokens` - Email verification
- `phone_verification_codes` - Phone login codes

### **Users Table Extended:**
- `phone_number` - Phone for login
- `date_of_birth` - DOB
- `profile_photo` - Profile picture path
- `bio` - User bio
- `address`, `city`, `country`, `postal_code` - Address fields
- `emergency_contact_name`, `emergency_contact_phone` - Emergency contacts
- `email_verified`, `phone_verified` - Verification status
- `two_factor_enabled` - 2FA flag
- `updated_at` - Last update timestamp

---

## 🔐 **Setup Social Login**

### **Google OAuth Setup:**

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing
3. Enable Google+ API
4. Create OAuth 2.0 credentials
5. Add authorized redirect URI: `http://localhost:8080/abbis3.2/api/social-auth.php?action=google_auth`
6. Add credentials to System → Configuration:
   - `google_client_id` - Your Google Client ID
   - `google_client_secret` - Your Google Client Secret
   - `google_redirect_uri` - Full redirect URL

### **Facebook OAuth Setup:**

1. Go to [Facebook Developers](https://developers.facebook.com/)
2. Create a new app
3. Add Facebook Login product
4. Configure OAuth redirect URI
5. Add credentials to System → Configuration:
   - `facebook_app_id` - Your Facebook App ID
   - `facebook_app_secret` - Your Facebook App Secret
   - `facebook_redirect_uri` - Full redirect URL

---

## 📱 **Phone Login Setup**

### **SMS Gateway Integration:**

The phone login requires an SMS gateway. Update `api/social-auth.php`:

```php
function sendSMS($phoneNumber, $message) {
    // Integrate with your SMS provider:
    // - Twilio
    // - Africa's Talking
    // - Your local SMS gateway
    
    // Example with Africa's Talking:
    // $apiKey = getConfigValue('sms_api_key');
    // $username = getConfigValue('sms_username');
    // // Send SMS...
    
    return true;
}
```

**Current Behavior:**
- Development: Shows code in response (for testing)
- Production: Requires SMS gateway integration

---

## 🔑 **Password Recovery**

### **User Flow:**
1. Click "Forgot Password?" on login page
2. Enter email address
3. Receive reset link via email
4. Click link (valid for 1 hour)
5. Enter new password
6. Login with new password

### **Email Configuration:**
Ensure email settings are configured in `includes/email.php` for production.

---

## 👤 **User Profile Management**

### **Access Profile:**
- Click on your name/photo in the header
- Or go to: `/modules/profile.php`

### **Profile Features:**
- Upload profile photo (JPEG, PNG, GIF - Max 5MB)
- Edit personal information
- Update contact details
- Add emergency contacts
- Change password
- Manage connected social accounts

---

## 🔧 **Configuration**

### **System Config Keys:**
Add these to `system_config` table:

```sql
INSERT INTO system_config (config_key, config_value, description) VALUES
('google_client_id', 'your-client-id', 'Google OAuth Client ID'),
('google_client_secret', 'your-client-secret', 'Google OAuth Client Secret'),
('google_redirect_uri', 'http://localhost:8080/abbis3.2/api/social-auth.php?action=google_auth', 'Google OAuth Redirect URI'),
('facebook_app_id', 'your-app-id', 'Facebook App ID'),
('facebook_app_secret', 'your-app-secret', 'Facebook App Secret'),
('facebook_redirect_uri', 'http://localhost:8080/abbis3.2/api/social-auth.php?action=facebook_auth', 'Facebook OAuth Redirect URI');
```

---

## 📝 **Usage**

### **Login Options:**
1. **Username/Password** - Traditional login
2. **Google** - Click "Google" button
3. **Facebook** - Click "Facebook" button  
4. **Phone Number** - Click "📱 Login with Phone Number"

### **Profile Management:**
1. Navigate to Profile (click name in header)
2. Update information
3. Upload photo
4. Change password if needed
5. Connect/disconnect social accounts

---

## ⚠️ **Important Notes**

1. **Migration Required:** Run `database/user_profiles_migration.sql` first
2. **Email Setup:** Configure email for password recovery to work
3. **SMS Gateway:** Phone login requires SMS gateway integration
4. **OAuth Credentials:** Social login needs OAuth credentials configured
5. **Profile Photos:** Stored in `uploads/profiles/` directory

---

*Last Updated: November 2024*
*ABBIS Version: 3.2.0*


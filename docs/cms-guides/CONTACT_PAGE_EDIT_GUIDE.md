# How to Edit the Contact Us Page

## 📍 Page Location
- **File:** `cms/public/contact.php`
- **URL:** `http://localhost:8080/abbis3.2/cms/contact`

## ✏️ How to Edit Contact Information

### Method 1: Via CMS Admin Settings (Recommended)

1. **Login to CMS Admin:**
   - Go to: `http://localhost:8080/abbis3.2/cms/admin/`
   - Login with your admin credentials

2. **Navigate to Settings:**
   - Click on **"Settings"** in the admin sidebar
   - Click on the **"Contact"** tab (second tab after General)

3. **Edit Contact Information:**
   - **Contact Email:** Enter your email address
   - **Contact Phone:** Enter your phone number
   - **Contact Address:** Enter your physical address
   - **Business Hours:** Enter your business hours (e.g., "Monday - Friday: 8:00 AM - 5:00 PM")

4. **Save Changes:**
   - Click **"Save Changes"** button at the bottom
   - Changes will appear immediately on the contact page

### Method 2: Direct File Editing

If you need to edit the page design, styling, or form fields:

1. **Edit the file directly:**
   - File: `cms/public/contact.php`
   - You can modify:
     - Page styling (CSS in `<style>` tag)
     - Form fields
     - Layout structure
     - Hero section text

2. **Contact information placeholders:**
   - The contact info pulls from CMS settings
   - If settings are empty, it shows placeholders:
     - Email: `info@example.com`
     - Phone: `+233 XX XXX XXXX`
     - Address: `123 Main Street, Accra, Ghana`
     - Hours: `Monday - Friday: 8:00 AM - 5:00 PM`

## 🎨 What You Can Edit

### Via CMS Settings (No coding required):
- ✅ Contact Email
- ✅ Contact Phone
- ✅ Contact Address
- ✅ Business Hours

### Via File Editing (Requires coding):
- Page title and hero text
- Form fields (add/remove fields)
- Page styling and colors
- Layout structure
- Map integration (Google Maps embed)

## 📝 Notes

- Changes made in CMS Settings are saved to the `cms_settings` table
- The contact page automatically reads from these settings
- If settings are empty, placeholder values are shown
- Form submissions are stored in the `contact_submissions` table
- Submissions also create/update client records in the CRM

## 🔗 Quick Links

- **View Contact Page:** `/cms/contact`
- **Edit in CMS Admin:** `/cms/admin/settings.php` → Contact tab
- **Edit File Directly:** `cms/public/contact.php`


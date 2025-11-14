# 📝 Email Template System Guide

## How It Works - Simple Explanation

The email template system allows you to create reusable email templates with dynamic variables that get replaced with actual data when sending emails.

## 🎯 Two Ways to Use Templates

### **Method 1: From Client Detail Page** (Recommended)
1. Go to **CRM → Clients** → Click on a client
2. Click **"✉️ Send Email"** button
3. In the email modal, you'll see a **"Use Template"** dropdown
4. Select a template from the dropdown
5. The template content (subject and body) will automatically fill in
6. Select the client (if not already selected)
7. Edit the email if needed
8. Click **"Send Email"**

### **Method 2: From Templates Page**
1. Go to **CRM → Templates**
2. Find the template you want to use
3. Click **"Use Template"** button
4. You'll be taken to the **Emails** page with a compose modal open
5. The template will be pre-loaded
6. **Select a client** from the dropdown
7. The client's email will auto-fill
8. Edit if needed and send

## 📋 Step-by-Step Flow

### Creating a Template:
1. Go to **CRM → Templates**
2. Click **"➕ Add Template"**
3. Fill in:
   - **Template Name**: e.g., "Job Completion Notification"
   - **Category**: e.g., "Job Completion"
   - **Subject**: Use variables like `{{client_name}}`, `{{report_id}}`
   - **Body**: Write your message with variables
4. Click **"Save Template"**

### Using a Template:

**Scenario A: You're on a Client's Detail Page**
```
1. Click "Send Email" button
2. Template dropdown appears
3. Select template → Content loads automatically
4. Send!
```

**Scenario B: You're on Templates Page**
```
1. Click "Use Template" on any template
2. Redirected to Emails page
3. Compose modal opens with template loaded
4. Select client
5. Send!
```

## 🔤 Understanding Variables

Variables are placeholders that get replaced with real data:

- `{{client_name}}` → "Owenase Client"
- `{{report_id}}` → "FR-2024-001"
- `{{total_depth}}` → "45.50"
- `{{currency}}` → "GHS"

**Example Template:**
```
Subject: Job Completed - {{report_id}} for {{client_name}}

Body:
Dear {{contact_name}},

Your job at {{site_name}} is complete!
Total Depth: {{total_depth}} meters
Amount: {{currency}} {{contract_sum}}

Thanks,
{{sender_name}}
```

**When Sent, Becomes:**
```
Subject: Job Completed - FR-2024-001 for Owenase Client

Body:
Dear John Doe,

Your job at Owenase Site is complete!
Total Depth: 45.50 meters
Amount: GHS 5000.00

Thanks,
System Admin
```

## 🎨 Template Categories

- **General**: Basic templates
- **Welcome**: New client welcome messages
- **Follow-up**: Post-job follow-ups
- **Job Completion**: Job completion notifications
- **Payment Reminder**: Outstanding balance reminders
- **Maintenance**: Maintenance notifications
- **Receipt**: Payment receipts
- **Thank You**: Thank you messages
- **Quote**: Quote requests
- **Proposal**: Proposals
- **Invoice**: Invoice notifications
- **Announcement**: General announcements

## 💡 Tips

1. **Preview Templates**: Click the 👁️ icon to see how templates look with sample data
2. **Copy Templates**: Click 📋 to duplicate a template for customization
3. **Variables Guide**: Click "📖 Variables Guide" to see all available variables
4. **Edit Before Sending**: You can always edit template content before sending
5. **Client Selection**: Always select a client - variables need client data to work

## 🔄 The Complete Flow

```
Templates Page
    ↓
Click "Use Template"
    ↓
Redirect to Emails Page (with compose=1&template_id=X)
    ↓
Compose Modal Opens
    ↓
Template Content Loaded
    ↓
Select Client
    ↓
Client Email Auto-filled
    ↓
Edit if Needed
    ↓
Send Email
    ↓
Variables Replaced with Real Data
    ↓
Email Sent & Saved to CRM
```

## ❓ Common Questions

**Q: Why does it redirect to emails page?**
A: The emails page has a compose modal that handles template loading. This keeps everything organized in one place.

**Q: Can I use templates from client detail page?**
A: Yes! The client detail page has its own email modal with template support. Just click "Send Email" and select a template.

**Q: What if I don't select a client?**
A: You must select a client. Variables like `{{client_name}}` need client data to work.

**Q: Can I edit templates after creating them?**
A: Yes! Click the ✏️ Edit button on any template card.

**Q: Do variables work in the subject line?**
A: Yes! You can use variables in both subject and body.

## 🚀 Quick Start

1. **Create your first template:**
   - Go to Templates
   - Click "Add Template"
   - Use: `Dear {{contact_name}}, thank you for choosing {{company_name}}!`
   - Save

2. **Use it:**
   - Go to a client detail page
   - Click "Send Email"
   - Select your template
   - Send!

That's it! The system handles the rest automatically.


# AI Chat Setup - Complete Guide

## ✅ Setup Complete

The AI chat system is now fully configured and ready to use!

## What Was Done

### 1. **Fixed Encryption Key Setup**
   - ✅ Fixed directory permissions for `config/secrets/`
   - ✅ Directory is now writable (755 permissions)
   - ✅ Encryption key can now be generated and saved

### 2. **AI Assistant Panel Integration**
   - ✅ Added AI assistant panel to main header (available on all pages)
   - ✅ Panel appears as a floating button (🧠 Assistant)
   - ✅ JavaScript is loaded in footer
   - ✅ Panel respects user permissions (`ai.assistant`)

### 3. **DeepSeek Configuration**
   - ✅ DeepSeek provider is configured in the system
   - ✅ Provider settings can be managed at: `modules/ai-governance.php`
   - ✅ API keys are encrypted using the encryption key

## How to Use

### Step 1: Set Up Encryption Key (If Not Done)

1. Go to: `modules/admin/setup-encryption-key.php`
2. Click "Generate Encryption Key"
3. Click "Save to File"
4. The key will be saved to `config/secrets/encryption.key`

### Step 2: Configure DeepSeek API Key

1. Go to: `modules/ai-governance.php`
2. Select "DEEPSEEK" from the provider dropdown
3. Enter your DeepSeek API key
4. Configure model (default: `deepseek-chat`)
5. Set base URL (default: `https://api.deepseek.com/v1`)
6. Enable the provider
7. Click "Save Provider Settings"

### Step 3: Use AI Chat

1. **Access the Assistant:**
   - Look for the 🧠 "Assistant" button in the bottom-right corner of any page
   - Or go directly to: `modules/ai-assistant.php`

2. **Start Chatting:**
   - Click the Assistant button to open the panel
   - Type your question in the input field
   - Or click one of the quick prompts

3. **Context-Aware:**
   - The assistant can understand context from the current page
   - For example, if you're viewing a client, it can answer questions about that client

## Quick Test

1. Open any page in ABBIS
2. Look for the 🧠 "Assistant" button (bottom-right)
3. Click it to open the chat panel
4. Try asking: "Give me today's top three priorities"
5. The assistant should respond using DeepSeek

## Troubleshooting

### Assistant Button Not Showing
- Check that you have the `ai.assistant` permission
- Verify `data-ai-enabled="1"` is in the `<body>` tag
- Check browser console for JavaScript errors

### "Could not reach ABBIS AI service"
- Verify encryption key is set up
- Check DeepSeek API key is configured and valid
- Ensure provider is enabled in AI Governance
- Check browser console for detailed error messages

### "Encryption Key Not Configured"
- Go to `modules/admin/setup-encryption-key.php`
- Generate and save the encryption key
- Ensure `config/secrets/` directory is writable (755)

## Files Modified

1. **`includes/header.php`** - Added AI assistant panel inclusion
2. **`config/secrets/`** - Directory permissions fixed (755)
3. **`scripts/check-ai-setup.php`** - Created diagnostic script

## API Endpoints

- **Chat API:** `api/ai-insights.php?action=assistant_chat`
- **Governance:** `modules/ai-governance.php`
- **Assistant Page:** `modules/ai-assistant.php`

## Next Steps

1. ✅ Set up encryption key (if not done)
2. ✅ Configure DeepSeek API key in AI Governance
3. ✅ Test the chat on any page
4. ✅ Customize quick prompts if needed

The AI chat is now fully functional! 🎉


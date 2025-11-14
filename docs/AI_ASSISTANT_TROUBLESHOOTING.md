# ABBIS Assistant - Troubleshooting Guide

## ❌ Error: "Received an unexpected response from the AI service"

### What This Means

This error appears when the ABBIS Assistant tries to communicate with AI providers (like OpenAI, DeepSeek) but something goes wrong. The most common cause is **no AI providers are configured**.

### 🔍 Common Causes

1. **No AI Providers Set Up** (Most Common)
   - You haven't configured any AI providers yet
   - No API keys have been added

2. **Missing API Keys**
   - Provider is enabled but API key is missing or invalid
   - API key encryption failed

3. **Server Configuration Issue**
   - PHP error occurred (check server logs)
   - Database connection issue
   - Missing required files

### ✅ How to Fix

#### Step 1: Go to AI Governance Page
```
http://localhost:8080/abbis3.2/modules/ai-governance.php
```

#### Step 2: Configure at Least One Provider

1. **Select a Provider** from the dropdown:
   - OpenAI (requires API key from openai.com)
   - DeepSeek (requires API key from deepseek.com)
   - Gemini (requires API key from Google Cloud)
   - Ollama (self-hosted, no API key needed)

2. **Enter API Key** (if required):
   - Get your API key from the provider's website
   - Paste it in the "API Key" field
   - The key will be encrypted and stored securely

3. **Set Limits** (optional):
   - Daily limit: Max requests per day
   - Monthly limit: Max requests per month
   - Leave blank for unlimited

4. **Set Priority**:
   - Lower number = tried first
   - Example: OpenAI = 1, DeepSeek = 2

5. **Enable Provider**:
   - Check "Provider enabled for use"

6. **Click "💾 Save Provider Settings"**

#### Step 3: Test the Assistant

1. Go back to any page with the ABBIS Assistant
2. Click the 🧠 Assistant button
3. Type "hello" or use a quick prompt
4. It should work now!

### 🆘 Still Not Working?

#### Check 1: Verify Provider is Enabled
- Go to AI Governance page
- Check "Current Provider State" table
- Make sure at least one shows "Enabled" status

#### Check 2: Verify API Key
- Make sure you entered a valid API key
- For OpenAI: Get key from https://platform.openai.com/api-keys
- For DeepSeek: Get key from https://platform.deepseek.com/api_keys
- The key should start with `sk-` for OpenAI or similar for others

#### Check 3: Check Server Logs
- Look in `/opt/lampp/htdocs/abbis3.2/logs/` for error messages
- Check PHP error logs for details

#### Check 4: Test API Directly
Open in browser (while logged in):
```
http://localhost:8080/abbis3.2/api/ai-insights.php?action=assistant_chat
```

If you see JSON, the API is working. If you see HTML/error, there's a server issue.

### 💡 Quick Setup Guide

**For OpenAI (Recommended for beginners):**
1. Sign up at https://platform.openai.com
2. Go to API Keys section
3. Create a new API key
4. Copy the key (starts with `sk-`)
5. Go to AI Governance page
6. Select "OPENAI"
7. Paste the key
8. Click "Save Provider Settings"
9. Done! ✅

**For DeepSeek (Cheaper alternative):**
1. Sign up at https://platform.deepseek.com
2. Get your API key
3. Follow same steps as OpenAI above

### 📝 What Each Provider Needs

| Provider | API Key Required? | Where to Get |
|----------|------------------|--------------|
| **OpenAI** | ✅ Yes | https://platform.openai.com/api-keys |
| **DeepSeek** | ✅ Yes | https://platform.deepseek.com/api_keys |
| **Gemini** | ✅ Yes | Google Cloud Console |
| **Ollama** | ❌ No | Self-hosted (needs local server) |

### 🔒 Security Note

- API keys are encrypted using `ABBIS_ENCRYPTION_KEY`
- Make sure this key is set in your server environment
- Keys are never displayed after saving (only shown once)

---

**Need More Help?** Check the AI Governance page for detailed provider setup instructions.


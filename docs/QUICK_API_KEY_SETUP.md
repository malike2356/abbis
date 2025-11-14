# Quick API Key Setup Guide

## Your API Keys

**OpenAI API Key:**
```
sk-proj-YOUR_OPENAI_API_KEY_HERE
```

**DeepSeek API Key:**
```
sk-YOUR_DEEPSEEK_API_KEY_HERE
```

## Step-by-Step Instructions

### 1. Go to AI Governance
Navigate to: `http://localhost:8080/abbis3.2/modules/ai-governance.php`

### 2. Configure OpenAI

1. In the **"🤖 AI Provider Setup"** section:
   - Select **"OPENAI"** from the "Which AI Service?" dropdown
   
2. Scroll down to the OpenAI settings (they appear after selecting OpenAI):
   - **API Key**: Paste your OpenAI key:
     ```
     sk-proj-YOUR_OPENAI_API_KEY_HERE
     ```
   - **Default Model**: Enter `gpt-4` or `gpt-3.5-turbo`
   - **Base URL**: Leave blank (uses default OpenAI endpoint)
   - **Request Timeout**: 30 (default)

3. Set **Priority Number**: `1` (to make it primary)

4. Check **"Status: Provider enabled for use"**

5. Click **"💾 Save Provider Settings"**

### 3. Configure DeepSeek (Optional Backup)

1. Select **"DEEPSEEK"** from the dropdown

2. In DeepSeek settings:
   - **API Key**: Paste your DeepSeek key:
     ```
     sk-YOUR_DEEPSEEK_API_KEY_HERE
     ```
   - **Default Model**: Enter `deepseek-chat`
   - **Base URL**: Leave blank
   - **Request Timeout**: 30

3. Set **Priority Number**: `2` (backup provider)

4. Check **"Status: Provider enabled for use"**

5. Click **"💾 Save Provider Settings"**

### 4. Verify Setup

After saving, you should see:
- ✅ Both providers listed under "Currently Configured Providers"
- ✅ "API Key stored" message
- ✅ Providers showing as "Enabled"

### 5. Test the AI Assistant

1. Go to any page with the AI Assistant (🧠 icon)
2. Try asking: "Give me today's top three priorities"
3. It should work now!

## Troubleshooting

- **"API key not saved"**: Make sure the encryption key is configured first
- **"Provider not working"**: Check that the API key is valid and has credits
- **"Still getting errors"**: Clear browser cache and try again


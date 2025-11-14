# How to Get AI Provider API Keys

This guide explains how to obtain API keys for different AI providers supported by ABBIS.

## 🤖 OpenAI

**Status:** ✅ Already configured

You already have an OpenAI API key configured. If you need a new one:

1. Go to: https://platform.openai.com/api-keys
2. Sign in or create an account
3. Click "Create new secret key"
4. Copy the key (starts with `sk-`)
5. Configure in ABBIS: `modules/ai-governance.php`

---

## 🔷 DeepSeek

**Status:** ✅ Already configured

You already have a DeepSeek API key configured. If you need a new one:

1. Go to: https://platform.deepseek.com/
2. Sign in or create an account
3. Navigate to API Keys section
4. Create a new API key
5. Copy the key (starts with `sk-`)
6. Configure in ABBIS: `modules/ai-governance.php`

---

## 🟢 Google Gemini

### Step 1: Create Google Cloud Project

1. Go to: https://console.cloud.google.com/
2. Sign in with your Google account
3. Click "Select a project" → "New Project"
4. Enter project name (e.g., "ABBIS AI")
5. Click "Create"

### Step 2: Enable Generative AI API

1. In your project, go to: https://console.cloud.google.com/apis/library
2. Search for "Generative Language API"
3. Click on it and click "Enable"

### Step 3: Create API Key

1. Go to: https://console.cloud.google.com/apis/credentials
2. Click "Create Credentials" → "API Key"
3. Copy the API key (it will be a long string)
4. **Optional but recommended:** Click "Restrict key" to limit usage
   - Under "API restrictions", select "Restrict key"
   - Choose "Generative Language API"
   - Click "Save"

### Step 4: Configure in ABBIS

1. Go to: `modules/ai-governance.php`
2. Select "GEMINI" from the provider dropdown
3. Enter your API key
4. Model: `gemini-1.5-flash-latest` (or `gemini-pro`)
5. Base URL: Leave blank (uses Google default)
6. Enable the provider
7. Click "Save Provider Settings"

**Cost:** Free tier available, then pay-as-you-go

---

## 🦙 Ollama

Ollama is different from other providers - it's typically **self-hosted** and doesn't require an API key.

### Option 1: Local Ollama (Recommended for Development)

1. **Install Ollama:**
   ```bash
   # Linux/Mac
   curl -fsSL https://ollama.com/install.sh | sh
   
   # Or download from: https://ollama.com/download
   ```

2. **Start Ollama:**
   ```bash
   ollama serve
   ```
   This runs on `http://localhost:11434` by default

3. **Pull a model:**
   ```bash
   ollama pull llama2
   # or
   ollama pull mistral
   ```

4. **Configure in ABBIS:**
   - Go to: `modules/ai-governance.php`
   - Select "OLLAMA"
   - **API Key:** Leave blank (not required for local Ollama)
   - **Base URL:** `http://localhost:11434` (or your Ollama server URL)
   - **Model:** `llama2` (or whatever model you pulled)
   - Enable the provider
   - Click "Save Provider Settings"

### Option 2: Remote Ollama Server

If you have Ollama running on a remote server:

1. Ensure Ollama is accessible (firewall, network, etc.)
2. Configure in ABBIS:
   - **Base URL:** `http://your-server-ip:11434` or `https://your-domain.com`
   - **Model:** The model name on that server
   - **API Key:** Leave blank (unless your server requires authentication)

### Option 3: Hosted Ollama Services

Some services offer hosted Ollama with API keys:

1. Check services like:
   - **Ollama Cloud** (if available)
   - **AnyScale** (offers Ollama-compatible endpoints)
   - Other hosting providers

2. If they provide an API key:
   - Enter it in the "API Key" field
   - Use their provided base URL
   - Configure as normal

**Cost:** Free for local use, varies for hosted services

---

## 📋 Quick Setup Checklist

### Gemini Setup:
- [ ] Create Google Cloud project
- [ ] Enable Generative Language API
- [ ] Create API key
- [ ] Configure in ABBIS AI Governance

### Ollama Setup:
- [ ] Install Ollama locally OR set up remote server
- [ ] Pull a model (e.g., `ollama pull llama2`)
- [ ] Start Ollama server
- [ ] Configure in ABBIS AI Governance (no API key needed)

---

## 🔧 Configuration Tips

### For Gemini:
- **Recommended Model:** `gemini-1.5-flash-latest` (fast and free tier available)
- **Alternative:** `gemini-pro` (more capable, may cost more)
- **Base URL:** Leave blank to use Google's default endpoint

### For Ollama:
- **Recommended Models:**
  - `llama2` - Good balance
  - `mistral` - Fast and efficient
  - `codellama` - Good for code-related tasks
  - `llama2:13b` - More capable (requires more RAM)
- **Base URL:** `http://localhost:11434` for local, or your server URL
- **No API Key Required** for standard Ollama installations

---

## 🧪 Testing Your Setup

After configuring:

1. Go to: `modules/ai-assistant.php`
2. Click the 🧠 "Assistant" button
3. Try asking: "Hello, can you hear me?"
4. If you get a response, your provider is working!

---

## 💰 Cost Comparison

| Provider | Free Tier | Paid Pricing |
|----------|-----------|--------------|
| **OpenAI** | Limited | ~$0.002 per 1K tokens |
| **DeepSeek** | Limited | Very affordable |
| **Gemini** | ✅ Generous free tier | Pay-as-you-go |
| **Ollama** | ✅ Completely free (self-hosted) | Free (hosting costs if remote) |

---

## 🆘 Troubleshooting

### Gemini Issues:
- **"API key invalid"**: Check that you copied the full key
- **"API not enabled"**: Make sure Generative Language API is enabled in Google Cloud
- **"Quota exceeded"**: Check your Google Cloud quotas

### Ollama Issues:
- **"Connection refused"**: Make sure Ollama is running (`ollama serve`)
- **"Model not found"**: Pull the model first (`ollama pull model-name`)
- **"Timeout"**: Check network/firewall if using remote server

---

## 📚 Additional Resources

- **Gemini Documentation:** https://ai.google.dev/docs
- **Ollama Documentation:** https://ollama.com/docs
- **Ollama Models:** https://ollama.com/library

---

**Need Help?** Check the AI Governance page in ABBIS for provider-specific settings and error messages.


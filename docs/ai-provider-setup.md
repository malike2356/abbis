## ABBIS AI Provider Configuration

ABBIS now supports multiple inference backends through the provider abstraction layer. Four adapters ship out-of-the-box:

- `openai` – suitable for hosted OpenAI or Azure OpenAI compatible endpoints.
- `deepseek` – targets DeepSeek’s GPT-compatible API.
- `gemini` – uses Google’s Gemini Generative Language API (non-streaming).
- `ollama` – targets self-hosted Ollama instances (ideal for on-prem or air-gapped deployments).

### 1. Environment Variables

| Variable | Purpose | Example |
| --- | --- | --- |
| `AI_PROVIDERS` | Comma-separated provider priority order. | `openai,ollama` |
| `AI_PROVIDER_FAILOVER` | Optional override for failover order (falls back to `AI_PROVIDERS` then defaults). | `gemini,openai,ollama` |
| `AI_OPENAI_API_KEY` | Secret key for OpenAI-compatible APIs. | `sk-...` |
| `AI_OPENAI_MODEL` | Default OpenAI model. | `gpt-4.1-mini` |
| `AI_OPENAI_BASE_URL` | Custom base URL (set for Azure/OpenAI proxy). | `https://api.openai.com/v1` |
| `AI_DEEPSEEK_API_KEY` | Secret key for DeepSeek. | `sk-deepseek...` |
| `AI_DEEPSEEK_MODEL` | Default DeepSeek model. | `deepseek-chat` |
| `AI_DEEPSEEK_BASE_URL` | Optional DeepSeek base URL override. | `https://api.deepseek.com/v1` |
| `AI_GEMINI_API_KEY` | Google Generative AI API key. | `AIza...` |
| `AI_GEMINI_MODEL` | Default Gemini model. | `gemini-1.5-flash-latest` |
| `AI_GEMINI_BASE_URL` | Optional Gemini base URL override. | `https://generativelanguage.googleapis.com/v1beta` |
| `AI_OLLAMA_BASE_URL` | Ollama host URL. | `http://127.0.0.1:11434` |
| `AI_OLLAMA_MODEL` | Default Ollama model to load. | `llama3` |
| `AI_OLLAMA_TIMEOUT` | Optional timeout in seconds (default: `120`). | `90` |
| `AI_HOURLY_LIMIT` / `AI_DAILY_LIMIT` | Override governance quotas per user. | `30` / `200` |

> **Tip:** Configure provider variables in `.env` or your deployment environment before enabling AI features.

> **In-app configuration:** From ABBIS v3.2 you can also manage provider API keys, default models, and failover priority from the **System → AI Governance & Audit** screen. Values saved in the UI are encrypted with `ABBIS_ENCRYPTION_KEY` and override the environment defaults at runtime.

### 2. Database Requirements

Run the AI governance migration to create usage logging and cache tables:

```bash
php scripts/run-migration.php database/migrations/phase5/001_create_ai_tables.sql
```

This creates:

- `ai_usage_logs` – captures per-request metadata and token counts.
- `ai_response_cache` – reserved for future response caching.
- `ai_provider_config` – stores per-provider enablement and limits.

### 3. Permissions & Access Control

- Users must hold the `ai.assistant` permission (managed in `config/access-control.php`).
- Navigation items and UI panels respect this permission automatically.

### 4. Runtime Behaviour

1. ABBIS reads `AI_PROVIDERS` to determine which adapters to register. Unknown keys are skipped without failing the request.
2. The `AIServiceBus` honours `AI_PROVIDER_FAILOVER` (falling back to defaults) when routing a chat completion.
3. Providers that don’t support streaming (currently Gemini) automatically fallback to regular completions.
4. `UsageLimiter` enforces hourly/daily quotas using entries in `ai_usage_logs`.
5. Responses and governance data are audited via `AIAuditLogger`.

### 5. Testing Checklist

1. Set environment variables for your chosen provider(s).
2. Run the migration script for the governance tables.
3. Log in as a user with `ai.assistant` permission and open `modules/ai-assistant.php`.
4. Send a prompt; verify a response is returned and that an entry appears in `ai_usage_logs`.
5. Review `storage/logs/ai-usage.log` (if enabled) for diagnostics.

For advanced customisations (additional providers, prompt templates, rate limits), extend the classes under `includes/AI/`.


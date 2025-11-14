# Phase 5: AI Insight Layer – Architecture & Implementation Plan

## 1. Current State (2025-11-09 Sweep)

- **Data & Reporting**
  - `modules/analytics.php`, `modules/dashboard.php`, and related API endpoints (`api/analytics-api.php`, `api/get-data.php`) already assemble KPIs from `field_reports`, `rig_requests`, `cms_quote_requests`, `inventory_*` tables.
  - Offline-first workflows (`offline/index.html`, `api/sync-offline-reports.php`) cache report payloads in IndexedDB, later synced to MySQL. Context builders must read from the canonical tables, not the offline store.

- **Operations & Requests**
  - Detailed lifecycle code in `modules/requests.php` and helper classes (`includes/request-response-manager.php`, `modules/rig-requests.php`) track statuses, approvals, and items. These are prime inputs for contextual insights (e.g., quote status, rig deployment blockers).

- **Existing AI Surface**
  - `api/ai-service.php` offers heuristic stubs (`forecast_cashflow`, `forecast_materials`, `lead_score`). No LLM integration, prompt assembly, or governance. Responses run straight from query parameters with no permission checks.

- **Security & Governance**
  - Authentication handled via `includes/auth.php` with role-based permissions defined in `config/access-control.php` and audited through `access_control_logs` (viewed in `modules/access-logs.php`).
  - Rate-limiting exists only for login attempts. API endpoints rely on ad-hoc checks (e.g., `includes/auth.php->requireAuth`, `requireRole`, `requirePermission`).

- **Infrastructure Touchpoints**
  - Central configuration lives in `config/app.php`, `config/environment.php`, `.env` support through helper functions.
  - Reusable utilities (`includes/helpers.php`, `includes/functions.php`) expose logging, caching (`storage/cache`), and HTTP helpers.

## 2. Target Outcomes for Phase 5

1. **AI Insight Layer** with provider abstraction, structured context builders, and reusable inference pipeline.
2. **User-Facing Assistant** embedded within ABBIS, surfacing insights, forecasts, and “next best actions”.
3. **Governance Guardrails** covering access control, rate limiting, usage quotas, and auditable activity logs.
4. **Pilot Evaluation Loop** using real operational data to benchmark prompt quality, latency, cost, and accuracy.

## 3. Proposed Architecture

### 3.1 Core Components

| Component | Responsibility | Initial Location |
|-----------|----------------|------------------|
| `AIProviderInterface` | Contract for chat/completion providers (streaming + non-streaming) | `includes/AI/AIProviderInterface.php` |
| Provider adapters | Concrete implementations (`OpenAIProvider`, `AzureOpenAIProvider`, `OllamaProvider` etc.) with retry & telemetry hooks | `includes/AI/Providers/*` |
| `AIServiceBus` | Provider registry, routing logic, failover policies, metric publishing | `includes/AI/AIServiceBus.php` |
| `ContextAssembler` | Aggregates structured context slices (user profile, permissions, domain data) using pluggable builders | `includes/AI/Context/ContextAssembler.php` |
| Context builders | Source-specific fetchers (`FieldReportContextBuilder`, `RigRequestContextBuilder`, etc.) | `includes/AI/Context/Builders/*` |
| `PromptTemplate` utilities | Manage prompt versions, variable injection, guardrails | `includes/AI/Prompting/*` |
| `AIInsightManager` | High-level orchestrator invoked by APIs/UI to assemble context, call provider, post-process | `includes/AI/AIInsightManager.php` |
| Storage | Tables for usage audit (`ai_usage_logs`), cached responses (`ai_response_cache`), provider configs (`ai_providers`) | `database/migrations/phase5/*.sql` |

### 3.2 API Layer

- **New Endpoint:** `api/ai-insights.php`
  - Actions: `assistant_chat`, `insight_summary`, `forecast`, `playbook`.
  - All requests pass through `Auth::requirePermission('ai.assistant')`.
  - Rate limiting via `AIUsageLimiter` (Redis if available, otherwise MySQL-based windowing) built in `includes/AI/Governance/UsageLimiter.php`.
  - Logs request metadata (user, action, tokens, provider, latency, success/error) into `ai_usage_logs`.
  - Supports streaming via Server-Sent Events for UI typing indicator (`Content-Type: text/event-stream`).

- **Legacy Endpoint Upgrade:** `api/ai-service.php`
  - Deprecate direct heuristics; shim into `AIInsightManager` with deterministic fallback when provider unavailable.
  - Mark old parameter names as deprecated; route via new permission checks and audit trail.

### 3.3 Front-End Assistant

- **Placement:** Primary entry within `modules/dashboard.php` (floating assistant panel) and `modules/analytics.php` (insight sidebar).
- **Assets:** New JS module `assets/js/ai-assistant.js` leveraging existing fetch helpers, SSE handling, conversation state, context preview.
- **UI Elements:**
  - Thread view with message grouping and system notices (e.g., “Context: Rig Request #1023 – Negotiating”).
  - Quick action buttons (pre-set prompts: “Summarise field reports variance”, “Generate next steps for stuck quotes”).
  - Context selector drop-down pulling from `/api/get-data.php` to set focus entity.
- **Accessibility:** Use existing CSS tokens (`var(--primary)`, etc.); ensure keyboard navigation, transcript export.

### 3.4 Context Assembly Strategy

1. **Baseline context** (always): user profile, role, permissions, current module/view, organisation metadata.
2. **Entity context** (optional): When user selects an entity (client, rig, quote, report), fetch via dedicated builder.
3. **Historical context:** Pull last N related interactions (e.g., last 5 field reports) to give temporal trends.
4. **Policy snippets:** Incorporate compliance reminders (e.g., GDPR notices) from `modules/policies.php` when relevant.

Implementation guidelines:
- Builders return a normalised array `['type' => 'rig_request', 'payload' => [...], 'tokens' => approx]`.
- `ContextAssembler` enforces token budget by scoring each slice; low-priority slices trimmed first.
- Sensitive fields (PII) flagged (`sensitivity` key) for redaction rules.

### 3.5 Governance Enhancements

| Control | Implementation |
|---------|----------------|
| **Access Control** | Add `ai.assistant` permission in `config/access-control.php` (default roles: Admin, Manager). Apply to new module `modules/ai-assistant.php` and API actions. |
| **Rate Limiting** | Per-user + per-organisation quotas configurable in `config/app.php` (`ABBIS_AI_MAX_REQUESTS_HOURLY`, etc.). Enforced via `AIUsageLimiter`. |
| **Audit Logging** | `ai_usage_logs` table storing user_id, role, action, provider, token counts, latency, error codes. Expose viewer `modules/ai-audit.php`. |
| **Prompt Safety** | Template repository includes system prompts with explicit constraints (non-disclosure of PII, disclaimers). Add guardrail check to drop requests flagged as high-risk. |
| **Cost Guard** | Optional spending limits per provider; `AIServiceBus` consults `ai_provider_config` for allowed monthly usage and flips provider to “suspended” when exceeded. |

### 3.6 Provider Abstraction

- **Configuration** via `config/environment.php` entries (`AI_PROVIDERS=openai,azure; AI_OPENAI_API_KEY=...`).
- `AIServiceBus` loads available providers, handles failover (e.g., try OpenAI, fallback to Azure; fallback to heuristic offline model).
- Each provider implements:
  - `supportsStreaming()`
  - `complete(array $messages, array $options): AIResponse`
  - Error normalization (`AIProviderException` with code categories: `rate_limit`, `auth`, `service_unavailable`).
- Telemetry hooks publish metrics to:
  - `storage/logs/ai-usage.log`
  - Optional external sink (`hooks.ai_usage_callback` config).

## 4. Database Changes

- `ai_usage_logs` (id, user_id, role, action, provider, prompt_tokens, completion_tokens, total_tokens, latency_ms, input_hash, context_summary, is_success, error_code, created_at).
- `ai_response_cache` (cache_key, response_json, metadata_json, expires_at, created_at).
- `ai_provider_config` (provider_key, is_enabled, daily_limit, monthly_limit, failover_priority, settings_json, created_at, updated_at).
- Stored procedures or scheduled job to purge expired cache/log data (`scripts/cleanup-ai-logs.php`).

Migration scripts placed in `database/migrations/phase5/001_create_ai_tables.sql` etc., with matching PHP runner under `scripts/`.

## 5. Implementation Roadmap

1. **Scaffold AI namespace**
   - Create `includes/AI/` structure; introduce provider interface, base classes, exceptions.
   - Implement OpenAI adapter first (uses `gpt-4.1` or enterprise-friendly model stored in env).

2. **Build governance helpers**
   - Rate limiter, audit logger, cache utilities.
   - Extend `includes/auth.php` with helper `enforceAIPermission()` if needed.

3. **Database migrations**
   - Apply new tables; extend `includes/functions.php` with helper to run migrations automatically.

4. **API endpoints**
   - New `api/ai-insights.php`.
   - Refactor `api/ai-service.php` to delegate to new layer, preserving backward compatibility.

5. **Front-end integration**
   - Implement `assets/js/ai-assistant.js`, CSS additions in `assets/css/styles.css`.
   - Add assistant drawer component to `modules/dashboard.php` and `modules/analytics.php`.
   - Provide module page `modules/ai-assistant.php` for full screen conversations and history.

6. **Governance UI**
   - Update `config/access-control.php` with new permission.
   - Add controls to `modules/feature-management.php` or new `modules/ai-settings.php` for administrators (set quotas, enable providers).
   - Build `modules/ai-audit.php` to view usage logs (filters by user, action, provider, date).

7. **Pilot & Evaluation**
   - Configure evaluation harness (`scripts/ai-eval-runner.php`):
     - Replay anonymised historical data (e.g., last 90 days field reports) against prompts.
     - Store evaluation metrics (BLEU for summaries, numeric deviation for forecasts).
   - Collect user feedback: embed thumbs-up/down in assistant; store in `ai_usage_logs.feedback`.
   - Iterate on prompt templates in `includes/AI/Prompting/Templates/*.md`.

8. **Documentation & Training**
   - Update `docs/` with admin guide, user quick start, prompt library, governance checklist.
   - Produce runbook for incident response (e.g., provider outage fallback).

## 6. Pilot Evaluation Plan

1. **Scope**
   - Participants: Service Delivery team leads + 5 managers.
   - Duration: 4 weeks; target 300 assistant interactions.

2. **Success Metrics**
   - ≥80% positive feedback on assistant usefulness.
   - Forecast variance ≤15% against actuals for weekly field productivity.
   - Mean response latency ≤6 seconds (p95 ≤10s).
   - No security incidents (zero unauthorised access, all usage logged).

3. **Process**
   - Week 1: Enable assistant with guardrails, collect baseline telemetry.
   - Week 2-3: Prompt tuning via cached transcripts; refine context slices.
   - Week 4: Summarise metrics, prepare go/no-go report for production rollout.

4. **Risk Mitigation**
   - Fallback to heuristic engine if provider unavailable.
   - Redaction checks before prompt submission (strip phone numbers, emails).
   - Admin dashboard to immediately disable provider if anomaly detected.

## 7. Next Steps

1. Confirm provider priority stack and obtain API credentials.
2. Approve database schema additions.
3. Implement scaffolding (Steps 1-3 in roadmap), then iterate towards front-end assistant.
4. Schedule pilot kickoff with Service Delivery stakeholders and align on evaluation cadence.

---

Prepared: 2025-11-09 |
Contact: Laud Paul-Gablah |
System: ABBIS v3.2 (LAMP/PHP)


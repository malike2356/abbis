# ABBIS AI Assistant - Capabilities & Limitations

## 📊 **What the AI Assistant CAN See**

### ✅ **Always Available Context**

1. **User Information** (Priority: 10)
   - User ID, username, full name, email, role
   - Last login timestamp (if available)
   - **Source:** `users` table

2. **Organisation Information** (Priority: 20)
   - Company name
   - Contact email and phone
   - Company address
   - Industry type
   - Timezone
   - **Source:** `system_config` or `company_profile` table

3. **Business Intelligence Data** (Priority: 20-30) ⭐ **NEW**
   - **Top Clients by Revenue** (Top 5)
     - Client name, total jobs, total revenue, total profit, average profit per job
   - **Recent Field Reports** (Last 10)
     - Project details, client, financials, status
   - **Dashboard KPIs**
     - Today's metrics (reports, income, expenses, profit)
     - This month's metrics
     - Overall totals and averages
   - **Today's Priorities**
     - Pending follow-ups (overdue, today, upcoming)
     - Pending quote requests
     - Pending rig requests
   - **Top Performing Rigs** (Top 5)
     - Rig name, code, job count, revenue, profit
   - **Financial Health Summary**
     - Total income, expenses, profit
     - Profit margin, expense ratio
     - Deposits, outstanding fees
     - Materials value, loans
   - **Pending Quote Requests** (Top 5)
     - Name, email, location, budget, status
   - **Operational Metrics**
     - Average job duration, depth
     - Total operating hours
     - Active rigs, jobs per rig

### ✅ **Context-Specific Data** (Only when viewing a specific entity)

When you're on a page with a specific entity (field report, client, quote request, etc.), the AI can see:

3. **Field Report Context** (Priority: 30)
   - Report ID, date, project name, location, status
   - Total income, expenses, net profit
   - Client name
   - **Source:** `field_reports` table (joined with `clients`)

4. **Client Context** (Priority: 30)
   - Client ID, name, contact person, email, phone
   - Industry, city, country
   - **Lifetime value** (sum of all net_profit from field_reports)
   - **Recent reports** (last 5 field reports with dates, project names, status, profit)
   - **Source:** `clients` table + aggregated `field_reports` data

5. **Quote Request Context** (Priority: 30)
   - Request details (name, email, phone, location, status, budget)
   - Status history (last 10 status changes)
   - **Source:** `cms_quote_requests` + `crm_request_status_history`

6. **Rig Request Context** (Priority: 30)
   - Request number, requester, location, status, priority
   - Estimated budget, number of boreholes
   - Status history (last 10 status changes)
   - **Source:** `rig_requests` + `crm_request_status_history`

---

## ❌ **What the AI Assistant CANNOT See**

### 🚫 **No Direct Database Access**
- The AI **cannot** query the database directly
- It **cannot** run SQL queries
- It **cannot** access tables that aren't explicitly included in context builders

### 🚫 **Missing Data Sources**

The AI **does NOT** have access to:

1. ~~**General Client List**~~ ✅ **NOW AVAILABLE**
   - ✅ Can see top 5 clients by revenue
   - ✅ Can identify biggest client
   - ✅ Can compare client performance

2. ~~**All Field Reports**~~ ✅ **NOW AVAILABLE**
   - ✅ Can see last 10 recent reports
   - ✅ Can analyze recent trends
   - ✅ Can see report summaries

3. ~~**Dashboard/Summary Data**~~ ✅ **NOW AVAILABLE**
   - ✅ Can see KPI metrics (today, this month, overall)
   - ✅ Can see financial summaries
   - ✅ Can see top performing clients/rigs
   - ✅ Can see recent activity

4. **Materials Inventory**
   - Cannot see material stock levels
   - Cannot see material costs or values
   - Cannot see material requests or transfers
   - **Why:** No context builder for materials

5. **POS/Sales Data**
   - Cannot see POS sales
   - Cannot see product catalog
   - Cannot see inventory levels
   - **Why:** No context builder for POS system

6. **CRM Data**
   - Cannot see all follow-ups
   - Cannot see all activities
   - Cannot see email history
   - **Why:** Only loads specific entity context, not general CRM data

7. **Real-Time Data**
   - Cannot see "today's" data unless it's in the context
   - Cannot see pending tasks
   - Cannot see alerts or notifications
   - **Why:** Context is built once per request, not refreshed

---

## 🎯 **What the AI Assistant CAN Do**

### ✅ **Analysis & Insights**
- Analyze data provided in context
- Summarize information from context slices
- Identify patterns in provided data
- Provide recommendations based on context

### ✅ **Text Generation**
- Generate summaries
- Create reports based on context
- Write explanations
- Suggest next actions

### ✅ **Context-Aware Responses**
- Understand what page/entity you're viewing
- Provide relevant insights for that specific entity
- Reference data from the current context

---

## 🚫 **What the AI Assistant CANNOT Do**

### ❌ **Cannot Query Database**
- Cannot run SQL queries
- Cannot fetch new data
- Cannot search across tables
- Cannot aggregate data from multiple sources

### ❌ **Cannot Perform Actions**
- Cannot create/update/delete records
- Cannot send emails
- Cannot trigger workflows
- Cannot modify system settings

### ❌ **Cannot Access Real-Time Data**
- Cannot see live updates
- Cannot refresh data automatically
- Cannot monitor system state
- Cannot access external APIs

### ✅ **Can Now Answer General Questions** ⭐ **NEW**
- "Who is our biggest client?" → ✅ **Can answer** (has top clients by revenue)
- "What are today's priorities?" → ✅ **Can answer** (has pending follow-ups, quotes, rig requests)
- "Show me recent field reports" → ✅ **Can answer** (has last 10 reports)
- "What's our total revenue?" → ✅ **Can answer** (has dashboard KPIs)
- "Which rigs are performing best?" → ✅ **Can answer** (has top rigs data)
- "What's our financial health?" → ✅ **Can answer** (has financial health summary)

---

## 💡 **How to Get Better Answers**

### ✅ **When Viewing a Specific Entity**
- Ask questions about that specific entity
- Example: On a client page → "What is this client's lifetime value?"
- Example: On a field report → "Summarize this report's financial performance"

### ✅ **Provide Context in Your Question**
- Be specific about what you want
- Reference the entity you're viewing
- Ask for analysis of the current page's data

### ❌ **Avoid General Questions**
- Don't ask about "all clients" unless you're on a client list page
- Don't ask about "today's data" unless it's in the context
- Don't ask for comparisons across entities not in context

---

## 🔧 **How to Extend AI Capabilities**

To make the AI see more data, you need to:

1. **Create New Context Builders**
   - Add a `BusinessIntelligenceContextBuilder` for dashboard data
   - Add a `ClientListContextBuilder` for all clients
   - Add a `MaterialsContextBuilder` for inventory data

2. **Register Builders in Bootstrap**
   - Add to `includes/AI/bootstrap.php`
   - Register with `$assembler->registerBuilder()`

3. **Increase Token Budget**
   - Update `AI_CONTEXT_TOKEN_BUDGET` environment variable
   - Default is 3200 tokens

---

## 📝 **Current Limitations Summary**

| Feature | Status | Reason |
|---------|--------|--------|
| View specific entity data | ✅ Yes | Entity context builder exists |
| View all clients | ✅ Yes | BusinessIntelligenceContextBuilder provides top 5 |
| View all field reports | ✅ Yes | BusinessIntelligenceContextBuilder provides last 10 |
| View dashboard KPIs | ✅ Yes | BusinessIntelligenceContextBuilder provides KPIs |
| View materials inventory | ❌ No | No materials context builder |
| View POS data | ❌ No | No POS context builder |
| Query database directly | ❌ No | Security/architecture limitation |
| Perform actions | ❌ No | Read-only by design |
| Real-time data | ❌ No | Context built once per request |

---

## 🎯 **Best Practices**

1. **Use Entity-Specific Questions**
   - When on a client page, ask about that client
   - When on a report page, ask about that report

2. **Be Specific**
   - Instead of "who is our biggest client?"
   - Ask "analyze this client's performance" (when viewing a client)

3. **Understand Context**
   - The AI only knows what's in the current page's context
   - It cannot "remember" previous conversations or fetch new data

4. **Use for Analysis, Not Queries**
   - Good: "Summarize this field report"
   - Bad: "Show me all field reports from last month"

---

## 🔮 **Future Enhancements**

Potential improvements:
- Add business intelligence context builder
- Add client list context builder
- Add materials context builder
- Add dashboard KPI context builder
- Add real-time data refresh capability
- Add ability to query specific tables on-demand


# AI Assistant Context Enhancement Guide

## Overview

This guide explains how to enhance the AI Assistant's ability to answer questions correctly by providing it with more comprehensive database information from ABBIS.

## Current Context System

The AI Assistant uses a **Context Builder** system that assembles relevant data from the database before sending it to the AI provider. The system includes:

### Existing Context Builders

1. **UserContextBuilder** - Current user information
2. **OrganisationContextBuilder** - Company details and settings
3. **EntityContextBuilder** - Specific entity data (field reports, clients, quotes, rigs)
4. **BusinessIntelligenceContextBuilder** - Business metrics and KPIs
5. **PageContextBuilder** - Current page information

### Current Data Sources

The `BusinessIntelligenceContextBuilder` currently provides:
- Top clients by revenue
- Recent field reports
- Dashboard KPIs
- Today's priorities (follow-ups, quotes, rig requests)
- Top performing rigs
- Financial health summary
- Pending quote requests
- Operational metrics

## Enhancement Strategy

### 1. Expand BusinessIntelligenceContextBuilder

Add more data sources to provide comprehensive context:

#### A. POS & Ecommerce Data
- Recent POS sales
- Top-selling products
- Inventory levels and alerts
- CMS orders and revenue
- Product catalog information

#### B. Materials & Inventory
- Materials inventory status
- Low stock alerts
- Material usage trends
- Material returns and transfers

#### C. CRM & Client Data
- Client activity summary
- Recent follow-ups
- Client lifetime value
- Communication history

#### D. Financial Transactions
- Recent payments
- Outstanding invoices
- Cash flow trends
- Bank deposits

#### E. Recruitment & HR
- Active job postings
- Application statistics
- Hiring pipeline

#### F. Field Operations
- Active field reports
- Rig utilization
- Worker assignments
- Project status

### 2. Create Specialized Context Builders

For specific use cases, create focused context builders:

- **POSContextBuilder** - POS-specific data
- **MaterialsContextBuilder** - Materials and inventory
- **CRMContextBuilder** - Client relationship data
- **FinancialContextBuilder** - Financial transactions and trends

### 3. Implement Query-Based Context

Allow the AI to request specific data based on the user's question:

```php
// Example: If user asks about "inventory", include inventory context
if (strpos(strtolower($userQuestion), 'inventory') !== false) {
    $contextBuilder->includeInventoryData();
}
```

## Implementation Steps

### Step 1: Enhance BusinessIntelligenceContextBuilder

Add new methods to fetch additional data:

```php
private function getPOSData(): array
{
    // Recent sales, top products, inventory alerts
}

private function getMaterialsData(): array
{
    // Materials inventory, low stock, usage trends
}

private function getCRMData(): array
{
    // Client activity, follow-ups, communications
}

private function getFinancialData(): array
{
    // Payments, invoices, cash flow
}
```

### Step 2: Add Dynamic Context Selection

Modify the context assembler to include relevant data based on:
- Current page/module
- User's question keywords
- User's role and permissions

### Step 3: Optimize Token Usage

Since context is limited by token budget:
- Prioritize most relevant data
- Use summaries for large datasets
- Cache frequently accessed data
- Implement smart filtering

### Step 4: Add Context Metadata

Include metadata about data freshness, source, and relevance:

```php
[
    'type' => 'pos_sales',
    'data' => [...],
    'metadata' => [
        'last_updated' => '2024-01-15 10:30:00',
        'relevance_score' => 0.95,
        'source' => 'pos_sales table'
    ]
]
```

## Best Practices

### 1. Data Privacy & Security
- Only include data the user has permission to access
- Filter sensitive information based on user role
- Never include passwords or API keys

### 2. Performance
- Use efficient database queries
- Cache expensive queries
- Limit data volume to stay within token budget
- Use indexes for fast lookups

### 3. Relevance
- Prioritize recent data
- Include related entities (e.g., client's field reports)
- Provide context for the current page/module
- Filter based on user's question keywords

### 4. Accuracy
- Use consistent data formatting
- Include timestamps for temporal data
- Provide data source information
- Handle missing data gracefully

## Example: Enhanced Context Response

When a user asks "What's our inventory status?", the AI should receive:

```json
{
  "context_slices": [
    {
      "type": "materials_inventory",
      "data": {
        "total_items": 45,
        "low_stock_items": 3,
        "total_value": 125000.00,
        "recent_transfers": [...],
        "alerts": [...]
      },
      "priority": 30,
      "approxTokens": 400
    },
    {
      "type": "pos_inventory",
      "data": {
        "total_products": 120,
        "out_of_stock": 5,
        "low_stock": 12,
        "recent_sales": [...]
      },
      "priority": 25,
      "approxTokens": 350
    },
    {
      "type": "catalog_items",
      "data": {
        "total_items": 200,
        "active_items": 180,
        "inactive_items": 20
      },
      "priority": 20,
      "approxTokens": 200
    }
  ]
}
```

## Testing

After implementing enhancements:

1. **Test with various questions:**
   - "What's our inventory status?"
   - "Show me recent sales"
   - "What are today's priorities?"
   - "Which clients need follow-up?"

2. **Verify data accuracy:**
   - Compare AI responses with actual database data
   - Check that permissions are respected
   - Ensure sensitive data is not exposed

3. **Monitor performance:**
   - Check token usage
   - Monitor query performance
   - Verify response times

## Next Steps

1. Review and implement the enhanced `BusinessIntelligenceContextBuilder`
2. Test with real questions
3. Monitor AI response quality
4. Iterate based on user feedback


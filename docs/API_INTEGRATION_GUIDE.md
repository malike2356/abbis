# ABBIS API Integration Guide for Wazuh and External Systems

## 🔌 API Integration Overview

Your ABBIS system is **now ready for API integrations** with external systems like Wazuh, monitoring tools, SIEM systems, and other business intelligence platforms.

---

## ✅ **Current API Readiness Status**

### **What's Implemented:**
- ✅ **API Key Authentication** - Secure token-based authentication
- ✅ **Monitoring API Endpoint** - Dedicated endpoint for system metrics
- ✅ **Rate Limiting** - Configurable per API key
- ✅ **Health Checks** - System status monitoring
- ✅ **Performance Metrics** - Response times, memory usage
- ✅ **Alert System** - Security and system alerts
- ✅ **Log Access** - System log retrieval
- ✅ **CORS Support** - Cross-origin resource sharing enabled

### **API Architecture:**
- **RESTful Design** - Standard HTTP methods (GET, POST)
- **JSON Responses** - Consistent JSON format
- **Error Handling** - Proper HTTP status codes
- **Security** - API key authentication required
- **Rate Limiting** - Prevents abuse

---

## 🚀 **Quick Start for Wazuh Integration**

### **Step 1: Generate API Key**

1. Log in as **Administrator**
2. Navigate to **Configuration** → **API Key Management** (or `/modules/api-keys.php`)
3. Click **"Generate New API Key"**
4. Fill in:
   - **Key Name**: "Wazuh Integration"
   - **Rate Limit**: 100 requests/minute (adjust as needed)
   - **Expires In**: Optional (leave empty for no expiration)
5. Click **"Generate API Key"**
6. **Copy and securely store** the generated API key (shown only once!)

### **Step 2: Test API Connection**

```bash
# Health Check
curl -H "X-API-Key: your_api_key_here" \
  "http://localhost:8080/abbis3.2/api/monitoring-api.php?endpoint=health"

# Get Metrics
curl -H "X-API-Key: your_api_key_here" \
  "http://localhost:8080/abbis3.2/api/monitoring-api.php?endpoint=metrics"

# Performance Data
curl -H "X-API-Key: your_api_key_here" \
  "http://localhost:8080/abbis3.2/api/monitoring-api.php?endpoint=performance"
```

### **Step 3: Configure Wazuh**

**For Wazuh Agent (Linux/Windows):**

1. **Create custom integration script** (`/var/ossec/etc/integrations/abbis-monitoring.sh`):

```bash
#!/bin/bash
# ABBIS Monitoring Integration for Wazuh

API_KEY="your_api_key_here"
API_URL="http://your-domain.com/abbis3.2/api/monitoring-api.php"

# Fetch metrics
METRICS=$(curl -s -H "X-API-Key: ${API_KEY}" "${API_URL}?endpoint=metrics")

# Fetch alerts
ALERTS=$(curl -s -H "X-API-Key: ${API_KEY}" "${API_URL}?endpoint=alerts")

# Send to Wazuh Manager
echo "${METRICS}" | /var/ossec/bin/wazuh-control submit-agent-info
echo "${ALERTS}" | /var/ossec/bin/wazuh-control submit-agent-info
```

2. **Make executable:**
```bash
chmod +x /var/ossec/etc/integrations/abbis-monitoring.sh
```

3. **Configure in Wazuh Manager:**
```xml
<integration>
    <name>abbis-monitoring</name>
    <level>9</level>
    <command>exec</command>
    <location>/var/ossec/etc/integrations/abbis-monitoring.sh</location>
    <interval>300</interval> <!-- Every 5 minutes -->
</integration>
```

---

## 📡 **Available API Endpoints**

### **Base URL:**
```
http://your-domain.com/abbis3.2/api/monitoring-api.php
```

### **Authentication:**
All requests require the `X-API-Key` header:
```
X-API-Key: your_api_key_here
```

---

### **1. Health Check**
**Endpoint:** `?endpoint=health`

**Response:**
```json
{
  "success": true,
  "data": {
    "status": "healthy",
    "timestamp": "2024-11-03 12:00:00",
    "uptime": "online",
    "database": "connected",
    "version": "3.2.0"
  },
  "api_key_name": "Wazuh Integration"
}
```

**Use Case:** System health monitoring, uptime checks

---

### **2. System Metrics**
**Endpoint:** `?endpoint=metrics`

**Response:**
```json
{
  "success": true,
  "data": {
    "timestamp": "2024-11-03 12:00:00",
    "metrics": {
      "total_reports": 150,
      "active_users": 5,
      "reports_24h": 12,
      "total_revenue": 450000.00,
      "database_size_mb": 15.5
    }
  }
}
```

**Use Case:** Business intelligence, performance tracking, capacity planning

---

### **3. Performance Data**
**Endpoint:** `?endpoint=performance`

**Response:**
```json
{
  "success": true,
  "data": {
    "timestamp": "2024-11-03 12:00:00",
    "performance": {
      "response_time_ms": 45.23,
      "memory_usage_mb": 32.5,
      "memory_peak_mb": 45.8,
      "database_query_time_ms": 12.5
    }
  }
}
```

**Use Case:** Performance monitoring, bottleneck detection, resource optimization

---

### **4. System Alerts**
**Endpoint:** `?endpoint=alerts`

**Response:**
```json
{
  "success": true,
  "data": {
    "timestamp": "2024-11-03 12:00:00",
    "alerts": [
      {
        "level": "warning",
        "message": "High number of failed login attempts in the last hour",
        "count": 15
      }
    ],
    "alert_count": 1
  }
}
```

**Use Case:** Security monitoring, anomaly detection, incident response

**Alert Levels:**
- `info` - Informational messages
- `warning` - Warning conditions
- `critical` - Critical system issues

---

### **5. System Logs**
**Endpoint:** `?endpoint=logs&limit=100`

**Response:**
```json
{
  "success": true,
  "data": {
    "timestamp": "2024-11-03 12:00:00",
    "logs": [
      {
        "id": 1,
        "username": "admin",
        "attempt_time": "2024-11-03 11:45:00",
        "ip_address": "192.168.1.100"
      }
    ],
    "count": 100
  }
}
```

**Parameters:**
- `limit` - Number of log entries (default: 100, max: 1000)

**Use Case:** Audit logging, security analysis, troubleshooting

---

## 🔒 **Security Features**

### **1. API Key Authentication**
- Each API key is unique and cryptographically secure
- Keys are stored as hashed values in the database
- API keys can be activated/deactivated
- API keys can have expiration dates

### **2. Rate Limiting**
- Configurable per API key (default: 100 requests/minute)
- Prevents abuse and DDoS attacks
- Returns HTTP 429 when limit exceeded

### **3. Access Control**
- Only administrators can generate API keys
- API key usage is logged
- Last used timestamp is tracked

### **4. Error Handling**
- Proper HTTP status codes (200, 400, 401, 429, 500)
- No sensitive information in error messages
- Errors logged server-side for debugging

---

## 📊 **Response Format**

### **Success Response:**
```json
{
  "success": true,
  "data": { ... },
  "api_key_name": "Wazuh Integration"
}
```

### **Error Response:**
```json
{
  "success": false,
  "error": "Error Type",
  "message": "Human-readable error message"
}
```

### **HTTP Status Codes:**
- `200` - Success
- `400` - Bad Request (invalid parameters)
- `401` - Unauthorized (invalid/missing API key)
- `429` - Too Many Requests (rate limit exceeded)
- `500` - Internal Server Error

---

## 🔧 **Integration Examples**

### **Python Script:**
```python
import requests
import json

API_KEY = "your_api_key_here"
BASE_URL = "http://your-domain.com/abbis3.2/api/monitoring-api.php"

headers = {
    "X-API-Key": API_KEY,
    "Content-Type": "application/json"
}

# Get metrics
response = requests.get(
    f"{BASE_URL}?endpoint=metrics",
    headers=headers
)

if response.status_code == 200:
    data = response.json()
    print(json.dumps(data, indent=2))
else:
    print(f"Error: {response.status_code}")
    print(response.text)
```

### **Node.js Script:**
```javascript
const https = require('https');

const API_KEY = 'your_api_key_here';
const BASE_URL = 'your-domain.com';

const options = {
  hostname: BASE_URL,
  path: '/abbis3.2/api/monitoring-api.php?endpoint=metrics',
  method: 'GET',
  headers: {
    'X-API-Key': API_KEY
  }
};

const req = https.request(options, (res) => {
  let data = '';
  
  res.on('data', (chunk) => {
    data += chunk;
  });
  
  res.on('end', () => {
    const result = JSON.parse(data);
    console.log(JSON.stringify(result, null, 2));
  });
});

req.on('error', (error) => {
  console.error('Error:', error);
});

req.end();
```

### **PowerShell Script:**
```powershell
$apiKey = "your_api_key_here"
$url = "http://your-domain.com/abbis3.2/api/monitoring-api.php?endpoint=metrics"

$headers = @{
    "X-API-Key" = $apiKey
}

$response = Invoke-RestMethod -Uri $url -Headers $headers -Method Get
$response | ConvertTo-Json -Depth 10
```

---

## 📈 **Wazuh Dashboard Configuration**

### **Custom Dashboard Widget:**

1. **Create custom JSON dashboard** in Wazuh:
```json
{
  "title": "ABBIS System Metrics",
  "type": "visualizations",
  "visState": {
    "title": "ABBIS Metrics",
    "type": "histogram",
    "params": {
      "index_pattern": "abbis-metrics-*"
    }
  }
}
```

2. **Use Wazuh API to push metrics:**
```bash
curl -X POST "https://wazuh-manager:55000/v1/integrations" \
  -H "Authorization: Bearer your_wazuh_token" \
  -d @abbis_metrics.json
```

---

## ⚠️ **Best Practices**

1. **Store API Keys Securely**
   - Never commit API keys to version control
   - Use environment variables or secret management
   - Rotate API keys regularly

2. **Monitor API Usage**
   - Check "Last Used" timestamp in API Key Management
   - Review rate limit logs
   - Set up alerts for unusual activity

3. **Use HTTPS in Production**
   - API keys sent over HTTP are vulnerable
   - Always use HTTPS for production deployments

4. **Implement Retry Logic**
   - Handle rate limit (429) errors gracefully
   - Implement exponential backoff

5. **Log API Access**
   - Monitor API usage for security
   - Track which endpoints are accessed most

---

## 🛠️ **Troubleshooting**

### **401 Unauthorized:**
- Verify API key is correct
- Check API key is active
- Verify API key hasn't expired
- Ensure `X-API-Key` header is sent

### **429 Rate Limit Exceeded:**
- Reduce request frequency
- Increase rate limit for the API key
- Implement request queuing

### **500 Internal Server Error:**
- Check server logs
- Verify database connectivity
- Check PHP error logs

### **CORS Issues:**
- Verify CORS headers in response
- Check browser console for errors
- Ensure proper `Access-Control-Allow-Origin` header

---

## 📝 **API Key Management**

### **Viewing API Keys:**
- Navigate to **Configuration** → **API Key Management**
- View all keys, their status, last used time, and expiration

### **Revoking API Keys:**
- Click **"Deactivate"** to temporarily disable
- Click **"Delete"** to permanently remove

### **Regenerating Keys:**
- Delete old key
- Generate new key with same name
- Update integration configuration

---

## 🔄 **Future Enhancements**

Potential future additions:
- [ ] Webhook support for real-time notifications
- [ ] OAuth2 authentication
- [ ] GraphQL API
- [ ] API versioning
- [ ] Swagger/OpenAPI documentation
- [ ] Real-time WebSocket connections
- [ ] Batch operations endpoint
- [ ] Custom metric endpoints

---

## 📚 **Additional Resources**

- **Wazuh Documentation**: https://documentation.wazuh.com/
- **API Key Management**: `/modules/api-keys.php`
- **Monitoring API**: `/api/monitoring-api.php`

---

*Last Updated: November 2024*
*ABBIS Version: 3.2.0*


# 🔐 Secure Architecture - No Database Credentials on API Server

## Overview

This is the **NEW SECURE ARCHITECTURE** where:
- ✅ API server **NEVER** has direct database access
- ✅ Database credentials **NEVER** leave WordPress
- ✅ SQL queries are executed in WordPress's own environment
- ✅ API server only stores OpenAI keys and site URLs
- ✅ Each site gets a unique API key for authentication

## Architecture Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                         USER QUERY FLOW                          │
└─────────────────────────────────────────────────────────────────┘

1. User asks question in chatbot
   ↓
2. WordPress sends to API Server (with site's API key)
   POST /api/query
   Authorization: Bearer ht_abc123...
   {
     "question": "Show me today's sales",
     "site_url": "https://myshop.com"
   }
   ↓
3. API Server:
   - Validates API key
   - Gets user's OpenAI key from database
   - Uses OpenAI to generate SQL query
   ↓
4. API Server sends SQL back to WordPress
   POST https://myshop.com/wp-json/heytrisha/v1/execute-sql
   {
     "sql": "SELECT ...",
     "api_key": "ht_abc123..."
   }
   ↓
5. WordPress Plugin:
   - Validates API key
   - Validates SQL (read-only, no DELETE/DROP)
   - Executes SQL on local database
   - Returns results
   ↓
6. API Server receives results
   - Formats into natural language
   - Returns to WordPress
   ↓
7. WordPress shows response in chatbot
```

## Setup Flow (Onboarding)

```
┌─────────────────────────────────────────────────────────────────┐
│                      ONBOARDING FLOW                             │
└─────────────────────────────────────────────────────────────────┘

1. User installs WordPress plugin
   ↓
2. Plugin shows onboarding screen
   Fields:
   - OpenAI API Key
   - Site URL (auto-filled)
   - Email (optional for support)
   ↓
3. User clicks "Register with HeyTrisha API"
   ↓
4. WordPress sends registration to API Server
   POST https://api.heytrisha.com/api/register
   {
     "site_url": "https://myshop.com",
     "openai_key": "sk-...",
     "email": "admin@myshop.com",
     "wordpress_version": "6.4",
     "woocommerce_version": "8.5"
   }
   ↓
5. API Server:
   - Validates OpenAI key
   - Generates unique API key for this site
   - Stores config in database (encrypted)
   - Returns API key
   ↓
6. WordPress Plugin:
   - Saves API key in encrypted storage
   - Marks onboarding as complete
   - Shows success message
```

## Data Storage

### WordPress Plugin Stores:
- ✅ API Server URL (`https://api.heytrisha.com`)
- ✅ Site's unique API key (encrypted)
- ✅ Onboarding status
- ✅ Chat history

### API Server Stores:
- ✅ Site URL
- ✅ OpenAI API key (encrypted)
- ✅ Site's unique API key (hashed)
- ✅ Email (optional)
- ✅ WordPress/WooCommerce versions
- ✅ Usage statistics
- ❌ **NO database credentials**
- ❌ **NO WooCommerce API keys**

## Security Benefits

1. **Zero Trust Database Access**
   - API server never connects to user's database
   - Only WordPress can execute queries

2. **SQL Validation**
   - WordPress validates all SQL before execution
   - Only SELECT queries allowed
   - Blacklist: DELETE, DROP, UPDATE, INSERT, TRUNCATE

3. **Encrypted Storage**
   - OpenAI keys encrypted in API database
   - API keys encrypted in WordPress database
   - All data transmission over HTTPS

4. **API Key Rotation**
   - Users can regenerate API keys anytime
   - Old keys immediately invalidated

5. **Rate Limiting**
   - API server limits requests per API key
   - Prevents abuse

## API Endpoints

### API Server Endpoints

#### 1. Register Site
```
POST /api/register
Content-Type: application/json

{
  "site_url": "https://myshop.com",
  "openai_key": "sk-...",
  "email": "admin@myshop.com"
}

Response:
{
  "success": true,
  "api_key": "ht_abc123...",
  "message": "Site registered successfully"
}
```

#### 2. Process Query
```
POST /api/query
Authorization: Bearer ht_abc123...
Content-Type: application/json

{
  "question": "Show me today's sales",
  "site_url": "https://myshop.com"
}

Response:
{
  "success": true,
  "answer": "Today's sales are $1,234",
  "sql": "SELECT ...",
  "data": [...]
}
```

#### 3. Update Configuration
```
PUT /api/config
Authorization: Bearer ht_abc123...
Content-Type: application/json

{
  "openai_key": "sk-new-key...",
  "email": "newemail@myshop.com"
}

Response:
{
  "success": true,
  "message": "Configuration updated"
}
```

#### 4. Regenerate API Key
```
POST /api/regenerate-key
Authorization: Bearer ht_abc123...

Response:
{
  "success": true,
  "new_api_key": "ht_xyz789...",
  "message": "API key regenerated. Update your WordPress plugin settings."
}
```

### WordPress Plugin Endpoints

#### 1. Execute SQL (Called by API Server)
```
POST /wp-json/heytrisha/v1/execute-sql
X-HeyTrisha-API-Key: ht_abc123...
Content-Type: application/json

{
  "sql": "SELECT * FROM wp_posts WHERE post_type = 'product' LIMIT 10",
  "validate": true
}

Response:
{
  "success": true,
  "data": [...],
  "row_count": 10
}
```

#### 2. Get Schema (Called by API Server)
```
GET /wp-json/heytrisha/v1/schema
X-HeyTrisha-API-Key: ht_abc123...

Response:
{
  "success": true,
  "tables": {
    "wp_posts": {
      "columns": [...],
      "row_count": 1234
    },
    ...
  }
}
```

## Implementation Checklist

### WordPress Plugin

- [ ] Create onboarding screen
- [ ] REST endpoint: `/execute-sql` (validates and runs SQL)
- [ ] REST endpoint: `/schema` (returns database schema)
- [ ] Function: `register_with_api_server()`
- [ ] Function: `update_api_server_config()`
- [ ] SQL validation (whitelist SELECT, blacklist dangerous keywords)
- [ ] API key validation middleware
- [ ] Settings page integration

### API Server

- [ ] Database table: `sites` (store site configs)
- [ ] Endpoint: `POST /api/register`
- [ ] Endpoint: `POST /api/query`
- [ ] Endpoint: `PUT /api/config`
- [ ] Endpoint: `POST /api/regenerate-key`
- [ ] Middleware: Validate API keys
- [ ] Service: OpenAI integration
- [ ] Service: Call WordPress REST API
- [ ] Encryption for OpenAI keys
- [ ] Rate limiting per API key

## Migration from Old Architecture

If you have existing .env-based setup:

1. Run migration script to move configs to database
2. Generate API keys for existing sites
3. Update WordPress plugins with new API keys
4. Remove .env file (keep only APP_KEY)

## Development vs Production

### Development
- API Server: `http://localhost:8000`
- WordPress: `http://localhost/mysite`
- Debug mode enabled

### Production
- API Server: `https://api.heytrisha.com`
- WordPress: `https://myshop.com`
- Debug mode disabled
- HTTPS required
- Rate limiting enabled



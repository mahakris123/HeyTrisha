# 🧪 Testing Guide - New Secure Architecture

## Overview

This guide provides step-by-step testing procedures for the new secure architecture.

## Prerequisites

- API server deployed and running
- WordPress plugin installed and activated
- OpenAI API key configured
- Site registered with API server

## Test Suite

### 1. API Server Health Checks

#### Test 1.1: Basic Health Check

```bash
curl https://api.heytrisha.com/api/health
```

**Expected Response:**
```json
{
  "status": "ok",
  "timestamp": "2026-01-22T...",
  "laravel_version": "10.x.x",
  "php_version": "8.1.x",
  "database_connected": true,
  "app_key_set": true,
  "storage_writable": true
}
```

✅ **Pass Criteria:** All values should be `true` or show expected versions.

#### Test 1.2: Diagnostic Check

```bash
curl https://api.heytrisha.com/api/diagnostic
```

**Expected Response:**
```json
{
  "php_version": "8.1.x",
  "laravel_version": "10.x.x",
  "database_connected": true,
  "sites_table_exists": true,
  ...
}
```

✅ **Pass Criteria:** `sites_table_exists` should be `true`.

### 2. Site Registration

#### Test 2.1: Register New Site

```bash
curl -X POST https://api.heytrisha.com/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "site_url": "https://test-site.com",
    "openai_key": "sk-test-key-here",
    "email": "test@example.com"
  }'
```

**Expected Response:**
```json
{
  "success": true,
  "api_key": "ht_abc123...",
  "message": "Site registered successfully..."
}
```

✅ **Pass Criteria:**
- `success` is `true`
- `api_key` starts with `ht_`
- API key is 67 characters long (`ht_` + 64 hex chars)

**Important:** Save the API key! It's shown only once.

#### Test 2.2: Duplicate Registration

Re-run the same registration request.

**Expected Response:**
```json
{
  "success": true,
  "api_key": "ht_xyz789...",
  "message": "Site re-registered successfully. Previous API key has been invalidated."
}
```

✅ **Pass Criteria:**
- New API key is different from the first one
- Old API key should no longer work

### 3. WordPress Plugin

#### Test 3.1: WordPress Health Check

```bash
curl https://yoursite.com/wp-json/heytrisha/v1/health
```

**Expected Response:**
```json
{
  "success": true,
  "status": "ok",
  "wordpress_version": "6.4.x",
  "woocommerce_version": "8.x.x",
  "onboarding_complete": true,
  "has_api_key": true,
  "database_connected": true
}
```

✅ **Pass Criteria:** All checks should pass.

#### Test 3.2: Get Schema (Authenticated)

```bash
curl -H "X-HeyTrisha-API-Key: ht_your_api_key_here" \
     https://yoursite.com/wp-json/heytrisha/v1/schema
```

**Expected Response:**
```json
{
  "success": true,
  "tables": {
    "wp_posts": {
      "columns": [...],
      "row_count": 123
    },
    ...
  },
  "table_count": 15
}
```

✅ **Pass Criteria:**
- Returns list of tables
- Tables have `columns` and `row_count`
- Only tables with `wp_` prefix included

#### Test 3.3: Execute SQL (Authenticated)

```bash
curl -X POST \
  -H "X-HeyTrisha-API-Key: ht_your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{"sql": "SELECT ID, post_title FROM wp_posts LIMIT 5"}' \
  https://yoursite.com/wp-json/heytrisha/v1/execute-sql
```

**Expected Response:**
```json
{
  "success": true,
  "data": [
    {"ID": "1", "post_title": "Hello World"},
    ...
  ],
  "row_count": 5,
  "sql": "SELECT ID, post_title FROM wp_posts LIMIT 5"
}
```

✅ **Pass Criteria:**
- Query executes successfully
- Returns expected data
- Row count matches

### 4. SQL Validation

#### Test 4.1: Valid SELECT Query

```bash
curl -X POST \
  -H "X-HeyTrisha-API-Key: ht_your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{"sql": "SELECT * FROM wp_users LIMIT 1"}' \
  https://yoursite.com/wp-json/heytrisha/v1/execute-sql
```

✅ **Pass Criteria:** Query executes successfully.

#### Test 4.2: Blocked DELETE Query

```bash
curl -X POST \
  -H "X-HeyTrisha-API-Key: ht_your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{"sql": "DELETE FROM wp_posts WHERE ID = 1"}' \
  https://yoursite.com/wp-json/heytrisha/v1/execute-sql
```

**Expected Response:**
```json
{
  "code": "invalid_sql",
  "message": "SQL validation failed: Dangerous keyword detected: DELETE",
  "data": {"status": 400}
}
```

✅ **Pass Criteria:** Query is blocked with error message.

#### Test 4.3: Blocked DROP Query

```bash
curl -X POST \
  -H "X-HeyTrisha-API-Key: ht_your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{"sql": "DROP TABLE wp_posts"}' \
  https://yoursite.com/wp-json/heytrisha/v1/execute-sql
```

✅ **Pass Criteria:** Query is blocked.

#### Test 4.4: Blocked UPDATE Query

```bash
curl -X POST \
  -H "X-HeyTrisha-API-Key: ht_your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{"sql": "UPDATE wp_posts SET post_title = 'Hacked'"}' \
  https://yoursite.com/wp-json/heytrisha/v1/execute-sql
```

✅ **Pass Criteria:** Query is blocked.

### 5. End-to-End Query Flow

#### Test 5.1: Simple Question

```bash
curl -X POST \
  -H "Authorization: Bearer ht_your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{"question": "How many posts do I have?"}' \
  https://api.heytrisha.com/api/query
```

**Expected Response:**
```json
{
  "success": true,
  "answer": "I found X results for your question.",
  "sql": "SELECT COUNT(*) as count FROM wp_posts WHERE post_status = 'publish'",
  "data": [...],
  "row_count": 1
}
```

✅ **Pass Criteria:**
- OpenAI generates valid SQL
- WordPress executes SQL successfully
- Returns formatted answer

#### Test 5.2: Product Query (WooCommerce)

```bash
curl -X POST \
  -H "Authorization: Bearer ht_your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{"question": "Show me my 5 latest products"}' \
  https://api.heytrisha.com/api/query
```

✅ **Pass Criteria:** Returns product data.

### 6. Authentication & Authorization

#### Test 6.1: Missing API Key

```bash
curl -X POST \
  -H "Content-Type: application/json" \
  -d '{"question": "test"}' \
  https://api.heytrisha.com/api/query
```

**Expected Response:**
```json
{
  "success": false,
  "message": "API key is required..."
}
```

✅ **Pass Criteria:** Returns 401 Unauthorized.

#### Test 6.2: Invalid API Key

```bash
curl -X POST \
  -H "Authorization: Bearer ht_invalid_key" \
  -H "Content-Type: application/json" \
  -d '{"question": "test"}' \
  https://api.heytrisha.com/api/query
```

**Expected Response:**
```json
{
  "success": false,
  "message": "Invalid or inactive API key..."
}
```

✅ **Pass Criteria:** Returns 403 Forbidden.

### 7. Configuration Management

#### Test 7.1: Update OpenAI Key

```bash
curl -X PUT \
  -H "Authorization: Bearer ht_your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{"openai_key": "sk-new-key-here"}' \
  https://api.heytrisha.com/api/config
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Configuration updated successfully"
}
```

✅ **Pass Criteria:** Configuration updates successfully.

#### Test 7.2: Regenerate API Key

```bash
curl -X POST \
  -H "Authorization: Bearer ht_your_old_api_key" \
  https://api.heytrisha.com/api/regenerate-key
```

**Expected Response:**
```json
{
  "success": true,
  "new_api_key": "ht_new_key_here...",
  "message": "API key regenerated successfully..."
}
```

✅ **Pass Criteria:**
- New API key is returned
- Old API key no longer works
- New API key works immediately

#### Test 7.3: Get Site Info

```bash
curl -H "Authorization: Bearer ht_your_api_key_here" \
     https://api.heytrisha.com/api/site/info
```

**Expected Response:**
```json
{
  "success": true,
  "site": {
    "site_url": "https://yoursite.com",
    "email": "your@email.com",
    "query_count": 42,
    "last_query_at": "2026-01-22...",
    "registered_at": "2026-01-20..."
  }
}
```

✅ **Pass Criteria:** Returns site information.

## Automated Testing Script

Save this as `test-heytrisha.sh`:

```bash
#!/bin/bash

# Configuration
API_URL="https://api.heytrisha.com"
WP_URL="https://yoursite.com"
API_KEY="ht_your_api_key_here"

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Test counter
PASSED=0
FAILED=0

test_endpoint() {
    local name=$1
    local url=$2
    local expected_status=$3
    
    echo -n "Testing $name... "
    
    status=$(curl -s -o /dev/null -w "%{http_code}" "$url")
    
    if [ "$status" == "$expected_status" ]; then
        echo -e "${GREEN}✓ PASSED${NC} (HTTP $status)"
        ((PASSED++))
    else
        echo -e "${RED}✗ FAILED${NC} (Expected $expected_status, got $status)"
        ((FAILED++))
    fi
}

echo "🧪 Testing HeyTrisha API..."
echo "========================================"

# API Server Tests
test_endpoint "API Health" "$API_URL/api/health" "200"
test_endpoint "API Diagnostic" "$API_URL/api/diagnostic" "200"

# WordPress Tests
test_endpoint "WordPress Health" "$WP_URL/wp-json/heytrisha/v1/health" "200"

echo "========================================"
echo "Results: ${GREEN}$PASSED passed${NC}, ${RED}$FAILED failed${NC}"

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ All tests passed!${NC}"
    exit 0
else
    echo -e "${RED}✗ Some tests failed${NC}"
    exit 1
fi
```

Run with:
```bash
chmod +x test-heytrisha.sh
./test-heytrisha.sh
```

## Performance Testing

### Test Response Times

```bash
# API Server Response Time
time curl -s https://api.heytrisha.com/api/health > /dev/null

# WordPress Response Time
time curl -s https://yoursite.com/wp-json/heytrisha/v1/health > /dev/null

# Full Query Response Time
time curl -s -X POST \
  -H "Authorization: Bearer ht_your_api_key" \
  -H "Content-Type: application/json" \
  -d '{"question":"test"}' \
  https://api.heytrisha.com/api/query > /dev/null
```

✅ **Pass Criteria:**
- API health: < 200ms
- WordPress health: < 500ms
- Full query: < 5 seconds (depends on OpenAI response time)

## Troubleshooting

If tests fail, check:

1. **API Server Logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **WordPress Debug Log:**
   - Enable: `define('WP_DEBUG_LOG', true);` in `wp-config.php`
   - Check: `wp-content/debug.log`

3. **Network Connectivity:**
   ```bash
   curl -v https://api.heytrisha.com/api/health
   curl -v https://yoursite.com/wp-json/heytrisha/v1/health
   ```

4. **Database Connection:**
   ```bash
   # On API server
   php artisan tinker
   > DB::connection()->getPdo();
   ```

## Test Checklist

Before deploying to production:

- [ ] All API health checks pass
- [ ] WordPress health checks pass
- [ ] Site registration works
- [ ] SQL validation blocks dangerous queries
- [ ] Valid SELECT queries execute successfully
- [ ] End-to-end query flow works
- [ ] Invalid API keys are rejected
- [ ] API key regeneration works
- [ ] Configuration updates work
- [ ] Response times are acceptable
- [ ] Error handling works correctly
- [ ] Logs show no errors

## Success Criteria

All tests should pass with:
- ✅ No 500 errors
- ✅ Proper authentication
- ✅ SQL validation working
- ✅ End-to-end flow complete
- ✅ Response times acceptable

If all tests pass, the implementation is ready for production! 🎉



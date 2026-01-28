# 📊 Database Schema Communication Flow

## Complete Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    WordPress Plugin                         │
│                                                             │
│  1. User types: "Show me all orders"                        │
│  2. JavaScript → WordPress AJAX handler                     │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│         heytrisha_ajax_query_handler()                      │
│                                                             │
│  ✅ Fetches database schema:                               │
│     $schema = heytrisha_get_database_schema()              │
│                                                             │
│  Schema format:                                             │
│  {                                                          │
│    "wp_posts": ["ID", "post_title", ...],                  │
│    "wp_postmeta": ["meta_id", "post_id", ...],              │
│    "wp_woocommerce_order_items": [...],                     │
│    ...                                                      │
│  }                                                          │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       │ POST https://api.heytrisha.com/api/query
                       │ Headers: Authorization: Bearer {API_KEY}
                       │ Body: { question, site, context, schema }
                       ▼
┌─────────────────────────────────────────────────────────────┐
│              API Server (api.heytrisha.com)                 │
│                                                             │
│  1. ValidateApiKey middleware:                              │
│     - Validates API key                                     │
│     - Adds site_url to request                              │
│                                                             │
│  2. NLPController::query():                                 │
│     - Receives: question, site, context, schema              │
│     - Uses schema to understand database structure          │
│     - Generates SQL using OpenAI                            │
│     - Returns formatted response                            │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       │ JSON Response
                       │ { success, message, data }
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                    WordPress Plugin                         │
│                                                             │
│  ✅ Receives response                                       │
│  ✅ Displays in chat interface                              │
│  ✅ Formats data as table/list                              │
└─────────────────────────────────────────────────────────────┘
```

---

## 📤 Request Payload Example

### What WordPress Plugin Sends

```json
{
  "question": "Show me all orders from last week",
  "site": "https://example.com",
  "context": "woocommerce",
  "schema": {
    "wp_posts": [
      "ID",
      "post_author",
      "post_date",
      "post_date_gmt",
      "post_content",
      "post_title",
      "post_excerpt",
      "post_status",
      "post_type",
      "post_name",
      "post_parent",
      "post_mime_type"
    ],
    "wp_postmeta": [
      "meta_id",
      "post_id",
      "meta_key",
      "meta_value"
    ],
    "wp_users": [
      "ID",
      "user_login",
      "user_pass",
      "user_nicename",
      "user_email",
      "user_url",
      "user_registered",
      "user_status",
      "display_name"
    ],
    "wp_woocommerce_order_items": [
      "order_item_id",
      "order_item_name",
      "order_item_type",
      "order_id"
    ],
    "wp_woocommerce_order_itemmeta": [
      "meta_id",
      "order_item_id",
      "meta_key",
      "meta_value"
    ],
    "wp_terms": [
      "term_id",
      "name",
      "slug",
      "term_group"
    ],
    "wp_term_taxonomy": [
      "term_taxonomy_id",
      "term_id",
      "taxonomy",
      "description",
      "parent",
      "count"
    ],
    "wp_term_relationships": [
      "object_id",
      "term_taxonomy_id",
      "term_order"
    ]
  }
}
```

---

## 📥 API Server Processing

### Step 1: Receive Schema

```php
$schema = $request->input('schema');

// Schema is now available as:
// $schema['wp_posts'] = ['ID', 'post_author', ...]
// $schema['wp_postmeta'] = ['meta_id', 'post_id', ...]
```

### Step 2: Use Schema for SQL Generation

```php
// Convert schema to string format for OpenAI
$schemaString = '';
foreach ($schema as $table => $columns) {
    $schemaString .= "$table(" . implode(',', $columns) . ")\n";
}

// Send to OpenAI with question
$prompt = "Database Schema:\n$schemaString\n\nUser Query: $question\n\nGenerate SQL query:";
```

### Step 3: Generate SQL

```php
$sqlQuery = $this->generateSQLWithOpenAI($question, $schemaString);
// Returns: "SELECT * FROM wp_posts WHERE post_type = 'shop_order' AND post_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
```

### Step 4: Return Response

```php
return response()->json([
    'success' => true,
    'message' => 'Found 5 orders from last week',
    'data' => [
        ['id' => 123, 'date' => '2026-01-15', 'total' => '99.00'],
        ['id' => 124, 'date' => '2026-01-16', 'total' => '149.00']
    ]
]);
```

---

## 🎨 Response Display in WordPress

### JavaScript Receives Response

```javascript
const result = await response.json();

// result = {
//   success: true,
//   message: "Found 5 orders from last week",
//   data: [
//     { id: 123, date: "2026-01-15", total: "99.00" },
//     { id: 124, date: "2026-01-16", total: "149.00" }
//   ]
// }
```

### Display Logic

```javascript
if (result.success) {
    // Add bot message
    addMessage('bot', result.message);
    
    // Display data if present
    if (result.data && Array.isArray(result.data)) {
        if (result.data.length > 0) {
            // Format as table
            displayDataAsTable(result.data);
        } else {
            addMessage('bot', 'No results found.');
        }
    }
} else {
    // Display error
    addMessage('bot', result.message, 'error');
}
```

---

## 🔍 Debugging Tips

### 1. Check Schema is Fetched

Add to `heytrisha_get_database_schema()`:
```php
error_log('Hey Trisha: Schema fetched - ' . count($schema) . ' tables');
```

### 2. Check Schema is Sent

Add to `heytrisha_ajax_query_handler()`:
```php
error_log('Hey Trisha: Request body size - ' . strlen(wp_json_encode($request_body)) . ' bytes');
error_log('Hey Trisha: Schema tables - ' . count($schema));
```

### 3. Check API Receives Schema

In Laravel controller:
```php
Log::info('Schema received', [
    'tables_count' => count($schema),
    'first_table' => array_key_first($schema),
    'first_table_columns' => $schema[array_key_first($schema)] ?? []
]);
```

### 4. Test with Postman

Use the example request from `API-TESTING-GUIDE.md` to test your API server directly.

---

## ✅ Summary

**Current Status:**
- ✅ Plugin fetches schema automatically
- ✅ Schema included in every API request
- ✅ Response handling works
- ✅ Display logic implemented

**What You Need:**
- ✅ API server receives `schema` parameter
- ✅ API server uses schema for SQL generation
- ✅ API server returns formatted response

**Everything is ready on the WordPress side!** Just implement the API server to process the schema.



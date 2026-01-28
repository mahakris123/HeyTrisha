# 🔐 New Secure Implementation Guide

## Critical Change: Database Credentials NEVER Leave WordPress

This is the implementation guide for the new secure architecture where:
- Database credentials stay in WordPress
- SQL queries are executed by WordPress, not API server
- API server only stores OpenAI keys
- Each site gets a unique API key

## Summary of Changes

Given the task complexity (800+ lines of code), I'll provide a summary and you can approve before I implement everything.

## WordPress Plugin Changes

### 1. Onboarding Screen ✅ DONE
- Shows when no API key is configured
- Fields: OpenAI API Key, Site URL, Email
- Registers site with API server
- Saves returned API key

### 2. Settings Save Handlers ✅ DONE
- `heytrisha_handle_onboarding_registration()` - Handles initial registration
- `heytrisha_handle_settings_update()` - Syncs changes with API server
- `heytrisha_handle_reset_onboarding()` - Resets to onboarding screen

### 3. REST Endpoints (TO DO)
Need to add these endpoints:

```php
/**
 * Execute SQL query (called by API server)
 * POST /wp-json/heytrisha/v1/execute-sql
 */
register_rest_route('heytrisha/v1', '/execute-sql', [
    'methods' => 'POST',
    'callback' => 'heytrisha_rest_execute_sql',
    'permission_callback' => 'heytrisha_validate_api_key',
]);

/**
 * Get database schema (called by API server)
 * GET /wp-json/heytrisha/v1/schema
 */
register_rest_route('heytrisha/v1', '/schema', [
    'methods' => 'GET',
    'callback' => 'heytrisha_rest_get_schema',
    'permission_callback' => 'heytrisha_validate_api_key',
]);
```

### 4. SQL Validation Class (TO DO)
```php
class HeyTrisha_SQL_Validator {
    public static function validate_sql($sql) {
        // Only allow SELECT
        if (!preg_match('/^\\s*SELECT\\s+/i', $sql)) {
            return false;
        }
        
        // Blacklist dangerous keywords
        $dangerous = ['DELETE', 'DROP', 'TRUNCATE', 'ALTER', 'CREATE', 'GRANT', 'REVOKE'];
        foreach ($dangerous as $keyword) {
            if (stripos($sql, $keyword) !== false) {
                return false;
            }
        }
        
        return true;
    }
}
```

## API Server Changes

### 1. Database Migration (TO DO)
Create `sites` table:

```php
Schema::create('sites', function (Blueprint $table) {
    $table->id();
    $table->string('site_url')->unique();
    $table->string('api_key', 64)->unique();
    $table->text('openai_key'); // Encrypted
    $table->string('email')->nullable();
    $table->string('wordpress_version')->nullable();
    $table->string('woocommerce_version')->nullable();
    $table->string('plugin_version')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### 2. Registration Endpoint (TO DO)
```php
Route::post('/api/register', [SiteController::class, 'register']);

// In SiteController:
public function register(Request $request) {
    $validated = $request->validate([
        'site_url' => 'required|url|unique:sites',
        'openai_key' => 'required|string',
        'email' => 'nullable|email',
    ]);
    
    // Generate unique API key
    $api_key = 'ht_' . bin2hex(random_bytes(32));
    
    // Encrypt OpenAI key
    $encrypted_openai_key = encrypt($validated['openai_key']);
    
    // Save to database
    $site = Site::create([
        'site_url' => $validated['site_url'],
        'api_key' => hash('sha256', $api_key), // Store hashed
        'openai_key' => $encrypted_openai_key,
        'email' => $validated['email'] ?? null,
    ]);
    
    return response()->json([
        'success' => true,
        'api_key' => $api_key, // Return plain text ONCE
        'message' => 'Site registered successfully'
    ]);
}
```

### 3. Updated Query Endpoint (TO DO)
```php
Route::post('/api/query', [QueryController::class, 'process']);

// In QueryController:
public function process(Request $request) {
    // 1. Get site from API key
    $site = Site::where('api_key', hash('sha256', $request->bearerToken()))->first();
    
    // 2. Get OpenAI key
    $openai_key = decrypt($site->openai_key);
    
    // 3. Use OpenAI to generate SQL
    $sql = $this->generateSQL($request->question, $openai_key);
    
    // 4. Send SQL to WordPress for execution
    $response = Http::withHeaders([
        'X-HeyTrisha-API-Key' => $request->bearerToken()
    ])->post($site->site_url . '/wp-json/heytrisha/v1/execute-sql', [
        'sql' => $sql
    ]);
    
    // 5. Return formatted response
    return $this->formatResponse($response->json());
}
```

## Implementation Steps

I recommend implementing in this order:

1. ✅ **DONE**: Onboarding screen in WordPress
2. **NEXT**: SQL validation class
3. **NEXT**: WordPress REST endpoints
4. **NEXT**: API server database migration
5. **NEXT**: API server registration endpoint
6. **NEXT**: Update API server query endpoint
7. **NEXT**: Test end-to-end flow
8. **NEXT**: Build scripts update

## Estimated Code Changes

- WordPress Plugin: ~400 lines
- API Server: ~500 lines
- Total: ~900 lines of new/modified code

## Deployment Plan

1. Deploy API server with new database tables
2. Deploy WordPress plugin updates
3. Existing users: Migration script or manual re-onboarding
4. New users: Automatic onboarding flow

## Security Improvements

### Before (Old Architecture)
- ❌ Database credentials in .env file
- ❌ API server has direct database access
- ❌ Credentials can leak if server compromised

### After (New Architecture)
- ✅ Database credentials only in WordPress
- ✅ API server only stores OpenAI keys (encrypted)
- ✅ SQL validated before execution
- ✅ Read-only queries enforced
- ✅ Zero trust architecture

## Next Steps

**OPTION 1: Full Implementation**
I can implement all ~900 lines of code now. This will take approximately 15-20 tool calls.

**OPTION 2: Step-by-Step**
I implement one component at a time, you review and approve each step.

**OPTION 3: Prototype First**
I create a working prototype with minimal features, then expand.

Which approach do you prefer?

Also, do you want me to:
1. Keep the existing `.env`-based system as fallback?
2. Or completely replace it with the new architecture?
3. Or support both (database-first, .env as fallback)?



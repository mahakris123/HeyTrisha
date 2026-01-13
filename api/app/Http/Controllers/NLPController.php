<?php

// Fetching Working Code 02/01/2025 12:00 PM

// namespace App\Http\Controllers;

// use App\Services\SQLGeneratorService;
// use App\Services\MySQLService;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Log;

// class NLPController extends Controller
// {
//     protected $sqlGenerator;
//     protected $mysqlService;

//     public function __construct(SQLGeneratorService $sqlGenerator, MySQLService $mysqlService)
//     {
//         $this->sqlGenerator = $sqlGenerator;
//         $this->mysqlService = $mysqlService;
//     }

//     public function handleQuery(Request $request)
//     {
//         $userQuery = $request->input('query');

//         try {
//             // ✅ Get the database schema from MySQLService
//             $schema = $this->mysqlService->getCompactSchema();

//             // ✅ Generate the SQL query using OpenAI
//             $queryResponse = $this->sqlGenerator->queryChatGPTForSQL($userQuery, $schema);

//             // ✅ Ensure OpenAI returned a valid query
//             if (isset($queryResponse['error'])) {
//                 return response()->json(['success' => false, 'message' => $queryResponse['error']], 500);
//             }

//             $sqlQuery = $queryResponse['query'];

//             // ✅ Execute the SQL query
//             $result = $this->mysqlService->executeSQLQuery($sqlQuery);

//             // ✅ Return the result in JSON format
//             return response()->json([
//                 'success' => true,
//                 'data' => $result,
//                 'query' => $sqlQuery
//             ]);
//         } catch (\Exception $e) {
//             Log::error("Error handling user query: " . $e->getMessage());
//             return response()->json([
//                 'success' => false,
//                 'message' => $e->getMessage()
//             ]);
//         }
//     }
// }

// Working Fine code 2nd version

namespace App\Http\Controllers;

use App\Services\SQLGeneratorService;
use App\Services\MySQLService;
use App\Services\WordPressApiService;
use App\Services\WordPressRequestGeneratorService; // ✅ Add new service
use App\Services\PostProductSearchService; // ✅ Add search service
use App\Services\WordPressConfigService; // ✅ Add config service
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB; // ✅ Add DB facade for database queries

class NLPController extends Controller
{
    protected $sqlGenerator;
    protected $mysqlService;
    protected $wordpressApiService;
    protected $wordpressRequestGeneratorService;
    protected $postProductSearchService;
    protected $configService;

    public function __construct(
        SQLGeneratorService $sqlGenerator,
        MySQLService $mysqlService,
        WordPressApiService $wordpressApiService,
        WordPressRequestGeneratorService $wordpressRequestGeneratorService,
        PostProductSearchService $postProductSearchService,
        WordPressConfigService $configService // ✅ Inject config service
    ) {
        $this->sqlGenerator = $sqlGenerator;
        $this->mysqlService = $mysqlService;
        $this->wordpressApiService = $wordpressApiService;
        $this->wordpressRequestGeneratorService = $wordpressRequestGeneratorService;
        $this->postProductSearchService = $postProductSearchService;
        $this->configService = $configService;
    }
    
    /**
     * Load WordPress security filter class if available
     * @return bool True if class is loaded and available
     */
    private function loadSecurityFilter() {
        // Use global namespace to prevent Laravel autoloader issues
        $securityFilterClass = '\\HeyTrisha_Security_Filter';
        
        // If already loaded, return true
        if (class_exists($securityFilterClass)) {
            return true;
        }
        
        // Try multiple path calculation methods for reliability
        $possible_paths = array();
        
        // Method 1: Calculate from current file location
        // NLPController.php is at: plugin/api/app/Http/Controllers/NLPController.php
        // We need: plugin/includes/class-heytrisha-security-filter.php
        // So: go up 4 levels from Controllers to plugin root
        $current_dir = __DIR__; // Controllers directory
        $plugin_root = dirname(dirname(dirname(dirname($current_dir))));
        $possible_paths[] = $plugin_root . '/includes/class-heytrisha-security-filter.php';
        
        // Method 2: Use ABSPATH if defined (WordPress root)
        if (defined('ABSPATH')) {
            $plugins_dir = ABSPATH . 'wp-content/plugins/';
            
            // Try exact name first
            $possible_paths[] = $plugins_dir . 'heytrisha-woo/includes/class-heytrisha-security-filter.php';
            
            // Try versioned directories using glob
            $versioned_dirs = glob($plugins_dir . 'heytrisha-woo-v*');
            if (!empty($versioned_dirs)) {
                foreach ($versioned_dirs as $versioned_dir) {
                    if (is_dir($versioned_dir)) {
                        $possible_paths[] = $versioned_dir . '/includes/class-heytrisha-security-filter.php';
                    }
                }
            }
        }
        
        // Method 3: Try to find plugin directory by looking for main plugin file
        // Go up from Controllers: Controllers -> Http -> app -> api -> plugin
        $plugin_base = dirname(dirname(dirname(dirname(__DIR__))));
        
        // Check if we're in the right place (should have api directory)
        if (file_exists($plugin_base . '/api') || file_exists($plugin_base . '/heytrisha-woo.php')) {
            $possible_paths[] = $plugin_base . '/includes/class-heytrisha-security-filter.php';
        }
        
        // Method 4: Try to find by searching for the main plugin file
        // Look in parent directories for heytrisha-woo.php
        $search_dir = dirname(dirname(dirname(dirname(__DIR__))));
        $max_levels = 3;
        $level = 0;
        while ($level < $max_levels && $search_dir !== '/' && $search_dir !== '') {
            if (file_exists($search_dir . '/heytrisha-woo.php')) {
                $possible_paths[] = $search_dir . '/includes/class-heytrisha-security-filter.php';
                break;
            }
            $search_dir = dirname($search_dir);
            $level++;
        }
        
        // Remove duplicates and empty paths
        $possible_paths = array_unique(array_filter($possible_paths));
        
        // Try each path until we find the file
        foreach ($possible_paths as $security_filter_path) {
            // Normalize path
            $security_filter_path = str_replace('\\', '/', $security_filter_path);
            
            if (file_exists($security_filter_path) && is_readable($security_filter_path)) {
                try {
                    // Define LARAVEL_START to allow loading from Laravel context
                    if (!defined('LARAVEL_START')) {
                        define('LARAVEL_START', microtime(true));
                    }
                    
                    // Suppress errors during require to prevent fatal errors
                    $old_error_reporting = error_reporting();
                    error_reporting($old_error_reporting & ~E_WARNING & ~E_NOTICE);
                    
                    require_once $security_filter_path;
                    
                    error_reporting($old_error_reporting);
                    
                    // Verify class exists and has required methods (use global namespace)
                    $securityFilterClass = '\\HeyTrisha_Security_Filter';
                    if (class_exists($securityFilterClass)) {
                        // Check if required methods exist
                        $required_methods = array('is_sensitive_query', 'is_sensitive_sql', 'filter_sensitive_results', 'get_rejection_message');
                        $all_methods_exist = true;
                        foreach ($required_methods as $method) {
                            if (!method_exists($securityFilterClass, $method)) {
                                $all_methods_exist = false;
                                Log::warning("Security filter class loaded but method '{$method}' not found");
                                break;
                            }
                        }
                        
                        if ($all_methods_exist) {
                            return true;
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to load security filter from ' . $security_filter_path . ': ' . $e->getMessage());
                    continue;
                } catch (\Error $e) {
                    Log::warning('Fatal error loading security filter from ' . $security_filter_path . ': ' . $e->getMessage());
                    continue;
                }
            }
        }
        
        return false;
    }

    public function handleQuery(Request $request)
    {
        // ✅ Try multiple methods to get the query (Laravel Request can have data in different places)
        $userQuery = null;
        
        // Method 1: Try request->request->get() (POST data bag)
        if ($request->request->has('query')) {
            $userQuery = $request->request->get('query');
        }
        
        // Method 2: Try input() (works for form data and JSON, checks multiple sources)
        if (empty($userQuery)) {
            $userQuery = $request->input('query');
        }
        
        // Method 3: Try json() if it's JSON request
        if (empty($userQuery) && $request->isJson()) {
            $jsonData = $request->json()->all();
            $userQuery = isset($jsonData['query']) ? $jsonData['query'] : null;
        }
        
        // Method 4: Try all() and check for query
        if (empty($userQuery)) {
            $allData = $request->all();
            $userQuery = isset($allData['query']) ? $allData['query'] : null;
        }
        
        // Method 5: Try get() (for query parameters)
        if (empty($userQuery)) {
            $userQuery = $request->get('query');
        }
        
        // Method 6: Try request() helper
        if (empty($userQuery)) {
            $userQuery = request('query');
        }
        
        // Method 7: Try direct access to request bag
        if (empty($userQuery) && method_exists($request, 'get')) {
            $userQuery = $request->get('query');
        }
        
        // Log what we received for debugging
        Log::info("📥 Request Debug - request->request->get('query'): " . var_export($request->request->get('query'), true));
        Log::info("📥 Request Debug - input('query'): " . var_export($request->input('query'), true));
        Log::info("📥 Request Debug - all(): " . json_encode($request->all()));
        Log::info("📥 Request Debug - request->request->all(): " . json_encode($request->request->all()));
        Log::info("📥 Request Debug - json()->all(): " . ($request->isJson() ? json_encode($request->json()->all()) : 'not JSON'));
        Log::info("📥 Request Debug - Content-Type: " . ($request->header('Content-Type') ?? 'not set'));
        Log::info("📥 Request Debug - Method: " . $request->method());
        
        $isConfirmed = $request->input('confirmed', false);
        $confirmationData = $request->input('confirmation_data', null);
        
        // ✅ Validate query is not empty
        if (empty($userQuery) || !is_string($userQuery) || trim($userQuery) === '') {
            Log::warning("⚠️ Empty or invalid query received - userQuery: " . var_export($userQuery, true));
            Log::warning("⚠️ Request data dump: " . json_encode([
                'input_query' => $request->input('query'),
                'all' => $request->all(),
                'json' => $request->isJson() ? $request->json()->all() : null,
                'method' => $request->method(),
                'content_type' => $request->header('Content-Type'),
            ]));
            return response()->json([
                'success' => false,
                'message' => 'Please provide a valid query.'
            ], 400);
        }
        
        // ✅ Normalize query (trim whitespace)
        $userQuery = trim($userQuery);
        
        Log::info("📥 Received query: '{$userQuery}'");

        try {
            // 🚨 CRITICAL SECURITY: Check for sensitive data queries FIRST
            // Load WordPress security filter if available
            $securityFilterLoaded = false;
            $isSensitiveQuery = false;
            
            try {
                // Use global namespace class name to prevent Laravel autoloader issues
                $securityFilterClass = '\\HeyTrisha_Security_Filter';
                
                if ($this->loadSecurityFilter() && class_exists($securityFilterClass)) {
                    $securityFilterLoaded = true;
                    // Verify the method exists before calling
                    if (method_exists($securityFilterClass, 'is_sensitive_query')) {
                        // Use call_user_func to avoid namespace resolution issues
                        $isSensitiveQuery = call_user_func(array($securityFilterClass, 'is_sensitive_query'), $userQuery);
                        Log::info("🔒 Security Filter Check - Query: '{$userQuery}' | IsSensitive: " . ($isSensitiveQuery ? 'true' : 'false'));
                        
                        if ($isSensitiveQuery) {
                            // Verify get_rejection_message exists
                            $rejection_msg = method_exists($securityFilterClass, 'get_rejection_message') 
                                ? call_user_func(array($securityFilterClass, 'get_rejection_message'))
                                : "I'm designed to help with data analytics and insights, but I can't access or display sensitive personal information like passwords, emails, or contact details. This protects user privacy and security.";
                            
                            Log::warning("🚨 BLOCKED sensitive query: '{$userQuery}'");
                            
                            return response()->json([
                                'success' => true,
                                'data' => null,
                                'message' => $rejection_msg,
                                'sql_query' => null
                            ]);
                        }
                    }
                }
            } catch (\Exception $e) {
                // If security filter fails, log but continue processing
                Log::warning('Security filter error: ' . $e->getMessage());
                // Continue with normal processing
            } catch (\Error $e) {
                // Catch fatal errors too
                Log::warning('Security filter fatal error: ' . $e->getMessage());
                // Continue with normal processing
            }
            
            // ✅ Fallback security check if security filter class failed to load
            // This ensures we still block sensitive queries even if the class can't be loaded
            if (!$securityFilterLoaded) {
                $fallbackSensitivePatterns = array(
                    '/\b(password|passwd|pwd|pass|credentials)\b/i',
                    '/\b[\w\.-]+@[\w\.-]+\.\w+\s+(password|credentials)\b/i',
                    '/\b(password|credentials)\s+(for|of|to)\s+[\w\.-]+@[\w\.-]+\.\w+\b/i',
                    '/\b(give|get|show|tell|provide|share)\s+(.*\s+)?(password|credentials)\b/i',
                    '/\bcan\s+(you\s+)?(give|get|show|tell|provide|share)\s+(.*\s+)?(password|credentials)\b/i',
                    '/\buser\s+credentials\b/i',
                    '/\bcredentials\s+(for|of|to)\s+.*@.*\./i',
                );
                
                foreach ($fallbackSensitivePatterns as $pattern) {
                    if (preg_match($pattern, strtolower($userQuery))) {
                        Log::warning("🚨 BLOCKED sensitive query (fallback): '{$userQuery}'");
                        return response()->json([
                            'success' => true,
                            'data' => null,
                            'message' => "I'm designed to help with data analytics and insights, but I can't access or display sensitive personal information like passwords, emails, or contact details. This protects user privacy and security.",
                            'sql_query' => null
                        ]);
                    }
                }
            }
            
            // ✅ If this is a confirmed edit, proceed directly
            if ($isConfirmed && $confirmationData) {
                return $this->executeConfirmedEdit($confirmationData);
            }

            // ✅ Check for capability questions FIRST (before fetch operations)
            // This prevents questions like "What you can do?" from being treated as data queries
            $isCapability = $this->isCapabilityQuestion($userQuery);
            $isFetch = $this->isFetchOperation($userQuery);
            
            // ✅ Aggressive fallback: If query has data terms and question words, treat as fetch
            if (!$isFetch && !$isCapability) {
                $lowerQuery = strtolower(trim($userQuery));
                $hasDataTerms = preg_match('/\b(data|information|details|report|statistics|stats|summary|product|item|order|transaction|sale|customer|user|post|page|category|tag|revenue|income|profit|earnings|sales|orders|transactions)\b/i', $lowerQuery);
                $hasQuestionWords = preg_match('/\b(can|could|what|how|when|where|who|which|give|get|show|tell|list|find|provide|retrieve)\b/i', $lowerQuery);
                
                if ($hasDataTerms && $hasQuestionWords) {
                    Log::info("🔄 Aggressive detection: Treating as fetch operation - '{$userQuery}'");
                    $isFetch = true;
                }
            }
            
            Log::info("🔍 Query Analysis - Query: '{$userQuery}' | IsCapability: " . ($isCapability ? 'true' : 'false') . " | IsFetch: " . ($isFetch ? 'true' : 'false'));
            
            if ($isCapability) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => $this->getHelpfulResponse($userQuery)
                ]);
            }

            // ✅ If the query is a fetch operation, use NLP with OpenAI
            if ($isFetch) {
                Log::info("🤖 NLP Flow: Detected fetch operation");
                
                // Check if OpenAI API key is configured (from WordPress database)
                try {
                    $openaiKey = $this->configService->getOpenAIApiKey();
                    if (empty($openaiKey)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'OpenAI API Key is not configured. Please set it in the Hey Trisha Chatbot settings page.'
                        ], 500);
                    }
                } catch (\Exception $e) {
                    Log::error("❌ Error getting OpenAI API key: " . $e->getMessage());
                    return response()->json([
                        'success' => false,
                        'message' => 'Configuration error. Please check your WordPress settings.'
                    ], 500);
                }

                // ✅ Step 1: Get intelligent database schema
                // Analyzes query to include relevant tables (reduces tokens while maintaining NLP)
                Log::info("📊 Step 1: Fetching intelligent database schema...");
                try {
                    $schema = $this->mysqlService->getCompactSchema($userQuery);
                    
                    if (isset($schema['error'])) {
                        return response()->json([
                            'success' => false,
                            'message' => $schema['error']
                        ], 500);
                    }
                } catch (\Exception $e) {
                    Log::error("❌ Error fetching schema: " . $e->getMessage());
                    Log::error("❌ Stack trace: " . $e->getTraceAsString());
                    return response()->json([
                        'success' => false,
                        'message' => 'Database connection error. Please check your database settings.'
                    ], 500);
                }

                // ✅ Step 2: Send FULL user input + FULL schema to OpenAI
                // OpenAI uses NLP to understand the query and generate SQL
                Log::info("🧠 Step 2: Sending to OpenAI for NLP SQL generation...");
                $queryResponse = $this->sqlGenerator->queryChatGPTForSQL($userQuery, $schema);

                if (isset($queryResponse['error'])) {
                    Log::error("SQL Generation Error: " . $queryResponse['error']);
                    // Return user-friendly error message (already formatted in SQLGeneratorService)
                    return response()->json([
                        'success' => false,
                        'message' => $queryResponse['error']
                    ], 500);
                }

                if (!isset($queryResponse['query'])) {
                    Log::error("SQL Generation returned no query: " . json_encode($queryResponse));
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to generate SQL query. Please try rephrasing your request.'
                    ], 500);
                }

                // ✅ Step 3: Get the generated SQL query from OpenAI
                $sqlQuery = $queryResponse['query'];
                Log::info("✅ Step 3: OpenAI generated SQL: " . $sqlQuery);

                // 🚨 CRITICAL SECURITY: Validate SQL for sensitive data access
                try {
                    // Use global namespace class name to prevent Laravel autoloader issues
                    $securityFilterClass = '\\HeyTrisha_Security_Filter';
                    
                    if ($this->loadSecurityFilter() && class_exists($securityFilterClass)) {
                        // Verify the method exists before calling
                        if (method_exists($securityFilterClass, 'is_sensitive_sql')) {
                            // Use call_user_func to avoid namespace resolution issues
                            $isSensitiveSQL = call_user_func(array($securityFilterClass, 'is_sensitive_sql'), $sqlQuery);
                            if ($isSensitiveSQL) {
                                Log::warning('🚨 Blocked SQL query attempting to access sensitive data');
                                // Verify get_rejection_message exists
                                $rejection_msg = method_exists($securityFilterClass, 'get_rejection_message') 
                                    ? call_user_func(array($securityFilterClass, 'get_rejection_message'))
                                    : "I'm designed to help with data analytics and insights, but I can't access or display sensitive personal information like passwords, emails, or contact details. This protects user privacy and security.";
                                
                                return response()->json([
                                    'success' => true,
                                    'data' => null,
                                    'message' => $rejection_msg,
                                    'sql_query' => null
                                ]);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // If security filter fails, log but continue processing
                    Log::warning('Security filter SQL validation error: ' . $e->getMessage());
                    // Continue with normal processing
                } catch (\Error $e) {
                    // Catch fatal errors too
                    Log::warning('Security filter SQL validation fatal error: ' . $e->getMessage());
                    // Continue with normal processing
                }

                // ✅ Step 4: Execute the SQL query locally on our database
                Log::info("💾 Step 4: Executing SQL query locally...");
                $result = $this->mysqlService->executeSQLQuery($sqlQuery);
                
                if (isset($result['error'])) {
                    Log::error("SQL Execution Error: " . $result['error']);
                    
                    // ✅ Check if this is a "table doesn't exist" error for order-related queries
                    $errorMessage = $result['error'];
                    $isTableNotFoundError = (
                        stripos($errorMessage, "doesn't exist") !== false ||
                        stripos($errorMessage, "Base table or view not found") !== false ||
                        (stripos($errorMessage, "Table") !== false && stripos($errorMessage, "not found") !== false) ||
                        stripos($errorMessage, "1146") !== false || // MySQL error code for table not found
                        stripos($errorMessage, "42S02") !== false   // SQLSTATE for table not found
                    );
                    
                    // Check if this is an order/customer query
                    $queryLower = strtolower($userQuery);
                    $queryLower = preg_replace('/\borderes?\b/i', 'orders', $queryLower);
                    $isOrderQuery = (
                        strpos($queryLower, 'order') !== false ||
                        strpos($queryLower, 'customer') !== false ||
                        strpos($queryLower, 'ordered') !== false ||
                        strpos($queryLower, 'customers') !== false
                    );
                    
                    // Check if the SQL query references order tables that don't exist
                    $isHPOSQuery = $this->isHPOSQuery($sqlQuery);
                    
                    // Debug logging
                    Log::info("🔍 Error Analysis - TableNotFound: " . ($isTableNotFoundError ? 'YES' : 'NO') . 
                             " | OrderQuery: " . ($isOrderQuery ? 'YES' : 'NO') . 
                             " | HPOSQuery: " . ($isHPOSQuery ? 'YES' : 'NO'));
                    
                    // If it's a table-not-found error for an order query, try legacy fallback
                    if ($isTableNotFoundError && $isOrderQuery && $isHPOSQuery) {
                        Log::info("🔄 Table not found error for order query. Attempting legacy table fallback...");
                        $legacyResult = $this->tryLegacyOrderQueryForCustomerDetails($userQuery, $sqlQuery);
                        
                        if ($legacyResult !== null && !isset($legacyResult['error'])) {
                            // Legacy query succeeded, use its result
                            // Extract data from legacy result structure
                            $result = isset($legacyResult['data']) ? $legacyResult['data'] : $legacyResult;
                            // Update SQL query to reflect the legacy query used
                            if (isset($legacyResult['sql_query'])) {
                                $sqlQuery = $legacyResult['sql_query'];
                            }
                            // Convert objects to arrays
                            $result = $this->convertObjectsToArrays($result);
                            Log::info("✅ Legacy order query succeeded!");
                        } else {
                            // Legacy query also failed, return original error
                            Log::warning("⚠️ Legacy fallback also failed or returned null");
                            return response()->json([
                                'success' => false,
                                'message' => $result['error']
                            ], 500);
                        }
                    } else {
                        // Not an order query or not a table-not-found error, return error as-is
                        Log::info("ℹ️ Not triggering legacy fallback - TableNotFound: " . ($isTableNotFoundError ? 'YES' : 'NO') . 
                                 " | OrderQuery: " . ($isOrderQuery ? 'YES' : 'NO') . 
                                 " | HPOSQuery: " . ($isHPOSQuery ? 'YES' : 'NO'));
                        return response()->json([
                            'success' => false,
                            'message' => $result['error']
                        ], 500);
                    }
                }

                // ✅ Step 5: Check if result is valid array before processing
                if (!is_array($result)) {
                    Log::error("❌ Result is not an array: " . gettype($result));
                    return response()->json([
                        'success' => false,
                        'message' => "Invalid data format returned from database.",
                        'sql_query' => $sqlQuery
                    ], 500);
                }
                
                // ✅ Check if result is empty OR contains only a message (no actual data rows)
                // MySQLService returns ["message" => "No matching records found"] when empty
                // This is NOT empty() but has no actual data rows
                $hasDataRows = false;
                $noDataMessage = null;
                $isCountQuery = preg_match('/\bCOUNT\s*\(/i', $sqlQuery);
                $countValue = null;
                
                if (isset($result['message']) && count($result) === 1) {
                    // Result has only a "message" key - this means no data found
                    $noDataMessage = $result['message'];
                    $hasDataRows = false;
                } elseif (empty($result)) {
                    // Truly empty array
                    $noDataMessage = "I couldn't find any data matching your request.";
                    $hasDataRows = false;
                } else {
                    // Check if result has numeric keys (actual data rows)
                    // Data rows will have numeric keys (0, 1, 2...) or be indexed arrays/objects
                    $hasDataRows = false;
                    foreach ($result as $key => $value) {
                        // Convert object to array if needed (DB::select returns objects)
                        $valueArray = is_object($value) ? (array)$value : (is_array($value) ? $value : []);
                        
                        if (is_numeric($key) || (is_array($valueArray) && !isset($valueArray['message']))) {
                            $hasDataRows = true;
                            // For COUNT queries, extract the count value
                            if ($isCountQuery && is_array($valueArray) && !empty($valueArray)) {
                                // Look for count-related keys (total_orders, count, total, etc.)
                                foreach ($valueArray as $colKey => $colValue) {
                                    if (is_numeric($colValue) && (
                                        stripos($colKey, 'count') !== false ||
                                        stripos($colKey, 'total') !== false ||
                                        stripos($colKey, 'sum') !== false
                                    )) {
                                        $countValue = (int)$colValue;
                                        Log::info("🔍 COUNT query result value: {$countValue} (from column: {$colKey})");
                                        break;
                                    }
                                }
                                // If no named column found, use first numeric value
                                if ($countValue === null) {
                                    foreach ($valueArray as $colValue) {
                                        if (is_numeric($colValue)) {
                                            $countValue = (int)$colValue;
                                            Log::info("🔍 COUNT query result value: {$countValue} (from first numeric column)");
                                            break;
                                        }
                                    }
                                }
                            }
                            break;
                        }
                    }
                    
                    // If no numeric keys found, check if it's a single row with data
                    if (!$hasDataRows && isset($result[0])) {
                        $hasDataRows = true;
                        // Check for COUNT value in result[0] (handle both objects and arrays)
                        if ($isCountQuery) {
                            $row = is_object($result[0]) ? (array)$result[0] : (is_array($result[0]) ? $result[0] : []);
                            if (is_array($row) && !empty($row)) {
                                foreach ($row as $colKey => $colValue) {
                                    if (is_numeric($colValue) && (
                                        stripos($colKey, 'count') !== false ||
                                        stripos($colKey, 'total') !== false ||
                                        stripos($colKey, 'sum') !== false
                                    )) {
                                        $countValue = (int)$colValue;
                                        Log::info("🔍 COUNT query result value: {$countValue} (from column: {$colKey})");
                                        break;
                                    }
                                }
                                // If still not found, use first numeric value
                                if ($countValue === null) {
                                    foreach ($row as $colValue) {
                                        if (is_numeric($colValue)) {
                                            $countValue = (int)$colValue;
                                            Log::info("🔍 COUNT query result value: {$countValue} (from first numeric value in result[0])");
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                
                // ✅ CRITICAL: Check if COUNT query returned 0, or if no data rows found
                // For COUNT queries, if count is 0, we should try legacy tables
                $shouldTryLegacyFallback = false;
                if ($isCountQuery) {
                    // For COUNT queries, check if count value is 0 or null (meaning no count extracted)
                    if ($countValue === 0 || ($countValue === null && $hasDataRows)) {
                        // If countValue is null but we have data rows, try to extract it from result[0]
                        if ($countValue === null && $hasDataRows && isset($result[0])) {
                            foreach ($result[0] as $colKey => $colValue) {
                                if (is_numeric($colValue)) {
                                    $countValue = (int)$colValue;
                                    Log::info("🔍 COUNT query result value extracted: {$countValue} (from column: {$colKey})");
                                    break;
                                }
                            }
                        }
                        
                        if ($countValue === 0) {
                            Log::info("🔄 COUNT query returned 0, checking if legacy fallback needed...");
                            $shouldTryLegacyFallback = true;
                        }
                    }
                } elseif (!$hasDataRows) {
                    $shouldTryLegacyFallback = true;
                }
                
                if ($shouldTryLegacyFallback) {
                    // Check if this is an order query that might need comprehensive research across all tables
                    $queryLower = strtolower($userQuery);
                    // Handle typos like "orderes" -> "orders"
                    $queryLower = preg_replace('/\borderes?\b/i', 'orders', $queryLower);
                    $isOrderQuery = strpos($queryLower, 'order') !== false;
                    
                    // ✅ CRITICAL: If order query returned 0 results, conduct comprehensive research across ALL order tables
                    if ($isOrderQuery) {
                        Log::info("🔬 Order query returned 0 results. Conducting comprehensive research across all order tables...");
                        $researchResult = $this->researchAllOrderTables($userQuery, $sqlQuery, $isCountQuery, $countValue);
                        
                        if ($researchResult !== null) {
                            // Research found results in alternative tables!
                            if (isset($researchResult['error'])) {
                                Log::warning("⚠️ Order research encountered error: " . $researchResult['error']);
                            } else {
                                // For COUNT queries, check count_value; for others, check data
                                if ($isCountQuery) {
                                    // Extract count_value from research result
                                    $foundCount = isset($researchResult['count_value']) ? $researchResult['count_value'] : 0;
                                    
                                    // If count_value not set, try to extract from data
                                    if ($foundCount === 0 && !empty($researchResult['data'])) {
                                        foreach ($researchResult['data'] as $row) {
                                            // Convert object to array if needed (DB::select returns objects)
                                            $rowArray = is_object($row) ? (array)$row : (is_array($row) ? $row : []);
                                            if (is_array($rowArray) && !empty($rowArray)) {
                                                foreach ($rowArray as $colKey => $colValue) {
                                                    if (is_numeric($colValue)) {
                                                        $foundCount = (int)$colValue;
                                                        Log::info("🔍 Extracted count_value {$foundCount} from research data (column: {$colKey})");
                                                        break;
                                                    }
                                                }
                                                if ($foundCount > 0) break;
                                            }
                                        }
                                        
                                        // Also check result[0] directly if still not found
                                        if ($foundCount === 0 && isset($researchResult['data'][0])) {
                                            $row = is_object($researchResult['data'][0]) ? (array)$researchResult['data'][0] : (is_array($researchResult['data'][0]) ? $researchResult['data'][0] : []);
                                            if (is_array($row) && !empty($row)) {
                                                foreach ($row as $colKey => $colValue) {
                                                    if (is_numeric($colValue)) {
                                                        $foundCount = (int)$colValue;
                                                        Log::info("🔍 Extracted count_value {$foundCount} from research data[0] (column: {$colKey})");
                                                        break;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    
                                    if ($foundCount > 0) {
                                        Log::info("✅ Comprehensive research found {$foundCount} orders in alternative tables!");
                                        // Use the research results
                                        $result = $researchResult['data'];
                                        $hasDataRows = true;
                                        $sqlQuery = $researchResult['sql_query'] ?? $sqlQuery;
                                        $countValue = $foundCount;
                                        Log::info("✅ Updated countValue to {$countValue} from research results");
                                        // Continue processing with research results
                                    } else {
                                        Log::info("ℹ️ Research found results but count is 0 or could not extract count value");
                                    }
                                } elseif (!empty($researchResult['data'])) {
                                    $foundCount = is_array($researchResult['data']) ? count($researchResult['data']) : 1;
                                    Log::info("✅ Comprehensive research found {$foundCount} results in alternative tables!");
                                    // Use the research results
                                    $result = $researchResult['data'];
                                    $hasDataRows = true;
                                    $sqlQuery = $researchResult['sql_query'] ?? $sqlQuery;
                                    $countValue = null; // Reset count value since we're using actual data now
                                    // Continue processing with research results
                                }
                            }
                        }
                    }
                }
                
                // If still no data rows after comprehensive research, return confident confirmation
                if (!$hasDataRows) {
                    $queryLower = strtolower($userQuery);
                    $queryLower = preg_replace('/\borderes?\b/i', 'orders', $queryLower);
                    $isOrderQuery = strpos($queryLower, 'order') !== false;
                    
                    if ($isOrderQuery) {
                        // Confident, research-based response for order queries
                        $message = "After conducting a comprehensive search across all order tables in your database (including wp_wc_order_stats, wp_wc_orders, wp_posts with post_type='shop_order', wp_woocommerce_order_items, and other order-related tables), I can confidently confirm that there are currently 0 orders matching your criteria.";
                        
                        if ($isCountQuery && $countValue === 0) {
                            $message = "After thoroughly researching all order tables in your database, I can confirm with confidence that you have 0 orders" . 
                                      (strpos($queryLower, 'last') !== false || strpos($queryLower, 'year') !== false ? " matching the specified time period" : "") . 
                                      " in your system.";
                        }
                        
                        Log::info("✅ Comprehensive research complete - confidently confirming 0 orders after checking all tables");
                    } else {
                        $message = $noDataMessage ?: "After checking the relevant data tables, I couldn't find any data matching your request.";
                    }
                    
                    Log::info("ℹ️ Final result: No data found after comprehensive research");
                    Log::info("ℹ️ SQL Query: " . $sqlQuery);
                    Log::info("ℹ️ User Query: " . $userQuery);
                    
                    return response()->json([
                        'success' => true,
                        'data' => [],
                        'message' => $message,
                        'sql_query' => $sqlQuery
                    ]);
                }

                // ✅ Step 6: Convert all objects to arrays for proper JSON encoding
                // DB::select() returns objects, but we need arrays for JSON response
                $result = $this->convertObjectsToArrays($result);
                
                // ✅ Step 6.5: Filter out invalid product IDs (0 or NULL)
                $result = $this->filterInvalidProductIds($result);
                
                // ✅ Step 7: Post-process results to add product names if product_id is present
                $result = $this->addProductNamesToResults($result);
                
                // 🚨 CRITICAL SECURITY: Filter sensitive columns from results
                try {
                    // Use global namespace class name to prevent Laravel autoloader issues
                    $securityFilterClass = '\\HeyTrisha_Security_Filter';
                    
                    if ($this->loadSecurityFilter() && class_exists($securityFilterClass)) {
                        // Verify the method exists before calling
                        if (method_exists($securityFilterClass, 'filter_sensitive_results')) {
                            // Use call_user_func to avoid namespace resolution issues
                            $result = call_user_func(array($securityFilterClass, 'filter_sensitive_results'), $result);
                            Log::info('✅ Filtered results for sensitive data');
                        }
                    }
                } catch (\Exception $e) {
                    // If security filter fails, log but continue processing
                    Log::warning('Security filter result filtering error: ' . $e->getMessage());
                    // Continue with unfiltered results (better than failing completely)
                } catch (\Error $e) {
                    // Catch fatal errors too
                    Log::warning('Security filter result filtering fatal error: ' . $e->getMessage());
                    // Continue with unfiltered results (better than failing completely)
                }
                
                // ✅ Step 8: Analyze results and generate human-friendly response
                try {
                    $analysis = $this->analyzeResultsAndGenerateResponse($userQuery, $result, $sqlQuery);
                } catch (\Exception $e) {
                    Log::error("⚠️ Error in analysis: " . $e->getMessage());
                    // Fallback to simple message if analysis fails
                    $analysis = [
                        'message' => "I found " . count($result) . " result" . (count($result) > 1 ? 's' : '') . " for your query.",
                        'analysis' => null
                    ];
                }
                
                // ✅ Step 9: Return results to frontend with analysis
                Log::info("✅ Step 9: Returning results to frontend (" . count($result) . " rows)");
                
                // Ensure message is always set (use analysis message or fallback)
                $message = $analysis['message'] ?? "Here's what I found:";
                
                return response()->json([
                    'success' => true, 
                    'data' => $result,
                    'message' => $message, // Human-friendly response
                    'analysis' => $analysis['analysis'] ?? null, // Detailed analysis
                    'sql_query' => $sqlQuery // Include SQL for transparency
                ]);
            } else {
                // ✅ Check if this is an edit operation by name
                $editInfo = $this->detectEditByName($userQuery);
                
                if ($editInfo && isset($editInfo['name'])) {
                    // ✅ Search for the post/product by name
                    $itemType = $editInfo['type'] ?? 'both';
                    $foundItem = $this->postProductSearchService->searchByName($editInfo['name'], $itemType);
                    
                    if (!$foundItem) {
                        return response()->json([
                            'success' => false,
                            'message' => "No {$itemType} found with the name '{$editInfo['name']}'.",
                            'requires_confirmation' => false
                        ]);
                    }

                    // ✅ Generate the API request with the found ID
                    $modifiedQuery = $this->replaceNameWithId($userQuery, $editInfo['name'], $foundItem['id'], $foundItem['type']);
                    $apiRequest = $this->wordpressRequestGeneratorService->generateWordPressRequest($modifiedQuery);
                    
                    if (isset($apiRequest['error'])) {
                        return response()->json(['success' => false, 'message' => $apiRequest['error']], 500);
                    }

                    // ✅ Return confirmation request
                    $itemName = $foundItem['type'] === 'post' ? $foundItem['title'] : $foundItem['name'];
                    return response()->json([
                        'success' => true,
                        'requires_confirmation' => true,
                        'confirmation_message' => "I found a {$foundItem['type']} named '{$itemName}' (ID: {$foundItem['id']}). Do you want to proceed with the edit?",
                        'confirmation_data' => [
                            'item_id' => $foundItem['id'],
                            'item_name' => $itemName,
                            'item_type' => $foundItem['type'],
                            'api_request' => $apiRequest,
                            'original_query' => $userQuery
                        ],
                        'data' => [
                            'found_item' => $foundItem,
                            'preview' => "This will {$apiRequest['method']} the {$foundItem['type']} '{$itemName}'"
                        ]
                    ]);
                }

                    // ✅ Check if this is a WordPress API operation (create, update, delete)
                if ($this->isWordPressApiOperation($userQuery)) {
                    // ✅ Standard flow for ID-based or create operations
                    $apiRequest = $this->wordpressRequestGeneratorService->generateWordPressRequest($userQuery);
                    
                    if (!is_array($apiRequest) || !isset($apiRequest['method']) || !isset($apiRequest['endpoint'])) {
                        Log::error("❌ Invalid API Request Structure. Missing 'method' or 'endpoint'.");
                        Log::error("🛠 Debugging: " . json_encode($apiRequest, JSON_PRETTY_PRINT));

                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid API request structure. Missing method or endpoint.'
                        ], 500);
                    }

                    Log::info("📢 Sending WordPress API Request: Method: {$apiRequest['method']}, Endpoint: {$apiRequest['endpoint']}");

                    $response = $this->wordpressApiService->sendRequest(
                        $apiRequest['method'],
                        $apiRequest['endpoint'],
                        $apiRequest['payload'] ?? []
                    );

                    return response()->json(['success' => true, 'data' => $response]);
                } else {
                    // ✅ Unrecognized query - return helpful message
                    Log::warning("⚠️ Unrecognized query - no patterns matched: '{$userQuery}'");
                    Log::warning("⚠️ Query details - IsCapability: " . ($isCapability ? 'true' : 'false') . " | IsFetch: " . ($isFetch ? 'true' : 'false') . " | IsWordPressApi: " . ($this->isWordPressApiOperation($userQuery) ? 'true' : 'false'));
                    return response()->json([
                        'success' => true,
                        'data' => null,
                        'message' => $this->getHelpfulResponse($userQuery)
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error("🚨 Error handling user query: " . $e->getMessage());
            Log::error("🚨 Stack trace: " . $e->getTraceAsString());
            
            // Return user-friendly error message
            $errorMessage = "Sorry, I encountered an error processing your request. ";
            
            // Provide more specific error messages for common issues
            if (strpos($e->getMessage(), 'Connection') !== false || strpos($e->getMessage(), 'timeout') !== false) {
                $errorMessage .= "The database connection timed out. Please try again.";
            } elseif (strpos($e->getMessage(), 'OpenAI') !== false) {
                $errorMessage .= "There was an issue with the AI service. Please check your OpenAI API key configuration.";
            } elseif (strpos($e->getMessage(), 'WordPress') !== false) {
                $errorMessage .= "There was an issue connecting to WordPress. Please check your WordPress API settings.";
            } else {
                $errorMessage .= "Please try rephrasing your request or contact support if the issue persists.";
            }
            
            return response()->json([
                'success' => false, 
                'message' => $errorMessage
            ], 500);
        }
    }

    /**
     * Execute a confirmed edit operation
     */
    private function executeConfirmedEdit($confirmationData)
    {
        try {
            $apiRequest = $confirmationData['api_request'];
            
            if (!isset($apiRequest['method']) || !isset($apiRequest['endpoint'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid confirmation data.'
                ], 500);
            }

            Log::info("✅ Executing confirmed edit: " . json_encode($apiRequest, JSON_PRETTY_PRINT));

            $response = $this->wordpressApiService->sendRequest(
                $apiRequest['method'],
                $apiRequest['endpoint'],
                $apiRequest['payload'] ?? []
            );

            return response()->json([
                'success' => true,
                'data' => $response,
                'message' => "Successfully edited {$confirmationData['item_type']} '{$confirmationData['item_name']}'"
            ]);
        } catch (\Exception $e) {
            Log::error("🚨 Error executing confirmed edit: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Detect if the query is an edit operation by name
     */
    private function detectEditByName($query)
    {
        // Check for edit/update operations
        if (!preg_match('/\b(edit|update|modify|change|alter)\b/i', $query)) {
            return null;
        }

        // Check if query contains an ID (numeric) - if so, it's not a name-based edit
        if (preg_match('/\b(id|ID)\s*[:\-]?\s*\d+\b/i', $query) || preg_match('/\b\d+\b.*\b(edit|update|modify|change|alter)\b/i', $query)) {
            return null;
        }

        // Try to extract name from common patterns
        $patterns = [
            // "edit post named 'X'"
            '/edit\s+(?:post|product)\s+(?:named|called|titled)\s+["\']([^"\']+)["\']/i',
            // "edit the post 'X'"
            '/edit\s+(?:the\s+)?(post|product)\s+["\']([^"\']+)["\']/i',
            // "update post 'X'"
            '/update\s+(?:the\s+)?(post|product)\s+["\']([^"\']+)["\']/i',
            // "edit 'X' post"
            '/edit\s+["\']([^"\']+)["\']\s+(post|product)/i',
            // "edit post X" (without quotes, but not a number)
            '/edit\s+(?:the\s+)?(post|product)\s+([a-zA-Z][a-zA-Z0-9\s]+?)(?:\s+with|\s+to|\s+and|$)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $query, $matches)) {
                $type = 'both';
                $name = null;

                // Determine type and name from matches
                if (count($matches) >= 3) {
                    // Pattern has both type and name
                    if (in_array(strtolower($matches[1]), ['post', 'product'])) {
                        $type = strtolower($matches[1]);
                        $name = $matches[2];
                    } else if (in_array(strtolower($matches[2]), ['post', 'product'])) {
                        $type = strtolower($matches[2]);
                        $name = $matches[1];
                    } else {
                        $name = $matches[1] ?? $matches[2];
                    }
                } else if (count($matches) >= 2) {
                    $name = $matches[1];
                }

                // Clean up the name
                if ($name) {
                    $name = trim($name);
                    // Remove common words that might be captured
                    $name = preg_replace('/\s+(with|to|and|the|a|an)\s+/i', ' ', $name);
                    $name = trim($name);
                    
                    // Make sure it's not just a number
                    if (!is_numeric($name) && strlen($name) > 0) {
                        return [
                            'name' => $name,
                            'type' => $type
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Replace name with ID in the query
     */
    private function replaceNameWithId($query, $name, $id, $type)
    {
        // Replace the name with ID in the query
        $modifiedQuery = str_ireplace(
            ["'{$name}'", "\"{$name}\"", "{$name}"],
            ["{$id}", "{$id}", "{$id}"],
            $query
        );
        
        // Also try to replace patterns like "post named X" with "post ID X"
        $modifiedQuery = preg_replace(
            "/\b(post|product)\s+(?:named|called|titled)\s+[\"']?{$name}[\"']?/i",
            "{$type} ID {$id}",
            $modifiedQuery
        );

        return $modifiedQuery;
    }

    // ✅ Detects questions about bot capabilities (should NOT be treated as fetch operations)
    private function isCapabilityQuestion($query)
    {
        $lowerQuery = strtolower(trim($query));
        
        // Patterns that indicate questions about capabilities
        // IMPORTANT: These should NOT match action requests like "Can you make a post"
        $capabilityPatterns = [
            '/^what\s+(can|do|are)\s+(you|i)\s+(do|help|assist)(\s+me)?\s*$/i',  // "What can you do?", "What do you do?"
            '/^what\s+(are|is)\s+(your|you)\s+(capabilities|features|functions|abilities)/i',  // "What are your capabilities?"
            '/^how\s+(can|do)\s+(you|i)\s+(help|assist)(\s+me)?\s*$/i',  // "How can you help?"
            '/^(tell|show)\s+(me\s+)?(what\s+)?(can\s+)?(you\s+)?(do|help)(\s+me)?\s*$/i',  // "Tell me what you can do"
            '/^(what|how)\s+(you\s+)?(can\s+)?(do|help)(\s+me)?\s*$/i',  // "What you can do?", "How you can help?"
            '/^can\s+you\s+(help|do|assist)(\s+me)?\s*$/i',  // "Can you help?" (but NOT "Can you make a post")
            '/^(what|how)\s+are\s+you(\s+doing)?\s*$/i',  // "What are you?", "How are you?"
        ];
        
        foreach ($capabilityPatterns as $pattern) {
            if (preg_match($pattern, $lowerQuery)) {
                // Additional check: if the query contains action verbs or WordPress terms, it's NOT a capability question
                // This prevents "Can you make a post" from being treated as a capability question
                if (preg_match('/\b(make|create|add|new|insert|update|edit|modify|change|delete|remove|post|product|page|category|tag|order)\b/i', $lowerQuery)) {
                    return false; // It's an action request, not a capability question
                }
                return true;
            }
        }
        
        return false;
    }

    // ✅ Detects Fetch operations (SELECT queries)
    private function isFetchOperation($query)
    {
        $lowerQuery = strtolower(trim($query));
        
        // Exclude capability questions from fetch operations FIRST
        if ($this->isCapabilityQuestion($query)) {
            return false;
        }
        
        // Check for explicit fetch keywords (excluding "what" when it's about capabilities)
        $fetchKeywords = '/\b(show|list|fetch|get|view|display|select|give|provide|retrieve|find|search|see|tell|which|how many|count|sum|total|sales|revenue|orders|products|posts|users|customers|data|information|details|report|selling|sold|popular|best|top|most|least|highest|lowest|average|avg|maximum|minimum|max|min|transaction|transactions)\b/i';
        
        // Check for question patterns that indicate data requests
        $questionPattern = '/\b(can you|could you|please|i need|i want|show me|give me|get me|tell me|what is|what are|how many|how much|who|when|where)\b/i';
        
        // Check for data-related terms (expanded list)
        $dataTerms = '/\b(data|information|details|report|statistics|stats|summary|overview|all|every|each|product|item|order|transaction|transactions|sale|sales|customer|customers|user|users|post|posts|page|pages|category|categories|tag|tags|revenue|income|profit|earnings)\b/i';
        
        // Check for time-related terms (often part of analytical queries)
        $timeTerms = '/\b(last|this|next|previous|yesterday|today|tomorrow|week|month|year|january|february|march|april|may|june|july|august|september|october|november|december|monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/i';
        
        // Check for comparative/superlative queries (most, best, top, etc.)
        $comparativePattern = '/\b(most|best|top|worst|least|highest|lowest|biggest|smallest|largest|fastest|slowest|newest|oldest|latest|earliest)\b/i';
        
        // ✅ PRIMARY CHECK: If query contains fetch keywords, it's a fetch operation
        if (preg_match($fetchKeywords, $lowerQuery)) {
            Log::info("✅ Fetch detected by keywords: '{$query}'");
            return true;
        }
        
        // ✅ SECONDARY CHECK: Question pattern + data terms = fetch operation
        if (preg_match($questionPattern, $lowerQuery) && preg_match($dataTerms, $lowerQuery)) {
            Log::info("✅ Fetch detected by question + data terms: '{$query}'");
            return true;
        }
        
        // ✅ TERTIARY CHECK: Question pattern + time terms = likely fetch operation (analytical query)
        if (preg_match($questionPattern, $lowerQuery) && preg_match($timeTerms, $lowerQuery)) {
            Log::info("✅ Fetch detected by question + time terms: '{$query}'");
            return true;
        }
        
        // ✅ FOURTH CHECK: Comparative/superlative + data terms = fetch operation
        if (preg_match($comparativePattern, $lowerQuery) && preg_match($dataTerms, $lowerQuery)) {
            Log::info("✅ Fetch detected by comparative + data terms: '{$query}'");
            return true;
        }
        
        // ✅ FIFTH CHECK: Data terms + time terms = fetch operation (analytical query)
        if (preg_match($dataTerms, $lowerQuery) && preg_match($timeTerms, $lowerQuery)) {
            Log::info("✅ Fetch detected by data + time terms: '{$query}'");
            return true;
        }
        
        // ✅ SIXTH CHECK: "what" questions about data (not capabilities)
        if (preg_match('/\bwhat\b/i', $lowerQuery)) {
            // Only treat as fetch if it's asking about data, not capabilities
            if (preg_match('/\bwhat\s+(is|are|was|were)\s+(the|a|an|my|our|this|that|these|those)/i', $lowerQuery) ||
                preg_match('/\bwhat\s+(is|are)\s+(in|from|of)\s+(the|my|our|this|that)/i', $lowerQuery) ||
                preg_match('/\bwhat\s+(data|information|details|report|statistics|stats|summary|overview)\b/i', $lowerQuery)) {
                Log::info("✅ Fetch detected by 'what' question: '{$query}'");
                return true;
            }
        }
        
        // ✅ SEVENTH CHECK: Sales/revenue/orders/transactions specific queries
        if (preg_match('/\b(sales|revenue|income|profit|orders|transactions|earnings|selling|sold)\b/i', $lowerQuery)) {
            Log::info("✅ Fetch detected by sales/revenue terms: '{$query}'");
            return true;
        }
        
        // ✅ EIGHTH CHECK: "give me" or "show me" patterns (common fetch requests)
        if (preg_match('/\b(give|show|get|tell|find|list)\s+(me\s+)?(the\s+)?(most|best|top|all|every|last|this|next)\b/i', $lowerQuery)) {
            Log::info("✅ Fetch detected by 'give/show me' pattern: '{$query}'");
            return true;
        }
        
        // ✅ NINTH CHECK: "can you" + action verb + data term = fetch operation
        if (preg_match('/\bcan\s+(you\s+)?(give|get|show|tell|provide|fetch|retrieve|find|list)\b/i', $lowerQuery) && preg_match($dataTerms, $lowerQuery)) {
            Log::info("✅ Fetch detected by 'can you' + action + data: '{$query}'");
            return true;
        }
        
        Log::info("❌ Query NOT detected as fetch operation: '{$query}'");
        return false;
    }

    // ✅ Detects WordPress API operations (create, update, delete)
    private function isWordPressApiOperation($query)
    {
        // Check for create, update, delete, edit operations
        // Added "make" and other common verbs for creating content
        $operationPattern = '/\b(create|make|add|new|insert|update|edit|modify|change|delete|remove|alter|publish|draft|build|generate)\b/i';
        
        // Check for WordPress/WooCommerce specific terms
        $wpTermsPattern = '/\b(post|product|page|category|tag|order|customer|user|woocommerce|wordpress)\b/i';
        
        // Check for ID references (indicates specific operation)
        $idPattern = '/\b(id|ID)\s*[:\-]?\s*\d+\b/i';
        
        // Check for property assignments (indicates update/create)
        // Enhanced to catch "Title Hello World" pattern
        $propertyPattern = '/\b(with|set|to|as|price|title|content|name|status|titled|called|named)\s*[:\-]?\s*["\']?[^"\']+["\']?/i';
        
        // Also check for direct property patterns like "Title Hello World" (without "with/set/to")
        $directPropertyPattern = '/\b(title|content|name|price|status)\s+["\']?[^"\']+["\']?/i';
        
        return preg_match($operationPattern, $query) && 
               (preg_match($wpTermsPattern, $query) || preg_match($idPattern, $query) || preg_match($propertyPattern, $query) || preg_match($directPropertyPattern, $query));
    }

    // ✅ Returns helpful response for unrecognized queries
    /**
     * ✅ Post-process results to add product names if product_id is present
     * Fetches product names from WordPress posts table when product_id is in results
     */
    private function addProductNamesToResults($result)
    {
        // Check if result is an array and has product_id
        if (!is_array($result) || empty($result)) {
            return $result;
        }
        
        // Check if any row has product_id but no product name
        $needsProductNames = false;
        $productIds = [];
        
        foreach ($result as $row) {
            if (is_object($row)) {
                $row = (array)$row;
            }
            
            if (isset($row['product_id']) && !isset($row['product_name']) && !isset($row['post_title']) && !isset($row['name'])) {
                $needsProductNames = true;
                $productIds[] = $row['product_id'];
            }
        }
        
        if (!$needsProductNames || empty($productIds)) {
            return $result;
        }
        
        // Fetch product names from database
        try {
            $productIds = array_unique($productIds);
            $productIdsStr = implode(',', array_map('intval', $productIds));
            
            // Get posts table name from config
            $wpInfo = $this->configService->getWordPressInfo();
            $isMultisite = $wpInfo['is_multisite'] ?? false;
            $currentSiteId = $wpInfo['current_site_id'] ?? 1;
            
            // Detect posts table name (auto-detect site ID if needed)
            $postsTable = 'wp_posts';
            $allTables = \Illuminate\Support\Facades\DB::select('SHOW TABLES');
            $allTableNames = array_map(fn($table) => reset($table), $allTables);
            
            // Auto-detect site ID from table names if config returned site ID 1
            $hasMultisitePattern = false;
            foreach ($allTableNames as $tableName) {
                if (preg_match('/^wp\d+_\d+_/', $tableName)) {
                    $hasMultisitePattern = true;
                    break;
                }
            }
            
            if ($hasMultisitePattern || $isMultisite) {
                // If site ID is 1, try to detect actual site ID
                if ($currentSiteId == 1) {
                    $siteIdCounts = [];
                    foreach ($allTableNames as $tableName) {
                        if (preg_match('/^wp\d+_(\d+)_/', $tableName, $matches)) {
                            $siteId = (int)$matches[1];
                            if ($siteId > 0) {
                                $siteIdCounts[$siteId] = ($siteIdCounts[$siteId] ?? 0) + 1;
                            }
                        }
                    }
                    if (!empty($siteIdCounts)) {
                        arsort($siteIdCounts);
                        $currentSiteId = array_key_first($siteIdCounts);
                    }
                }
                
                // Find posts table for current site ID
                foreach ($allTableNames as $tableName) {
                    if (preg_match('/^wp\d+_' . preg_quote($currentSiteId, '/') . '_posts$/', $tableName)) {
                        $postsTable = $tableName;
                        break;
                    }
                }
            }
            
            // Fetch product names from posts table
            $products = \Illuminate\Support\Facades\DB::select(
                "SELECT ID, post_title FROM `{$postsTable}` WHERE ID IN ({$productIdsStr}) AND post_type = 'product'"
            );
            
            // Create a map of product_id => product_name
            $productNameMap = [];
            foreach ($products as $product) {
                $productNameMap[$product->ID] = $product->post_title;
            }
            
            // Add product names to results
            foreach ($result as &$row) {
                if (is_object($row)) {
                    $row = (array)$row;
                }
                
                if (isset($row['product_id']) && isset($productNameMap[$row['product_id']])) {
                    $row['product_name'] = $productNameMap[$row['product_id']];
                }
            }
            
            Log::info("✅ Added product names for " . count($productNameMap) . " products");
        } catch (\Exception $e) {
            Log::warning("⚠️ Could not fetch product names: " . $e->getMessage());
            // Continue without product names if fetch fails
        }
        
        return $result;
    }
    
    /**
     * ✅ Analyze SQL results and generate human-friendly, analytical response
     * Uses OpenAI to analyze data and provide insights in conversational format
     */
    private function analyzeResultsAndGenerateResponse($userQuery, $result, $sqlQuery)
    {
        try {
            // Validate result is a proper array
            if (!is_array($result)) {
                Log::warning("⚠️ Result is not an array: " . gettype($result));
                return [
                    'message' => "I couldn't process the results. Please try again.",
                    'analysis' => null
                ];
            }
            
            // If result is empty or has error message, return simple message
            if (empty($result) || (isset($result['message']) && !isset($result[0]))) {
                $message = isset($result['message']) ? $result['message'] : "I couldn't find any data matching your request.";
                return [
                    'message' => $message,
                    'analysis' => null
                ];
            }
            
            // Check if result contains error key (shouldn't happen here, but safety check)
            if (isset($result['error'])) {
                Log::warning("⚠️ Result contains error: " . $result['error']);
                return [
                    'message' => $result['error'],
                    'analysis' => null
                ];
            }
            
            // Prepare data summary for OpenAI
            $dataSummary = $this->prepareDataSummary($result);
            
            // Get OpenAI API key
            $apiKey = $this->configService->getOpenAIApiKey();
            if (!$apiKey) {
                // Fallback to simple summary if no API key
                return $this->generateSimpleSummary($userQuery, $result);
            }
            
            // Build prompt for analysis
            $prompt = "You are a helpful AI assistant analyzing WordPress/WooCommerce data. Your role is to provide clear, friendly, and insightful responses.\n\n" .
                     "User's Question: \"$userQuery\"\n\n" .
                     "Data Retrieved:\n$dataSummary\n\n" .
                     "Your Task:\n" .
                     "1. Analyze the data and understand what the user is asking\n" .
                     "2. Provide a friendly, conversational response that answers their question\n" .
                     "3. Include key insights and numbers in a natural, human way\n" .
                     "4. If showing specific items (products, orders, etc.), mention them by name when available\n" .
                     "5. For analytical queries (totals, counts, trends), provide context and insights\n" .
                     "6. Be concise but informative - don't just list numbers, explain what they mean\n" .
                     "7. Use friendly, conversational language - like you're explaining to a colleague\n\n" .
                     "Response Guidelines:\n" .
                     "- Start with a friendly acknowledgment of their question\n" .
                     "- Present the key findings clearly\n" .
                     "- Use natural language (e.g., 'I found 5 products' not 'Result count: 5')\n" .
                     "- If showing a list, mention the most important items\n" .
                     "- For totals/amounts, format numbers nicely (e.g., '$1,234.56' not '1234.56')\n" .
                     "- End with a helpful note if relevant\n\n" .
                     "IMPORTANT: Return ONLY the response text - no markdown, no code blocks, no JSON. Just plain, friendly text.\n\n" .
                     "Your Response:";
            
            // Call OpenAI for analysis
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ])->timeout(15)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful AI assistant that analyzes data and provides friendly, conversational responses.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => 500,
                'temperature' => 0.7
            ]);
            
            if ($response->successful()) {
                $openAIResponse = $response->json();
                $analysisText = $openAIResponse['choices'][0]['message']['content'] ?? null;
                
                if ($analysisText) {
                    // Clean up the response (remove markdown if any)
                    $analysisText = trim($analysisText);
                    $analysisText = preg_replace('/^```[\w]*\n?/', '', $analysisText);
                    $analysisText = preg_replace('/\n?```$/', '', $analysisText);
                    $analysisText = trim($analysisText);
                    
                    Log::info("✅ Generated friendly response: " . substr($analysisText, 0, 100) . "...");
                    
                    return [
                        'message' => $analysisText,
                        'analysis' => $dataSummary
                    ];
                }
            }
            
            // Fallback to simple summary if OpenAI fails
            return $this->generateSimpleSummary($userQuery, $result);
            
        } catch (\Exception $e) {
            Log::warning("⚠️ Error generating analysis: " . $e->getMessage());
            // Fallback to simple summary
            return $this->generateSimpleSummary($userQuery, $result);
        }
    }
    
    /**
     * ✅ Prepare data summary for OpenAI analysis
     */
    private function prepareDataSummary($result)
    {
        // Validate input
        if (!is_array($result)) {
            Log::warning("⚠️ prepareDataSummary: Result is not an array");
            return "Invalid data format.";
        }
        
        // Check for error messages
        if (isset($result['error']) || (isset($result['message']) && !isset($result[0]))) {
            return "No data found.";
        }
        
        if (empty($result)) {
            return "No data found.";
        }
        
        // Ensure result is a numeric array (not associative with error keys)
        $numericArray = array_values($result);
        if (empty($numericArray)) {
            return "No data found.";
        }
        
        // Limit to first 20 rows for analysis (to avoid token limits)
        $sampleData = array_slice($numericArray, 0, 20);
        
        $summary = "Total records: " . count($result) . "\n\n";
        
        if (count($sampleData) > 0) {
            $summary .= "Sample data:\n";
            foreach ($sampleData as $index => $row) {
                if (is_object($row)) {
                    $row = (array)$row;
                }
                
                $summary .= "Record " . ($index + 1) . ":\n";
                foreach ($row as $key => $value) {
                    // ✅ Format values intelligently based on field type
                    if (is_numeric($value)) {
                        $keyLower = strtolower($key);
                        
                        // ✅ IDs should NEVER be formatted (keep as integers)
                        if (strpos($keyLower, 'id') !== false || strpos($keyLower, '_id') !== false || $key === 'ID') {
                            $value = (int)$value; // Keep as integer, no formatting
                        }
                        // ✅ Counts, quantities should be integers (no decimals)
                        elseif (strpos($keyLower, 'count') !== false || strpos($keyLower, 'quantity') !== false || 
                                strpos($keyLower, 'qty') !== false || strpos($keyLower, 'total_items') !== false ||
                                strpos($keyLower, 'num_') !== false) {
                            $value = (int)$value; // Integer, no decimals
                        }
                        // ✅ Currency/price/amount fields - format with 2 decimals ONLY if it has decimals
                        elseif (strpos($keyLower, 'price') !== false || strpos($keyLower, 'amount') !== false || 
                                strpos($keyLower, 'total') !== false || strpos($keyLower, 'revenue') !== false ||
                                strpos($keyLower, 'sales') !== false || strpos($keyLower, 'cost') !== false ||
                                strpos($keyLower, 'value') !== false) {
                            // Only format if it's a decimal number
                            if (is_float($value) || strpos((string)$value, '.') !== false) {
                                $value = number_format((float)$value, 2, '.', ','); // e.g., 1,234.56
                            } else {
                                $value = number_format((float)$value, 2, '.', ','); // e.g., 1,234.00
                            }
                        }
                        // ✅ Large integers (non-currency) - format with commas but no decimals
                        elseif (abs($value) >= 1000) {
                            $value = number_format((int)$value, 0, '.', ','); // e.g., 1,234
                        }
                        // ✅ Small numbers/percentages - keep as-is
                        else {
                            $value = $value; // Keep original value
                        }
                    }
                    $summary .= "  - " . ucwords(str_replace('_', ' ', $key)) . ": " . $value . "\n";
                }
                $summary .= "\n";
            }
            
            if (count($result) > 20) {
                $summary .= "... and " . (count($result) - 20) . " more records.\n";
            }
        }
        
        return $summary;
    }
    
    /**
     * ✅ Generate simple summary when OpenAI is not available
     */
    private function generateSimpleSummary($userQuery, $result)
    {
        if (empty($result) || (is_array($result) && isset($result['message']))) {
            $message = is_array($result) && isset($result['message']) ? $result['message'] : "I couldn't find any data matching your request.";
            return [
                'message' => $message,
                'analysis' => null
            ];
        }
        
        $count = count($result);
        $queryLower = strtolower($userQuery);
        
        // Generate friendly message based on query type
        $firstRow = is_object($result[0]) ? (array)$result[0] : $result[0];
        
        // ✅ Check for COUNT queries (single row, single column with count/total/number)
        if ($count === 1 && is_array($firstRow)) {
            $keys = array_keys($firstRow);
            $values = array_values($firstRow);
            
            // If single column result, it's likely a COUNT or aggregate query
            if (count($keys) === 1) {
                $keyName = strtolower($keys[0]);
                $value = $values[0];
                
                // ✅ Count queries - show as integer
                if (strpos($keyName, 'count') !== false || strpos($keyName, 'total') !== false || 
                    strpos($keyName, 'number') !== false || strpos($keyName, 'num_') !== false) {
                    $displayValue = is_numeric($value) ? number_format((int)$value, 0) : $value;
                    $friendlyKey = str_replace('_', ' ', $keys[0]);
                    return [
                        'message' => "I found the answer to your query. The " . $friendlyKey . " is " . $displayValue . ".",
                        'analysis' => null
                    ];
                }
                
                // ✅ Sum/revenue/amount queries - show with currency formatting
                if (strpos($keyName, 'sum') !== false || strpos($keyName, 'revenue') !== false || 
                    strpos($keyName, 'sales') !== false || strpos($keyName, 'amount') !== false ||
                    strpos($keyName, 'value') !== false || strpos($keyName, 'price') !== false) {
                    $displayValue = is_numeric($value) ? '$' . number_format((float)$value, 2) : $value;
                    $friendlyKey = str_replace('_', ' ', $keys[0]);
                    return [
                        'message' => "I found the total for you. The " . $friendlyKey . " is " . $displayValue . ".",
                        'analysis' => null
                    ];
                }
            }
        }
        
        // ✅ For queries with specific total/sum/value columns
        if (strpos($queryLower, 'total') !== false || strpos($queryLower, 'sum') !== false || 
            strpos($queryLower, 'revenue') !== false || strpos($queryLower, 'sales') !== false) {
            $totalKey = null;
            foreach ($firstRow as $key => $value) {
                $keyLower = strtolower($key);
                if (strpos($keyLower, 'total') !== false || strpos($keyLower, 'sum') !== false || 
                    strpos($keyLower, 'value') !== false || strpos($keyLower, 'amount') !== false ||
                    strpos($keyLower, 'revenue') !== false || strpos($keyLower, 'sales') !== false) {
                    $totalKey = $key;
                    break;
                }
            }
            
            if ($totalKey && isset($firstRow[$totalKey])) {
                $value = $firstRow[$totalKey];
                $keyLower = strtolower($totalKey);
                
                // Format based on field type
                if (strpos($keyLower, 'count') !== false) {
                    $displayValue = is_numeric($value) ? number_format((int)$value, 0) : $value;
                } else {
                    $displayValue = is_numeric($value) ? '$' . number_format((float)$value, 2) : $value;
                }
                
                return [
                    'message' => "I found the total you're looking for. The " . str_replace('_', ' ', $totalKey) . " is " . $displayValue . ".",
                    'analysis' => null
                ];
            }
        }
        
        // Default friendly message
        $message = "I found " . $count . " result" . ($count > 1 ? 's' : '') . " for your query. ";
        
        if ($count > 0 && $count <= 5) {
            $message .= "Here are the details:";
        } elseif ($count > 5) {
            $message .= "Here are the top results:";
        }
        
        return [
            'message' => $message,
            'analysis' => null
        ];
    }
    
    /**
     * ✅ Check if SQL query uses HPOS tables (wc_orders, wc_order_stats)
     * Returns true if query uses HPOS tables, false otherwise
     */
    private function isHPOSQuery($sqlQuery)
    {
        $sqlLower = strtolower($sqlQuery);
        // Check for HPOS table patterns (handle both wp_wc_orders and wc_orders)
        $hposPatterns = [
            '/\bwc_orders\b/i',           // Matches wc_orders
            '/wp_wc_orders/i',             // Matches wp_wc_orders (underscore is word char, so \b might not work)
            '/\bwc_order_stats\b/i',
            '/wp_wc_order_stats/i',
            '/\bwc_order_product_lookup\b/i',
            '/wp_wc_order_product_lookup/i',
        ];
        
        foreach ($hposPatterns as $pattern) {
            if (preg_match($pattern, $sqlLower)) {
                Log::info("🔍 Detected HPOS table in query: " . $pattern);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * ✅ Try legacy order query using wp_posts and wp_postmeta tables
     * This is for old WordPress sites where orders are stored as posts with post_type='shop_order'
     * 
     * @param string $userQuery Original user query
     * @param string $originalSqlQuery Original SQL query that returned 0 results
     * @param bool $isCountQuery Whether the original query was a COUNT query
     * @return array|null Returns result array with 'data' and 'sql_query' if successful, null if should not try, or array with 'error' if failed
     */
    private function tryLegacyOrderQuery($userQuery, $originalSqlQuery, $isCountQuery = false)
    {
        try {
            Log::info("🔄 Attempting legacy order query fallback for: " . $userQuery);
            
            // Get WordPress config to find posts table name
            $wpInfo = $this->configService->getWordPressInfo();
            $isMultisite = $wpInfo['is_multisite'] ?? false;
            $currentSiteId = $wpInfo['current_site_id'] ?? 1;
            
            // Detect posts table name
            $postsTable = $this->detectPostsTableName($currentSiteId, $isMultisite);
            if (!$postsTable) {
                Log::warning("⚠️ Could not detect posts table name for legacy query");
                return null;
            }
            
            $postmetaTable = str_replace('_posts', '_postmeta', $postsTable);
            
            Log::info("📋 Using legacy tables: {$postsTable} and {$postmetaTable}");
            
            // Extract LIMIT from original query if present
            $limit = 50; // Default limit
            if (preg_match('/\bLIMIT\s+(\d+)\b/i', $originalSqlQuery, $limitMatches)) {
                $limit = (int)$limitMatches[1];
            }
            
            // Extract number from user query if "last N orders"
            if (preg_match('/\blast\s+(\d+)\s+orders?\b/i', strtolower($userQuery), $userLimitMatches)) {
                $limit = (int)$userLimitMatches[1];
            }
            
            // Build legacy SQL query
            // For COUNT queries, use: SELECT COUNT(*) FROM wp_posts WHERE post_type='shop_order' [with date filters]
            // For regular queries, use: SELECT * FROM wp_posts WHERE post_type='shop_order' ORDER BY post_date DESC LIMIT N
            if ($isCountQuery) {
                // Extract the alias name from original COUNT query if present
                $countAlias = 'total_orders';
                if (preg_match('/COUNT\s*\(\s*\*\s*\)\s+AS\s+(\w+)/i', $originalSqlQuery, $aliasMatches)) {
                    $countAlias = $aliasMatches[1];
                } elseif (preg_match('/COUNT\s*\(\s*\*\s*\)\s+(\w+)/i', $originalSqlQuery, $aliasMatches)) {
                    $countAlias = $aliasMatches[1];
                }
                
                // Extract WHERE clause from original query (date filters, etc.)
                $whereClause = "p.post_type = 'shop_order'";
                
                // Try to extract date filters from original query and convert to wp_posts format
                // Original might use: date_created >= ... AND date_created < ...
                // Legacy needs: post_date >= ... AND post_date < ...
                if (preg_match('/WHERE\s+(.+?)(?:\s+ORDER\s+BY|\s+LIMIT|$)/is', $originalSqlQuery, $whereMatches)) {
                    $originalWhere = $whereMatches[1];
                    // Replace date_created with post_date for legacy tables
                    $legacyWhere = preg_replace('/\bdate_created\b/i', 'post_date', $originalWhere);
                    // Replace date_created_gmt with post_date_gmt
                    $legacyWhere = preg_replace('/\bdate_created_gmt\b/i', 'post_date_gmt', $legacyWhere);
                    // If we found date filters, combine with post_type filter
                    if (trim($legacyWhere) && $legacyWhere !== $originalWhere) {
                        $whereClause = "p.post_type = 'shop_order' AND (" . $legacyWhere . ")";
                    }
                }
                
                // Also check user query for date patterns and add them
                $queryLower = strtolower($userQuery);
                if (preg_match('/\blast\s+(\d+)\s+(year|years|month|months|week|weeks|day|days)\b/i', $queryLower, $dateMatches)) {
                    $number = (int)$dateMatches[1];
                    $unit = strtolower($dateMatches[2]);
                    $unit = rtrim($unit, 's'); // Remove plural
                    
                    // Build date filter for legacy tables
                    $dateFilter = "p.post_date >= DATE_SUB(CURDATE(), INTERVAL {$number} {$unit})";
                    if (strpos($whereClause, 'post_date') === false) {
                        $whereClause .= " AND " . $dateFilter;
                    }
                } elseif (preg_match('/\b(last|past)\s+(\d+)\s+(year|years|month|months)\b/i', $queryLower, $dateMatches)) {
                    $number = (int)$dateMatches[2];
                    $unit = strtolower($dateMatches[3]);
                    $unit = rtrim($unit, 's');
                    
                    $dateFilter = "p.post_date >= DATE_SUB(CURDATE(), INTERVAL {$number} {$unit})";
                    if (strpos($whereClause, 'post_date') === false) {
                        $whereClause .= " AND " . $dateFilter;
                    }
                }
                
                $legacySql = "SELECT COUNT(*) AS {$countAlias} " .
                            "FROM `{$postsTable}` p " .
                            "WHERE {$whereClause}";
                
                Log::info("💾 Executing legacy COUNT order query: " . $legacySql);
            } else {
                // Include common order fields that users might expect
                $legacySql = "SELECT p.ID as order_id, p.post_date as order_date, p.post_date_gmt as order_date_gmt, " .
                            "p.post_modified as order_modified, p.post_modified_gmt as order_modified_gmt, " .
                            "p.post_status as status, p.post_title, p.post_excerpt, p.post_content, " .
                            "p.post_parent as parent_id, p.post_author as customer_id " .
                            "FROM `{$postsTable}` p " .
                            "WHERE p.post_type = 'shop_order' " .
                            "ORDER BY p.post_date DESC " .
                            "LIMIT {$limit}";
                
                Log::info("💾 Executing legacy order query: " . $legacySql);
            }
            
            // Execute the legacy query
            $legacyResult = $this->mysqlService->executeSQLQuery($legacySql);
            
            if (isset($legacyResult['error'])) {
                Log::warning("⚠️ Legacy order query execution failed: " . $legacyResult['error']);
                return ['error' => $legacyResult['error']];
            }
            
            // Check if we got results
            if (empty($legacyResult) || (isset($legacyResult['message']) && count($legacyResult) === 1)) {
                Log::info("ℹ️ Legacy order query also returned 0 results");
                return null;
            }
            
            // For COUNT queries, return the count result directly
            if ($isCountQuery) {
                // COUNT queries return a single row with the count value
                // Check if we have a valid count result
                $hasCountResult = false;
                $countValue = 0;
                
                // Try multiple ways to extract the count (handle both objects and arrays)
                foreach ($legacyResult as $key => $value) {
                    // Convert object to array if needed (DB::select returns objects)
                    $valueArray = is_object($value) ? (array)$value : (is_array($value) ? $value : []);
                    
                    if (is_numeric($key) && is_array($valueArray) && !empty($valueArray)) {
                        // Extract count value from the result row
                        foreach ($valueArray as $colKey => $colValue) {
                            if (is_numeric($colValue)) {
                                $countValue = (int)$colValue;
                                $hasCountResult = true;
                                Log::info("✅ Legacy COUNT query found {$countValue} orders in wp_posts (from column: {$colKey})");
                                break;
                            }
                        }
                        if ($hasCountResult) break;
                    }
                }
                
                // Also check result[0] directly if not found yet
                if (!$hasCountResult && isset($legacyResult[0])) {
                    $row = is_object($legacyResult[0]) ? (array)$legacyResult[0] : (is_array($legacyResult[0]) ? $legacyResult[0] : []);
                    if (is_array($row) && !empty($row)) {
                        foreach ($row as $colKey => $colValue) {
                            if (is_numeric($colValue)) {
                                $countValue = (int)$colValue;
                                $hasCountResult = true;
                                Log::info("✅ Legacy COUNT query found {$countValue} orders in wp_posts (from result[0], column: {$colKey})");
                                break;
                            }
                        }
                    }
                }
                
                // Return result if we found a count (even if 0, we need to return it to confirm)
                if ($hasCountResult) {
                    Log::info("✅ Legacy COUNT query result: {$countValue} orders found");
                    return [
                        'data' => $legacyResult,
                        'sql_query' => $legacySql,
                        'count_value' => $countValue
                    ];
                } else {
                    Log::info("ℹ️ Legacy COUNT query returned 0 or no valid count - could not extract count value");
                    return null;
                }
            }
            
            // For non-COUNT queries, check if we have actual data rows
            $hasDataRows = false;
            foreach ($legacyResult as $key => $value) {
                if (is_numeric($key) || (is_array($value) && !isset($value['message']))) {
                    $hasDataRows = true;
                    break;
                }
            }
            
            if ($hasDataRows) {
                Log::info("✅ Legacy order query found " . count($legacyResult) . " results!");
                
                // Optionally enrich with postmeta data (order totals, customer info, etc.)
                $enrichedResult = $this->enrichLegacyOrderResults($legacyResult, $postmetaTable);
                
                return [
                    'data' => $enrichedResult,
                    'sql_query' => $legacySql
                ];
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error("❌ Error in legacy order query fallback: " . $e->getMessage());
            Log::error("❌ Stack trace: " . $e->getTraceAsString());
            return null;
        }
    }
    
    /**
     * ✅ Try legacy order query for customer details using wp_posts and wp_users tables
     * This handles queries like "latest 5 ordered customers details" when HPOS tables don't exist
     * 
     * @param string $userQuery Original user query
     * @param string $originalSqlQuery Original SQL query that failed (for reference)
     * @return array|null Returns result array with 'data' and 'sql_query' if successful, null if should not try, or array with 'error' if failed
     */
    private function tryLegacyOrderQueryForCustomerDetails($userQuery, $originalSqlQuery)
    {
        try {
            Log::info("🔄 Attempting legacy customer order details query fallback for: " . $userQuery);
            
            // Get WordPress config to find posts and users table names
            $wpInfo = $this->configService->getWordPressInfo();
            $isMultisite = $wpInfo['is_multisite'] ?? false;
            $currentSiteId = $wpInfo['current_site_id'] ?? 1;
            
            // Detect posts table name
            $postsTable = $this->detectPostsTableName($currentSiteId, $isMultisite);
            if (!$postsTable) {
                Log::warning("⚠️ Could not detect posts table name for legacy customer query");
                return null;
            }
            
            // Detect users table name (usually wp_users or wp_{site_id}_users for multisite)
            $usersTable = 'wp_users';
            if ($isMultisite && $currentSiteId > 1) {
                // For multisite, users table might be site-specific
                $dbPrefix = $wpInfo['db_prefix'] ?? 'wp_';
                $usersTable = $dbPrefix . $currentSiteId . '_users';
            }
            
            // Verify users table exists
            try {
                $tableCheck = DB::select("SHOW TABLES LIKE '{$usersTable}'");
                if (empty($tableCheck)) {
                    // Try default wp_users
                    $usersTable = 'wp_users';
                    $tableCheck = DB::select("SHOW TABLES LIKE '{$usersTable}'");
                    if (empty($tableCheck)) {
                        Log::warning("⚠️ Users table not found: {$usersTable}");
                        return null;
                    }
                }
            } catch (\Exception $e) {
                Log::warning("⚠️ Could not verify users table: " . $e->getMessage());
                return null;
            }
            
            Log::info("📋 Using legacy tables: {$postsTable} and {$usersTable}");
            
            // Extract LIMIT from original query or user query
            $limit = 5; // Default for "latest N"
            if (preg_match('/\bLIMIT\s+(\d+)\b/i', $originalSqlQuery, $limitMatches)) {
                $limit = (int)$limitMatches[1];
            }
            if (preg_match('/\blast\s+(\d+)\b/i', strtolower($userQuery), $userLimitMatches)) {
                $limit = (int)$userLimitMatches[1];
            }
            if (preg_match('/\blatest\s+(\d+)\b/i', strtolower($userQuery), $latestMatches)) {
                $limit = (int)$latestMatches[1];
            }
            
            // Build legacy SQL query joining orders with customers
            // Get customer details from wp_users and order details from wp_posts
            // Match orders to customers via post_author (customer_id) or postmeta
            $legacySql = "SELECT 
                u.ID as customer_id,
                u.display_name,
                u.user_email,
                p.ID as order_id,
                p.post_date as date_created,
                p.post_date_gmt as date_created_gmt,
                p.post_modified as order_modified,
                p.post_status as status,
                p.post_title as order_title
            FROM `{$postsTable}` p
            LEFT JOIN `{$usersTable}` u ON p.post_author = u.ID
            WHERE p.post_type = 'shop_order'
            ORDER BY p.post_date DESC
            LIMIT {$limit}";
            
            Log::info("💾 Executing legacy customer order details query: " . $legacySql);
            
            // Execute the legacy query
            $legacyResult = $this->mysqlService->executeSQLQuery($legacySql);
            
            if (isset($legacyResult['error'])) {
                Log::warning("⚠️ Legacy customer order query execution failed: " . $legacyResult['error']);
                return ['error' => $legacyResult['error']];
            }
            
            // Check if we got results
            if (empty($legacyResult) || (isset($legacyResult['message']) && count($legacyResult) === 1)) {
                Log::info("ℹ️ Legacy customer order query also returned 0 results");
                return null;
            }
            
            // Check if we have actual data rows
            $hasDataRows = false;
            foreach ($legacyResult as $key => $value) {
                if (is_numeric($key) || (is_array($value) && !isset($value['message']))) {
                    $hasDataRows = true;
                    break;
                }
            }
            
            if ($hasDataRows) {
                Log::info("✅ Legacy customer order query found " . count($legacyResult) . " results!");
                
                // Enrich with postmeta data (order totals, billing info, etc.)
                $postmetaTable = str_replace('_posts', '_postmeta', $postsTable);
                $enrichedResult = $this->enrichLegacyOrderResults($legacyResult, $postmetaTable);
                
                return [
                    'data' => $enrichedResult,
                    'sql_query' => $legacySql
                ];
            }
            
            Log::info("ℹ️ Legacy customer order query returned no data rows");
            return null;
            
        } catch (\Exception $e) {
            Log::error("❌ Error in tryLegacyOrderQueryForCustomerDetails: " . $e->getMessage());
            Log::error("❌ Stack trace: " . $e->getTraceAsString());
            return ['error' => "Failed to execute legacy customer order query: " . $e->getMessage()];
        }
    }
    
    /**
     * ✅ Comprehensive research across ALL order tables before confirming 0 results
     * This is a data analytical tool - confidence and thorough research are critical
     * 
     * @param string $userQuery Original user query
     * @param string $originalSqlQuery Original SQL query that returned 0 results
     * @param bool $isCountQuery Whether the original query was a COUNT query
     * @param int|null $originalCountValue The count value from original query (if COUNT query)
     * @return array|null Returns result array with 'data', 'sql_query', and 'count_value' if successful, null if all tables confirm 0
     */
    private function researchAllOrderTables($userQuery, $originalSqlQuery, $isCountQuery = false, $originalCountValue = null)
    {
        try {
            Log::info("🔬 Starting comprehensive order research across all available tables...");
            
            // Get WordPress config
            $wpInfo = $this->configService->getWordPressInfo();
            $isMultisite = $wpInfo['is_multisite'] ?? false;
            $currentSiteId = $wpInfo['current_site_id'] ?? 1;
            
            // Get all tables in database
            $allTables = \Illuminate\Support\Facades\DB::select('SHOW TABLES');
            $allTableNames = array_map(fn($table) => reset($table), $allTables);
            
            // Identify all order-related tables
            $orderTables = [];
            foreach ($allTableNames as $table) {
                $tableLower = strtolower($table);
                if (strpos($tableLower, 'order') !== false || 
                    strpos($tableLower, 'wc_order') !== false ||
                    preg_match('/_posts$/', $tableLower)) {
                    $orderTables[] = $table;
                }
            }
            
            Log::info("📋 Found " . count($orderTables) . " potential order-related tables: " . implode(', ', $orderTables));
            
            // Research strategy: Check tables in priority order
            $researchResults = [];
            
            // 1. Check wp_posts with post_type='shop_order' (legacy)
            $postsTable = $this->detectPostsTableName($currentSiteId, $isMultisite);
            if ($postsTable) {
                Log::info("🔍 Research Step 1: Checking {$postsTable} (legacy orders)...");
                $result = $this->tryLegacyOrderQuery($userQuery, $originalSqlQuery, $isCountQuery);
                if ($result !== null && !isset($result['error'])) {
                    // For COUNT queries, check count_value; for others, check data
                    if ($isCountQuery) {
                        $foundCount = isset($result['count_value']) ? $result['count_value'] : 0;
                        if ($foundCount > 0) {
                            Log::info("✅ Found {$foundCount} orders in {$postsTable}!");
                            return $result;
                        }
                    } elseif (!empty($result['data'])) {
                        $count = is_array($result['data']) ? count($result['data']) : 1;
                        Log::info("✅ Found {$count} orders in {$postsTable}!");
                        return $result;
                    }
                }
                $researchResults['wp_posts'] = $result;
            }
            
            // 2. Check wp_wc_orders (HPOS alternative)
            foreach ($orderTables as $table) {
                if (preg_match('/wc_orders$/', strtolower($table)) && !preg_match('/wc_order_stats/', strtolower($table))) {
                    Log::info("🔍 Research Step 2: Checking {$table} (HPOS orders)...");
                    $result = $this->queryOrderTable($table, $userQuery, $originalSqlQuery, $isCountQuery);
                    if ($result !== null && !isset($result['error'])) {
                        if ($isCountQuery) {
                            $foundCount = isset($result['count_value']) ? $result['count_value'] : 0;
                            if ($foundCount > 0) {
                                Log::info("✅ Found {$foundCount} orders in {$table}!");
                                return $result;
                            }
                        } elseif (!empty($result['data'])) {
                            $count = is_array($result['data']) ? count($result['data']) : 1;
                            Log::info("✅ Found {$count} orders in {$table}!");
                            return $result;
                        }
                    }
                    $researchResults[$table] = $result;
                }
            }
            
            // 3. Check wp_woocommerce_order_items (legacy order items)
            foreach ($orderTables as $table) {
                if (preg_match('/woocommerce_order_items$/', strtolower($table))) {
                    Log::info("🔍 Research Step 3: Checking {$table} (order items)...");
                    $result = $this->queryOrderItemsTable($table, $userQuery, $originalSqlQuery, $isCountQuery);
                    if ($result !== null && !isset($result['error'])) {
                        if ($isCountQuery) {
                            $foundCount = isset($result['count_value']) ? $result['count_value'] : 0;
                            if ($foundCount > 0) {
                                Log::info("✅ Found {$foundCount} distinct orders in {$table}!");
                                return $result;
                            }
                        } elseif (!empty($result['data'])) {
                            $count = is_array($result['data']) ? count($result['data']) : 1;
                            Log::info("✅ Found {$count} order items in {$table}!");
                            return $result;
                        }
                    }
                    $researchResults[$table] = $result;
                }
            }
            
            // 4. Check any other order-related tables
            foreach ($orderTables as $table) {
                $tableLower = strtolower($table);
                // Skip already checked tables
                if (preg_match('/wc_order_stats$/', $tableLower) || 
                    preg_match('/wc_orders$/', $tableLower) ||
                    preg_match('/_posts$/', $tableLower) ||
                    preg_match('/woocommerce_order_items$/', $tableLower)) {
                    continue;
                }
                
                if (strpos($tableLower, 'order') !== false) {
                    Log::info("🔍 Research Step 4: Checking {$table} (alternative order table)...");
                    $result = $this->queryOrderTable($table, $userQuery, $originalSqlQuery, $isCountQuery);
                    if ($result !== null && !isset($result['error'])) {
                        if ($isCountQuery) {
                            $foundCount = isset($result['count_value']) ? $result['count_value'] : 0;
                            if ($foundCount > 0) {
                                Log::info("✅ Found {$foundCount} orders in {$table}!");
                                return $result;
                            }
                        } elseif (!empty($result['data'])) {
                            $count = is_array($result['data']) ? count($result['data']) : 1;
                            Log::info("✅ Found {$count} orders in {$table}!");
                            return $result;
                        }
                    }
                    $researchResults[$table] = $result;
                }
            }
            
            // 5. Final check: If COUNT query, verify with a simple COUNT on posts table (without date filters)
            if ($isCountQuery && $postsTable) {
                Log::info("🔍 Research Step 5: Final verification with simple COUNT on {$postsTable} (no date filters)...");
                $simpleCountSql = "SELECT COUNT(*) AS total_orders FROM `{$postsTable}` WHERE post_type = 'shop_order'";
                $simpleResult = $this->mysqlService->executeSQLQuery($simpleCountSql);
                
                if (!isset($simpleResult['error']) && !empty($simpleResult)) {
                    $countValue = 0;
                    $hasCount = false;
                    
                    foreach ($simpleResult as $key => $row) {
                        // Convert object to array if needed (DB::select returns objects)
                        $rowArray = is_object($row) ? (array)$row : (is_array($row) ? $row : []);
                        
                        if (is_array($rowArray) && !empty($rowArray)) {
                            foreach ($rowArray as $colKey => $colValue) {
                                if (is_numeric($colValue)) {
                                    $countValue = (int)$colValue;
                                    $hasCount = true;
                                    Log::info("✅ Final verification found {$countValue} orders in {$postsTable} (from column: {$colKey})!");
                                    break;
                                }
                            }
                            if ($hasCount) break;
                        }
                    }
                    
                    // Also check result[0] directly if not found yet
                    if (!$hasCount && isset($simpleResult[0])) {
                        $row = is_object($simpleResult[0]) ? (array)$simpleResult[0] : (is_array($simpleResult[0]) ? $simpleResult[0] : []);
                        if (is_array($row) && !empty($row)) {
                            foreach ($row as $colKey => $colValue) {
                                if (is_numeric($colValue)) {
                                    $countValue = (int)$colValue;
                                    $hasCount = true;
                                    Log::info("✅ Final verification found {$countValue} orders in {$postsTable} (from result[0], column: {$colKey})!");
                                    break;
                                }
                            }
                        }
                    }
                    
                    if ($hasCount && $countValue > 0) {
                        return [
                            'data' => $simpleResult,
                            'sql_query' => $simpleCountSql,
                            'count_value' => $countValue
                        ];
                    }
                }
            }
            
            // All research methods returned 0 or null - confidently confirm 0 results
            Log::info("🔬 Comprehensive research complete. All " . count($researchResults) . " tables confirmed: 0 orders found.");
            return null;
            
        } catch (\Exception $e) {
            Log::error("❌ Error in comprehensive order research: " . $e->getMessage());
            Log::error("❌ Stack trace: " . $e->getTraceAsString());
            return null;
        }
    }
    
    /**
     * ✅ Query a generic order table (for HPOS or alternative order tables)
     * Safely checks for column existence before using them
     */
    private function queryOrderTable($tableName, $userQuery, $originalSqlQuery, $isCountQuery = false)
    {
        try {
            // First, check what columns exist in this table
            $columns = \Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM `{$tableName}`");
            $columnNames = array_map(fn($col) => $col->Field, $columns);
            $columnNamesLower = array_map('strtolower', $columnNames);
            
            // Find appropriate date column
            $dateColumn = null;
            $dateColumns = ['date_created', 'date_created_gmt', 'order_date', 'created_date', 'post_date', 'created_at'];
            foreach ($dateColumns as $dc) {
                if (in_array(strtolower($dc), $columnNamesLower)) {
                    $dateColumn = $dc;
                    Log::info("📋 Found date column '{$dateColumn}' in {$tableName}");
                    break;
                }
            }
            
            // Extract date filters from original query (only if we have a date column)
            $whereClause = "1=1";
            $dateFilter = null;
            if ($dateColumn) {
                $dateFilter = $this->extractDateFilterFromQuery($userQuery, $originalSqlQuery, $dateColumn);
            }
            
            if ($isCountQuery) {
                $countAlias = 'total_orders';
                if (preg_match('/COUNT\s*\(\s*\*\s*\)\s+AS\s+(\w+)/i', $originalSqlQuery, $aliasMatches)) {
                    $countAlias = $aliasMatches[1];
                }
                
                $sql = "SELECT COUNT(*) AS {$countAlias} FROM `{$tableName}` WHERE {$whereClause}";
                if ($dateFilter) {
                    $sql .= " AND {$dateFilter}";
                }
            } else {
                $limit = 50;
                if (preg_match('/\bLIMIT\s+(\d+)\b/i', $originalSqlQuery, $limitMatches)) {
                    $limit = (int)$limitMatches[1];
                }
                if (preg_match('/\blast\s+(\d+)\s+orders?\b/i', strtolower($userQuery), $userLimitMatches)) {
                    $limit = (int)$userLimitMatches[1];
                }
                
                $sql = "SELECT * FROM `{$tableName}` WHERE {$whereClause}";
                if ($dateFilter) {
                    $sql .= " AND {$dateFilter}";
                }
                if ($dateColumn) {
                    $sql .= " ORDER BY {$dateColumn} DESC LIMIT {$limit}";
                } else {
                    $sql .= " LIMIT {$limit}";
                }
            }
            
            Log::info("💾 Querying {$tableName}: " . $sql);
            $result = $this->mysqlService->executeSQLQuery($sql);
            
            if (isset($result['error'])) {
                // If error is about column not found, skip this table silently
                if (strpos($result['error'], 'Column not found') !== false || 
                    strpos($result['error'], 'Unknown column') !== false) {
                    Log::info("ℹ️ {$tableName} doesn't have required columns, skipping");
                    return null;
                }
                return ['error' => $result['error']];
            }
            
            if (empty($result) || (isset($result['message']) && count($result) === 1)) {
                return null;
            }
            
            // For COUNT queries, extract the count value
            if ($isCountQuery) {
                $countValue = 0;
                $hasCount = false;
                
                foreach ($result as $key => $value) {
                    // Convert object to array if needed
                    $valueArray = is_object($value) ? (array)$value : (is_array($value) ? $value : []);
                    
                    if (is_numeric($key) && is_array($valueArray) && !empty($valueArray)) {
                        foreach ($valueArray as $colKey => $colValue) {
                            if (is_numeric($colValue)) {
                                $countValue = (int)$colValue;
                                $hasCount = true;
                                Log::info("✅ Found {$countValue} orders in {$tableName} (from column: {$colKey})");
                                break;
                            }
                        }
                        if ($hasCount) break;
                    }
                }
                
                // Also check result[0] directly if not found yet
                if (!$hasCount && isset($result[0])) {
                    $row = is_object($result[0]) ? (array)$result[0] : (is_array($result[0]) ? $result[0] : []);
                    if (is_array($row) && !empty($row)) {
                        foreach ($row as $colKey => $colValue) {
                            if (is_numeric($colValue)) {
                                $countValue = (int)$colValue;
                                $hasCount = true;
                                Log::info("✅ Found {$countValue} orders in {$tableName} (from result[0], column: {$colKey})");
                                break;
                            }
                        }
                    }
                }
                
                if ($hasCount && $countValue > 0) {
                    return [
                        'data' => $result,
                        'sql_query' => $sql,
                        'count_value' => $countValue
                    ];
                } elseif ($hasCount) {
                    // Return even if 0 to confirm the check was done
                    return [
                        'data' => $result,
                        'sql_query' => $sql,
                        'count_value' => 0
                    ];
                }
            }
            
            // Check if we have actual data rows (for non-COUNT queries)
            foreach ($result as $key => $value) {
                if (is_numeric($key) || (is_array($value) && !isset($value['message']))) {
                    return [
                        'data' => $result,
                        'sql_query' => $sql
                    ];
                }
            }
            
            return null;
        } catch (\Exception $e) {
            Log::warning("⚠️ Error querying {$tableName}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * ✅ Query order items table
     */
    private function queryOrderItemsTable($tableName, $userQuery, $originalSqlQuery, $isCountQuery = false)
    {
        try {
            if ($isCountQuery) {
                $countAlias = 'total_orders';
                if (preg_match('/COUNT\s*\(\s*\*\s*\)\s+AS\s+(\w+)/i', $originalSqlQuery, $aliasMatches)) {
                    $countAlias = $aliasMatches[1];
                }
                
                // Count distinct order_id from order items
                $sql = "SELECT COUNT(DISTINCT order_id) AS {$countAlias} FROM `{$tableName}`";
                Log::info("💾 Querying {$tableName}: " . $sql);
                $result = $this->mysqlService->executeSQLQuery($sql);
                
                if (isset($result['error'])) {
                    return ['error' => $result['error']];
                }
                
                if (!empty($result)) {
                    $countValue = 0;
                    $hasCount = false;
                    
                    foreach ($result as $key => $row) {
                        // Convert object to array if needed (DB::select returns objects)
                        $rowArray = is_object($row) ? (array)$row : (is_array($row) ? $row : []);
                        
                        if (is_array($rowArray) && !empty($rowArray)) {
                            foreach ($rowArray as $colKey => $colValue) {
                                if (is_numeric($colValue)) {
                                    $countValue = (int)$colValue;
                                    $hasCount = true;
                                    Log::info("✅ Found {$countValue} distinct orders in {$tableName} (from column: {$colKey})");
                                    break;
                                }
                            }
                            if ($hasCount) break;
                        }
                    }
                    
                    // Also check result[0] directly if not found yet
                    if (!$hasCount && isset($result[0])) {
                        $row = is_object($result[0]) ? (array)$result[0] : (is_array($result[0]) ? $result[0] : []);
                        if (is_array($row) && !empty($row)) {
                            foreach ($row as $colKey => $colValue) {
                                if (is_numeric($colValue)) {
                                    $countValue = (int)$colValue;
                                    $hasCount = true;
                                    Log::info("✅ Found {$countValue} distinct orders in {$tableName} (from result[0], column: {$colKey})");
                                    break;
                                }
                            }
                        }
                    }
                    
                    if ($hasCount && $countValue > 0) {
                        return [
                            'data' => $result,
                            'sql_query' => $sql,
                            'count_value' => $countValue
                        ];
                    } elseif ($hasCount) {
                        // Return even if 0 to confirm check was done
                        return [
                            'data' => $result,
                            'sql_query' => $sql,
                            'count_value' => 0
                        ];
                    }
                }
            }
            
            return null;
        } catch (\Exception $e) {
            Log::warning("⚠️ Error querying order items table {$tableName}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * ✅ Extract date filter from query and convert to appropriate column name
     */
    private function extractDateFilterFromQuery($userQuery, $originalSqlQuery, $dateColumn = 'date_created')
    {
        $queryLower = strtolower($userQuery);
        
        // Extract date filters from original SQL
        if (preg_match('/WHERE\s+(.+?)(?:\s+ORDER\s+BY|\s+LIMIT|$)/is', $originalSqlQuery, $whereMatches)) {
            $originalWhere = $whereMatches[1];
            // Replace date column names with target column
            $dateFilter = preg_replace('/\b(date_created|date_created_gmt|order_date|post_date)\b/i', $dateColumn, $originalWhere);
            return $dateFilter;
        }
        
        // Extract from user query
        if (preg_match('/\blast\s+(\d+)\s+(year|years|month|months|week|weeks|day|days)\b/i', $queryLower, $dateMatches)) {
            $number = (int)$dateMatches[1];
            $unit = strtolower($dateMatches[2]);
            $unit = rtrim($unit, 's');
            return "{$dateColumn} >= DATE_SUB(CURDATE(), INTERVAL {$number} {$unit})";
        } elseif (preg_match('/\b(last|past)\s+(\d+)\s+(year|years|month|months)\b/i', $queryLower, $dateMatches)) {
            $number = (int)$dateMatches[2];
            $unit = strtolower($dateMatches[3]);
            $unit = rtrim($unit, 's');
            return "{$dateColumn} >= DATE_SUB(CURDATE(), INTERVAL {$number} {$unit})";
        }
        
        return null;
    }
    
    /**
     * ✅ Detect posts table name based on site ID and multisite status
     */
    private function detectPostsTableName($currentSiteId, $isMultisite)
    {
        try {
            $allTables = \Illuminate\Support\Facades\DB::select('SHOW TABLES');
            $allTableNames = array_map(fn($table) => reset($table), $allTables);
            
            // Detect multisite pattern
            $hasMultisitePattern = false;
            foreach ($allTableNames as $tableName) {
                if (preg_match('/^wp\d+_\d+_/', $tableName)) {
                    $hasMultisitePattern = true;
                    break;
                }
            }
            
            if ($hasMultisitePattern || $isMultisite) {
                // Find posts table for current site ID
                $pattern = '/^wp\d+_' . preg_quote($currentSiteId, '/') . '_posts$/';
                foreach ($allTableNames as $tableName) {
                    if (preg_match($pattern, $tableName)) {
                        return $tableName;
                    }
                }
            } else {
                // Standard WordPress - find wp_posts or wp{number}_posts
                foreach ($allTableNames as $tableName) {
                    if (preg_match('/^wp\d*_posts$/', $tableName)) {
                        return $tableName;
                    }
                }
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error("❌ Error detecting posts table: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * ✅ Enrich legacy order results with postmeta data (totals, customer info, etc.)
     */
    private function enrichLegacyOrderResults($orders, $postmetaTable)
    {
        if (empty($orders)) {
            return $orders;
        }
        
        try {
            // Get order IDs
            $orderIds = [];
            foreach ($orders as $order) {
                $orderObj = is_object($order) ? (array)$order : $order;
                if (isset($orderObj['order_id'])) {
                    $orderIds[] = (int)$orderObj['order_id'];
                } elseif (isset($orderObj['ID'])) {
                    $orderIds[] = (int)$orderObj['ID'];
                }
            }
            
            if (empty($orderIds)) {
                return $orders;
            }
            
            $orderIdsStr = implode(',', $orderIds);
            
            // Fetch relevant postmeta data
            $metaQuery = "SELECT post_id, meta_key, meta_value 
                         FROM `{$postmetaTable}` 
                         WHERE post_id IN ({$orderIdsStr}) 
                         AND meta_key IN ('_order_total', '_order_currency', '_billing_email', '_billing_first_name', '_billing_last_name', '_billing_phone', '_order_key', '_customer_user')";
            
            $metaResults = \Illuminate\Support\Facades\DB::select($metaQuery);
            
            // Organize meta data by post_id
            $metaByOrder = [];
            foreach ($metaResults as $meta) {
                $metaObj = is_object($meta) ? (array)$meta : $meta;
                $postId = (int)$metaObj['post_id'];
                $metaKey = $metaObj['meta_key'];
                $metaValue = $metaObj['meta_value'];
                
                if (!isset($metaByOrder[$postId])) {
                    $metaByOrder[$postId] = [];
                }
                
                // Convert meta keys to readable names
                $readableKey = str_replace('_', ' ', $metaKey);
                $readableKey = ucwords($readableKey);
                $readableKey = str_replace(' ', '_', $readableKey);
                
                $metaByOrder[$postId][$readableKey] = $metaValue;
            }
            
            // Merge meta data into orders
            $enrichedOrders = [];
            foreach ($orders as $order) {
                $orderObj = is_object($order) ? (array)$order : $order;
                $orderId = isset($orderObj['order_id']) ? (int)$orderObj['order_id'] : (isset($orderObj['ID']) ? (int)$orderObj['ID'] : null);
                
                if ($orderId && isset($metaByOrder[$orderId])) {
                    $orderObj = array_merge($orderObj, $metaByOrder[$orderId]);
                }
                
                $enrichedOrders[] = $orderObj;
            }
            
            return $enrichedOrders;
            
        } catch (\Exception $e) {
            Log::warning("⚠️ Could not enrich legacy order results: " . $e->getMessage());
            // Return original orders if enrichment fails
            return $orders;
        }
    }
    
    /**
     * ✅ Filter out invalid product IDs (0, NULL, or negative)
     * Product ID 0 is not a valid product - it indicates missing or invalid data
     * 
     * @param array $result Database query results
     * @return array Results with invalid product IDs filtered out
     */
    private function filterInvalidProductIds($result)
    {
        if (empty($result) || !is_array($result)) {
            return $result;
        }
        
        $filtered = [];
        foreach ($result as $key => $row) {
            if (is_object($row)) {
                $row = (array)$row;
            }
            
            // Check if this row has a product_id field
            if (isset($row['product_id'])) {
                $productId = is_numeric($row['product_id']) ? (int)$row['product_id'] : null;
                
                // Filter out invalid product IDs (0, NULL, or negative)
                if ($productId === null || $productId <= 0) {
                    Log::info("⚠️ Filtering out invalid product_id: " . ($productId ?? 'NULL'));
                    continue; // Skip this row
                }
            }
            
            $filtered[$key] = $row;
        }
        
        // Re-index array if we removed items
        if (count($filtered) !== count($result)) {
            $filtered = array_values($filtered);
            Log::info("✅ Filtered out " . (count($result) - count($filtered)) . " rows with invalid product IDs");
        }
        
        return $filtered;
    }
    
    /**
     * ✅ Convert all objects in result array to arrays for proper JSON encoding
     * DB::select() returns objects, but frontend needs arrays
     * 
     * @param array $result Database query results
     * @return array Results with all objects converted to arrays
     */
    private function convertObjectsToArrays($result)
    {
        if (empty($result) || !is_array($result)) {
            return $result;
        }
        
        $converted = [];
        foreach ($result as $key => $value) {
            if (is_object($value)) {
                // Convert object to array recursively
                $converted[$key] = json_decode(json_encode($value), true);
            } elseif (is_array($value)) {
                // Recursively convert nested objects
                $converted[$key] = $this->convertObjectsToArrays($value);
            } else {
                $converted[$key] = $value;
            }
        }
        
        return $converted;
    }
    
    private function getHelpfulResponse($query)
    {
        $lowerQuery = strtolower($query);
        
        // Greetings
        if (preg_match('/\b(hi|hello|hey|greetings|good morning|good afternoon|good evening)\b/i', $query)) {
            return "Hello! I'm Hey Trisha, your WordPress assistant. I'm here to help you manage your WordPress site. " .
                   "You can ask me to show posts, products, or other data from your database. " .
                   "I can also help you edit posts or products by name or ID, and create new content. " .
                   "Try asking me something like 'Show me the last 10 posts' or 'Edit post named Your Post Title' and I'll help you right away!";
        }
        
        // Questions about capabilities
        if (preg_match('/\b(what|how|can you|help|capabilities|features)\b/i', $query)) {
            return "I can help you manage your WordPress site in many ways! " .
                   "I can view data from your database - just ask me things like 'Show me the last 10 posts', 'List all products', or 'Get all users' and I'll fetch that information for you. " .
                   "I can also edit your content - you can say 'Edit post named Your Post Title', 'Update product Laptop with price 1200', or 'Edit post ID 123'. " .
                   "And I can create new content too - try 'Create a new post titled Hello World' or 'Add a product named Widget priced at 50'. " .
                   "Just ask me in natural language and I'll understand what you need!";
        }
        
        // Default helpful message
        return "I'm not sure how to help with that specific request, but I'm here to assist you! " .
               "I can help you view data from your WordPress site - just ask me to show posts, list products, or get information about users. " .
               "I can also edit your content - you can edit posts or products by name or ID. " .
               "And I can create new posts or products for you. " .
               "Try rephrasing your request in a different way, or ask me 'What can you do?' and I'll explain all my capabilities!";
    }
}

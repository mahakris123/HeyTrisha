<?php
/**
 * REST API Handler for Hey Trisha
 * 
 * Provides endpoints for the external API server to:
 * 1. Execute SQL queries
 * 2. Get database schema
 */

if (!defined('ABSPATH')) {
    exit;
}

class HeyTrisha_REST_API {
    
    /**
     * Register REST API routes
     */
    public static function register_routes() {
        // Execute SQL query
        register_rest_route('heytrisha/v1', '/execute-sql', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'execute_sql'],
            'permission_callback' => [__CLASS__, 'validate_api_key'],
        ]);
        
        // Get database schema
        register_rest_route('heytrisha/v1', '/schema', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_schema'],
            'permission_callback' => [__CLASS__, 'validate_api_key'],
        ]);
        
        // Health check (for API server to verify WordPress is accessible)
        register_rest_route('heytrisha/v1', '/health', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'health_check'],
            'permission_callback' => '__return_true', // Public endpoint
        ]);
    }
    
    /**
     * Validate API key from request header
     * 
     * @param WP_REST_Request $request
     * @return bool
     */
    public static function validate_api_key($request) {
        // Get API key from header
        $api_key = $request->get_header('X-HeyTrisha-API-Key');
        
        if (empty($api_key)) {
            // Try Authorization header (Bearer token)
            $auth_header = $request->get_header('Authorization');
            if (!empty($auth_header) && preg_match('/Bearer\s+(.+)/i', $auth_header, $matches)) {
                $api_key = $matches[1];
            }
        }
        
        if (empty($api_key)) {
            return new WP_Error(
                'missing_api_key',
                'API key is required. Provide X-HeyTrisha-API-Key header or Authorization: Bearer header.',
                ['status' => 401]
            );
        }
        
        // Get stored API key
        $stored_api_key = heytrisha_get_credential(
            HeyTrisha_Secure_Credentials::KEY_API_TOKEN,
            'heytrisha_api_key',
            ''
        );
        
        if (empty($stored_api_key)) {
            return new WP_Error(
                'not_configured',
                'HeyTrisha is not configured. Please complete onboarding first.',
                ['status' => 503]
            );
        }
        
        // Validate API key
        if (!hash_equals($stored_api_key, $api_key)) {
            return new WP_Error(
                'invalid_api_key',
                'Invalid API key provided.',
                ['status' => 403]
            );
        }
        
        return true;
    }
    
    /**
     * Execute SQL query
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function execute_sql($request) {
        global $wpdb;
        
        // Get SQL from request
        $sql = $request->get_param('sql');
        
        if (empty($sql)) {
            return new WP_Error(
                'missing_sql',
                'SQL query is required',
                ['status' => 400]
            );
        }
        
        // Validate SQL
        $validation = HeyTrisha_SQL_Validator::validate($sql);
        
        if (!$validation['valid']) {
            return new WP_Error(
                'invalid_sql',
                'SQL validation failed: ' . $validation['error'],
                ['status' => 400]
            );
        }
        
        // Sanitize table names
        $sql = HeyTrisha_SQL_Validator::sanitize_table_names($sql);
        
        // Ensure LIMIT clause (max 1000 rows for safety)
        $max_limit = $request->get_param('max_limit') ?? 1000;
        $sql = HeyTrisha_SQL_Validator::ensure_limit($sql, intval($max_limit));
        
        // Execute query
        $results = $wpdb->get_results($sql, ARRAY_A);
        
        // Check for errors
        if ($wpdb->last_error) {
            return new WP_Error(
                'query_error',
                'Database error: ' . $wpdb->last_error,
                ['status' => 500]
            );
        }
        
        // Return results
        return new WP_REST_Response([
            'success' => true,
            'data' => $results,
            'row_count' => count($results),
            'sql' => $sql, // Return executed SQL for debugging
        ], 200);
    }
    
    /**
     * Get database schema
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function get_schema($request) {
        global $wpdb;
        
        // Get list of tables
        $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
        
        if ($wpdb->last_error) {
            return new WP_Error(
                'schema_error',
                'Failed to get database schema: ' . $wpdb->last_error,
                ['status' => 500]
            );
        }
        
        $schema = [];
        
        foreach ($tables as $table) {
            $table_name = $table[0];
            
            // Only include WordPress tables (with prefix)
            if (strpos($table_name, $wpdb->prefix) !== 0) {
                continue;
            }
            
            // Get columns for this table
            $columns = $wpdb->get_results("DESCRIBE `{$table_name}`", ARRAY_A);
            
            if ($wpdb->last_error) {
                continue; // Skip tables we can't describe
            }
            
            // Get row count (estimated)
            $row_count = $wpdb->get_var("SELECT COUNT(*) FROM `{$table_name}`");
            
            $schema[$table_name] = [
                'columns' => $columns,
                'row_count' => intval($row_count),
            ];
        }
        
        return new WP_REST_Response([
            'success' => true,
            'tables' => $schema,
            'table_count' => count($schema),
        ], 200);
    }
    
    /**
     * Health check endpoint
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function health_check($request) {
        global $wpdb;
        
        // Check database connection
        $db_connected = false;
        try {
            $wpdb->query("SELECT 1");
            $db_connected = empty($wpdb->last_error);
        } catch (Exception $e) {
            $db_connected = false;
        }
        
        // Check if onboarding is complete
        $onboarding_complete = get_option('heytrisha_onboarding_complete', false);
        $has_api_key = !empty(heytrisha_get_credential(
            HeyTrisha_Secure_Credentials::KEY_API_TOKEN,
            'heytrisha_api_key',
            ''
        ));
        
        return new WP_REST_Response([
            'success' => true,
            'status' => 'ok',
            'wordpress_version' => get_bloginfo('version'),
            'woocommerce_version' => defined('WC_VERSION') ? WC_VERSION : null,
            'plugin_version' => '1.0.0',
            'site_url' => get_site_url(),
            'onboarding_complete' => $onboarding_complete,
            'has_api_key' => $has_api_key,
            'database_connected' => $db_connected,
            'timestamp' => current_time('c'),
        ], 200);
    }
}

// Register routes on REST API init
add_action('rest_api_init', ['HeyTrisha_REST_API', 'register_routes']);



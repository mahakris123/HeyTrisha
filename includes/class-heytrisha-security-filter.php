<?php
/**
 * Hey Trisha - Security Filter
 * 
 * Prevents extraction of sensitive data
 * Ensures the tool is used for analytics only, not data theft
 * 
 * @package HeyTrisha
 * @since 1.0.0
 */

// Allow loading from Laravel context or when required/included
// Only prevent direct web access to this file
// If LARAVEL_START is defined, we're being loaded from Laravel - allow it
if (!defined('ABSPATH') && !defined('LARAVEL_START')) {
    // Check if this is a direct web request
    // If we're being included/required, the file will be in get_included_files()
    $included_files = function_exists('get_included_files') ? get_included_files() : array();
    $is_direct_access = !in_array(__FILE__, $included_files);
    
    // Only exit on direct web access (not when required/included)
    if ($is_direct_access && php_sapi_name() !== 'cli') {
        exit;
    }
}

class HeyTrisha_Security_Filter {
    
    // ✅ Sensitive CREDENTIAL columns that should NEVER be returned
    // Only blocking passwords, payment credentials, and site credentials
    // Personal data (emails, addresses, phones) is ALLOWED for analytics
    private static $sensitive_columns = array(
        // Passwords and authentication keys
        'password',
        'user_pass',
        'pass',
        'pwd',
        'user_password',
        'passwd',
        'user_activation_key',
        'activation_key',
        'reset_key',
        
        // Payment and card data
        'credit_card',
        'card_number',
        'cvv',
        'cvc',
        'card_cvv',
        'card_cvc',
        'card_expiry',
        'card_expiration',
        'account_number',
        'routing_number',
        'bank_account',
        
        // Site credentials and API keys
        'consumer_secret',
        'secret',
        'api_key',
        'private_key',
        'token',
        'session_token',
        'auth_key',
        'nonce',
        'salt',
        'encryption_key',
        'secret_key',
        
        // SSN (sensitive personal identifier)
        'ssn',
        'social_security',
        
        // Note: user_email, email, address, phone, username are ALLOWED for analytics
    );
    
    // ✅ Sensitive query patterns - Only blocking credential extraction, NOT analytics
    // Allow queries like "orders from email" or "total orders for user" (analytics)
    // Block queries like "give me password" or "show credit card" (extraction)
    private static $sensitive_query_patterns = array(
        // Direct password requests (anywhere in query)
        '/\b(password|passwd|pwd|pass)\b/i',
        
        // Email + password combination (email address followed by password request)
        '/\b[\w\.-]+@[\w\.-]+\.\w+\s+password\b/i',
        '/\bpassword\s+(for|of|to)\s+[\w\.-]+@[\w\.-]+\.\w+\b/i',
        
        // Give/get/show/share + anything + password (extraction, not analytics)
        '/\b(give|get|show|tell|provide|send|fetch|retrieve|list|share)\s+(me\s+)?(the\s+)?(.*\s+)?password\b/i',
        '/\b(give|get|show|tell|provide|send|fetch|retrieve|list|share)\s+(me\s+)?(the\s+)?password\s+(for|of|to|of)\b/i',
        
        // Can you + give/get/share + password
        '/\bcan\s+(you\s+)?(give|get|show|tell|provide|share)\s+(.*\s+)?password\b/i',
        
        // Credentials requests (extraction)
        '/\b(give|get|show|tell|provide|send|fetch|retrieve|list|share)\s+(me\s+)?(the\s+)?(.*\s+)?credentials\b/i',
        '/\b(give|get|show|tell|provide|send|fetch|retrieve|list|share)\s+(me\s+)?(the\s+)?credentials\s+(for|of|to|of)\b/i',
        '/\bcan\s+(you\s+)?(give|get|show|tell|provide|share)\s+(.*\s+)?credentials\b/i',
        '/\buser\s+credentials\b/i',
        '/\bcredentials\s+(for|of|to)\s+.*@.*\./i', // credentials for email
        
        // Credit card requests (extraction)
        '/\b(give|show|get|fetch|find|retrieve|list|display)\s+(me\s+)?(the\s+)?(credit\s*card|card\s*number|cvv|cvc)\b/i',
        '/\b(credit\s*card|card\s*number|cvv|cvc)\s+(number|of|for)\b/i',
        
        // SSN requests (extraction)
        '/\b(give|show|get|fetch|find|retrieve|list|display)\s+(me\s+)?(the\s+)?(ssn|social\s*security)\b/i',
        '/\b(ssn|social\s*security)\s+(number|of|for)\b/i',
        
        // API key/secret/token requests (site credentials)
        '/\b(give|show|get|fetch|find|retrieve|list|display)\s+(me\s+)?(the\s+)?(api\s*key|secret|token|consumer\s*secret)\b/i',
        '/\b(api\s*key|secret|token|consumer\s*secret)\s+(of|for)\b/i',
        
        // All/every passwords/credentials (bulk extraction)
        '/\b(all|every)\s+(passwords|credentials|api\s*keys|secrets|tokens)\b/i',
        
        // Note: Queries like "orders from email" or "total for user" are ALLOWED (analytics)
        // These use personal data as filters, not extraction targets
    );
    
    /**
     * Check if user query is trying to access sensitive data
     * ✅ Allows analytical queries that use personal data as filters
     * ❌ Blocks extraction queries for credentials
     */
    public static function is_sensitive_query($user_query) {
        $query_lower = strtolower(trim($user_query));
        
        // ✅ Check if this is an analytical query (COUNT, TOTAL, SUM, etc.)
        // Analytical queries using personal data as filters are ALLOWED
        $analytics_keywords = array(
            'how many', 'count', 'total', 'sum', 'average', 'avg', 'mean',
            'number of', 'amount of', 'quantity of',
            'statistics', 'trend', 'analysis', 'report', 'summary',
            'top', 'bottom', 'most', 'least', 'best', 'worst',
            'percentage', 'ratio', 'compare', 'comparison',
            'group by', 'aggregate', 'overall', 'metrics',
            'placed from', 'ordered by', 'purchased by', 'from this', 'for this'
        );
        
        $is_analytical = false;
        foreach ($analytics_keywords as $keyword) {
            if (strpos($query_lower, $keyword) !== false) {
                $is_analytical = true;
                break;
            }
        }
        
        // ✅ If it's an analytical query, only block if it's asking for credentials
        // Examples: "total orders from email" = ALLOWED, "password for email" = BLOCKED
        if ($is_analytical) {
            // Only block if query contains password/credential extraction patterns
            $credential_patterns = array(
                '/\b(password|passwd|pwd|pass)\b/i',
                '/\b(credit\s*card|card\s*number|cvv|cvc)\b/i',
                '/\b(api\s*key|secret|token|consumer\s*secret)\b/i',
                '/\b(ssn|social\s*security)\b/i',
            );
            
            foreach ($credential_patterns as $pattern) {
                if (preg_match($pattern, $query_lower)) {
                    error_log('🚨 HeyTrisha: Blocked sensitive credential query - ' . $user_query);
                    return true;
                }
            }
            
            // ✅ Analytical queries using personal data as filters are ALLOWED
            return false;
        }
        
        // ✅ For non-analytical queries, check against all sensitive patterns
        foreach (self::$sensitive_query_patterns as $pattern) {
            if (preg_match($pattern, $query_lower)) {
                error_log('🚨 HeyTrisha: Blocked sensitive query - ' . $user_query);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if SQL query is trying to access sensitive columns
     * ✅ Only blocks sensitive CREDENTIAL columns, not personal data
     */
    public static function is_sensitive_sql($sql_query) {
        $sql_lower = strtolower($sql_query);
        
        // ✅ Check for sensitive CREDENTIAL column names in SELECT clause
        // Personal data columns (email, address, phone) are ALLOWED
        foreach (self::$sensitive_columns as $column) {
            // Check if column is explicitly selected (not just used in WHERE)
            // Pattern: SELECT ... column ... FROM (column is in SELECT list)
            if (preg_match('/\bselect\b[^from]*\b' . preg_quote($column, '/') . '\b[^from]*\bfrom\b/i', $sql_lower)) {
                error_log('🚨 HeyTrisha: Blocked SQL with sensitive credential column - ' . $column);
                return true;
            }
        }
        
        // ✅ Check for SELECT * on sensitive credential tables
        // Only block if selecting all columns from credential tables
        if (preg_match('/\bselect\s+\*\s+from\s+(.*_)?users\b/i', $sql_lower)) {
            // Allow if it's an aggregate query (COUNT, SUM, etc.)
            if (!preg_match('/\b(count|sum|avg|min|max|group\s+by)\b/i', $sql_lower)) {
                error_log('🚨 HeyTrisha: Blocked SELECT * FROM users (non-aggregate)');
                return true;
            }
        }
        
        // Note: SELECT * FROM orders, customers, etc. is ALLOWED for analytics
        // Only blocking SELECT * FROM users/usermeta if not aggregate
        
        return false;
    }
    
    /**
     * Filter sensitive CREDENTIAL columns from SQL results
     * ✅ Only filters passwords, payment data, and site credentials
     * ✅ Personal data (emails, addresses, phones) is ALLOWED and returned
     */
    public static function filter_sensitive_results($results) {
        if (empty($results) || !is_array($results)) {
            return $results;
        }
        
        $filtered_results = array();
        
        foreach ($results as $row) {
            // Convert object to array
            if (is_object($row)) {
                $row = (array)$row;
            }
            
            if (!is_array($row)) {
                continue;
            }
            
            $filtered_row = array();
            
            foreach ($row as $column_name => $value) {
                $column_lower = strtolower($column_name);
                
                // Check if column is sensitive
                $is_sensitive = false;
                foreach (self::$sensitive_columns as $sensitive_col) {
                    if (strpos($column_lower, $sensitive_col) !== false) {
                        $is_sensitive = true;
                        error_log('🚨 HeyTrisha: Filtered sensitive column from results - ' . $column_name);
                        break;
                    }
                }
                
                if (!$is_sensitive) {
                    $filtered_row[$column_name] = $value;
                }
            }
            
            // Only include row if it has data after filtering
            if (!empty($filtered_row)) {
                $filtered_results[] = $filtered_row;
            }
        }
        
        return $filtered_results;
    }
    
    /**
     * Get polite rejection message for sensitive queries
     */
    public static function get_rejection_message() {
        $messages = array(
            "I'm designed to help with data analytics and insights, but I can't access or display sensitive personal information like passwords, emails, or contact details. This protects user privacy and security.",
            
            "For security and privacy reasons, I cannot provide sensitive user data such as passwords, email addresses, or personal information. I can help you with aggregated analytics and insights instead.",
            
            "I understand you're looking for information, but I'm built to protect user privacy. I can't retrieve or display sensitive data like passwords, emails, or personal details. However, I can help with statistical analysis and trends!",
            
            "To protect user privacy and maintain security, I cannot access passwords, email addresses, or other personal information. I'm here to help with data analytics - ask me about trends, counts, or aggregated statistics!",
        );
        
        // Return a random message for variety
        return $messages[array_rand($messages)];
    }
    
    /**
     * Validate that query is analytics-focused, not data extraction
     */
    public static function is_analytics_query($user_query) {
        $analytics_keywords = array(
            'how many', 'count', 'total', 'sum', 'average', 'mean',
            'statistics', 'trend', 'analysis', 'report', 'summary',
            'top', 'bottom', 'most', 'least', 'best', 'worst',
            'percentage', 'ratio', 'compare', 'comparison',
            'group by', 'aggregate', 'overall', 'metrics'
        );
        
        $query_lower = strtolower($user_query);
        
        foreach ($analytics_keywords as $keyword) {
            if (strpos($query_lower, $keyword) !== false) {
                return true;
            }
        }
        
        // If asking for "show", "list", "get" without analytics context
        if (preg_match('/\b(show|list|get|give|display)\b/i', $query_lower)) {
            // Check if it's asking for individual records vs aggregated data
            if (preg_match('/\b(all|every|each)\b.*\b(user|customer|person|people)\b/i', $query_lower)) {
                return false; // Asking for all individual users = NOT analytics
            }
        }
        
        return true;
    }
}



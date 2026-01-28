<?php
/**
 * SQL Validator for Hey Trisha
 * 
 * Validates SQL queries to ensure they are safe to execute
 * Only allows SELECT queries and blocks dangerous operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class HeyTrisha_SQL_Validator {
    
    /**
     * Dangerous SQL keywords that are not allowed
     */
    private static $dangerous_keywords = [
        'DELETE',
        'DROP',
        'TRUNCATE',
        'ALTER',
        'CREATE',
        'INSERT',
        'UPDATE',
        'REPLACE',
        'GRANT',
        'REVOKE',
        'EXECUTE',
        'EXEC',
        'CALL',
        'PREPARE',
        'DEALLOCATE',
        'DESCRIBE',
        'EXPLAIN',
        'HANDLER',
        'LOAD DATA',
        'LOAD XML',
        'RENAME',
        'SET',
        'SHOW',
        'START TRANSACTION',
        'COMMIT',
        'ROLLBACK',
        'SAVEPOINT',
        'LOCK',
        'UNLOCK',
    ];
    
    /**
     * Dangerous functions that could be used maliciously
     */
    private static $dangerous_functions = [
        'LOAD_FILE',
        'OUTFILE',
        'DUMPFILE',
        'INTO',
        'BENCHMARK',
        'SLEEP',
    ];
    
    /**
     * Validate SQL query
     * 
     * @param string $sql SQL query to validate
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validate($sql) {
        if (empty($sql)) {
            return [
                'valid' => false,
                'error' => 'SQL query is empty'
            ];
        }
        
        // Remove comments
        $sql = self::remove_comments($sql);
        
        // Trim and normalize whitespace
        $sql = trim($sql);
        $sql_upper = strtoupper($sql);
        
        // Check if it starts with SELECT
        if (!preg_match('/^\s*SELECT\s+/i', $sql)) {
            return [
                'valid' => false,
                'error' => 'Only SELECT queries are allowed'
            ];
        }
        
        // Check for dangerous keywords
        foreach (self::$dangerous_keywords as $keyword) {
            if (self::contains_keyword($sql_upper, $keyword)) {
                return [
                    'valid' => false,
                    'error' => "Dangerous keyword detected: {$keyword}"
                ];
            }
        }
        
        // Check for dangerous functions
        foreach (self::$dangerous_functions as $function) {
            if (self::contains_keyword($sql_upper, $function)) {
                return [
                    'valid' => false,
                    'error' => "Dangerous function detected: {$function}"
                ];
            }
        }
        
        // Check for multiple statements (SQL injection attempt)
        if (self::has_multiple_statements($sql)) {
            return [
                'valid' => false,
                'error' => 'Multiple SQL statements are not allowed'
            ];
        }
        
        // Check for subqueries with dangerous keywords
        if (self::has_dangerous_subquery($sql_upper)) {
            return [
                'valid' => false,
                'error' => 'Subquery contains dangerous operations'
            ];
        }
        
        return [
            'valid' => true,
            'error' => null
        ];
    }
    
    /**
     * Remove SQL comments from query
     * 
     * @param string $sql SQL query
     * @return string SQL without comments
     */
    private static function remove_comments($sql) {
        // Remove -- style comments
        $sql = preg_replace('/--[^\n]*/', '', $sql);
        
        // Remove /* */ style comments
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        
        // Remove # style comments
        $sql = preg_replace('/#[^\n]*/', '', $sql);
        
        return $sql;
    }
    
    /**
     * Check if SQL contains a specific keyword
     * 
     * @param string $sql SQL query (uppercase)
     * @param string $keyword Keyword to search for
     * @return bool
     */
    private static function contains_keyword($sql, $keyword) {
        // Use word boundaries to avoid false positives
        // e.g., "DELETE" should match but not "DELETED_AT"
        return preg_match('/\b' . preg_quote($keyword, '/') . '\b/', $sql) === 1;
    }
    
    /**
     * Check if SQL contains multiple statements (separated by ;)
     * 
     * @param string $sql SQL query
     * @return bool
     */
    private static function has_multiple_statements($sql) {
        // Remove string literals first to avoid false positives
        $sql = preg_replace("/'[^']*'/", '', $sql);
        $sql = preg_replace('/"[^"]*"/', '', $sql);
        
        // Check for semicolons (statement separators)
        $semicolons = substr_count($sql, ';');
        
        // Allow one trailing semicolon
        if ($semicolons > 1) {
            return true;
        }
        
        if ($semicolons === 1 && !preg_match('/;\s*$/', $sql)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if subqueries contain dangerous operations
     * 
     * @param string $sql SQL query (uppercase)
     * @return bool
     */
    private static function has_dangerous_subquery($sql) {
        // Extract content within parentheses (potential subqueries)
        preg_match_all('/\([^)]+\)/', $sql, $matches);
        
        foreach ($matches[0] as $subquery) {
            // Check if subquery contains dangerous keywords
            foreach (self::$dangerous_keywords as $keyword) {
                if (self::contains_keyword($subquery, $keyword)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Sanitize table name to WordPress table prefix
     * 
     * @param string $sql SQL query
     * @return string SQL with proper table prefix
     */
    public static function sanitize_table_names($sql) {
        global $wpdb;
        
        // Replace wp_ with actual table prefix
        $sql = str_replace('wp_', $wpdb->prefix, $sql);
        
        return $sql;
    }
    
    /**
     * Add LIMIT clause if not present (security measure)
     * 
     * @param string $sql SQL query
     * @param int $max_limit Maximum number of rows
     * @return string SQL with LIMIT clause
     */
    public static function ensure_limit($sql, $max_limit = 1000) {
        // Check if LIMIT already exists
        if (!preg_match('/\bLIMIT\s+\d+/i', $sql)) {
            // Remove trailing semicolon if present
            $sql = rtrim($sql, ';');
            
            // Add LIMIT clause
            $sql .= ' LIMIT ' . intval($max_limit);
        }
        
        return $sql;
    }
}



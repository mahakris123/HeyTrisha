<?php

// CRITICAL: Suppress PHP notices and warnings from other plugins/themes
// This prevents "headers already sent" errors when other plugins output notices
// We only suppress display, errors are still logged
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

// Start output buffering early to catch any stray output from other plugins
ob_start();

// Prevent direct access if not called from WordPress (ABSPATH defined)
// Allow direct access only with allow_direct=1 parameter for testing
if (!defined('ABSPATH') && (!isset($_GET['allow_direct']) || $_GET['allow_direct'] !== '1')) {
    ob_end_clean();
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Direct access not allowed. Please use WordPress REST API: /wp-json/heytrisha/v1/api/'
    ]);
    exit;
}

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Simple test endpoint before Laravel loads (for debugging)
if (isset($_GET['test']) && $_GET['test'] === 'simple') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Simple test endpoint works!',
        'php_version' => PHP_VERSION,
        'vendor_exists' => file_exists(__DIR__.'/../vendor/autoload.php'),
        'env_exists' => file_exists(__DIR__.'/../.env'),
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Check If The Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| If the application is in maintenance / demo mode via the "down" command
| we will load this file so that any pre-rendered content can be shown
| instead of starting the framework, which could cause an exception.
|
*/

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| this application. We just need to utilize it! We'll simply require it
| into the script here so we don't need to manually load our classes.
|
*/

// Check if vendor exists
if (!file_exists(__DIR__.'/../vendor/autoload.php')) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Laravel dependencies not installed. Please run: cd api && composer install --no-dev --optimize-autoloader'
    ]);
    exit;
}

// Disable Composer platform check for shared hosting compatibility
// This allows the plugin to work on PHP 7.4.3+ even if vendor was installed on PHP 8.2+
$platform_check = __DIR__.'/../vendor/composer/platform_check.php';
if (file_exists($platform_check)) {
    // Temporarily disable platform check by renaming it
    $platform_check_backup = __DIR__.'/../vendor/composer/platform_check.php.bak';
    if (!file_exists($platform_check_backup)) {
        @rename($platform_check, $platform_check_backup);
    }
}

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Check .env and APP_KEY
|--------------------------------------------------------------------------
|
| Ensure .env file exists and has APP_KEY set
|
*/

$env_file = __DIR__.'/../.env';
$env_example = __DIR__.'/../.env.example';

// Ensure storage directories exist and are writable
$storage_dirs = [
    __DIR__.'/../storage',
    __DIR__.'/../storage/app',
    __DIR__.'/../storage/framework',
    __DIR__.'/../storage/framework/cache',
    __DIR__.'/../storage/framework/sessions',
    __DIR__.'/../storage/framework/views',
    __DIR__.'/../storage/logs',
    __DIR__.'/../bootstrap/cache',
];

foreach ($storage_dirs as $dir) {
    if (!file_exists($dir)) {
        @mkdir($dir, 0755, true);
    }
    // Try to make writable if not already
    if (file_exists($dir) && !is_writable($dir)) {
        @chmod($dir, 0755);
    }
}

// Create .env from .env.example if it doesn't exist
if (!file_exists($env_file)) {
    if (file_exists($env_example)) {
        copy($env_example, $env_file);
    } else {
        // Create minimal .env file
        $minimal_env = "APP_NAME=HeyTrisha\n";
        $minimal_env .= "APP_ENV=production\n";
        $minimal_env .= "APP_DEBUG=true\n";
        $minimal_env .= "APP_URL=\n\n";
        $minimal_env .= "LOG_CHANNEL=stack\n";
        $minimal_env .= "LOG_LEVEL=error\n\n";
        $minimal_env .= "DB_CONNECTION=mysql\n";
        $minimal_env .= "DB_HOST=127.0.0.1\n";
        $minimal_env .= "DB_PORT=3306\n";
        $minimal_env .= "DB_DATABASE=\n";
        $minimal_env .= "DB_USERNAME=\n";
        $minimal_env .= "DB_PASSWORD=\n\n";
        $minimal_env .= "OPENAI_API_KEY=\n";
        @file_put_contents($env_file, $minimal_env);
    }
}

// Generate APP_KEY if missing
if (file_exists($env_file)) {
    $env_content = file_get_contents($env_file);
    if (!preg_match('/^APP_KEY=base64:[A-Za-z0-9+\/]+={0,2}$/m', $env_content)) {
        // Generate key programmatically
        $key = 'base64:' . base64_encode(random_bytes(32));
        if (preg_match('/^APP_KEY=.*$/m', $env_content)) {
            $env_content = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, $env_content);
        } else {
            if (preg_match('/^(APP_NAME=.*)$/m', $env_content)) {
                $env_content = preg_replace('/^(APP_NAME=.*)$/m', '$1' . "\n" . 'APP_KEY=' . $key, $env_content);
            } else {
                $env_content = 'APP_KEY=' . $key . "\n" . $env_content;
            }
        }
        file_put_contents($env_file, $env_content);
    }
    
    // Load .env variables into $_ENV and putenv BEFORE Laravel loads
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Remove quotes if present
            $value = trim($value, '"\'');
            if (!empty($key)) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
    
    // Enable debug mode for better error messages (can be disabled in production)
    if (!isset($_ENV['APP_DEBUG']) || $_ENV['APP_DEBUG'] === '') {
        $_ENV['APP_DEBUG'] = 'true';
        putenv('APP_DEBUG=true');
    }
}

// Clear ALL bootstrap cache files to prevent stale cache issues
$bootstrap_cache = __DIR__.'/../bootstrap/cache';
$cache_files = ['config.php', 'routes.php', 'services.php', 'packages.php'];
foreach ($cache_files as $cache_file) {
    $cache_path = $bootstrap_cache . '/' . $cache_file;
    if (file_exists($cache_path)) {
        // Always clear cache to prevent syntax errors from cached files
        @unlink($cache_path);
    }
}

// Also clear compiled views cache that might contain syntax errors
$compiled_views = __DIR__.'/../storage/framework/views';
if (is_dir($compiled_views)) {
    $files = glob($compiled_views . '/*.php');
    if ($files) {
        foreach ($files as $file) {
            @unlink($file);
        }
    }
}

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request using
| the application's HTTP kernel. Then, we will send the response back
| to this client's browser, allowing them to enjoy our application.
|
*/

try {
    // Load environment variables (ensure they're loaded)
    if (file_exists($env_file)) {
        $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                $value = trim($value, '"\'');
                if (!empty($key)) {
                    $_ENV[$key] = $value;
                    putenv("$key=$value");
                }
            }
        }
    }
    
    // Bootstrap Laravel application
    $app = require_once __DIR__.'/../bootstrap/app.php';
    
    // Set APP_DEBUG for better error messages
    if (!defined('APP_DEBUG')) {
        $debug_value = isset($_ENV['APP_DEBUG']) ? $_ENV['APP_DEBUG'] : 'true';
        define('APP_DEBUG', $debug_value === 'true' || $debug_value === true);
    }
    
    // CRITICAL: Set facade root before any facades are used
    // This must be done before making the kernel or handling exceptions
    \Illuminate\Support\Facades\Facade::setFacadeApplication($app);
    
    // Make kernel with error handling
    try {
        $kernel = $app->make(Kernel::class);
    } catch (\Exception $kernelError) {
        throw new \Exception("Failed to create Kernel: " . $kernelError->getMessage(), 0, $kernelError);
    }
    
    // Bootstrap the application early to ensure all service providers are loaded
    // This ensures facades are available even if exceptions occur during request handling
    // CRITICAL: Bootstrap must complete successfully for all services (including View) to be available
    $kernel->bootstrap();
    
    // ✅ Capture request - check for WordPress proxy data first
    // If WordPress proxy set request body in global variable, use it
    if (isset($GLOBALS['heytrisha_request_body']) && !empty($GLOBALS['heytrisha_request_body'])) {
        // Log what we're receiving
        error_log("🔍 Laravel Debug - heytrisha_request_body: " . json_encode($GLOBALS['heytrisha_request_body']));
        
        // ✅ CRITICAL: Set $_POST so Laravel's Request::capture() can read it
        // Laravel checks $_POST for POST request data
        $_POST = $GLOBALS['heytrisha_request_body'];
        
        // Ensure Content-Type is set
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';
        
        // ✅ Use Request::capture() which reads from $_POST automatically
        // This is more reliable than Request::create() for POST data
        $request = Request::capture();
        
        // ✅ CRITICAL: Manually ensure data is in the request
        // Even though we set $_POST, manually populate request bag to be sure
        foreach ($GLOBALS['heytrisha_request_body'] as $key => $value) {
            $request->request->set($key, $value);
            // Also set in query bag (some Laravel methods check there)
            $request->query->set($key, $value);
        }
        
        error_log("🔍 Laravel Debug - Request captured");
        error_log("🔍 Laravel Debug - _POST: " . json_encode($_POST));
        error_log("🔍 Laravel Debug - query from input(): " . var_export($request->input('query'), true));
        error_log("🔍 Laravel Debug - query from request->request->get(): " . var_export($request->request->get('query'), true));
        error_log("🔍 Laravel Debug - Request all(): " . json_encode($request->all()));
        
        // Clear the global variable
        unset($GLOBALS['heytrisha_request_body']);
    } else {
        // Normal Laravel request capture
        $request = Request::capture();
    }
    
    // Handle request
    $response = $kernel->handle($request);
    
    // Check if we're being called from WordPress (via proxy)
    // If so, output JSON instead of sending headers
    if (defined('ABSPATH')) {
        // Clean any output from other plugins/themes that might have been buffered
        // This prevents "headers already sent" errors
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Called from WordPress - return JSON string for WordPress to handle
        $content = $response->getContent();
        echo $content;
        $kernel->terminate($request, $response);
        exit;
    }
    
    // Normal Laravel response (standalone)
    // Clean output buffer first
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    $response->send();
    
    // Terminate
    $kernel->terminate($request, $response);
    
} catch (\Throwable $e) {
    // Clean any output from other plugins/themes that might have been buffered
    // This prevents "headers already sent" errors
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Better error handling for shared hosting
    http_response_code(500);
    header('Content-Type: application/json');
    
    // Check for common issues
    $error_message = 'Internal server error';
    $error_details = [];
    
    // Check APP_KEY
    if (file_exists($env_file)) {
        $env_content = file_get_contents($env_file);
        if (!preg_match('/^APP_KEY=base64:/m', $env_content)) {
            $error_message = 'APP_KEY is missing. Please regenerate it from WordPress admin.';
            $error_details[] = 'APP_KEY not found in .env file';
        }
    } else {
        $error_message = '.env file not found. Please check plugin installation.';
        $error_details[] = '.env file missing';
    }
    
    // Check storage permissions
    $storage_path = __DIR__.'/../storage';
    if (!is_writable($storage_path)) {
        $error_details[] = 'Storage directory not writable';
    }
    
    // Check bootstrap cache
    $bootstrap_cache = __DIR__.'/../bootstrap/cache';
    if (file_exists($bootstrap_cache) && !is_writable($bootstrap_cache)) {
        $error_details[] = 'Bootstrap cache not writable';
    }
    
    // Log detailed error
    $log_message = 'Hey Trisha Laravel Error: ' . $e->getMessage() . PHP_EOL;
    $log_message .= 'File: ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
    $log_message .= 'Stack trace: ' . $e->getTraceAsString();
    error_log($log_message);
    
    // Write to Laravel log if possible
    $log_file = __DIR__.'/../storage/logs/laravel.log';
    if (is_writable(dirname($log_file))) {
        @file_put_contents($log_file, date('Y-m-d H:i:s') . ' - ' . $log_message . PHP_EOL . PHP_EOL, FILE_APPEND);
    }
    
    $response_data = [
        'success' => false,
        'message' => $error_message,
    ];
    
    // Include error details in debug mode or if APP_DEBUG is set
    $show_debug = (defined('APP_DEBUG') && APP_DEBUG) || (isset($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true');
    if ($show_debug) {
        $response_data['error'] = $e->getMessage();
        $response_data['file'] = $e->getFile() . ':' . $e->getLine();
        $response_data['type'] = get_class($e);
        if (!empty($error_details)) {
            $response_data['details'] = $error_details;
        }
        // Include first few lines of stack trace
        $trace = $e->getTraceAsString();
        $trace_lines = explode("\n", $trace);
        $response_data['trace'] = array_slice($trace_lines, 0, 10);
    }
    
    echo json_encode($response_data, JSON_PRETTY_PRINT);
    exit;
}

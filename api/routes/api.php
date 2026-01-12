<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WordPressApiController;
use App\Http\Controllers\NLPController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Route::post('/query', [ChatbotController::class, 'processQuery']);
// Route::post('/query', [WordPressApiController::class, 'handleQuery']);

// Route::post('/nlp-query', [NLPController::class, 'handleQuery']);

Route::post('/query', [NLPController::class, 'handleQuery']);

// Health check endpoint for server readiness
Route::get('/health', function () {
    // ✅ Use WordPressConfigService to check OpenAI key from WordPress (not .env)
    $configService = app(\App\Services\WordPressConfigService::class);
    $openai_key = $configService->getOpenAIApiKey();
    
    $info = [
        'status' => 'ok',
        'timestamp' => now(),
        'laravel_version' => app()->version(),
        'php_version' => PHP_VERSION,
        'app_key_set' => !empty(env('APP_KEY')),
        'openai_key_set' => !empty($openai_key) ? 1 : 0,
        'config_from_wordpress' => isset($_SERVER['HTTP_X_WORDPRESS_OPENAI_KEY']) ? 1 : 0,
        'storage_writable' => is_writable(storage_path()),
    ];
    return response()->json($info);
});

// Diagnostic endpoint
Route::get('/diagnostic', function () {
    // ✅ Use WordPressConfigService to check OpenAI key from WordPress (not .env)
    $configService = app(\App\Services\WordPressConfigService::class);
    $openai_key = $configService->getOpenAIApiKey();
    
    $diagnostics = [
        'php_version' => PHP_VERSION,
        'laravel_version' => app()->version(),
        'app_key_exists' => !empty(env('APP_KEY')),
        'app_key_length' => strlen(env('APP_KEY', '')),
        'storage_writable' => is_writable(storage_path()),
        'bootstrap_cache_writable' => is_writable(base_path('bootstrap/cache')),
        'vendor_exists' => file_exists(base_path('vendor/autoload.php')),
        'env_file_exists' => file_exists(base_path('.env')),
        'routes_loaded' => true,
    ];
    
    // Check .env file
    $env_file = base_path('.env');
    if (file_exists($env_file)) {
        $env_content = file_get_contents($env_file);
        $diagnostics['app_key_in_env'] = preg_match('/^APP_KEY=base64:/m', $env_content);
        // OLD: Check .env file - $diagnostics['openai_key_set'] = preg_match('/^OPENAI_API_KEY=.+/m', $env_content);
    }
    
    // ✅ NEW: Check OpenAI key from WordPress configuration (HTTP headers or REST API)
    $diagnostics['openai_key_set'] = !empty($openai_key) ? 1 : 0;
    $diagnostics['openai_key_source'] = !empty($openai_key) ? 'wordpress' : 'none';
    $diagnostics['openai_key_length'] = strlen($openai_key);
    
    // Check if config is coming from HTTP headers (WordPress proxy)
    $diagnostics['config_from_headers'] = isset($_SERVER['HTTP_X_WORDPRESS_OPENAI_KEY']) ? 1 : 0;
    
    return response()->json($diagnostics);
});
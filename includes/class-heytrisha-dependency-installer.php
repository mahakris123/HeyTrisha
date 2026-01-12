<?php
/**
 * Hey Trisha Dependency Installer
 * 
 * Automatically installs Laravel and React dependencies on plugin activation
 */

if (!defined('ABSPATH')) {
    exit;
}

class HeyTrisha_Dependency_Installer
{
    private $plugin_path;
    private $api_path;
    private $react_path;

    public function __construct()
    {
        $this->plugin_path = plugin_dir_path(dirname(__FILE__));
        $this->api_path = $this->plugin_path . 'api';
        $this->react_path = $this->plugin_path . 'assets/js/chatbot-react-app';
    }

    /**
     * Install all dependencies (Laravel and React)
     * 
     * @return array Result with success status and messages
     */
    public function install_all_dependencies()
    {
        $results = [
            'success' => true,
            'messages' => [],
            'errors' => []
        ];

        // Check if we're in a shared hosting environment
        if ($this->is_shared_hosting()) {
            return $this->handle_shared_hosting();
        }

        // Check prerequisites (warnings only - don't block installation)
        $prerequisites = $this->check_prerequisites();
        // Merge prerequisite messages/errors but don't fail installation
        $results['messages'] = array_merge($results['messages'], $prerequisites['messages']);
        if (!empty($prerequisites['errors'])) {
            $results['messages'] = array_merge($results['messages'], array_map(function($error) {
                return '⚠ ' . $error;
            }, $prerequisites['errors']));
        }

        // Install Laravel dependencies
        $laravel_result = $this->install_laravel_dependencies();
        $results['messages'] = array_merge($results['messages'], $laravel_result['messages']);
        if (!$laravel_result['success']) {
            $results['success'] = false;
            $results['errors'] = array_merge($results['errors'], $laravel_result['errors']);
        }

        // Generate Laravel app key if needed
        $key_result = $this->generate_laravel_key();
        $results['messages'] = array_merge($results['messages'], $key_result['messages']);
        if (!$key_result['success'] && !empty($key_result['errors'])) {
            $results['errors'] = array_merge($results['errors'], $key_result['errors']);
        }

        // Install React dependencies (optional - React is loaded from CDN)
        $react_result = $this->install_react_dependencies();
        $results['messages'] = array_merge($results['messages'], $react_result['messages']);
        if (!$react_result['success'] && !empty($react_result['errors'])) {
            // React dependencies are optional, so don't fail the whole installation
            $results['messages'][] = 'Note: React dependencies installation skipped (React is loaded from CDN)';
        }

        // Run database migrations
        $migration_result = $this->run_database_migrations();
        $results['messages'] = array_merge($results['messages'], $migration_result['messages']);
        if (!$migration_result['success'] && !empty($migration_result['errors'])) {
            // Migrations might fail if database is not configured yet, so don't fail installation
            $results['messages'][] = 'Note: Database migrations skipped (configure database settings first)';
        }

        return $results;
    }

    /**
     * Check if prerequisites are met (Composer, npm, PHP version)
     * 
     * @return array
     */
    private function check_prerequisites()
    {
        $results = [
            'success' => true,
            'messages' => [],
            'errors' => []
        ];

        // Check PHP version (Laravel 8 supports PHP 7.4.3+)
        if (version_compare(PHP_VERSION, '7.4.3', '<')) {
            $results['errors'][] = 'PHP 7.4.3 or higher is recommended. Current version: ' . PHP_VERSION . ' (Some features may not work)';
        } else {
            $results['messages'][] = '✓ PHP version check passed (' . PHP_VERSION . ')';
        }

        // Check if Composer is available
        $composer_path = $this->find_composer();
        if (!$composer_path) {
            $results['errors'][] = 'Composer is not found. Please install Composer to install Laravel dependencies. You can download it from https://getcomposer.org/';
        } else {
            $results['messages'][] = '✓ Composer found: ' . $composer_path;
        }
        
        // Don't fail installation if prerequisites are missing - just show warnings
        // Installation can proceed, but dependencies won't be installed until prerequisites are met

        // Check if npm is available (optional for React)
        $npm_path = $this->find_npm();
        if (!$npm_path) {
            $results['messages'][] = '⚠ npm not found (optional - React is loaded from CDN)';
        } else {
            $results['messages'][] = '✓ npm found: ' . $npm_path;
        }

        return $results;
    }

    /**
     * Find Composer executable
     * 
     * @return string|false
     */
    private function find_composer()
    {
        // Check common locations
        $possible_paths = [
            'composer', // In PATH
            'composer.phar', // In PATH
            $this->api_path . '/composer.phar', // Local composer.phar
        ];

        foreach ($possible_paths as $path) {
            if ($this->command_exists($path)) {
                return $path;
            }
        }

        return false;
    }

    /**
     * Find npm executable
     * 
     * @return string|false
     */
    private function find_npm()
    {
        if ($this->command_exists('npm')) {
            return 'npm';
        }

        return false;
    }

    /**
     * Check if exec() function is available
     * 
     * @return bool
     */
    private function is_exec_enabled()
    {
        // Check if exec() function exists
        if (!function_exists('exec')) {
            return false;
        }
        
        // Check if exec is disabled in php.ini
        $disabled_functions = explode(',', ini_get('disable_functions'));
        $disabled_functions = array_map('trim', $disabled_functions);
        if (in_array('exec', $disabled_functions)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if a command exists
     * 
     * @param string $command
     * @return bool
     */
    private function command_exists($command)
    {
        // CRITICAL: Check if exec() is available before trying to use it
        // On shared hosting, exec() is often disabled
        if (!$this->is_exec_enabled()) {
            return false;
        }
        
        $output = [];
        $return_var = 0;
        
        // Try to run the command with --version flag
        $test_command = escapeshellcmd($command) . ' --version 2>&1';
        
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows
            @exec($test_command, $output, $return_var);
        } else {
            // Unix/Linux
            @exec($test_command . ' 2>&1', $output, $return_var);
        }

        return $return_var === 0 && !empty($output);
    }

    /**
     * Install Laravel dependencies using Composer
     * 
     * @return array
     */
    private function install_laravel_dependencies()
    {
        $results = [
            'success' => false,
            'messages' => [],
            'errors' => []
        ];

        // Check if exec is available (required for running Composer)
        if (!$this->is_exec_enabled()) {
            $results['errors'][] = 'exec() function is disabled on this server (shared hosting)';
            $results['messages'][] = '⚠ Cannot install dependencies automatically on shared hosting';
            return $results;
        }

        $composer_path = $this->find_composer();
        if (!$composer_path) {
            $results['errors'][] = 'Composer not found';
            return $results;
        }

        // Check if vendor directory already exists
        if (is_dir($this->api_path . '/vendor')) {
            $results['messages'][] = '✓ Laravel dependencies already installed (vendor directory exists)';
            $results['success'] = true;
            return $results;
        }

        // Check if composer.json exists
        if (!file_exists($this->api_path . '/composer.json')) {
            $results['errors'][] = 'composer.json not found in api directory';
            return $results;
        }

        $results['messages'][] = 'Installing Laravel dependencies...';

        // Run composer install
        $command = escapeshellcmd($composer_path) . ' install --no-dev --optimize-autoloader --no-interaction --working-dir=' . escapeshellarg($this->api_path) . ' 2>&1';
        
        $output = [];
        $return_var = 0;
        
        @exec($command, $output, $return_var);
        
        if ($return_var === 0) {
            $results['success'] = true;
            $results['messages'][] = '✓ Laravel dependencies installed successfully';
        } else {
            $results['errors'][] = 'Failed to install Laravel dependencies';
            $results['errors'][] = 'Command output: ' . implode("\n", $output);
        }

        return $results;
    }

    /**
     * Generate Laravel application key if needed
     * 
     * @return array
     */
    private function generate_laravel_key()
    {
        $results = [
            'success' => true,
            'messages' => [],
            'errors' => []
        ];

        $env_file = $this->api_path . '/.env';
        $env_example = $this->api_path . '/.env.example';

        // Create .env from .env.example if it doesn't exist
        if (!file_exists($env_file) && file_exists($env_example)) {
            copy($env_example, $env_file);
            $results['messages'][] = '✓ Created .env file from .env.example';
        }

        // Check if APP_KEY is already set
        if (file_exists($env_file)) {
            $env_content = file_get_contents($env_file);
            if (strpos($env_content, 'APP_KEY=base64:') !== false && strpos($env_content, 'APP_KEY=base64:') < strpos($env_content, 'APP_KEY=')) {
                $results['messages'][] = '✓ Laravel app key already exists';
                return $results;
            }
        }

        // Generate Laravel app key programmatically (works even if exec is disabled)
        $key_generated = $this->generate_app_key_programmatically($env_file);
        
        if ($key_generated) {
            $results['messages'][] = '✓ Laravel application key generated';
        } else {
            // Try artisan command as fallback (only if exec is enabled)
            if (!$this->is_exec_enabled()) {
                $results['messages'][] = '⚠ Could not generate Laravel key (exec disabled on shared hosting)';
            } else {
                $php_path = $this->find_php();
                if ($php_path) {
                    $artisan_path = $this->api_path . '/artisan';
                    if (file_exists($artisan_path)) {
                        $command = escapeshellcmd($php_path) . ' ' . escapeshellarg($artisan_path) . ' key:generate --force 2>&1';
                        $output = [];
                        $return_var = 0;
                        @exec($command, $output, $return_var);
                        if ($return_var === 0) {
                            $results['messages'][] = '✓ Laravel application key generated (via artisan)';
                        } else {
                            $results['messages'][] = '⚠ Could not generate Laravel key (may need manual configuration)';
                        }
                    } else {
                        $results['messages'][] = '⚠ Laravel artisan not found, skipping key generation';
                    }
                } else {
                    $results['messages'][] = '⚠ Could not generate Laravel key (PHP executable not found)';
                }
            }
        }

        return $results;
    }

    /**
     * Install React dependencies using npm
     * 
     * @return array
     */
    private function install_react_dependencies()
    {
        $results = [
            'success' => true,
            'messages' => [],
            'errors' => []
        ];

        // Check if exec is available (required for running npm)
        if (!$this->is_exec_enabled()) {
            $results['messages'][] = '⚠ npm unavailable on shared hosting - React dependencies skipped (React is loaded from CDN)';
            return $results;
        }

        $npm_path = $this->find_npm();
        if (!$npm_path) {
            $results['messages'][] = '⚠ npm not found - React dependencies skipped (React is loaded from CDN)';
            return $results;
        }

        // Check if node_modules already exists
        if (is_dir($this->react_path . '/node_modules')) {
            $results['messages'][] = '✓ React dependencies already installed (node_modules exists)';
            return $results;
        }

        // Check if package.json exists
        if (!file_exists($this->react_path . '/package.json')) {
            $results['messages'][] = '⚠ package.json not found - React dependencies skipped';
            return $results;
        }

        $results['messages'][] = 'Installing React dependencies...';

        // Run npm install
        $command = escapeshellcmd($npm_path) . ' install --production --prefix ' . escapeshellarg($this->react_path) . ' 2>&1';
        
        $output = [];
        $return_var = 0;
        
        @exec($command, $output, $return_var);
        
        if ($return_var === 0) {
            $results['messages'][] = '✓ React dependencies installed successfully';
        } else {
            $results['messages'][] = '⚠ React dependencies installation skipped (optional - React is loaded from CDN)';
            // Don't fail - React is loaded from CDN anyway
        }

        return $results;
    }

    /**
     * Run Laravel database migrations
     * 
     * @return array
     */
    private function run_database_migrations()
    {
        $results = [
            'success' => true,
            'messages' => [],
            'errors' => []
        ];

        // Check if exec is available (required for running artisan)
        if (!$this->is_exec_enabled()) {
            $results['messages'][] = '⚠ Cannot run migrations on shared hosting (exec disabled)';
            return $results;
        }

        $php_path = $this->find_php();
        if (!$php_path) {
            $results['errors'][] = 'PHP executable not found';
            $results['success'] = false;
            return $results;
        }

        $artisan_path = $this->api_path . '/artisan';
        if (!file_exists($artisan_path)) {
            $results['messages'][] = '⚠ Laravel artisan not found, skipping migrations';
            return $results;
        }

        // Check if .env exists and has database config
        $env_file = $this->api_path . '/.env';
        if (!file_exists($env_file)) {
            $results['messages'][] = '⚠ .env file not found, skipping migrations (configure database first)';
            return $results;
        }

        $results['messages'][] = 'Running database migrations...';

        // Run migrations
        $command = escapeshellcmd($php_path) . ' ' . escapeshellarg($artisan_path) . ' migrate --force 2>&1';
        
        $output = [];
        $return_var = 0;
        
        @exec($command, $output, $return_var);
        
        if ($return_var === 0) {
            $results['messages'][] = '✓ Database migrations completed';
        } else {
            $results['messages'][] = '⚠ Database migrations skipped (database may not be configured yet)';
            // Don't fail - database can be configured later
        }

        return $results;
    }

    /**
     * Generate APP_KEY programmatically (without artisan)
     * 
     * @param string $env_file Path to .env file
     * @return bool
     */
    private function generate_app_key_programmatically($env_file)
    {
        if (!file_exists($env_file)) {
            return false;
        }
        
        $env_content = file_get_contents($env_file);
        
        // Check if APP_KEY already exists and is valid
        if (preg_match('/^APP_KEY=base64:[A-Za-z0-9+\/]+={0,2}$/m', $env_content)) {
            return true; // Key already exists
        }
        
        // Generate a random 32-byte key and encode it as base64
        $key = 'base64:' . base64_encode(random_bytes(32));
        
        // Replace or add APP_KEY
        if (preg_match('/^APP_KEY=.*$/m', $env_content)) {
            // Replace existing APP_KEY
            $env_content = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, $env_content);
        } else {
            // Add APP_KEY after APP_NAME or at the beginning
            if (preg_match('/^APP_NAME=.*$/m', $env_content)) {
                $env_content = preg_replace('/^(APP_NAME=.*)$/m', '$1' . "\n" . 'APP_KEY=' . $key, $env_content);
            } else {
                $env_content = 'APP_KEY=' . $key . "\n" . $env_content;
            }
        }
        
        // Write back to file
        return file_put_contents($env_file, $env_content) !== false;
    }

    /**
     * Find PHP executable
     * 
     * @return string|false
     */
    private function find_php()
    {
        $possible_paths = [
            'php', // In PATH
            PHP_BINARY, // Current PHP binary
        ];

        foreach ($possible_paths as $path) {
            if ($this->command_exists($path)) {
                return $path;
            }
        }

        return PHP_BINARY; // Fallback to current PHP binary
    }

    /**
     * Get installation status
     * 
     * @return array
     */
    public function get_installation_status()
    {
        $status = [
            'laravel_installed' => is_dir($this->api_path . '/vendor'),
            'react_installed' => is_dir($this->react_path . '/node_modules'),
            'laravel_key_exists' => false,
            'composer_available' => $this->find_composer() !== false,
            'npm_available' => $this->find_npm() !== false,
        ];

        // Check if Laravel key exists
        $env_file = $this->api_path . '/.env';
        if (file_exists($env_file)) {
            $env_content = file_get_contents($env_file);
            $status['laravel_key_exists'] = strpos($env_content, 'APP_KEY=base64:') !== false;
        }

        return $status;
    }

    /**
     * Detect if we're in a shared hosting environment
     * 
     * @return bool
     */
    private function is_shared_hosting()
    {
        // Check if Composer is not available AND exec is disabled
        $composer_available = $this->find_composer() !== false;
        $exec_disabled = !function_exists('exec') || in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))));
        
        // If dependencies already exist, not shared hosting issue
        if (is_dir($this->api_path . '/vendor')) {
            return false;
        }
        
        return !$composer_available || $exec_disabled;
    }

    /**
     * Handle shared hosting environment
     * 
     * @return array
     */
    private function handle_shared_hosting()
    {
        $results = [
            'success' => true,
            'messages' => [],
            'errors' => []
        ];

        $results['messages'][] = '🌐 Shared Hosting Environment Detected';
        $results['messages'][] = '';
        $results['messages'][] = 'This plugin requires Laravel dependencies (vendor folder) to be pre-installed.';
        $results['messages'][] = '';
        $results['messages'][] = '📋 Installation Instructions for Shared Hosting:';
        $results['messages'][] = '';
        $results['messages'][] = '1. Download the COMPLETE plugin package from:';
        $results['messages'][] = '   https://github.com/mahakris123/HeyTrisha/releases';
        $results['messages'][] = '';
        $results['messages'][] = '2. OR, if you have a local development environment:';
        $results['messages'][] = '   a. Copy the plugin folder to your local machine';
        $results['messages'][] = '   b. Open terminal/command prompt in the plugin folder';
        $results['messages'][] = '   c. Run the preparation script:';
        $results['messages'][] = '      Windows: prepare-for-hosting.bat';
        $results['messages'][] = '      Mac/Linux: ./prepare-for-hosting.sh';
        $results['messages'][] = '   d. Upload the ENTIRE plugin folder (including vendor) back to your shared hosting';
        $results['messages'][] = '';
        $results['messages'][] = '3. Configure the plugin settings in WordPress admin';
        $results['messages'][] = '';
        $results['messages'][] = '💡 Note: The plugin will work once the vendor folder is present in:';
        $results['messages'][] = '   wp-content/plugins/heytrisha-woo/api/vendor/';
        $results['messages'][] = '';
        $results['messages'][] = '📖 For detailed instructions, see: SHARED_HOSTING_SETUP.md';
        $results['messages'][] = '';
        
        // Check if .env exists and has APP_KEY
        $env_file = $this->api_path . '/.env';
        if (!file_exists($env_file)) {
            $results['messages'][] = '⚠ Also need to create .env file in api folder (copy from .env.example)';
        } else {
            $env_content = file_get_contents($env_file);
            if (strpos($env_content, 'APP_KEY=base64:') === false) {
                $results['messages'][] = '⚠ Also need to generate APP_KEY in .env file';
            }
        }

        return $results;
    }
}


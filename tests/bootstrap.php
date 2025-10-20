<?php
/**
 * PHPUnit Bootstrap File
 *
 * Note: This file uses Brain\Monkey and PHPUnit which are dev dependencies.
 * Install with: composer install --dev
 * IDE warnings about "Undefined function/type" are expected until installed.
 *
 * @package DatabaseImportPro\Tests
 */

// Require Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Initialize Brain Monkey for WordPress function mocking
\Brain\Monkey\setUp();

// Define WordPress constants for testing
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

// Load plugin constants
define('DBIP_IMPORTER_VERSION', '1.1.0');
define('DBIP_IMPORTER_PLUGIN_DIR', dirname(__DIR__) . '/');
define('DBIP_IMPORTER_PLUGIN_URL', 'http://example.com/wp-content/plugins/database-import-pro/');

// Mock WordPress core functions
require_once __DIR__ . '/mocks/wordpress-functions.php';

// Echo test environment info
echo "\n================================\n";
echo "Database Import Pro Test Suite\n";
echo "Version: " . esc_html(DBIP_IMPORTER_VERSION) . "\n";
echo "================================\n\n";

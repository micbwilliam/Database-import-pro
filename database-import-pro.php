<?php
/**
 * Plugin Name: Database Import Pro
 * Plugin URI: https://michaelbwilliam.com/database-import-pro
 * Description: Advanced CSV to database importer with field mapping, data transformations, and batch processing for WordPress databases.
 * Version: 1.0.0
 * Author: Michael B. William
 * Author URI: https://michaelbwilliam.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: database-import-pro
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('DBIP_IMPORTER_VERSION', '1.0.1');
define('DBIP_IMPORTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DBIP_IMPORTER_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Helper functions for transient-based data storage (replaces sessions)
 * Transients are more WordPress-friendly and work in clustered environments
 */
function dbip_get_import_data($key = null) {
    $user_id = get_current_user_id();
    $transient_name = 'dbip_import_' . $user_id;
    $data = get_transient($transient_name);
    
    if (false === $data) {
        $data = array();
    }
    
    if ($key !== null) {
        return isset($data[$key]) ? $data[$key] : null;
    }
    
    return $data;
}

function dbip_set_import_data($key, $value) {
    $user_id = get_current_user_id();
    $transient_name = 'dbip_import_' . $user_id;
    $data = get_transient($transient_name);
    
    if (false === $data) {
        $data = array();
    }
    
    $data[$key] = $value;
    
    // Set transient for 1 hour
    set_transient($transient_name, $data, HOUR_IN_SECONDS);
}

function dbip_delete_import_data($key = null) {
    $user_id = get_current_user_id();
    $transient_name = 'dbip_import_' . $user_id;
    
    if ($key === null) {
        // Delete entire transient
        delete_transient($transient_name);
    } else {
        // Delete specific key
        $data = get_transient($transient_name);
        if (false !== $data && isset($data[$key])) {
            unset($data[$key]);
            set_transient($transient_name, $data, HOUR_IN_SECONDS);
        }
    }
}

// Set PHP upload limits - Check if ini_set is available
if (function_exists('ini_set')) {
    $settings = array(
        'upload_max_filesize' => '50M',
        'post_max_size' => '50M',
        'memory_limit' => '128M',
        'max_execution_time' => '300',
        'max_input_time' => '300'
    );
    
    foreach ($settings as $key => $value) {
        $result = @ini_set($key, $value);
        if ($result === false) {
            error_log('Database Import Pro: Failed to set ' . $key . ' to ' . $value);
        }
    }
}

// Start session if not already started
function dbip_importer_start_session() {
    if (!session_id() && !headers_sent()) {
        session_start();
    }
    
    // Initialize session array if not exists
    if (!isset($_SESSION['dbip_importer'])) {
        $_SESSION['dbip_importer'] = array();
    }
}
add_action('init', 'dbip_importer_start_session');
add_action('admin_init', 'dbip_importer_start_session');
add_action('wp_ajax_nopriv_dbip_upload_file', 'dbip_importer_start_session');
add_action('wp_ajax_dbip_upload_file', 'dbip_importer_start_session');

// Include required files
require_once DBIP_IMPORTER_PLUGIN_DIR . 'includes/class-dbip-importer.php';
require_once DBIP_IMPORTER_PLUGIN_DIR . 'includes/class-dbip-importer-admin.php';
require_once DBIP_IMPORTER_PLUGIN_DIR . 'includes/class-dbip-importer-uploader.php';
require_once DBIP_IMPORTER_PLUGIN_DIR . 'includes/class-dbip-importer-table.php';
require_once DBIP_IMPORTER_PLUGIN_DIR . 'includes/class-dbip-importer-mapping.php';
require_once DBIP_IMPORTER_PLUGIN_DIR . 'includes/class-dbip-importer-processor.php';

/**
 * Initialize AJAX handlers
 * NOTE: AJAX actions are registered in class constructors to avoid duplication
 */
function dbip_importer_init_ajax() {
    // Initialize classes - they register their own AJAX handlers in constructors
    new DBIP_Importer_Uploader();
    new DBIP_Importer_Table();
    new DBIP_Importer_Mapping();
    new DBIP_Importer_Processor();
}
add_action('init', 'dbip_importer_init_ajax');

/**
 * Add admin scripts
 */
function dbip_importer_admin_scripts($hook) {
    if (strpos($hook, 'dbip-importer') !== false) {
        wp_enqueue_script('jquery');
        
        // Add the importer admin script
        wp_register_script(
            'dbip-importer-admin',
            DBIP_IMPORTER_PLUGIN_URL . 'assets/js/dbip-importer-admin.js',
            array('jquery'),
            DBIP_IMPORTER_VERSION,
            true
        );
        
        // Localize the script with AJAX URL and nonce
        wp_localize_script('dbip-importer-admin', 'dbipImporter', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dbip_importer_nonce'),
            'strings' => array(
                'import_error' => __('Import failed. Please try again.', 'database-import-pro'),
                'network_error' => __('Network error occurred. Please try again.', 'database-import-pro'),
                'confirm_cancel' => __('Are you sure you want to cancel the import?', 'database-import-pro')
            )
        ));

        // Enqueue the script
        wp_enqueue_script('dbip-importer-admin');
    }
}
add_action('admin_enqueue_scripts', 'dbip_importer_admin_scripts');

/**
 * Initialize the plugin
 */
function run_dbip_importer() {
    $plugin = new DBIP_Importer();
    $plugin->run();
}

// Start the plugin
run_dbip_importer();

// Clean up on deactivation
register_deactivation_hook(__FILE__, 'dbip_importer_cleanup');

function dbip_importer_cleanup() {
    // Remove temporary upload directory
    $upload_dir = wp_upload_dir();
    $temp_dir = trailingslashit($upload_dir['basedir']) . 'dbip-importer';
    if (is_dir($temp_dir)) {
        array_map('unlink', glob("$temp_dir/*.*"));
        rmdir($temp_dir);
    }
    
    // Clear session
    if (session_id()) {
        session_destroy();
    }
}

/**
 * Create plugin tables on activation
 */
function dbip_importer_activate() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'dbip_import_logs';
    
    $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        import_date datetime NOT NULL,
        file_name varchar(255) NOT NULL,
        table_name varchar(255) NOT NULL,
        total_rows int NOT NULL,
        inserted int NOT NULL,
        updated int NOT NULL,
        skipped int NOT NULL,
        failed int NOT NULL,
        error_log longtext,
        status varchar(50) NOT NULL,
        duration int NOT NULL,
        PRIMARY KEY  (id),
        KEY idx_user_date (user_id, import_date),
        KEY idx_status (status),
        KEY idx_import_date (import_date)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Add indexes if they don't exist (for existing installations)
    $indexes_exist = $wpdb->get_results("SHOW INDEX FROM {$table_name} WHERE Key_name = 'idx_user_date'");
    if (empty($indexes_exist)) {
        $wpdb->query("ALTER TABLE {$table_name} ADD INDEX idx_user_date (user_id, import_date)");
        $wpdb->query("ALTER TABLE {$table_name} ADD INDEX idx_status (status)");
        $wpdb->query("ALTER TABLE {$table_name} ADD INDEX idx_import_date (import_date)");
    }
}
register_activation_hook(__FILE__, 'dbip_importer_activate');
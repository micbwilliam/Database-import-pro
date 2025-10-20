<?php
/**
 * Plugin Name: Database Import Pro
 * Plugin URI: https://github.com/micbwilliam/Database-import-pro
 * Description: Advanced CSV to database importer with field mapping, data transformations, and batch processing for WordPress databases.
 * Version: 2.1.0
 * Author: Michael B. William
 * Author URI: https://michaelbwilliam.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: database-import-pro
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Tested up to: 6.8
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('DBIP_IMPORTER_VERSION', '2.1.0');
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

/**
 * Validate field type helper function
 * 
 * @param mixed $value The value to validate
 * @param string $db_type The database field type (e.g., 'varchar(255)', 'int(11)')
 * @return bool True if valid, false otherwise
 */
function dbip_validate_field_type($value, $db_type) {
    // Handle special case for empty values and [CURRENT DATA]
    if ($value === '' || $value === null || $value === '[CURRENT DATA]') {
        return true;
    }

    // Extract base type and length/values
    if (preg_match('/^([a-z]+)(\(([^)]+)\))?/', strtolower($db_type), $matches)) {
        $type = $matches[1];
        $constraint = isset($matches[3]) ? $matches[3] : '';
        
        switch ($type) {
            case 'tinyint':
                // Special handling for boolean (tinyint(1))
                if ($constraint === '1') {
                    if (is_bool($value)) return true;
                    if (is_numeric($value)) return in_array((int)$value, array(0, 1));
                    $val = strtolower(trim($value));
                    return in_array($val, array('0', '1', 'true', 'false', 'yes', 'no'));
                }
                // Fall through to regular int validation if not tinyint(1)
            case 'int':
            case 'smallint':
            case 'mediumint':
            case 'bigint':
                return is_numeric($value) && strpos($value, '.') === false;
            
            case 'decimal':
            case 'float':
            case 'double':
                return is_numeric($value);
            
            case 'date':
            case 'datetime':
            case 'timestamp':
                // Handle MySQL functions and special values
                $special_values = array(
                    'CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP()',
                    'NOW()', 'NOW', 'CURRENT_DATE()', 'CURRENT_DATE',
                    'NULL', 'null'
                );
                if (in_array(strtoupper($value), $special_values)) {
                    return true;
                }
                return dbip_validate_date($value);
            
            case 'time':
                return preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $value);
            
            case 'year':
                return is_numeric($value) && strlen($value) === 4;
            
            case 'char':
            case 'varchar':
                $max_length = (int)$constraint;
                return $max_length === 0 || strlen($value) <= $max_length;
            
            case 'enum':
            case 'set':
                $allowed_values = array_map('trim', explode(',', str_replace("'", '', $constraint)));
                return in_array($value, $allowed_values);
            
            default:
                return true;
        }
    }
    return true;
}

/**
 * Validate date format helper function
 * 
 * @param string $value The date value to validate
 * @return bool True if valid date, false otherwise
 */
function dbip_validate_date($value) {
    // First, handle backslash-separated dates by converting them to dashes
    if (strpos($value, '\\') !== false) {
        $value = str_replace('\\', '-', $value);
    }

    // Handle common date/datetime formats
    $formats = array(
        // SQL standard formats
        'Y-m-d H:i:s',
        'Y-m-d\TH:i:s',
        'Y-m-d',
        
        // Common US formats
        'm/d/Y H:i:s',
        'm/d/Y',
        'm-d-Y H:i:s',
        'm-d-Y',
        
        // Common UK/European formats
        'd/m/Y H:i:s',
        'd/m/Y',
        'd-m-Y H:i:s',
        'd-m-Y',
        
        // Other common formats
        'Y/m/d H:i:s',
        'Y/m/d',
    );

    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date && $date->format($format) === $value) {
            return true;
        }
    }

    // Try strtotime as a last resort
    return strtotime($value) !== false;
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
        // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- ini_set used to set runtime limits when available
        $result = @ini_set($key, $value);
        if ($result === false) {
            // Only log in debug mode to avoid noisy production logs
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- debug-only logging
                error_log('Database Import Pro: Failed to set ' . $key . ' to ' . $value);
            }
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
        // Use WP Filesystem API for file operations
        if ( ! function_exists('WP_Filesystem') ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        // Try to initialize WP_Filesystem in non-interactive mode. On deactivation there is no UI
        // to request credentials, so avoid request_filesystem_credentials which may attempt to prompt.
        if ( ! WP_Filesystem() ) {
            // Fallback to WordPress helper where possible
            foreach (glob($temp_dir . "/*") as $file) {
                if (is_file($file)) {
                    // prefer wp_delete_file for consistency
                    if (function_exists('wp_delete_file')) {
                        wp_delete_file($file);
                    } else {
                        // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- fallback when WP helper is not available
                        unlink($file);
                    }
                }
            }
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- fallback removal when Filesystem API not available
            rmdir($temp_dir);
        } else {
            global $wp_filesystem;
            // Use recursive delete
            if ( method_exists( $wp_filesystem, 'delete' ) ) {
                $wp_filesystem->delete( untrailingslashit( $temp_dir ), true );
            } elseif ( method_exists( $wp_filesystem, 'rmdir' ) ) {
                $wp_filesystem->rmdir( untrailingslashit( $temp_dir ), true );
            } else {
                // Fallback: try to remove files then directory using wp_delete_file where available
                foreach (glob($temp_dir . "/*") as $file) {
                    if (is_file($file)) {
                        if (function_exists('wp_delete_file')) {
                            wp_delete_file($file);
                        } else {
                            // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- fallback when WP helper is not available
                            unlink($file);
                        }
                    }
                }
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- fallback removal when Filesystem API not available
                rmdir($temp_dir);
            }
        }
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
    // Sanitize table name for use in SQL. Table name is derived from $wpdb->prefix so this is safe,
    // but escape it to satisfy static analysis.
    $safe_table = esc_sql( $table_name );

    // Table identifiers cannot be used with $wpdb->prepare() placeholders. The table name
    // is derived from $wpdb->prefix and escaped above with esc_sql(). Build the SQL via
    // concatenation and explicitly ignore the prepared-SQL rule for these lines.
    // Table identifiers cannot be used with $wpdb->prepare() placeholders. The table name
    // is derived from $wpdb->prefix and escaped above with esc_sql(). Build the SQL via
    // concatenation and explicitly ignore the prepared-SQL rule for these lines.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Schema/index changes must use direct queries, table name is sanitized with esc_sql()
    $indexes_exist = $wpdb->get_results("SHOW INDEX FROM `" . $safe_table . "` WHERE Key_name = 'idx_user_date'");
    if (empty($indexes_exist)) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared -- Schema/index changes must use direct queries, table name is sanitized with esc_sql()
        $wpdb->query("ALTER TABLE `" . $safe_table . "` ADD INDEX idx_user_date (user_id, import_date)");
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared -- Schema/index changes must use direct queries, table name is sanitized with esc_sql()
        $wpdb->query("ALTER TABLE `" . $safe_table . "` ADD INDEX idx_status (status)");
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared -- Schema/index changes must use direct queries, table name is sanitized with esc_sql()
        $wpdb->query("ALTER TABLE `" . $safe_table . "` ADD INDEX idx_import_date (import_date)");
    }
    // Note: Caching is not used for schema/index changes in activation hooks, as these are one-time operations and must reflect the current DB state.
}
register_activation_hook(__FILE__, 'dbip_importer_activate');
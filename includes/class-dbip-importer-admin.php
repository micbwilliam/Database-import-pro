<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 * @package    DBIP_Importer
 */

class DBIP_Importer_Admin {

    /**
     * The current step of the wizard
     *
     * @var int
     */
    private $current_step = 1;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->current_step = isset($_GET['step']) ? (int) $_GET['step'] : 1;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        $screen = get_current_screen();
        if (strpos($screen->id, 'dbip-importer') !== false) {
            wp_enqueue_style(
                'dbip-importer-admin',
                DBIP_IMPORTER_PLUGIN_URL . 'assets/css/dbip-importer-admin.css',
                array(),
                DBIP_IMPORTER_VERSION
            );
        }
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        $screen = get_current_screen();
        if (strpos($screen->id, 'dbip-importer') !== false) {
            wp_enqueue_script(
                'dbip-importer-admin',
                DBIP_IMPORTER_PLUGIN_URL . 'assets/js/dbip-importer-admin.js',
                array('jquery'),
                DBIP_IMPORTER_VERSION,
                true
            );

            wp_localize_script('dbip-importer-admin', 'dbipImporter', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('dbip_importer_nonce')
            ));
        }
    }

    /**
     * Register the administration menu for this plugin into the WordPress Dashboard menu.
     *
     * @since    1.0.0
     */
    public function add_plugin_admin_menu() {
        add_menu_page(
            'Database Import Pro',
            'Database Import Pro',
            'manage_options',
            'dbip-importer',
            array($this, 'display_plugin_admin_page'),
            'dashicons-upload',
            30
        );

        // Add logs submenu
        add_submenu_page(
            'dbip-importer',
            __('Import Logs', 'database-import-pro'),
            __('Import Logs', 'database-import-pro'),
            'manage_options',
            'dbip-importer-logs',
            array($this, 'display_logs_page')
        );
    }

    /**
     * Initialize admin hooks
     */
    public function init() {
        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_dbip_upload_csv', array($this, 'handle_csv_upload'));
        add_action('wp_ajax_dbip_save_mapping', array($this, 'save_field_mapping'));
        add_action('wp_ajax_dbip_process_import', array($this, 'process_import'));
    }

    /**
     * Render the admin page for this plugin.
     *
     * @since    1.0.0
     */
    public function display_plugin_admin_page() {
        include_once DBIP_IMPORTER_PLUGIN_DIR . 'admin/partials/dbip-importer-admin-display.php';
    }

    /**
     * Render the logs page for this plugin.
     *
     * @since    1.0.0
     */
    public function display_logs_page() {
        include_once DBIP_IMPORTER_PLUGIN_DIR . 'admin/partials/view-logs.php';
    }

    /**
     * Handle CSV file upload
     */
    public function handle_csv_upload() {
        check_ajax_referer('dbip_importer_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
        }

        // Handle file upload logic here
        wp_send_json_success(array('message' => 'File uploaded successfully'));
    }

    /**
     * Save field mapping
     */
    public function save_field_mapping() {
        check_ajax_referer('dbip_importer_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
        }

        // Handle field mapping logic here
        wp_send_json_success(array('message' => 'Mapping saved successfully'));
    }

    /**
     * Process the import
     */
    public function process_import() {
        check_ajax_referer('dbip_importer_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
        }

        // Handle import process logic here
        wp_send_json_success(array('message' => 'Import completed successfully'));
    }
}
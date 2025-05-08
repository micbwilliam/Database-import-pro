<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 * @package    AEDC_Importer
 */

class AEDC_Importer_Admin {

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
        if (strpos($screen->id, 'aedc-importer') !== false) {
            wp_enqueue_style(
                'aedc-importer-admin',
                AEDC_IMPORTER_PLUGIN_URL . 'assets/css/aedc-importer-admin.css',
                array(),
                AEDC_IMPORTER_VERSION
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
        if (strpos($screen->id, 'aedc-importer') !== false) {
            wp_enqueue_script(
                'aedc-importer-admin',
                AEDC_IMPORTER_PLUGIN_URL . 'assets/js/aedc-importer-admin.js',
                array('jquery'),
                AEDC_IMPORTER_VERSION,
                true
            );

            wp_localize_script('aedc-importer-admin', 'aedcImporter', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aedc_importer_nonce')
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
            'AEDC - Importer',
            'AEDC - Importer',
            'manage_options',
            'aedc-importer',
            array($this, 'display_plugin_admin_page'),
            'dashicons-upload',
            30
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
        add_action('wp_ajax_aedc_upload_csv', array($this, 'handle_csv_upload'));
        add_action('wp_ajax_aedc_save_mapping', array($this, 'save_field_mapping'));
        add_action('wp_ajax_aedc_process_import', array($this, 'process_import'));
    }

    /**
     * Render the admin page for this plugin.
     *
     * @since    1.0.0
     */
    public function display_plugin_admin_page() {
        include_once AEDC_IMPORTER_PLUGIN_DIR . 'admin/partials/aedc-importer-admin-display.php';
    }

    /**
     * Handle CSV file upload
     */
    public function handle_csv_upload() {
        check_ajax_referer('aedc_importer_nonce', 'nonce');
        
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
        check_ajax_referer('aedc_importer_nonce', 'nonce');
        
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
        check_ajax_referer('aedc_importer_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
        }

        // Handle import process logic here
        wp_send_json_success(array('message' => 'Import completed successfully'));
    }
} 
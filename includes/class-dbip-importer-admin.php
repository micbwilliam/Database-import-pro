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

        // Add system status submenu
        add_submenu_page(
            'dbip-importer',
            __('System Status', 'database-import-pro'),
            __('System Status', 'database-import-pro'),
            'manage_options',
            'dbip-importer-status',
            array($this, 'display_status_page')
        );
    }

    /**
     * Initialize admin hooks
     */
    public function init() {
        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_notices', array($this, 'show_capability_notice'));
        add_action('admin_init', array($this, 'validate_step_access'));
    }

    /**
     * Validate step access on admin_init (before any output)
     *
     * @since 1.0.4
     */
    public function validate_step_access() {
        // Only check on our plugin page
        if (!isset($_GET['page']) || $_GET['page'] !== 'dbip-importer') {
            return;
        }

        $current_step = isset($_GET['step']) ? sanitize_text_field($_GET['step']) : 'upload';
        
        if (!$this->can_access_step($current_step)) {
            // Redirect to upload step if user tries to access invalid step
            wp_safe_redirect(admin_url('admin.php?page=dbip-importer&step=upload'));
            exit;
        }
    }

    /**
     * Show capability notice in admin
     *
     * @since 1.1.0
     */
    public function show_capability_notice() {
        $screen = get_current_screen();
        if (strpos($screen->id, 'dbip-importer') === false) {
            return;
        }

        // Load system checker
        require_once DBIP_IMPORTER_PLUGIN_DIR . 'includes/class-dbip-importer-system-check.php';
        
        $notice = DBIP_Importer_System_Check::get_capability_notice();
        
        printf(
            '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
            esc_attr($notice['type']),
            wp_kses_post($notice['message'])
        );
    }

    /**
     * Check if user can access a specific step
     *
     * @param string $step Current step
     * @return bool
     */
    private function can_access_step($step) {
        // Step 1 (upload) is always accessible
        if ($step === 'upload' || empty($step)) {
            return true;
        }

        // Step 2 (select-table) requires uploaded file
        if ($step === 'select-table') {
            $file_path = dbip_get_import_data('file_path');
            // Verify file path exists AND file actually exists on disk
            return !empty($file_path) && file_exists($file_path);
        }

        // Step 3 (map-fields) requires selected table
        if ($step === 'map-fields') {
            $target_table = dbip_get_import_data('target_table');
            return !empty($target_table);
        }

        // Step 4 (preview) requires field mapping
        if ($step === 'preview') {
            $mapping = dbip_get_import_data('mapping');
            return !empty($mapping) && is_array($mapping);
        }

        // Step 5 (import) requires mapping
        if ($step === 'import') {
            $mapping = dbip_get_import_data('mapping');
            return !empty($mapping) && is_array($mapping);
        }

        // Step 6 (completion) requires import stats
        if ($step === 'completion') {
            $stats = dbip_get_import_data('import_stats');
            return !empty($stats);
        }

        return false;
    }

    /**
     * Render the admin page for this plugin.
     *
     * @since    1.0.0
     */
    public function display_plugin_admin_page() {
        // Step validation is handled in validate_step_access() on admin_init
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
     * Render the system status page for this plugin.
     *
     * @since    1.1.0
     */
    public function display_status_page() {
        include_once DBIP_IMPORTER_PLUGIN_DIR . 'admin/partials/system-status.php';
    }
}
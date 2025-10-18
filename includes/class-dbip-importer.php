<?php
/**
 * The core plugin class.
 *
 * @since      1.0.0
 * @package    DBIP_Importer
 */

class DBIP_Importer {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      DBIP_Importer_Admin    $admin    Maintains and registers all hooks for the admin area.
     */
    protected $admin;

    /**
     * Define the core functionality of the plugin.
     *
     * @since    1.0.0
     */
    public function __construct() {
        if (is_admin()) {
            $this->admin = new DBIP_Importer_Admin();
        }
    }

    /**
     * Run the plugin.
     *
     * @since    1.0.0
     */
    public function run() {
        if (is_admin()) {
            $this->admin->init();
        }
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.0
     * @return    DBIP_Importer_Admin    Orchestrates the admin hooks of the plugin.
     */
    public function get_admin() {
        return $this->admin;
    }
} 
<?php
/**
 * Table structure handler class
 *
 * @since      1.0.0
 * @package    DBIP_Importer
 */

class DBIP_Importer_Table {
    /**
     * Initialize the class
     */
    public function __construct() {
        add_action('wp_ajax_dbip_get_table_structure', array($this, 'get_table_structure'));
        add_action('wp_ajax_dbip_save_target_table', array($this, 'save_target_table'));
    }

    /**
     * Get table structure with caching
     */
    public function get_table_structure() {
        check_ajax_referer('dbip_importer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'database-import-pro'));
        }

        if (empty($_POST['table'])) {
            wp_send_json_error(__('No table specified', 'database-import-pro'));
        }

        global $wpdb;
        $table = sanitize_text_field($_POST['table']);
        $table = esc_sql($table);
        
        // Create cache key for this table
        $cache_key = 'dbip_table_structure_' . md5($table);
        
        // Try to get cached structure
        $cached_structure = get_transient($cache_key);
        if (false !== $cached_structure) {
            wp_send_json_success($cached_structure);
        }

        // Get columns
        $columns = $wpdb->get_results("SHOW FULL COLUMNS FROM `{$table}`");
        if (!$columns) {
            wp_send_json_error(__('Failed to get table structure', 'database-import-pro'));
        }

        // Get indexes
        $indexes = $wpdb->get_results("SHOW INDEX FROM `{$table}`");

        ob_start();
        ?>
        <div class="table-structure">
            <div class="structure-section columns">
                <h4><?php esc_html_e('Columns', 'database-import-pro'); ?></h4>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Name', 'database-import-pro'); ?></th>
                            <th><?php esc_html_e('Type', 'database-import-pro'); ?></th>
                            <th><?php esc_html_e('Null', 'database-import-pro'); ?></th>
                            <th><?php esc_html_e('Default', 'database-import-pro'); ?></th>
                            <th><?php esc_html_e('Extra', 'database-import-pro'); ?></th>
                            <th><?php esc_html_e('Comment', 'database-import-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($columns as $column) : ?>
                            <tr>
                                <td><?php echo esc_html($column->Field); ?></td>
                                <td><?php echo esc_html($column->Type); ?></td>
                                <td><?php echo esc_html($column->Null); ?></td>
                                <td><?php echo esc_html($column->Default); ?></td>
                                <td><?php echo esc_html($column->Extra); ?></td>
                                <td><?php echo esc_html($column->Comment); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($indexes) : ?>
                <div class="structure-section indexes">
                    <h4><?php esc_html_e('Indexes', 'database-import-pro'); ?></h4>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Key Name', 'database-import-pro'); ?></th>
                                <th><?php esc_html_e('Column', 'database-import-pro'); ?></th>
                                <th><?php esc_html_e('Type', 'database-import-pro'); ?></th>
                                <th><?php esc_html_e('Unique', 'database-import-pro'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($indexes as $index) : ?>
                                <tr>
                                    <td><?php echo esc_html($index->Key_name); ?></td>
                                    <td><?php echo esc_html($index->Column_name); ?></td>
                                    <td><?php echo esc_html($index->Index_type); ?></td>
                                    <td><?php echo !$index->Non_unique ? 'Yes' : 'No'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
        $html = ob_get_clean();
        
        // Cache the structure for 1 hour (3600 seconds)
        set_transient($cache_key, $html, 3600);
        
        wp_send_json_success($html);
    }

    /**
     * Get list of all database tables
     * 
     * @return array Array of table names
     */
    private function get_database_tables() {
        global $wpdb;
        
        // Get all tables in the database
        $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
        
        // Extract table names from result array
        $table_names = array();
        if (!empty($tables)) {
            foreach ($tables as $table) {
                if (isset($table[0])) {
                    $table_names[] = $table[0];
                }
            }
        }
        
        return $table_names;
    }

    /**
     * Save target table selection
     */
    public function save_target_table() {
        check_ajax_referer('dbip_importer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'database-import-pro'));
        }

        if (empty($_POST['table'])) {
            wp_send_json_error(__('No table selected', 'database-import-pro'));
        }

        $table = sanitize_text_field($_POST['table']);
        
        // Validate table name against actual database tables (whitelist validation)
        global $wpdb;
        $valid_tables = $this->get_database_tables();
        
        if (!in_array($table, $valid_tables, true)) {
            error_log('Database Import Pro: Invalid table selected: ' . $table);
            wp_send_json_error(__('Invalid table selected. Table does not exist in database.', 'database-import-pro'));
        }
        
        // Store table selection in transient
        dbip_set_import_data('target_table', $table);

        // Get table structure for next step
        $table_escaped = esc_sql($table);
        $columns = $wpdb->get_results("SHOW FULL COLUMNS FROM `{$table_escaped}`");
        dbip_set_import_data('table_columns', $columns);

        wp_send_json_success();
    }
} 
<?php
/**
 * Table structure handler class
 *
 * @since      1.0.0
 * @package    AEDC_Importer
 */

class AEDC_Importer_Table {
    /**
     * Initialize the class
     */
    public function __construct() {
        add_action('wp_ajax_aedc_get_table_structure', array($this, 'get_table_structure'));
        add_action('wp_ajax_aedc_save_target_table', array($this, 'save_target_table'));
    }

    /**
     * Get table structure
     */
    public function get_table_structure() {
        check_ajax_referer('aedc_importer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'aedc-importer'));
        }

        if (empty($_POST['table'])) {
            wp_send_json_error(__('No table specified', 'aedc-importer'));
        }

        global $wpdb;
        $table = sanitize_text_field($_POST['table']);

        // Get columns
        $columns = $wpdb->get_results("SHOW FULL COLUMNS FROM `{$table}`");
        if (!$columns) {
            wp_send_json_error(__('Failed to get table structure', 'aedc-importer'));
        }

        // Get indexes
        $indexes = $wpdb->get_results("SHOW INDEX FROM `{$table}`");

        ob_start();
        ?>
        <div class="table-structure">
            <div class="structure-section columns">
                <h4><?php esc_html_e('Columns', 'aedc-importer'); ?></h4>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Name', 'aedc-importer'); ?></th>
                            <th><?php esc_html_e('Type', 'aedc-importer'); ?></th>
                            <th><?php esc_html_e('Null', 'aedc-importer'); ?></th>
                            <th><?php esc_html_e('Default', 'aedc-importer'); ?></th>
                            <th><?php esc_html_e('Extra', 'aedc-importer'); ?></th>
                            <th><?php esc_html_e('Comment', 'aedc-importer'); ?></th>
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
                    <h4><?php esc_html_e('Indexes', 'aedc-importer'); ?></h4>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Key Name', 'aedc-importer'); ?></th>
                                <th><?php esc_html_e('Column', 'aedc-importer'); ?></th>
                                <th><?php esc_html_e('Type', 'aedc-importer'); ?></th>
                                <th><?php esc_html_e('Unique', 'aedc-importer'); ?></th>
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
        wp_send_json_success($html);
    }

    /**
     * Save target table selection
     */
    public function save_target_table() {
        check_ajax_referer('aedc_importer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'aedc-importer'));
        }

        if (empty($_POST['table'])) {
            wp_send_json_error(__('No table selected', 'aedc-importer'));
        }

        $table = sanitize_text_field($_POST['table']);
        
        // Store table selection in session
        $_SESSION['aedc_importer']['target_table'] = $table;

        // Get table structure for next step
        global $wpdb;
        $columns = $wpdb->get_results("SHOW FULL COLUMNS FROM `{$table}`");
        $_SESSION['aedc_importer']['table_columns'] = $columns;

        wp_send_json_success();
    }
} 
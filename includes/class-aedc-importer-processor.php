<?php
/**
 * Import processor class
 *
 * @since      1.0.0
 * @package    AEDC_Importer
 */

class AEDC_Importer_Processor {
    /**
     * Batch size for processing
     */
    const BATCH_SIZE = 100;

    /**
     * Initialize the class
     */
    public function __construct() {
        add_action('wp_ajax_aedc_process_import_batch', array($this, 'process_batch'));
        add_action('wp_ajax_aedc_get_import_status', array($this, 'get_status'));
        add_action('wp_ajax_aedc_cancel_import', array($this, 'cancel_import'));
    }

    /**
     * Process a batch of records
     */
    public function process_batch() {
        try {
            error_log('AEDC Importer: Starting batch processing');
            
            // Force error reporting
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
            
            check_ajax_referer('aedc_importer_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                error_log('AEDC Importer: Unauthorized access');
                wp_send_json_error(__('Unauthorized access', 'aedc-importer'));
                return;
            }

            // Get batch number
            $batch = isset($_POST['batch']) ? (int)$_POST['batch'] : 0;
            error_log('AEDC Importer: Processing batch ' . $batch);
            
            // Debug session data
            error_log('AEDC Importer: Session data: ' . print_r($_SESSION['aedc_importer'], true));

            // Verify required data
            if (!isset($_SESSION['aedc_importer']['file']) || 
                !isset($_SESSION['aedc_importer']['mapping']) || 
                !isset($_SESSION['aedc_importer']['target_table'])) {
                error_log('AEDC Importer: Missing required session data');
                wp_send_json_error(__('Missing required import data', 'aedc-importer'));
                return;
            }

            $file_info = $_SESSION['aedc_importer']['file'];
            
            // Verify file exists and is readable
            if (!file_exists($file_info['path']) || !is_readable($file_info['path'])) {
                error_log('AEDC Importer: Import file not found or not readable: ' . $file_info['path']);
                wp_send_json_error(__('Import file not found or not readable', 'aedc-importer'));
                return;
            }

            $import_mode = $_SESSION['aedc_importer']['import_mode'] ?? 'insert';
            $key_columns = $_SESSION['aedc_importer']['key_columns'] ?? [];
            $allow_null = $_SESSION['aedc_importer']['allow_null'] ?? false;
            $mapping = $_SESSION['aedc_importer']['mapping'];
            $table = $_SESSION['aedc_importer']['target_table'];

            $stats = array(
                'processed' => 0,
                'inserted' => 0,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
                'messages' => array(),
                'completed' => false
            );

            $handle = fopen($file_info['path'], 'r');
            if ($handle === false) {
                error_log('AEDC Importer: Failed to open import file');
                wp_send_json_error(__('Failed to open import file', 'aedc-importer'));
                return;
            }

            try {
                // Skip header row
                fgetcsv($handle);
                
                // Skip to current batch position
                for ($i = 0; $i < ($batch * self::BATCH_SIZE); $i++) {
                    if (fgetcsv($handle) === false) {
                        if (feof($handle)) {
                            error_log('AEDC Importer: Reached end of file while skipping to batch');
                            $stats['completed'] = true;
                            fclose($handle);
                            
                            // If this is the final batch, save the import log
                            if ($stats['completed']) {
                                $error_log = array();
                                foreach ($stats['messages'] as $msg) {
                                    if ($msg['type'] === 'error') {
                                        $error_log[] = array(
                                            'row' => $msg['row'] ?? 'unknown',
                                            'message' => $msg['message']
                                        );
                                    }
                                }
                                $this->save_import_log($stats, json_encode($error_log));
                            }
                            
                            wp_send_json_success($stats);
                            return;
                        }
                        throw new Exception(__('Error reading CSV file', 'aedc-importer'));
                    }
                }

                // Process batch
                $processed = 0;
                while ($processed < self::BATCH_SIZE && ($row = fgetcsv($handle)) !== false) {
                    $row_num = ($batch * self::BATCH_SIZE) + $processed + 1;
                    $result = $this->process_row($row, $table, $mapping, $import_mode, $key_columns, $allow_null);
                    
                    $stats['processed']++;
                    $stats[$result['status']]++;
                    
                    if (!empty($result['message'])) {
                        $stats['messages'][] = array(
                            'type' => $result['status'] === 'failed' ? 'error' : 'info',
                            'row' => $row_num,
                            'message' => sprintf(
                                __('Row %d: %s', 'aedc-importer'),
                                $row_num,
                                $result['message']
                            )
                        );
                    }

                    $processed++;
                }

                // Check if we've reached the end
                $stats['completed'] = feof($handle);

                // If this is the final batch, save the import log
                if ($stats['completed']) {
                    $error_log = array();
                    foreach ($stats['messages'] as $msg) {
                        if ($msg['type'] === 'error') {
                            $error_log[] = array(
                                'row' => $msg['row'] ?? 'unknown',
                                'message' => $msg['message']
                            );
                        }
                    }
                    $this->save_import_log($stats, json_encode($error_log));
                }

                fclose($handle);
                error_log('AEDC Importer: Batch ' . $batch . ' completed. Stats: ' . print_r($stats, true));
                wp_send_json_success($stats);

            } catch (Exception $e) {
                if (is_resource($handle)) {
                    fclose($handle);
                }
                error_log('AEDC Importer: Error processing batch: ' . $e->getMessage());
                wp_send_json_error($e->getMessage());
            }

        } catch (Exception $e) {
            error_log('AEDC Importer: Fatal error: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Process a single row
     */
    private function process_row($row_data, $table, $mapping, $import_mode, $key_columns, $allow_null) {
        global $wpdb;
        
        try {
            // Validate row data
            if (empty($row_data)) {
                return array('status' => 'failed', 'message' => 'Empty row data');
            }

            // Get CSV headers from session
            $csv_headers = isset($_SESSION['aedc_importer']['headers']) ? $_SESSION['aedc_importer']['headers'] : array();
            
            // Create row data map using either headers or numeric indices
            if (!empty($csv_headers)) {
                // Take only as many columns as we have headers
                $row_data = array_slice($row_data, 0, count($csv_headers));
                // Pad if we have fewer columns than headers
                $row_data = array_pad($row_data, count($csv_headers), '');
                $row = array_combine($csv_headers, $row_data);
            } else {
                // If no headers, just use numeric indices
                $row = array_values($row_data);
            }

            $data = array();
            
            // Process each mapped column
            foreach ($mapping as $column => $map) {
                if (!empty($map['skip'])) {
                    continue;
                }
                
                if (isset($map['csv_field']) && $map['csv_field'] === '__keep_current__') {
                    continue;
                }

                $value = '';
                if (!empty($map['csv_field'])) {
                    // Get value by CSV field name or column index
                    if (isset($row[$map['csv_field']])) {
                        $value = $row[$map['csv_field']];
                    } else if (is_numeric($map['csv_field']) && isset($row[(int)$map['csv_field']])) {
                        $value = $row[(int)$map['csv_field']];
                    }
                } elseif (!empty($map['default_value'])) {
                    $value = $map['default_value'];
                }

                if (!empty($map['transform'])) {
                    $value = $this->apply_transformation($value, $map['transform'], $map['custom_transform'] ?? '');
                }

                // Handle NULL values
                if (($value === '' || $value === null) && $allow_null && !empty($map['allow_null'])) {
                    $data[$column] = null;
                } else {
                    $data[$column] = $value;
                }
            }

            // Handle different import modes
            switch ($import_mode) {
                case 'insert':
                    if ($this->record_exists($table, $data, $key_columns)) {
                        return array('status' => 'skipped', 'message' => __('Record already exists', 'aedc-importer'));
                    }
                    
                    $result = $wpdb->insert($table, $data);
                    if ($result === false) {
                        throw new Exception($wpdb->last_error);
                    }
                    return array('status' => 'inserted');

                case 'update':
                    if (!$this->record_exists($table, $data, $key_columns)) {
                        return array('status' => 'skipped', 'message' => __('Record not found for update', 'aedc-importer'));
                    }
                    
                    $where = array();
                    foreach ($key_columns as $key) {
                        if (!isset($data[$key])) {
                            throw new Exception(sprintf(__('Missing key column %s', 'aedc-importer'), $key));
                        }
                        $where[$key] = $data[$key];
                        unset($data[$key]); // Don't update key columns
                    }
                    
                    $result = $wpdb->update($table, $data, $where);
                    if ($result === false) {
                        throw new Exception($wpdb->last_error);
                    }
                    return array('status' => 'updated');

                case 'upsert':
                    $where = array();
                    foreach ($key_columns as $key) {
                        if (!isset($data[$key])) {
                            throw new Exception(sprintf(__('Missing key column %s', 'aedc-importer'), $key));
                        }
                        $where[$key] = $data[$key];
                    }
                    
                    if ($this->record_exists($table, $data, $key_columns)) {
                        $update_data = $data;
                        foreach ($key_columns as $key) {
                            unset($update_data[$key]); // Don't update key columns
                        }
                        $result = $wpdb->update($table, $update_data, $where);
                        $status = 'updated';
                    } else {
                        $result = $wpdb->insert($table, $data);
                        $status = 'inserted';
                    }
                    
                    if ($result === false) {
                        throw new Exception($wpdb->last_error);
                    }
                    return array('status' => $status);

                default:
                    throw new Exception(sprintf(__('Invalid import mode: %s', 'aedc-importer'), $import_mode));
            }
        } catch (Exception $e) {
            error_log('AEDC Importer: Row processing error: ' . $e->getMessage());
            return array('status' => 'failed', 'message' => $e->getMessage());
        }
    }

    /**
     * Validate that all required fields have values
     */
    private function validate_required_fields($table, $data) {
        global $wpdb;
        
        // Get table structure
        $columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table}`");
        
        foreach ($columns as $column) {
            // Check if column is required (NOT NULL and no default value)
            if ($column->Null === 'NO' && $column->Default === null) {
                // Skip auto increment columns
                if ($column->Extra === 'auto_increment') {
                    continue;
                }
                
                // Check if required field has a value
                if (!isset($data[$column->Field]) || $data[$column->Field] === null || $data[$column->Field] === '') {
                    error_log("AEDC Importer: Missing required field {$column->Field}");
                    return false;
                }
            }
        }
        
        return true;
    }

    /**
     * Check if a record exists based on key columns
     */
    private function record_exists($table, $data, $key_columns) {
        global $wpdb;
        
        if (empty($key_columns)) {
            return false;
        }

        $where = array();
        foreach ($key_columns as $key) {
            if (isset($data[$key])) {
                $where[] = $wpdb->prepare("`{$key}` = %s", $data[$key]);
            }
        }

        if (empty($where)) {
            return false;
        }

        $query = "SELECT 1 FROM `{$table}` WHERE " . implode(' AND ', $where) . " LIMIT 1";
        return (bool)$wpdb->get_var($query);
    }

    /**
     * Apply data transformation
     */
    private function apply_transformation($value, $transform, $custom_code = '') {
        switch ($transform) {
            case 'trim':
                return trim($value);
            case 'uppercase':
                return strtoupper($value);
            case 'lowercase':
                return strtolower($value);
            case 'capitalize':
                return ucwords(strtolower($value));
            case 'custom':
                if (!empty($custom_code)) {
                    try {
                        return eval('return ' . $custom_code . ';');
                    } catch (Exception $e) {
                        error_log('Custom transform error: ' . $e->getMessage());
                    }
                }
                return $value;
            default:
                return $value;
        }
    }

    /**
     * Cancel import and clean up
     */
    public function cancel_import() {
        check_ajax_referer('aedc_importer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'aedc-importer'));
        }

        // Clean up any temporary data if needed
        if (isset($_SESSION['aedc_importer'])) {
            unset(
                $_SESSION['aedc_importer']['import_mode'],
                $_SESSION['aedc_importer']['key_columns'],
                $_SESSION['aedc_importer']['allow_null'],
                $_SESSION['aedc_importer']['dry_run']
            );
        }

        wp_send_json_success();
    }

    /**
     * Save import log to database
     */
    private function save_import_log($stats, $error_log = '') {
        global $wpdb;
        
        error_log('AEDC Importer Debug: Starting save_import_log');
        error_log('AEDC Importer Debug: Stats - ' . print_r($stats, true));
        
        // Calculate the total duration from the start time
        $start_time = isset($_SESSION['aedc_importer']['start_time']) ? strtotime($_SESSION['aedc_importer']['start_time']) : 0;
        $duration = $start_time > 0 ? (time() - $start_time) : 0;
        
        // Get total records from session
        $total_records = isset($_SESSION['aedc_importer']['total_records']) ? $_SESSION['aedc_importer']['total_records'] : $stats['processed'];
        
        $import_data = array(
            'user_id' => get_current_user_id(),
            'import_date' => current_time('mysql'),
            'file_name' => basename($_SESSION['aedc_importer']['file']['name']),
            'table_name' => $_SESSION['aedc_importer']['target_table'],
            'total_rows' => $total_records,
            'inserted' => isset($stats['inserted']) ? $stats['inserted'] : 0,
            'updated' => isset($stats['updated']) ? $stats['updated'] : 0,
            'skipped' => isset($stats['skipped']) ? $stats['skipped'] : 0,
            'failed' => isset($stats['failed']) ? $stats['failed'] : 0,
            'error_log' => $error_log,
            'status' => ($stats['failed'] > 0) ? 'completed_with_errors' : 'completed',
            'duration' => $duration
        );

        error_log('AEDC Importer Debug: Import data - ' . print_r($import_data, true));
        error_log('AEDC Importer Debug: Table name - ' . $wpdb->prefix . 'aedc_import_logs');

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}aedc_import_logs'");
        if (!$table_exists) {
            error_log('AEDC Importer Debug: Table does not exist, creating now...');
            $this->create_logs_table();
        }
        
        $result = $wpdb->insert($wpdb->prefix . 'aedc_import_logs', $import_data);
        
        if ($result === false) {
            error_log('AEDC Importer Error: Failed to save import log - ' . $wpdb->last_error);
            return false;
        }

        error_log('AEDC Importer Debug: Log saved successfully with ID: ' . $wpdb->insert_id);
        
        // Update session with final stats for completion page
        $_SESSION['aedc_importer']['import_stats'] = array_merge($stats, array('duration' => $duration));
        
        // If there are errors, store them in session for the completion page
        if (!empty($error_log)) {
            $_SESSION['aedc_importer']['error_log'] = json_decode($error_log, true);
        }
        
        return $wpdb->insert_id;
    }

    /**
     * Get all import logs
     */
    public function get_import_logs() {
        check_ajax_referer('aedc_importer_nonce', 'nonce');
        
        global $wpdb;
        $logs = $wpdb->get_results("
            SELECT l.*, u.display_name as user 
            FROM {$wpdb->prefix}aedc_import_logs l 
            LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID 
            ORDER BY import_date DESC
        ");
        
        wp_send_json_success($logs);
    }

    /**
     * Export failed rows as CSV
     */
    public function export_error_log() {
        check_ajax_referer('aedc_importer_nonce', 'nonce');
        
        if (!isset($_POST['log_id'])) {
            wp_send_json_error('Missing log ID');
            return;
        }
        
        global $wpdb;
        $log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aedc_import_logs WHERE id = %d",
            $_POST['log_id']
        ));
        
        if (!$log || empty($log->error_log)) {
            wp_send_json_error('No error log found');
            return;
        }
        
        $error_data = json_decode($log->error_log, true);
        if (empty($error_data)) {
            wp_send_json_error('Invalid error log data');
            return;
        }
        
        // Format CSV content
        $csv_content = "Row,Error Message\n";
        foreach ($error_data as $error) {
            $csv_content .= '"' . $error['row'] . '","' . addslashes($error['message']) . "\"\n";
        }
        
        wp_send_json_success($csv_content);
    }

    /**
     * Save import progress in session
     */
    public function save_import_progress() {
        check_ajax_referer('aedc_importer_nonce', 'nonce');
        
        if (!isset($_POST['stats']) || !isset($_POST['percentage'])) {
            wp_send_json_error('Missing required data');
            return;
        }

        // Store progress in session
        $_SESSION['aedc_importer']['import_stats'] = $_POST['stats'];
        $_SESSION['aedc_importer']['progress'] = $_POST['percentage'];
        
        wp_send_json_success();
    }

    /**
     * Save import start time
     */
    public function save_import_start() {
        check_ajax_referer('aedc_importer_nonce', 'nonce');
        $_SESSION['aedc_importer']['start_time'] = current_time('mysql');
        wp_send_json_success();
    }

    /**
     * Create the import logs table
     */
    private function create_logs_table() {
        global $wpdb;
        
        error_log('AEDC Importer Debug: Creating logs table');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aedc_import_logs (
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
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        error_log('AEDC Importer Debug: Logs table creation complete');
    }
}
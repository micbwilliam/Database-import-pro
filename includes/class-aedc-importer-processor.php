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
                            wp_send_json_success($stats);
                            return;
                        }
                        throw new Exception(__('Error reading CSV file', 'aedc-importer'));
                    }
                }

                // Process batch
                $processed = 0;
                while ($processed < self::BATCH_SIZE && ($row = fgetcsv($handle)) !== false) {
                    $result = $this->process_row($row, $table, $mapping, $import_mode, $key_columns, $allow_null);
                    
                    $stats['processed']++;
                    $stats[$result['status']]++;
                    
                    if (!empty($result['message'])) {
                        $stats['messages'][] = array(
                            'type' => $result['status'] === 'failed' ? 'error' : 'info',
                            'message' => sprintf(
                                __('Row %d: %s', 'aedc-importer'),
                                ($batch * self::BATCH_SIZE) + $processed + 1,
                                $result['message']
                            )
                        );
                    }

                    $processed++;
                }

                // Check if we've reached the end
                $stats['completed'] = feof($handle);

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
}
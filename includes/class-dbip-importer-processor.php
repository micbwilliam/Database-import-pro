<?php
/**
 * Import processor class
 *
 * @since      1.0.0
 * @package    DBIP_Importer
 */

class DBIP_Importer_Processor {
    /**
     * Batch size for processing
     */
    const BATCH_SIZE = 100;

    /**
     * Initialize the class
     */
    public function __construct() {
        add_action('wp_ajax_dbip_process_import_batch', array($this, 'process_batch'));
        add_action('wp_ajax_dbip_get_import_status', array($this, 'get_status'));
        add_action('wp_ajax_dbip_cancel_import', array($this, 'cancel_import'));
    }

    /**
     * Acquire an import lock to prevent concurrent imports
     * 
     * @return bool True if lock acquired, false if another import is running
     */
    private function acquire_import_lock(): bool {
        $lock_key = 'dbip_import_lock_' . get_current_user_id();
        $lock_timeout = 3600; // 1 hour
        
        // Check if lock exists
        $existing_lock = get_transient($lock_key);
        if ($existing_lock !== false) {
            // Lock exists, check if it's still valid
            $lock_age = time() - $existing_lock;
            if ($lock_age < $lock_timeout) {
                // Lock is still valid, import in progress
                return false;
            }
            // Lock expired, we can acquire it
        }
        
        // Set the lock with timestamp
        set_transient($lock_key, time(), $lock_timeout);
        error_log('Database Import Pro: Import lock acquired for user ' . get_current_user_id());
        return true;
    }

    /**
     * Release the import lock
     * 
     * @return void
     */
    private function release_import_lock(): void {
        $lock_key = 'dbip_import_lock_' . get_current_user_id();
        delete_transient($lock_key);
        error_log('Database Import Pro: Import lock released for user ' . get_current_user_id());
    }

    /**
     * Check if an import lock exists
     * 
     * @return bool True if locked, false otherwise
     */
    private function is_import_locked(): bool {
        $lock_key = 'dbip_import_lock_' . get_current_user_id();
        return get_transient($lock_key) !== false;
    }

    /**
     * Check memory availability before processing
     * 
     * @return array Array with 'available' (bool) and 'message' (string) keys
     */
    private function check_memory_availability(): array {
        // Get memory limit
        $memory_limit = ini_get('memory_limit');
        if ($memory_limit === '-1') {
            // Unlimited memory
            return array(
                'available' => true,
                'message' => 'Unlimited memory available'
            );
        }
        
        // Convert memory limit to bytes
        $limit_bytes = $this->convert_to_bytes($memory_limit);
        
        // Get current memory usage
        $current_usage = memory_get_usage(true);
        
        // Calculate available memory
        $available_memory = $limit_bytes - $current_usage;
        
        // Require at least 32MB free memory for batch processing
        $required_memory = 32 * 1024 * 1024; // 32MB in bytes
        
        if ($available_memory < $required_memory) {
            $available_mb = round($available_memory / (1024 * 1024), 2);
            $required_mb = round($required_memory / (1024 * 1024), 2);
            
            return array(
                'available' => false,
                'message' => sprintf(
                    __('Insufficient memory available. Required: %sMB, Available: %sMB. Please increase PHP memory_limit.', 'database-import-pro'),
                    $required_mb,
                    $available_mb
                )
            );
        }
        
        return array(
            'available' => true,
            'message' => sprintf(
                __('Memory check passed. Available: %sMB', 'database-import-pro'),
                round($available_memory / (1024 * 1024), 2)
            )
        );
    }

    /**
     * Convert PHP memory notation to bytes
     * 
     * @param string $value Memory value (e.g., '256M', '1G')
     * @return int Memory in bytes
     */
    private function convert_to_bytes(string $value): int {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int)$value;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
                // Fall through
            case 'm':
                $value *= 1024;
                // Fall through
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }

    /**
     * Process a batch of records
     * 
     * @return void
     */
    public function process_batch(): void {
        try {
            error_log('Database Import Pro Importer: Starting batch processing');
            
            // Force error reporting
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
            
            check_ajax_referer('dbip_importer_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                error_log('Database Import Pro Importer: Unauthorized access');
                wp_send_json_error(__('Unauthorized access', 'database-import-pro'));
                return;
            }
            
            // Check available memory before processing
            $memory_check = $this->check_memory_availability();
            if (!$memory_check['available']) {
                error_log('Database Import Pro Importer: Insufficient memory - ' . $memory_check['message']);
                wp_send_json_error($memory_check['message']);
                return;
            }

            // Get batch number
            $batch = isset($_POST['batch']) ? (int)$_POST['batch'] : 0;
            error_log('Database Import Pro Importer: Processing batch ' . $batch);
            
            // Acquire lock to prevent concurrent imports
            $lock_acquired = $this->acquire_import_lock();
            if (!$lock_acquired) {
                error_log('Database Import Pro Importer: Another import is already in progress');
                wp_send_json_error(__('Another import is already in progress. Please wait for it to complete.', 'database-import-pro'));
                return;
            }
            
            // Debug import data
            $import_data = dbip_get_import_data();
            error_log('Database Import Pro Importer: Import data: ' . print_r($import_data, true));

            // Verify required data
            if (!dbip_get_import_data('file') || 
                !dbip_get_import_data('mapping') || 
                !dbip_get_import_data('target_table')) {
                error_log('Database Import Pro Importer: Missing required import data');
                wp_send_json_error(__('Missing required import data', 'database-import-pro'));
                return;
            }

            $file_info = dbip_get_import_data('file');
            
            // Verify file exists and is readable
            if (!file_exists($file_info['path']) || !is_readable($file_info['path'])) {
                error_log('Database Import Pro Importer: Import file not found or not readable: ' . $file_info['path']);
                wp_send_json_error(__('Import file not found or not readable', 'database-import-pro'));
                return;
            }

            $import_mode = dbip_get_import_data('import_mode') ?: 'insert';
            $key_columns = dbip_get_import_data('key_columns') ?: [];
            $allow_null = dbip_get_import_data('allow_null') ?: false;
            $mapping = dbip_get_import_data('mapping');
            $table = dbip_get_import_data('target_table');

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
                error_log('Database Import Pro Importer: Failed to open import file');
                wp_send_json_error(__('Failed to open import file', 'database-import-pro'));
                return;
            }

            try {
                // Skip header row
                fgetcsv($handle);
                
                // Skip to current batch position
                for ($i = 0; $i < ($batch * self::BATCH_SIZE); $i++) {
                    if (fgetcsv($handle) === false) {
                        if (feof($handle)) {
                            error_log('Database Import Pro Importer: Reached end of file while skipping to batch');
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
                        throw new Exception(__('Error reading CSV file', 'database-import-pro'));
                    }
                }

                // Start database transaction for batch integrity
                global $wpdb;
                $wpdb->query('START TRANSACTION');
                
                $transaction_success = true;

                // Process batch
                $processed = 0;
                while ($processed < self::BATCH_SIZE && ($row = fgetcsv($handle)) !== false) {
                    $row_num = ($batch * self::BATCH_SIZE) + $processed + 1;
                    $result = $this->process_row($row, $table, $mapping, $import_mode, $key_columns, $allow_null);
                    
                    $stats['processed']++;
                    $stats[$result['status']]++;
                    
                    // Track if any critical failures occur
                    if ($result['status'] === 'failed') {
                        // Decide if failure should rollback entire batch
                        // For now, we continue but could rollback on critical errors
                        $transaction_success = true; // Still commit partial batch
                    }
                    
                    if (!empty($result['message'])) {
                        $stats['messages'][] = array(
                            'type' => $result['status'] === 'failed' ? 'error' : 'info',
                            'row' => $row_num,
                            'message' => sprintf(
                                __('Row %d: %s', 'database-import-pro'),
                                $row_num,
                                $result['message']
                            )
                        );
                    }

                    $processed++;
                }
                
                // Commit or rollback transaction
                if ($transaction_success) {
                    $wpdb->query('COMMIT');
                } else {
                    $wpdb->query('ROLLBACK');
                }

                // Check if we've reached the end
                $stats['completed'] = feof($handle);

                // If this is the final batch, save the import log and cleanup
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
                    
                    // Clean up uploaded file after successful import
                    $this->cleanup_import_file();
                    
                    // Release lock when import is complete
                    $this->release_import_lock();
                }

                fclose($handle);
                error_log('Database Import Pro Importer: Batch ' . $batch . ' completed. Stats: ' . print_r($stats, true));
                wp_send_json_success($stats);

            } catch (Exception $e) {
                if (is_resource($handle)) {
                    fclose($handle);
                }
                // Release lock on error
                $this->release_import_lock();
                error_log('Database Import Pro Importer: Error processing batch: ' . $e->getMessage());
                wp_send_json_error($e->getMessage());
            }

        } catch (Exception $e) {
            // Release lock on fatal error
            $this->release_import_lock();
            error_log('Database Import Pro Importer: Fatal error: ' . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Process a single row
     * 
     * @param array $row_data CSV row data
     * @param string $table Target database table name
     * @param array $mapping Column mappings
     * @param string $import_mode Import mode (insert, update, upsert)
     * @param array $key_columns Key columns for matching
     * @param bool $allow_null Whether to allow null values
     * @return array Processing result with status and message
     */
    private function process_row(array $row_data, string $table, array $mapping, string $import_mode, array $key_columns, bool $allow_null): array {
        global $wpdb;
        
        try {
            // Validate row data
            if (empty($row_data)) {
                return array('status' => 'failed', 'message' => 'Empty row data');
            }

            // Get CSV headers from transient
            $csv_headers = dbip_get_import_data('headers') ?: array();
            
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
                        return array('status' => 'skipped', 'message' => __('Record already exists', 'database-import-pro'));
                    }
                    
                    $result = $wpdb->insert($table, $data);
                    if ($result === false) {
                        throw new Exception($wpdb->last_error);
                    }
                    return array('status' => 'inserted');

                case 'update':
                    if (!$this->record_exists($table, $data, $key_columns)) {
                        return array('status' => 'skipped', 'message' => __('Record not found for update', 'database-import-pro'));
                    }
                    
                    $where = array();
                    foreach ($key_columns as $key) {
                        if (!isset($data[$key])) {
                            throw new Exception(sprintf(__('Missing key column %s', 'database-import-pro'), $key));
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
                            throw new Exception(sprintf(__('Missing key column %s', 'database-import-pro'), $key));
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
                    throw new Exception(sprintf(__('Invalid import mode: %s', 'database-import-pro'), $import_mode));
            }
        } catch (Exception $e) {
            error_log('Database Import Pro Importer: Row processing error: ' . $e->getMessage());
            return array('status' => 'failed', 'message' => $e->getMessage());
        }
    }

    /**
     * Validate that all required fields have values
     * 
     * @param string $table Database table name
     * @param array $data Row data to validate
     * @return bool True if valid, false otherwise
     */
    private function validate_required_fields(string $table, array $data): bool {
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
                    error_log("Database Import Pro: Missing required field {$column->Field}");
                    return false;
                }
            }
        }
        
        return true;
    }

    /**
     * Check if a record exists based on key columns
     * 
     * @param string $table Database table name
     * @param array $data Row data with key values
     * @param array $key_columns Column names to use for matching
     * @return bool True if record exists, false otherwise
     */
    private function record_exists(string $table, array $data, array $key_columns): bool {
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
     * 
     * @param string $value Value to transform
     * @param string $transform Transformation type
     * @param string $custom_code Custom code (deprecated for security)
     * @return string Transformed value
     */
    private function apply_transformation(string $value, string $transform, string $custom_code = ''): string {
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
                // SECURITY FIX: Removed eval() - custom transformations disabled for security
                // Custom PHP code execution has been removed due to security concerns
                error_log('Database Import Pro: Custom transformations are disabled for security reasons');
                return $value;
            default:
                return $value;
        }
    }

    /**
     * Clean up uploaded file after import completion
     * 
     * @return void
     */
    private function cleanup_import_file(): void {
        $file_info = dbip_get_import_data('file');
        if ($file_info && isset($file_info['path'])) {
            $file_path = $file_info['path'];
            if (file_exists($file_path)) {
                @unlink($file_path);
                error_log('Database Import Pro: Cleaned up import file: ' . $file_path);
            }
            dbip_set_import_data('file', null);
        }
    }

    /**
     * Cancel import and clean up
     * 
     * @return void
     */
    public function cancel_import(): void {
        check_ajax_referer('dbip_importer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'database-import-pro'));
        }

        // Clean up any temporary data
        dbip_set_import_data('import_mode', null);
        dbip_set_import_data('key_columns', null);
        dbip_set_import_data('allow_null', null);
        dbip_set_import_data('dry_run', null);
        
        // Also cleanup the uploaded file
        $this->cleanup_import_file();
        
        // Release import lock
        $this->release_import_lock();

        wp_send_json_success();
    }

    /**
     * Save import log to database
     * 
     * @param array $stats Import statistics
     * @param string $error_log Error messages
     * @return int|false Insert ID on success, false on failure
     */
    private function save_import_log(array $stats, string $error_log = '') {
        global $wpdb;
        
        error_log('Database Import Pro Importer Debug: Starting save_import_log');
        error_log('Database Import Pro Importer Debug: Stats - ' . print_r($stats, true));
        
        // Calculate the total duration from the start time
        $start_time_str = dbip_get_import_data('start_time');
        $start_time = $start_time_str ? strtotime($start_time_str) : 0;
        $duration = $start_time > 0 ? (time() - $start_time) : 0;
        
        // Get total records from transient
        $total_records = dbip_get_import_data('total_records') ?: $stats['processed'];
        
        $file_info = dbip_get_import_data('file');
        $import_data = array(
            'user_id' => get_current_user_id(),
            'import_date' => wp_date('Y-m-d H:i:s', null, wp_timezone()),
            'file_name' => basename($file_info['name']),
            'table_name' => dbip_get_import_data('target_table'),
            'total_rows' => $total_records,
            'inserted' => isset($stats['inserted']) ? $stats['inserted'] : 0,
            'updated' => isset($stats['updated']) ? $stats['updated'] : 0,
            'skipped' => isset($stats['skipped']) ? $stats['skipped'] : 0,
            'failed' => isset($stats['failed']) ? $stats['failed'] : 0,
            'error_log' => $error_log,
            'status' => ($stats['failed'] > 0) ? 'completed_with_errors' : 'completed',
            'duration' => $duration
        );

        error_log('Database Import Pro Importer Debug: Import data - ' . print_r($import_data, true));
        error_log('Database Import Pro Importer Debug: Table name - ' . $wpdb->prefix . 'dbip_import_logs');

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}dbip_import_logs'");
        if (!$table_exists) {
            error_log('Database Import Pro Importer Debug: Table does not exist, creating now...');
            $this->create_logs_table();
        }
        
        $result = $wpdb->insert($wpdb->prefix . 'dbip_import_logs', $import_data);
        
        if ($result === false) {
            error_log('Database Import Pro Importer Error: Failed to save import log - ' . $wpdb->last_error);
            return false;
        }

        error_log('Database Import Pro Importer Debug: Log saved successfully with ID: ' . $wpdb->insert_id);
        
        // Update transient with final stats for completion page
        dbip_set_import_data('import_stats', array_merge($stats, array('duration' => $duration)));
        
        // If there are errors, store them in transient for the completion page
        if (!empty($error_log)) {
            dbip_set_import_data('error_log', json_decode($error_log, true));
        }
        
        return $wpdb->insert_id;
    }

    /**
     * Get all import logs with pagination
     * 
     * @return void
     */
    public function get_import_logs(): void {
        check_ajax_referer('dbip_importer_nonce', 'nonce');
        
        global $wpdb;
        
        // Get pagination parameters
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 20;
        
        // Validate and set reasonable limits
        $page = max(1, $page);
        $per_page = max(1, min(100, $per_page)); // Cap at 100 per page
        
        // Calculate offset
        $offset = ($page - 1) * $per_page;
        
        // Get total count
        $total_count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}dbip_import_logs
        ");
        
        // Get paginated logs
        $logs = $wpdb->get_results($wpdb->prepare("
            SELECT l.*, u.display_name as user 
            FROM {$wpdb->prefix}dbip_import_logs l 
            LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID 
            ORDER BY import_date DESC
            LIMIT %d OFFSET %d
        ", $per_page, $offset));
        
        // Calculate pagination info
        $total_pages = ceil($total_count / $per_page);
        
        wp_send_json_success(array(
            'logs' => $logs,
            'pagination' => array(
                'current_page' => $page,
                'per_page' => $per_page,
                'total_items' => $total_count,
                'total_pages' => $total_pages
            )
        ));
    }

    /**
     * Export failed rows as CSV
     * 
     * @return void
     */
    public function export_error_log(): void {
        check_ajax_referer('dbip_importer_nonce', 'nonce');
        
        if (!isset($_POST['log_id'])) {
            wp_send_json_error('Missing log ID');
            return;
        }
        
        global $wpdb;
        $log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dbip_import_logs WHERE id = %d",
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
     * 
     * @return void
     */
    public function save_import_progress(): void {
        check_ajax_referer('dbip_importer_nonce', 'nonce');
        
        if (!isset($_POST['stats']) || !isset($_POST['percentage'])) {
            wp_send_json_error('Missing required data');
            return;
        }

        // Store progress in transient
        dbip_set_import_data('import_stats', $_POST['stats']);
        dbip_set_import_data('progress', $_POST['percentage']);
        
        wp_send_json_success();
    }

    /**
     * Save import start time
     * 
     * @return void
     */
    public function save_import_start(): void {
        check_ajax_referer('dbip_importer_nonce', 'nonce');
        dbip_set_import_data('start_time', wp_date('Y-m-d H:i:s', null, wp_timezone()));
        wp_send_json_success();
    }

    /**
     * Create the import logs table
     * 
     * @return void
     */
    private function create_logs_table(): void {
        global $wpdb;
        
        error_log('Database Import Pro Importer Debug: Creating logs table');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}dbip_import_logs (
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
        
        error_log('Database Import Pro Importer Debug: Logs table creation complete');
    }
}

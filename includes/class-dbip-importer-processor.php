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
        add_action('wp_ajax_dbip_save_import_progress', array($this, 'save_import_progress'));
        add_action('wp_ajax_dbip_save_import_start', array($this, 'save_import_start'));
        add_action('wp_ajax_dbip_download_error_log', array($this, 'download_error_log'));
        add_action('wp_ajax_dbip_get_import_logs', array($this, 'get_import_logs'));
        add_action('wp_ajax_dbip_export_error_log', array($this, 'export_error_log'));
    }

    /**
     * Get current import status
     * 
     * @return void
     */
    public function get_status(): void {
        check_ajax_referer('dbip_importer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'database-import-pro'));
        }

        $status = array(
            'is_running' => $this->is_import_locked(),
            'progress' => dbip_get_import_data('progress') ?: 0,
            'stats' => dbip_get_import_data('import_stats') ?: array()
        );

        wp_send_json_success($status);
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
        $this->debug_log('Database Import Pro: Import lock acquired for user ' . get_current_user_id());
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
        $this->debug_log('Database Import Pro: Import lock released for user ' . get_current_user_id());
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
                    /* translators: 1: required memory in MB, 2: available memory in MB */
                    __('Insufficient memory available. Required: %1$sMB, Available: %2$sMB. Please increase PHP memory_limit.', 'database-import-pro'),
                    $required_mb,
                    $available_mb
                )
            );
        }
        
        return array(
            'available' => true,
            'message' => sprintf(
                /* translators: %s: available memory in MB */
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
     * Debug logging wrapper that respects WP_DEBUG.
     *
     * @param string $message Message to log
     * @return void
     */
    private function debug_log(string $message): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- deliberate debug wrapper using error_log when WP_DEBUG is on
            error_log($message);
        }
    }

    /**
     * Process a batch of records
     * 
     * @return void
     */
    public function process_batch(): void {
        try {
            $this->debug_log('Database Import Pro Importer: Starting batch processing');
            
            check_ajax_referer('dbip_importer_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                $this->debug_log('Database Import Pro Importer: Unauthorized access');
                wp_send_json_error(__('Unauthorized access', 'database-import-pro'));
                return;
            }
            
            // Check available memory before processing
            $memory_check = $this->check_memory_availability();
            if (!$memory_check['available']) {
                $this->debug_log('Database Import Pro Importer: Insufficient memory - ' . $memory_check['message']);
                wp_send_json_error($memory_check['message']);
                return;
            }

            // Get batch number (unslash and sanitize)
            $batch = 0;
            if (isset($_POST['batch'])) {
                $batch = absint(wp_unslash($_POST['batch']));
            }
            $this->debug_log('Database Import Pro Importer: Processing batch ' . $batch);
            
            // Acquire lock to prevent concurrent imports
            $lock_acquired = $this->acquire_import_lock();
            if (!$lock_acquired) {
                $this->debug_log('Database Import Pro Importer: Another import is already in progress');
                wp_send_json_error(__('Another import is already in progress. Please wait for it to complete.', 'database-import-pro'));
                return;
            }
            
            // Debug import data
            $import_data = dbip_get_import_data();
            $this->debug_log('Database Import Pro Importer: Import data: ' . wp_json_encode($import_data));

            // Verify required data
                if (!dbip_get_import_data('file') || 
                !dbip_get_import_data('mapping') || 
                !dbip_get_import_data('target_table')) {
                $this->debug_log('Database Import Pro Importer: Missing required import data');
                wp_send_json_error(__('Missing required import data', 'database-import-pro'));
                return;
            }

            $file_info = dbip_get_import_data('file');
            
            // Verify file exists and is readable
            $file_path = $file_info['path'];
            if (!file_exists($file_path) || !is_readable($file_path)) {
                $this->debug_log('Database Import Pro Importer: Import file not found or not readable: ' . $file_path);
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

            // Use WP_Filesystem to read the CSV file to satisfy WP file operation requirements
            require_once ABSPATH . 'wp-admin/includes/file.php';
            if (false === WP_Filesystem()) {
                $this->debug_log('Database Import Pro Importer: WP_Filesystem initialization failed');
                wp_send_json_error(__('Failed to initialize file system', 'database-import-pro'));
                return;
            }

            global $wp_filesystem, $wpdb;

            $content = $wp_filesystem->get_contents($file_path);
                if ($content === false) {
                $this->debug_log('Database Import Pro Importer: Failed to read import file via WP_Filesystem: ' . $file_path);
                wp_send_json_error(__('Failed to read import file', 'database-import-pro'));
                return;
            }

            try {
                // Split CSV into lines and parse
                $lines = preg_split("/\r\n|\n|\r/", $content);
                if ($lines === false || count($lines) === 0) {
                    throw new Exception(__('Error parsing CSV file', 'database-import-pro'));
                }

                // Remove header row
                $header = array_shift($lines);

                // Calculate start offset for this batch
                $start_index = $batch * self::BATCH_SIZE;

                // If start index is beyond available lines, we've completed the import
                if ($start_index >= count($lines)) {
                    $stats['completed'] = true;
                    // Save logs and return
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
                    wp_send_json_success($stats);
                    return;
                }

                // Get the lines for this batch
                $batch_lines = array_slice($lines, $start_index, self::BATCH_SIZE);

                // Start database transaction for batch integrity
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction commands cannot be cached
                $wpdb->query('START TRANSACTION');
                $transaction_success = true;

                foreach ($batch_lines as $index => $line) {
                    // Parse CSV row
                    $row = str_getcsv($line);
                    $row_num = $start_index + $index + 1; // 1-based index relative to file (excluding header)

                    $result = $this->process_row($row, $table, $mapping, $import_mode, $key_columns, $allow_null);

                    $stats['processed']++;
                    $stats[$result['status']]++;

                    if ($result['status'] === 'failed') {
                        // Could decide to toggle $transaction_success = false for critical errors
                        $transaction_success = true;
                    }

                    if (!empty($result['message'])) {
                        $stats['messages'][] = array(
                            'type' => $result['status'] === 'failed' ? 'error' : 'info',
                            'row' => $row_num,
                            'message' => sprintf(
                                /* translators: 1: row number, 2: status message */
                                __('Row %1$d: %2$s', 'database-import-pro'),
                                $row_num,
                                $result['message']
                            )
                        );
                    }
                }

                // Commit or rollback transaction
                if ($transaction_success) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction commands cannot be cached
                    $wpdb->query('COMMIT');
                } else {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction commands cannot be cached
                    $wpdb->query('ROLLBACK');
                }

                // Check if we've reached the end
                $stats['completed'] = ($start_index + count($batch_lines) >= count($lines));

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

                $this->debug_log('Database Import Pro Importer: Batch ' . $batch . ' completed. Stats: ' . wp_json_encode($stats));
                wp_send_json_success($stats);

            } catch (Exception $e) {
                // Release lock on error
                $this->release_import_lock();
                $this->debug_log('Database Import Pro Importer: Error processing batch: ' . $e->getMessage());
                wp_send_json_error($e->getMessage());
            }

        } catch (Exception $e) {
            // Release lock on fatal error
            $this->release_import_lock();
            $this->debug_log('Database Import Pro Importer: Fatal error: ' . $e->getMessage());
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
                    
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- INSERT operations modify data, cannot be cached
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
                            throw new Exception(sprintf(
                                /* translators: %s: key column name */
                                __('Missing key column %s', 'database-import-pro'),
                                $key
                            ));
                        }
                        $where[$key] = $data[$key];
                        unset($data[$key]); // Don't update key columns
                    }
                    
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- UPDATE operations modify data, cannot be cached
                    $result = $wpdb->update($table, $data, $where);
                    if ($result === false) {
                        throw new Exception($wpdb->last_error);
                    }
                    return array('status' => 'updated');

                case 'upsert':
                    $where = array();
                    foreach ($key_columns as $key) {
                        if (!isset($data[$key])) {
                            throw new Exception(sprintf(
                                /* translators: %s: key column name */
                                __('Missing key column %s', 'database-import-pro'),
                                $key
                            ));
                        }
                        $where[$key] = $data[$key];
                    }
                    
                    if ($this->record_exists($table, $data, $key_columns)) {
                        $update_data = $data;
                        foreach ($key_columns as $key) {
                            unset($update_data[$key]); // Don't update key columns
                        }
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- UPDATE operations modify data, cannot be cached
                        $result = $wpdb->update($table, $update_data, $where);
                        $status = 'updated';
                    } else {
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- INSERT operations modify data, cannot be cached
                        $result = $wpdb->insert($table, $data);
                        $status = 'inserted';
                    }
                    
                    if ($result === false) {
                        throw new Exception($wpdb->last_error);
                    }
                    return array('status' => $status);

                default:
                    throw new Exception(sprintf(
                        /* translators: %s: import mode */
                        __('Invalid import mode: %s', 'database-import-pro'),
                        $import_mode
                    ));
            }
        } catch (Exception $e) {
            $this->debug_log('Database Import Pro Importer: Row processing error: ' . $e->getMessage());
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
        // Sanitize table name to reduce risk of SQL injection for table identifiers
        if (strpos($table, $wpdb->prefix) !== 0) {
            $table = $wpdb->prefix . sanitize_key($table);
        }
    $table_safe = esc_sql($table);
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema query with escaped table name; table identifiers cannot be parameterized
    $columns = $wpdb->get_results('SHOW COLUMNS FROM `' . $table_safe . '`');
        
        foreach ($columns as $column) {
            // Check if column is required (NOT NULL and no default value)
            if ($column->Null === 'NO' && $column->Default === null) {
                // Skip auto increment columns
                if ($column->Extra === 'auto_increment') {
                    continue;
                }
                
                // Check if required field has a value
                if (!isset($data[$column->Field]) || $data[$column->Field] === null || $data[$column->Field] === '') {
                    $this->debug_log("Database Import Pro: Missing required field {$column->Field}");
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

        $where_conditions = array();
        $where_values = array();
        
        foreach ($key_columns as $key) {
            if (isset($data[$key])) {
                $where_conditions[] = "`{$key}` = %s";
                $where_values[] = $data[$key];
            }
        }

        if (empty($where_conditions)) {
            return false;
        }

        // Escape the table identifier for use in SQL (identifiers can't be parameterized)
        $table_escaped = esc_sql($table);
        $where_clause = implode(' AND ', $where_conditions);

        // Use argument unpacking to pass dynamic values to prepare (PHP 5.6+)
        if (!empty($where_values)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- prepared SQL built dynamically but values are passed via prepare; table name escaped with esc_sql; WHERE clause contains placeholders that linter cannot detect statically
            return (bool) $wpdb->get_var($wpdb->prepare('SELECT 1 FROM `' . $table_escaped . '` WHERE ' . $where_clause . ' LIMIT 1', ...$where_values));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Direct query when no dynamic values, cannot be cached; table name escaped with esc_sql
        return (bool) $wpdb->get_var('SELECT 1 FROM `' . $table_escaped . '` WHERE ' . $where_clause . ' LIMIT 1');
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
                $this->debug_log('Database Import Pro: Custom transformations are disabled for security reasons');
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
                // Use WordPress file deletion helper when available
                if (function_exists('wp_delete_file')) {
                    wp_delete_file($file_path);
                } else {
                    // Try WP_Filesystem if available as a safer fallback
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    if (WP_Filesystem()) {
                        global $wp_filesystem;
                        $wp_filesystem->delete($file_path);
                    } else {
                        // WP_Filesystem not available; log that the file could not be removed here.
                        $this->debug_log('Database Import Pro: Unable to delete file via WP_Filesystem: ' . $file_path);
                    }
                }
                $this->debug_log('Database Import Pro: Cleaned up import file: ' . $file_path);
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
        
    $this->debug_log('Database Import Pro Importer Debug: Starting save_import_log');
    $this->debug_log('Database Import Pro Importer Debug: Stats - ' . wp_json_encode($stats));
        
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

    $this->debug_log('Database Import Pro Importer Debug: Import data - ' . wp_json_encode($import_data));
    $this->debug_log('Database Import Pro Importer Debug: Table name - ' . $wpdb->prefix . 'dbip_import_logs');

        // Check if table exists
        $like = esc_sql($wpdb->prefix . 'dbip_import_logs');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema queries need current data, cannot be cached
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $like));
        if (!$table_exists) {
            $this->debug_log('Database Import Pro Importer Debug: Table does not exist, creating now...');
            $this->create_logs_table();
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- INSERT operations modify data, cannot be cached
        $result = $wpdb->insert($wpdb->prefix . 'dbip_import_logs', $import_data);
        
        if ($result === false) {
            $this->debug_log('Database Import Pro Importer Error: Failed to save import log - ' . $wpdb->last_error);
            return false;
        }

        $this->debug_log('Database Import Pro Importer Debug: Log saved successfully with ID: ' . $wpdb->insert_id);
        
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
        
        // Get pagination parameters (unslash and sanitize)
        $page = 1;
        $per_page = 20;
        if (isset($_POST['page'])) {
            $page = max(1, absint(wp_unslash($_POST['page'])));
        }
        if (isset($_POST['per_page'])) {
            $per_page = max(1, absint(wp_unslash($_POST['per_page'])));
        }
        
        // Validate and set reasonable limits
        $page = max(1, $page);
        $per_page = max(1, min(100, $per_page)); // Cap at 100 per page
        
        // Calculate offset
        $offset = ($page - 1) * $per_page;
        
        // Get total count
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Count queries need current data, cannot be cached
        $total_count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}dbip_import_logs
        ");
        
        // Get paginated logs
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Log queries need current data, cannot be cached
        $logs = $wpdb->get_results($wpdb->prepare("
            SELECT l.*, u.display_name as user
            FROM " . esc_sql($wpdb->prefix . 'dbip_import_logs') . " l
            LEFT JOIN " . esc_sql($wpdb->users) . " u ON l.user_id = u.ID
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

        $log_id = absint(wp_unslash($_POST['log_id']));
        if ($log_id <= 0) {
            wp_send_json_error('Invalid log ID');
            return;
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Log queries need current data, cannot be cached
        $log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dbip_import_logs WHERE id = %d",
            $log_id
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
        // Unsash inputs
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized immediately below
        $stats_raw = wp_unslash($_POST['stats']);
    $percentage = intval(wp_unslash($_POST['percentage']));

    // Validate and sanitize raw stats payload (JSON expected)
    $stats_raw = wp_check_invalid_utf8($stats_raw);
    $stats_raw = sanitize_textarea_field($stats_raw);

    // Attempt to decode stats if it's a JSON string, otherwise coerce to array
    $stats = is_string($stats_raw) ? json_decode($stats_raw, true) : $stats_raw;
        if (!is_array($stats)) {
            // Fallback: store as-is but log for debugging
            $this->debug_log('Database Import Pro: save_import_progress received invalid stats payload');
            $stats = array();
        }

        // Store progress in transient
        dbip_set_import_data('import_stats', $stats);
        dbip_set_import_data('progress', $percentage);
        
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
     * Download error log from completed import
     * 
     * @return void
     */
    public function download_error_log(): void {
        check_ajax_referer('dbip_importer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'database-import-pro'));
        }

        // Get error log from transient
        $error_log = dbip_get_import_data('error_log');
        
        if (empty($error_log)) {
            wp_send_json_error(__('No error log found', 'database-import-pro'));
            return;
        }

        // Format as CSV
        $csv_content = "Row,Error Message\n";
        foreach ($error_log as $error) {
            $csv_content .= '"' . esc_attr($error['row']) . '","' . esc_attr($error['message']) . "\"\n";
        }

        wp_send_json_success($csv_content);
    }

    /**
     * Create the import logs table
     * 
     * @return void
     */
    private function create_logs_table(): void {
        global $wpdb;
        
    $this->debug_log('Database Import Pro Importer Debug: Creating logs table');
        
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
        
    $this->debug_log('Database Import Pro Importer Debug: Logs table creation complete');
    }
}

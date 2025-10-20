<?php
/**
 * Field mapping handler class
 *
 * Note: This file uses PhpOffice\PhpSpreadsheet for Excel support (optional).
 * The PhpSpreadsheet library is loaded via Composer and may not be present.
 * All usage is protected by class_exists() checks, so no runtime errors occur.
 * IDE warnings about "Undefined type" are expected until you run: composer install
 *
 * @since      1.0.0
 * @package    DBIP_Importer
 */

class DBIP_Importer_Mapping {
    /**
     * Option name for storing mapping templates
     */
    const TEMPLATE_OPTION = 'DBIP_Importer_mapping_templates';

    /**
     * Initialize the class
     */
    public function __construct() {
        add_action('wp_ajax_dbip_save_mapping_template', array($this, 'save_template'));
        add_action('wp_ajax_dbip_load_mapping_template', array($this, 'load_template'));
        add_action('wp_ajax_dbip_get_mapping_templates', array($this, 'get_templates'));
        add_action('wp_ajax_dbip_delete_mapping_template', array($this, 'delete_template'));
        add_action('wp_ajax_dbip_auto_suggest_mapping', array($this, 'auto_suggest_mapping'));
        add_action('wp_ajax_dbip_save_field_mapping', array($this, 'save_field_mapping'));
        add_action('wp_ajax_dbip_validate_import_data', array($this, 'validate_import_data')); // Add this line
        add_action('wp_ajax_dbip_save_import_options', array($this, 'save_import_options'));
    }

    /**
     * Helper: log debug messages only when WP_DEBUG is enabled
     *
     * @param string $message Debug message
     * @return void
     */
    private function log_debug(string $message): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- gated by WP_DEBUG
            error_log($message);
        }
    }

    /**
     * Recursively sanitize input data (arrays and strings).
     * Strips tags and sanitizes scalar strings; preserves non-string scalars.
     *
     * @param mixed $data Input to sanitize
     * @return mixed Sanitized data
     */
    private function sanitize_recursive($data) {
        if (is_array($data)) {
            $clean = array();
            foreach ($data as $key => $value) {
                $clean_key = is_string($key) ? sanitize_text_field($key) : $key;
                if (is_array($value)) {
                    $clean[$clean_key] = $this->sanitize_recursive($value);
                } else {
                    $clean[$clean_key] = is_string($value) ? sanitize_text_field($value) : $value;
                }
            }
            return $clean;
        }

        if (is_string($data)) {
            return sanitize_text_field($data);
        }

        return $data;
    }

    /**
     * Save mapping template
     * 
     * @return void
     */
    public function save_template(): void {
        check_ajax_referer('dbip_importer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'database-import-pro'));
        }

    $template_name = isset($_POST['template_name']) ? sanitize_text_field(wp_unslash($_POST['template_name'])) : '';
    $mapping_data = array();
    if (isset($_POST['mapping_data'])) {
        $raw = wp_unslash($_POST['mapping_data']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized immediately below
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $mapping_data = $this->sanitize_recursive($decoded);
        }
    }

        if (empty($template_name) || empty($mapping_data)) {
            wp_send_json_error(__('Invalid template data', 'database-import-pro'));
        }

        $templates = get_option(self::TEMPLATE_OPTION, array());
        $templates[$template_name] = array(
            'name' => $template_name,
            'mapping' => $mapping_data,
            'created' => wp_date('Y-m-d H:i:s', null, wp_timezone()),
            'table' => isset($_POST['table_name']) ? sanitize_text_field(wp_unslash($_POST['table_name'])) : ''
        );

        update_option(self::TEMPLATE_OPTION, $templates);
        wp_send_json_success();
    }

    /**
     * Load mapping template
     * 
     * @return void
     */
    public function load_template(): void {
        check_ajax_referer('dbip_importer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'database-import-pro'));
        }

    $template_name = isset($_POST['template_name']) ? sanitize_text_field(wp_unslash($_POST['template_name'])) : '';
        $templates = get_option(self::TEMPLATE_OPTION, array());

        if (!isset($templates[$template_name])) {
            wp_send_json_error(__('Template not found', 'database-import-pro'));
        }

        wp_send_json_success($templates[$template_name]);
    }

    /**
     * Get all mapping templates
     * 
     * @return void
     */
    public function get_templates(): void {
        check_ajax_referer('dbip_importer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'database-import-pro'));
        }

        $templates = get_option(self::TEMPLATE_OPTION, array());
        wp_send_json_success($templates);
    }

    /**
     * Delete mapping template
     * 
     * @return void
     */
    public function delete_template(): void {
        check_ajax_referer('dbip_importer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'database-import-pro'));
        }

    $template_name = isset($_POST['template_name']) ? sanitize_text_field(wp_unslash($_POST['template_name'])) : '';
        $templates = get_option(self::TEMPLATE_OPTION, array());

        if (isset($templates[$template_name])) {
            unset($templates[$template_name]);
            update_option(self::TEMPLATE_OPTION, $templates);
        }

        wp_send_json_success();
    }

    /**
     * Auto-suggest field mapping based on similarity
     * 
     * @return void
     */
    public function auto_suggest_mapping(): void {
        check_ajax_referer('dbip_importer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'database-import-pro'));
        }

    $csv_headers = array();
    if (isset($_POST['csv_headers'])) {
        $raw = wp_unslash($_POST['csv_headers']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized immediately below
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $csv_headers = $this->sanitize_recursive($decoded);
        }
    }

    $db_columns = array();
    if (isset($_POST['db_columns'])) {
        $raw = wp_unslash($_POST['db_columns']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized immediately below
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $db_columns = $this->sanitize_recursive($decoded);
        }
    }

        if (empty($csv_headers) || empty($db_columns)) {
            wp_send_json_error(__('Missing headers or columns', 'database-import-pro'));
        }

        $suggestions = array();
        foreach ($db_columns as $column) {
            $best_match = null;
            $highest_similarity = 0;

            foreach ($csv_headers as $header) {
                // Calculate similarity score
                $similarity = $this->calculate_similarity($column, $header);
                if ($similarity > $highest_similarity && $similarity > 0.6) { // 60% threshold
                    $highest_similarity = $similarity;
                    $best_match = $header;
                }
            }

            $suggestions[$column] = $best_match;
        }

        wp_send_json_success($suggestions);
    }

    /**
     * Calculate similarity between two strings
     * 
     * @param string $str1 First string
     * @param string $str2 Second string
     * @return float Similarity score (0.0 to 1.0)
     */
    private function calculate_similarity(string $str1, string $str2): float {
        // Convert to lowercase and remove special characters
        $str1 = preg_replace('/[^a-z0-9]/', '', strtolower($str1));
        $str2 = preg_replace('/[^a-z0-9]/', '', strtolower($str2));

        // Use levenshtein distance
        $lev = levenshtein($str1, $str2);
        $max_len = max(strlen($str1), strlen($str2));

        // Return similarity score between 0 and 1
        return 1 - ($lev / $max_len);
    }

    /**
     * Save field mapping
     * 
     * @return void
     */
    public function save_field_mapping(): void {
        check_ajax_referer('dbip_importer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'database-import-pro'));
        }

        $mapping = null;
        if (isset($_POST['mapping'])) {
            $raw = wp_unslash($_POST['mapping']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized immediately below
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $mapping = $this->sanitize_recursive($decoded);
            }
        }
        if (!is_array($mapping)) {
            wp_send_json_error(__('Invalid mapping data', 'database-import-pro'));
        }

        // Updated schema to handle auto-increment and keep-current fields
        $schema = array(
            'type' => 'object',
            'patternProperties' => array(
                '^.*$' => array(
                    'type' => 'object',
                    'properties' => array(
                        'skip' => array('type' => 'boolean'),
                        'csv_field' => array('type' => 'string'),
                        'default_value' => array('type' => 'string'),
                        'transform' => array(
                            'type' => 'string',
                            'enum' => array('', 'trim', 'uppercase', 'lowercase', 'capitalize', 'custom')
                        ),
                        'custom_transform' => array('type' => 'string'),
                        'allow_null' => array('type' => 'boolean')
                    ),
                    'required' => array('csv_field') // Only require csv_field as other fields are optional
                )
            )
        );

    $this->log_debug('Database Import Pro Importer Debug: Validating mapping data: ' . wp_json_encode($mapping));

        $validation = $this->validate_schema($mapping, $schema);
        if (!$validation['valid']) {
            $this->log_debug('Database Import Pro Importer Debug: Validation failed: ' . wp_json_encode($validation));
            wp_send_json_error($validation['error']);
        }

        // Validate default values against column types
        $table = dbip_get_import_data('target_table');
        if ($table) {
            $default_validation = $this->validate_default_values($mapping, $table);
            if (!$default_validation['valid']) {
                wp_send_json_error($default_validation['errors']);
            }
        }

        // Store mapping in transient
        dbip_set_import_data('mapping', $mapping);

        // Generate preview data
    $preview_data = $this->generate_preview_data($mapping);
        dbip_set_import_data('preview_data', $preview_data);

        wp_send_json_success();
    }

    /**
     * Validate data against JSON schema
     */
    private function validate_schema($data, $schema) {
        if ($schema['type'] === 'object') {
            if (!is_array($data)) {
                return array(
                    'valid' => false,
                    'error' => __('Expected object', 'database-import-pro')
                );
            }

            // Validate pattern properties
            if (isset($schema['patternProperties'])) {
                foreach ($data as $key => $value) {
                    $matched = false;
                    foreach ($schema['patternProperties'] as $pattern => $propertySchema) {
                        if (preg_match('/' . $pattern . '/', $key)) {
                            $matched = true;
                            $validation = $this->validate_schema($value, $propertySchema);
                            if (!$validation['valid']) {
                                return array(
                                    'valid' => false,
                                    'error' => sprintf(
                                        /* translators: 1: property name, 2: validation error message */
                                        __('Invalid property "%1$s": %2$s', 'database-import-pro'),
                                        $key,
                                        $validation['error']
                                    )
                                );
                            }
                        }
                    }
                    if (!$matched) {
                        return array(
                            'valid' => false,
                            'error' => sprintf(
                                /* translators: %s: property name */
                                __('Unknown property "%s"', 'database-import-pro'),
                                $key
                            )
                        );
                    }
                }
            }

            // Validate properties
            if (isset($schema['properties'])) {
                foreach ($schema['properties'] as $property => $propertySchema) {
                    if (isset($data[$property])) {
                        $validation = $this->validate_schema($data[$property], $propertySchema);
                        if (!$validation['valid']) {
                            return array(
                                'valid' => false,
                                'error' => sprintf(
                                    /* translators: 1: property name, 2: validation error message */
                                    __('Invalid property "%1$s": %2$s', 'database-import-pro'),
                                    $property,
                                    $validation['error']
                                )
                            );
                        }
                    }
                }
            }

            // Validate required properties
            if (isset($schema['required'])) {
                foreach ($schema['required'] as $required) {
                    if (!isset($data[$required])) {
                        return array(
                            'valid' => false,
                            'error' => sprintf(
                                /* translators: %s: required property name */
                                __('Missing required property "%s"', 'database-import-pro'),
                                $required
                            )
                        );
                    }
                }
            }
        }

        // Validate enum
        if (isset($schema['enum'])) {
            if (!in_array($data, $schema['enum'], true)) {
                return array(
                    'valid' => false,
                    'error' => sprintf(
                        /* translators: %s: comma-separated list of allowed values */
                        __('Value must be one of: %s', 'database-import-pro'),
                        implode(', ', $schema['enum'])
                    )
                );
            }
        }

        return array('valid' => true);
    }

    /**
     * Generate preview data using mapping
     */
    private function generate_preview_data($mapping) {
        $file_info = dbip_get_import_data('file');
        if (!$file_info || !file_exists($file_info['path'])) {
            return array();
        }

        $preview_data = array();
        $csv_data = array();

        if ($file_info['type'] === 'csv') {
            // Use WP_Filesystem to read file contents
            if ( ! function_exists('WP_Filesystem') ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            $content = false;
            if ( WP_Filesystem() && isset($GLOBALS['wp_filesystem']) && method_exists($GLOBALS['wp_filesystem'], 'get_contents') ) {
                $content = $GLOBALS['wp_filesystem']->get_contents( $file_info['path'] );
            }

            if ($content !== false && $content !== null) {
                $lines = preg_split('/\r\n|\r|\n/', trim($content));
                $headers = array_map('trim', str_getcsv(array_shift($lines)));

                $row_count = 0;
                foreach ($lines as $line) {
                    if ($row_count >= 10) break;
                    $row = str_getcsv($line);
                    if ($row === false) continue;
                    $row_data = array_combine($headers, $row);
                    $mapped_row = array();

                    foreach ($mapping as $column => $map) {
                        // Skip auto-increment fields
                        if (!empty($map['skip'])) {
                            continue;
                        }

                        // Skip fields marked to keep current data
                        if (isset($map['csv_field']) && $map['csv_field'] === '__keep_current__') {
                            $mapped_row[$column] = '[CURRENT DATA]'; // Placeholder for preview
                            continue;
                        }

                        $value = '';

                        if (!empty($map['csv_field']) && isset($row_data[$map['csv_field']])) {
                            $value = $row_data[$map['csv_field']];
                        } elseif (!empty($map['default_value'])) {
                            $value = $map['default_value'];
                        }

                        if (!empty($map['transform'])) {
                            $value = $this->apply_transformation($value, $map['transform'], $map['custom_transform'] ?? '');
                        }

                        if (empty($value) && !empty($map['allow_null'])) {
                            $value = null;
                        }

                        $mapped_row[$column] = $value;
                    }

                    $preview_data[] = $mapped_row;
                    $row_count++;
                }
            } else {
                // If WP_Filesystem is not available or failed, return empty preview to avoid direct filesystem operations
                $this->log_debug('Database Import Pro: WP_Filesystem unavailable, skipping CSV preview generation for ' . $file_info['path']);
            }
        } else if ($file_info['type'] === 'xlsx' || $file_info['type'] === 'xls') {
            // Handle Excel files using PhpSpreadsheet
            if (class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
                try {
                    /** @var \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet */
                    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_info['path']);
                    $worksheet = $spreadsheet->getActiveSheet();
                    $rows = $worksheet->toArray();
                    
                    $headers = array_map('trim', $rows[0]);
                    
                    for ($i = 1; $i <= min(10, count($rows) - 1); $i++) {
                        $row_data = array_combine($headers, $rows[$i]);
                        $mapped_row = array();
                        
                        foreach ($mapping as $column => $map) {
                            // Skip auto-increment fields
                            if (!empty($map['skip'])) {
                                continue;
                            }
                            
                            // Skip fields marked to keep current data
                            if (isset($map['csv_field']) && $map['csv_field'] === '__keep_current__') {
                                $mapped_row[$column] = '[CURRENT DATA]'; // Placeholder for preview
                                continue;
                            }

                            $value = '';
                            
                            if (!empty($map['csv_field']) && isset($row_data[$map['csv_field']])) {
                                $value = $row_data[$map['csv_field']];
                            } elseif (!empty($map['default_value'])) {
                                $value = $map['default_value'];
                            }

                            if (!empty($map['transform'])) {
                                $value = $this->apply_transformation($value, $map['transform'], $map['custom_transform'] ?? '');
                            }

                            if (empty($value) && !empty($map['allow_null'])) {
                                $value = null;
                            }

                            $mapped_row[$column] = $value;
                        }
                        
                        $preview_data[] = $mapped_row;
                    }
                    } catch (Exception $e) {
                    $this->log_debug('Database Import Pro Importer Excel Preview Error: ' . $e->getMessage());
                }
            }
        }

        // Store total records count in transient
    dbip_set_import_data('total_records', $this->get_total_records($file_info['path'], $file_info['type']));

        return $preview_data;
    }

    /**
     * Apply transformation to a value
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
                // SECURITY FIX: Removed eval() - custom transformations disabled for security
                // Custom PHP code execution has been removed due to security concerns
                $this->log_debug('Database Import Pro: Custom transformations are disabled for security reasons');
                return $value;
            default:
                // Auto-transform dates if the value looks like a date with backslashes
                if (preg_match('/^\d{4}\\\d{1,2}\\\d{1,2}$/', $value)) {
                    return $this->transform_date($value);
                }
                return $value;
        }
    }

    /**
     * Get total number of records in the file
     */
    private function get_total_records($file_path, $type) {
        $count = 0;
        
        if ($type === 'csv') {
            if ( ! function_exists('WP_Filesystem') ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            if ( WP_Filesystem() && isset($GLOBALS['wp_filesystem']) && method_exists($GLOBALS['wp_filesystem'], 'get_contents') ) {
                $content = $GLOBALS['wp_filesystem']->get_contents( $file_path );
                if ($content !== false && $content !== null) {
                    $lines = preg_split('/\r\n|\r|\n/', trim($content));
                    // Subtract header line if present
                    if (count($lines) > 0) {
                        $count = max(0, count($lines) - 1);
                    }
                }
            } else {
                $this->log_debug('Database Import Pro: WP_Filesystem unavailable, cannot count CSV records for ' . $file_path);
            }
        } else if (($type === 'xlsx' || $type === 'xls') && class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
            try {
                /** @var \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet */
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
                $worksheet = $spreadsheet->getActiveSheet();
                $count = $worksheet->getHighestRow() - 1; // Subtract header row
                } catch (Exception $e) {
                $this->log_debug('Database Import Pro Importer Excel Count Error: ' . $e->getMessage());
            }
        }
        
        return $count;
    }

    /**
     * Validate import data
     * 
     * @return void
     */
    public function validate_import_data(): void {
        check_ajax_referer('dbip_importer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'database-import-pro'));
        }

        $preview_data = dbip_get_import_data('preview_data') ?: array();
        $mapping = dbip_get_import_data('mapping') ?: array();
        $table = dbip_get_import_data('target_table') ?: '';

        if (empty($preview_data) || empty($mapping) || empty($table)) {
            wp_send_json_error(__('Missing required data', 'database-import-pro'));
        }

    global $wpdb;
    $table_esc = esc_sql($table);
    $columns = $wpdb->get_results("SHOW COLUMNS FROM `" . $table_esc . "`"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table name is an identifier and cannot use placeholders; escaped with esc_sql(); schema queries need current data, cannot be cached
        
        $validation_results = array(
            'errors' => array(),
            'warnings' => array()
        );

        foreach ($columns as $column) {
            $field_name = $column->Field;
            $is_required = $column->Null === 'NO' && $column->Default === null;
            $field_map = $mapping[$field_name] ?? array();
            
            // Check required fields
            if ($is_required && empty($field_map['csv_field']) && empty($field_map['default_value'])) {
                $validation_results['errors'][] = sprintf(
                    /* translators: %s: database field name */
                    __('Required field "%s" is not mapped', 'database-import-pro'),
                    $field_name
                );
                continue;
            }

            // Check data type compatibility
            if (!empty($preview_data)) {
                foreach ($preview_data as $index => $row) {
                    $value = $row[$field_name] ?? '';
                    if (!empty($value) && !$this->validate_field_type($value, $column->Type)) {
                        $validation_results['errors'][] = sprintf(
                            /* translators: 1: database field name, 2: row number */
                            __('Invalid data type for field "%1$s" in row %2$d', 'database-import-pro'),
                            $field_name,
                            $index + 1
                        );
                    }
                }
            }
        }

        // Generate HTML response
        ob_start();
        if (empty($validation_results['errors']) && empty($validation_results['warnings'])) {
            echo '<div class="notice notice-success"><p>' . esc_html__('All data is valid and ready for import.', 'database-import-pro') . '</p></div>';
        } else {
            if (!empty($validation_results['errors'])) {
                echo '<div class="notice notice-error"><p><strong>' . esc_html__('Validation Errors:', 'database-import-pro') . '</strong></p>';
                echo '<ul><li>' . implode('</li><li>', array_map('esc_html', $validation_results['errors'])) . '</li></ul></div>';
            }
            if (!empty($validation_results['warnings'])) {
                echo '<div class="notice notice-warning"><p><strong>' . esc_html__('Warnings:', 'database-import-pro') . '</strong></p>';
                echo '<ul><li>' . implode('</li><li>', array_map('esc_html', $validation_results['warnings'])) . '</li></ul></div>';
            }
        }
        $html = ob_get_clean();

        wp_send_json_success($html);
    }

    /**
     * Validate field type
     */
    private function validate_field_type($value, $db_type) {
        // Handle special case for empty values and [CURRENT DATA]
        if ($value === '' || $value === null || $value === '[CURRENT DATA]') {
            return true;
        }

        // Extract base type and length/values
        if (preg_match('/^([a-z]+)(\(([^)]+)\))?/', strtolower($db_type), $matches)) {
            $type = $matches[1];
            $constraint = $matches[3] ?? '';
            
            switch ($type) {
                case 'tinyint':
                    // Special handling for boolean (tinyint(1))
                    if ($constraint === '1') {
                        if (is_bool($value)) return true;
                        if (is_numeric($value)) return in_array((int)$value, [0, 1]);
                        $val = strtolower(trim($value));
                        return in_array($val, ['0', '1', 'true', 'false', 'yes', 'no']);
                    }
                    // Fall through to regular int validation if not tinyint(1)
                case 'int':
                case 'smallint':
                case 'mediumint':
                case 'bigint':
                    return is_numeric($value) && strpos($value, '.') === false;
                
                case 'decimal':
                case 'float':
                case 'double':
                    return is_numeric($value);
                
                case 'date':
                case 'datetime':
                case 'timestamp':
                    // Handle MySQL functions and special values
                    $special_values = [
                        'CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP()',
                        'NOW()', 'NOW', 'CURRENT_DATE()', 'CURRENT_DATE',
                        'NULL', 'null'
                    ];
                    if (in_array(strtoupper($value), $special_values)) {
                        return true;
                    }
                    return $this->validate_date($value);
                
                case 'time':
                    return preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $value);
                
                case 'year':
                    return is_numeric($value) && strlen($value) === 4;
                
                case 'char':
                case 'varchar':
                    $max_length = (int)$constraint;
                    return strlen($value) <= $max_length;
                
                case 'enum':
                case 'set':
                    $allowed_values = array_map('trim', explode(',', str_replace("'", '', $constraint)));
                    return in_array($value, $allowed_values);
                
                default:
                    return true;
            }
        }
        return true;
    }

    private function validate_date($value) {
        // First, handle backslash-separated dates by converting them to dashes
        if (strpos($value, '\\') !== false) {
            $value = str_replace('\\', '-', $value);
        }

        // Handle common date/datetime formats
        $formats = [
            // SQL standard formats
            'Y-m-d H:i:s',
            'Y-m-d\TH:i:s',
            'Y-m-d',
            
            // Common US formats
            'm/d/Y H:i:s',
            'm/d/Y',
            'm-d-Y H:i:s',
            'm-d-Y',
            
            // Common UK/European formats
            'd/m/Y H:i:s',
            'd/m/Y',
            'd-m-Y H:i:s',
            'd-m-Y',
            
            // Other common formats
            'Y/m/d H:i:s',
            'Y/m/d',
            'Y-m-d',
            'Y.m.d',
            'd.m.Y H:i:s',
            'd.m.Y',
            
            // Specific format for your data
            'Y-m-d',       // This will match 2005-12-22 (converted from 2005\12\22)
            
            // Short year formats
            'd/m/y',
            'm/d/y',
            'y-m-d',
            'y/m/d'
        ];

        // First try exact format matching
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $value);
            if ($date && $date->format($format) === $value) {
                return true;
            }
        }

        // If exact matching fails, try strtotime for more flexible parsing
        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return true;
        }

        return false;
    }

    private function transform_date($value) {
        // Convert backslashes to dashes
        if (strpos($value, '\\') !== false) {
            $value = str_replace('\\', '-', $value);
        }

        // Try to parse the date
        $date = date_create($value);
        if ($date) {
            // Return in MySQL format
            return $date->format('Y-m-d');
        }

        return $value;
    }

    /**
     * Validate default values against column types
     * 
     * @param array $mapping Field mapping configuration
     * @param string $table Target table name
     * @return array Validation result with 'valid' boolean and 'errors' array
     */
    private function validate_default_values($mapping, $table) {
        global $wpdb;
        
        $errors = array();
    $table_escaped = esc_sql($table);

    // Get table column information
    $columns = $wpdb->get_results("SHOW FULL COLUMNS FROM `" . $table_escaped . "`"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table name is an identifier and cannot use placeholders; escaped with esc_sql(); schema queries need current data, cannot be cached
        
        if (empty($columns)) {
            return array('valid' => true, 'errors' => array());
        }
        
        // Create a map of column names to column info
        $column_info = array();
        foreach ($columns as $column) {
            $column_info[$column->Field] = $column;
        }
        
        // Validate each field's default value
        foreach ($mapping as $field_name => $field_config) {
            // Skip if no default value is set
            if (!isset($field_config['default_value']) || $field_config['default_value'] === '') {
                continue;
            }
            
            // Check if column exists
            if (!isset($column_info[$field_name])) {
                continue; // Skip validation for non-existent columns
            }
            
            $column = $column_info[$field_name];
            $default_value = $field_config['default_value'];
            
            // Skip special values
            if (in_array(strtoupper($default_value), array('NULL', 'CURRENT_TIMESTAMP', 'NOW()', '[CURRENT DATA]', '[AUTO INCREMENT]'))) {
                continue;
            }
            
            // Validate the default value against the column type
            if (!$this->validate_default_value_type($default_value, $column->Type, $field_name)) {
                $errors[] = sprintf(
                    /* translators: 1: default value, 2: field name, 3: database column type */
                    __('Invalid default value "%1$s" for field "%2$s" (type: %3$s)', 'database-import-pro'),
                    esc_html($default_value),
                    esc_html($field_name),
                    esc_html($column->Type)
                );
            }
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => empty($errors) ? '' : implode('<br>', $errors)
        );
    }

    /**
     * Validate a single default value against its column type
     * 
     * @param string $value Default value to validate
     * @param string $db_type Database column type
     * @param string $field_name Field name for better error messages
     * @return bool True if valid, false otherwise
     */
    private function validate_default_value_type($value, $db_type, $field_name) {
        // Empty values are allowed (will be NULL if column allows)
        if ($value === '' || $value === null) {
            return true;
        }
        
        // Extract base type and constraints
        if (preg_match('/^([a-z]+)(\(([^)]+)\))?/i', $db_type, $matches)) {
            $type = strtolower($matches[1]);
            $constraint = isset($matches[3]) ? $matches[3] : '';
            
            switch ($type) {
                case 'tinyint':
                    // Special handling for boolean (tinyint(1))
                    if ($constraint === '1') {
                        return in_array(strtolower($value), array('0', '1', 'true', 'false', 'yes', 'no')) 
                            || is_numeric($value);
                    }
                    // Fall through to int validation
                    
                case 'smallint':
                case 'mediumint':
                case 'int':
                case 'integer':
                case 'bigint':
                    // Check if it's a valid integer
                    if (!is_numeric($value) || strpos($value, '.') !== false) {
                    $this->log_debug("Database Import Pro: Invalid integer default value '$value' for field '$field_name'");
                        return false;
                    }
                    
                    // Check range based on type (optional, can add later)
                    return true;
                    
                case 'decimal':
                case 'numeric':
                case 'float':
                case 'double':
                case 'real':
                    if (!is_numeric($value)) {
                        $this->log_debug("Database Import Pro: Invalid numeric default value '$value' for field '$field_name'");
                        return false;
                    }
                    return true;
                    
                case 'date':
                    // Validate date format (YYYY-MM-DD)
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                        $timestamp = strtotime($value);
                        if ($timestamp === false) {
                            $this->log_debug("Database Import Pro: Invalid date default value '$value' for field '$field_name'");
                            return false;
                        }
                    }
                    return true;
                    
                case 'datetime':
                case 'timestamp':
                    // Validate datetime format
                    $timestamp = strtotime($value);
                    if ($timestamp === false && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
                        $this->log_debug("Database Import Pro: Invalid datetime default value '$value' for field '$field_name'");
                        return false;
                    }
                    return true;
                    
                case 'time':
                    // Validate time format (HH:MM:SS or HH:MM)
                    if (!preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $value)) {
                        $this->log_debug("Database Import Pro: Invalid time default value '$value' for field '$field_name'");
                        return false;
                    }
                    return true;
                    
                case 'year':
                    // Validate year (must be 4 digits)
                    if (!is_numeric($value) || strlen($value) !== 4) {
                        $this->log_debug("Database Import Pro: Invalid year default value '$value' for field '$field_name'");
                        return false;
                    }
                    return true;
                    
                case 'char':
                case 'varchar':
                    // Check length constraint
                    if ($constraint && is_numeric($constraint)) {
                        $max_length = (int)$constraint;
                        if (strlen($value) > $max_length) {
                            $this->log_debug("Database Import Pro: Default value '$value' exceeds maximum length $max_length for field '$field_name'");
                            return false;
                        }
                    }
                    return true;
                    
                case 'text':
                case 'tinytext':
                case 'mediumtext':
                case 'longtext':
                    // Text fields accept any string
                    return true;
                    
                case 'enum':
                    // Validate against allowed enum values
                    if ($constraint) {
                        $enum_values = array_map(function($v) {
                            return trim($v, "'\"");
                        }, explode(',', $constraint));
                        
                        if (!in_array($value, $enum_values)) {
                            $this->log_debug("Database Import Pro: Default value '$value' not in ENUM values for field '$field_name'");
                            return false;
                        }
                    }
                    return true;
                    
                case 'set':
                    // SET values can be comma-separated
                    if ($constraint) {
                        $set_values = array_map(function($v) {
                            return trim($v, "'\"");
                        }, explode(',', $constraint));
                        
                        $input_values = explode(',', $value);
                        foreach ($input_values as $input_val) {
                            if (!in_array(trim($input_val), $set_values)) {
                                $this->log_debug("Database Import Pro: Default value '$input_val' not in SET values for field '$field_name'");
                                return false;
                            }
                        }
                    }
                    return true;
                    
                case 'json':
                    // Validate JSON
                    json_decode($value);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $this->log_debug("Database Import Pro: Invalid JSON default value for field '$field_name'");
                        return false;
                    }
                    return true;
                    
                case 'blob':
                case 'tinyblob':
                case 'mediumblob':
                case 'longblob':
                case 'binary':
                case 'varbinary':
                    // Binary data - accept any value
                    return true;
                    
                default:
                    // Unknown type - log warning but allow
                    $this->log_debug("Database Import Pro: Unknown column type '$type' for field '$field_name', skipping validation");
                    return true;
            }
        }
        
        // If we can't parse the type, log and allow
        $this->log_debug("Database Import Pro: Could not parse column type '$db_type' for field '$field_name'");
        return true;
    }

    /**
     * Save import options before starting import
     * 
     * @return void
     */
    public function save_import_options(): void {
        check_ajax_referer('dbip_importer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'database-import-pro'));
        }

        $form_data = array();
        if (isset($_POST['data'])) {
            $raw = wp_unslash($_POST['data']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized immediately below
            parse_str($raw, $form_data);
            if (!is_array($form_data)) {
                $form_data = array();
            } else {
                $form_data = $this->sanitize_recursive($form_data);
            }
        }

        dbip_set_import_data('import_mode', sanitize_text_field($form_data['import_mode'] ?? ''));
        dbip_set_import_data('key_columns', isset($form_data['key_columns']) && is_array($form_data['key_columns']) ? array_map('sanitize_text_field', $form_data['key_columns']) : []);
        dbip_set_import_data('allow_null', !empty($form_data['allow_null']));
        dbip_set_import_data('dry_run', !empty($form_data['dry_run']));

        wp_send_json_success();
    }
}

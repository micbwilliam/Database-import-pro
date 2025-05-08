<?php
/**
 * Field mapping handler class
 *
 * @since      1.0.0
 * @package    AEDC_Importer
 */

class AEDC_Importer_Mapping {
    /**
     * Option name for storing mapping templates
     */
    const TEMPLATE_OPTION = 'aedc_importer_mapping_templates';

    /**
     * Initialize the class
     */
    public function __construct() {
        add_action('wp_ajax_aedc_save_mapping_template', array($this, 'save_template'));
        add_action('wp_ajax_aedc_load_mapping_template', array($this, 'load_template'));
        add_action('wp_ajax_aedc_get_mapping_templates', array($this, 'get_templates'));
        add_action('wp_ajax_aedc_delete_mapping_template', array($this, 'delete_template'));
        add_action('wp_ajax_aedc_auto_suggest_mapping', array($this, 'auto_suggest_mapping'));
        add_action('wp_ajax_aedc_save_field_mapping', array($this, 'save_field_mapping'));
        add_action('wp_ajax_aedc_validate_import_data', array($this, 'validate_import_data')); // Add this line
        add_action('wp_ajax_aedc_save_import_options', array($this, 'save_import_options'));
    }

    /**
     * Save mapping template
     */
    public function save_template() {
        check_ajax_referer('aedc_importer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'aedc-importer'));
        }

        $template_name = sanitize_text_field($_POST['template_name']);
        $mapping_data = json_decode(stripslashes($_POST['mapping_data']), true);

        if (empty($template_name) || empty($mapping_data)) {
            wp_send_json_error(__('Invalid template data', 'aedc-importer'));
        }

        $templates = get_option(self::TEMPLATE_OPTION, array());
        $templates[$template_name] = array(
            'name' => $template_name,
            'mapping' => $mapping_data,
            'created' => current_time('mysql'),
            'table' => sanitize_text_field($_POST['table_name'])
        );

        update_option(self::TEMPLATE_OPTION, $templates);
        wp_send_json_success();
    }

    /**
     * Load mapping template
     */
    public function load_template() {
        check_ajax_referer('aedc_importer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'aedc-importer'));
        }

        $template_name = sanitize_text_field($_POST['template_name']);
        $templates = get_option(self::TEMPLATE_OPTION, array());

        if (!isset($templates[$template_name])) {
            wp_send_json_error(__('Template not found', 'aedc-importer'));
        }

        wp_send_json_success($templates[$template_name]);
    }

    /**
     * Get all mapping templates
     */
    public function get_templates() {
        check_ajax_referer('aedc_importer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'aedc-importer'));
        }

        $templates = get_option(self::TEMPLATE_OPTION, array());
        wp_send_json_success($templates);
    }

    /**
     * Delete mapping template
     */
    public function delete_template() {
        check_ajax_referer('aedc_importer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'aedc-importer'));
        }

        $template_name = sanitize_text_field($_POST['template_name']);
        $templates = get_option(self::TEMPLATE_OPTION, array());

        if (isset($templates[$template_name])) {
            unset($templates[$template_name]);
            update_option(self::TEMPLATE_OPTION, $templates);
        }

        wp_send_json_success();
    }

    /**
     * Auto-suggest field mapping based on similarity
     */
    public function auto_suggest_mapping() {
        check_ajax_referer('aedc_importer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'aedc-importer'));
        }

        $csv_headers = isset($_POST['csv_headers']) ? json_decode(stripslashes($_POST['csv_headers']), true) : array();
        $db_columns = isset($_POST['db_columns']) ? json_decode(stripslashes($_POST['db_columns']), true) : array();

        if (empty($csv_headers) || empty($db_columns)) {
            wp_send_json_error(__('Missing headers or columns', 'aedc-importer'));
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
     */
    private function calculate_similarity($str1, $str2) {
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
     */
    public function save_field_mapping() {
        check_ajax_referer('aedc_importer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'aedc-importer'));
        }

        $mapping = json_decode(stripslashes($_POST['mapping']), true);
        if (!is_array($mapping)) {
            wp_send_json_error(__('Invalid mapping data', 'aedc-importer'));
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

        error_log('AEDC Importer Debug: Validating mapping data: ' . print_r($mapping, true));

        $validation = $this->validate_schema($mapping, $schema);
        if (!$validation['valid']) {
            error_log('AEDC Importer Debug: Validation failed: ' . print_r($validation, true));
            wp_send_json_error($validation['error']);
        }

        // Store mapping in session
        $_SESSION['aedc_importer']['mapping'] = $mapping;

        // Generate preview data
        $preview_data = $this->generate_preview_data($mapping);
        $_SESSION['aedc_importer']['preview_data'] = $preview_data;

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
                    'error' => __('Expected object', 'aedc-importer')
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
                                        __('Invalid property "%s": %s', 'aedc-importer'),
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
                                __('Unknown property "%s"', 'aedc-importer'),
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
                                    __('Invalid property "%s": %s', 'aedc-importer'),
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
                                __('Missing required property "%s"', 'aedc-importer'),
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
                        __('Value must be one of: %s', 'aedc-importer'),
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
        $file_info = isset($_SESSION['aedc_importer']['file']) ? $_SESSION['aedc_importer']['file'] : null;
        if (!$file_info || !file_exists($file_info['path'])) {
            return array();
        }

        $preview_data = array();
        $csv_data = array();

        if ($file_info['type'] === 'csv') {
            $handle = fopen($file_info['path'], 'r');
            if ($handle !== false) {
                $headers = fgetcsv($handle);
                $headers = array_map('trim', $headers);

                $row_count = 0;
                while (($row = fgetcsv($handle)) !== false && $row_count < 10) {
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
                fclose($handle);
            }
        } else if ($file_info['type'] === 'xlsx' || $file_info['type'] === 'xls') {
            // Handle Excel files using PhpSpreadsheet
            if (class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
                try {
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
                    error_log('AEDC Importer Excel Preview Error: ' . $e->getMessage());
                }
            }
        }

        // Store total records count in session
        $_SESSION['aedc_importer']['total_records'] = $this->get_total_records($file_info['path'], $file_info['type']);

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
                if (!empty($custom_code)) {
                    try {
                        // Create a safe environment for custom code
                        return eval('return ' . $custom_code . ';');
                    } catch (Exception $e) {
                        error_log('Custom transform error: ' . $e->getMessage());
                        return $value;
                    }
                }
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
            $handle = fopen($file_path, 'r');
            if ($handle !== false) {
                // Skip header row
                fgetcsv($handle);
                // Count remaining rows
                while (fgetcsv($handle) !== false) {
                    $count++;
                }
                fclose($handle);
            }
        } else if (($type === 'xlsx' || $type === 'xls') && class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
                $worksheet = $spreadsheet->getActiveSheet();
                $count = $worksheet->getHighestRow() - 1; // Subtract header row
            } catch (Exception $e) {
                error_log('AEDC Importer Excel Count Error: ' . $e->getMessage());
            }
        }
        
        return $count;
    }

    /**
     * Validate import data
     */
    public function validate_import_data() {
        check_ajax_referer('aedc_importer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'aedc-importer'));
        }

        $preview_data = isset($_SESSION['aedc_importer']['preview_data']) ? $_SESSION['aedc_importer']['preview_data'] : array();
        $mapping = isset($_SESSION['aedc_importer']['mapping']) ? $_SESSION['aedc_importer']['mapping'] : array();
        $table = isset($_SESSION['aedc_importer']['target_table']) ? $_SESSION['aedc_importer']['target_table'] : '';

        if (empty($preview_data) || empty($mapping) || empty($table)) {
            wp_send_json_error(__('Missing required data', 'aedc-importer'));
        }

        global $wpdb;
        $columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table}`");
        
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
                    __('Required field "%s" is not mapped', 'aedc-importer'),
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
                            __('Invalid data type for field "%s" in row %d', 'aedc-importer'),
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
            echo '<div class="notice notice-success"><p>' . esc_html__('All data is valid and ready for import.', 'aedc-importer') . '</p></div>';
        } else {
            if (!empty($validation_results['errors'])) {
                echo '<div class="notice notice-error"><p><strong>' . esc_html__('Validation Errors:', 'aedc-importer') . '</strong></p>';
                echo '<ul><li>' . implode('</li><li>', array_map('esc_html', $validation_results['errors'])) . '</li></ul></div>';
            }
            if (!empty($validation_results['warnings'])) {
                echo '<div class="notice notice-warning"><p><strong>' . esc_html__('Warnings:', 'aedc-importer') . '</strong></p>';
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
     * Save import options before starting import
     */
    public function save_import_options() {
        check_ajax_referer('aedc_importer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'aedc-importer'));
        }

        parse_str($_POST['data'], $form_data);
        
        $_SESSION['aedc_importer']['import_mode'] = sanitize_text_field($form_data['import_mode']);
        $_SESSION['aedc_importer']['key_columns'] = isset($form_data['key_columns']) ? array_map('sanitize_text_field', $form_data['key_columns']) : [];
        $_SESSION['aedc_importer']['allow_null'] = !empty($form_data['allow_null']);
        $_SESSION['aedc_importer']['dry_run'] = !empty($form_data['dry_run']);

        wp_send_json_success();
    }
}
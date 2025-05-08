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

        // Validate mapping schema
        $schema = array(
            'type' => 'object',
            'patternProperties' => array(
                '^.*$' => array(
                    'type' => 'object',
                    'properties' => array(
                        'csv_field' => array('type' => 'string'),
                        'default_value' => array('type' => 'string'),
                        'transform' => array(
                            'type' => 'string',
                            'enum' => array('', 'trim', 'uppercase', 'lowercase', 'capitalize', 'custom')
                        ),
                        'custom_transform' => array('type' => 'string'),
                        'allow_null' => array('type' => 'boolean')
                    ),
                    'required' => array('csv_field', 'transform')
                )
            )
        );

        $validation = $this->validate_schema($mapping, $schema);
        if (!$validation['valid']) {
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
        if (!isset($_SESSION['aedc_importer']['csv_data'])) {
            return array();
        }

        $csv_data = $_SESSION['aedc_importer']['csv_data'];
        $preview_data = array();
        $preview_rows = array_slice($csv_data, 1, 5); // Skip header row, take 5 rows

        foreach ($preview_rows as $row) {
            $mapped_row = array();
            foreach ($mapping as $column => $map) {
                $value = '';
                
                // Get value from CSV or default
                if (!empty($map['csv_field']) && isset($row[$map['csv_field']])) {
                    $value = $row[$map['csv_field']];
                } elseif (!empty($map['default_value'])) {
                    $value = $map['default_value'];
                }

                // Apply transformations
                if (!empty($map['transform'])) {
                    switch ($map['transform']) {
                        case 'trim':
                            $value = trim($value);
                            break;
                        case 'uppercase':
                            $value = strtoupper($value);
                            break;
                        case 'lowercase':
                            $value = strtolower($value);
                            break;
                        case 'capitalize':
                            $value = ucwords(strtolower($value));
                            break;
                        case 'custom':
                            if (!empty($map['custom_transform'])) {
                                // Safely evaluate custom transform
                                try {
                                    $transform_function = create_function('$value', $map['custom_transform']);
                                    $value = $transform_function($value);
                                } catch (Exception $e) {
                                    // Log error but continue with original value
                                    error_log('Custom transform error: ' . $e->getMessage());
                                }
                            }
                            break;
                    }
                }

                // Handle null values
                if (empty($value) && !empty($map['allow_null'])) {
                    $value = null;
                }

                $mapped_row[$column] = $value;
            }
            $preview_data[] = $mapped_row;
        }

        return $preview_data;
    }
} 
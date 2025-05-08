<?php
/**
 * Step 4: Preview & Confirm Template
 *
 * @since      1.0.0
 * @package    AEDC_Importer
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get the mapping and preview data from session
$mapping = isset($_SESSION['aedc_importer']['mapping']) ? $_SESSION['aedc_importer']['mapping'] : array();
$preview_data = isset($_SESSION['aedc_importer']['preview_data']) ? $_SESSION['aedc_importer']['preview_data'] : array();
?>

<div class="aedc-step-content step-preview">
    <h2><?php esc_html_e('Preview & Confirm Import', 'aedc-importer'); ?></h2>
    
    <div class="preview-container">
        <div class="preview-summary">
            <h3><?php esc_html_e('Import Summary', 'aedc-importer'); ?></h3>
            <ul>
                <li>
                    <strong><?php esc_html_e('Target Table:', 'aedc-importer'); ?></strong>
                    <?php echo esc_html($_SESSION['aedc_importer']['target_table']); ?>
                </li>
                <li>
                    <strong><?php esc_html_e('Total Records:', 'aedc-importer'); ?></strong>
                    <?php echo esc_html(isset($_SESSION['aedc_importer']['total_records']) ? $_SESSION['aedc_importer']['total_records'] : 0); ?>
                </li>
                <li>
                    <strong><?php esc_html_e('Mapped Fields:', 'aedc-importer'); ?></strong>
                    <?php echo esc_html(count(array_filter($mapping, function($m) { return !empty($m['csv_field']); }))); ?>
                </li>
            </ul>
        </div>

        <div class="preview-data">
            <h3><?php esc_html_e('Data Preview', 'aedc-importer'); ?></h3>
            <p class="description"><?php esc_html_e('Showing first 5 rows of data after applying mappings and transformations', 'aedc-importer'); ?></p>
            
            <div class="preview-table-container">
                <table class="preview-table">
                    <thead>
                        <tr>
                            <?php foreach ($mapping as $column => $map) : ?>
                                <th><?php echo esc_html($column); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $preview_rows = array_slice($preview_data, 0, 5);
                        foreach ($preview_rows as $row) : 
                        ?>
                            <tr>
                                <?php foreach ($mapping as $column => $map) : ?>
                                    <td><?php echo esc_html($row[$column] ?? ''); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="preview-validation">
            <h3><?php esc_html_e('Data Validation', 'aedc-importer'); ?></h3>
            <div class="validation-summary">
                <div class="validation-errors"></div>
                <div class="validation-warnings"></div>
            </div>
            <div class="preview-table-container">
                <table class="preview-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Column', 'aedc-importer'); ?></th>
                            <th><?php esc_html_e('Sample Data', 'aedc-importer'); ?></th>
                            <th><?php esc_html_e('Expected Type', 'aedc-importer'); ?></th>
                            <th><?php esc_html_e('Status', 'aedc-importer'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        global $wpdb;
                        $table = $_SESSION['aedc_importer']['target_table'];
                        $columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table}`");
                        foreach ($columns as $column) :
                            $mapped_field = $mapping[$column->Field]['csv_field'] ?? '';
                            $sample_data = '';
                            if (!empty($preview_data)) {
                                $sample_data = $preview_data[0][$column->Field] ?? '';
                            }
                            $is_required = $column->Null === 'NO' && $column->Default === null;
                            $type_class = '';
                            $status_message = '';
                            
                            // Validate data type
                            if (!empty($sample_data)) {
                                $valid = validate_field_type($sample_data, $column->Type);
                                $type_class = $valid ? 'valid' : 'invalid';
                                $status_message = $valid ? __('Valid', 'aedc-importer') : __('Invalid Type', 'aedc-importer');
                            } elseif ($is_required) {
                                $type_class = 'required';
                                $status_message = __('Required Field', 'aedc-importer');
                            }
                        ?>
                            <tr class="<?php echo esc_attr($type_class); ?>">
                                <td>
                                    <?php echo esc_html($column->Field); ?>
                                    <?php if ($is_required) : ?>
                                        <span class="required-marker">*</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($sample_data); ?></td>
                                <td><?php echo esc_html($column->Type); ?></td>
                                <td class="status-column">
                                    <span class="status-badge <?php echo esc_attr($type_class); ?>">
                                        <?php echo esc_html($status_message); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="preview-validation">
            <h3><?php esc_html_e('Validation Results', 'aedc-importer'); ?></h3>
            <div id="validation-results">
                <p class="loading"><?php esc_html_e('Validating data...', 'aedc-importer'); ?></p>
            </div>
        </div>

        <div class="preview-actions">
            <form id="aedc-preview-form" method="post">
                <?php wp_nonce_field('aedc_importer_nonce', 'aedc_nonce'); ?>
                
                <div class="import-options">
                    <h4><?php esc_html_e('Import Mode', 'aedc-importer'); ?></h4>
                    <div class="import-mode-options">
                        <label>
                            <input type="radio" name="import_mode" value="insert" checked />
                            <?php esc_html_e('Insert Only (Skip existing records)', 'aedc-importer'); ?>
                        </label>
                        <label>
                            <input type="radio" name="import_mode" value="update" />
                            <?php esc_html_e('Update Only (Update existing records)', 'aedc-importer'); ?>
                        </label>
                        <label>
                            <input type="radio" name="import_mode" value="upsert" />
                            <?php esc_html_e('Insert & Update (Create new or update existing)', 'aedc-importer'); ?>
                        </label>
                    </div>

                    <div id="key-columns-selection" style="display: none; margin-top: 15px;">
                        <h4><?php esc_html_e('Key Columns', 'aedc-importer'); ?></h4>
                        <p class="description"><?php esc_html_e('Select columns to identify existing records', 'aedc-importer'); ?></p>
                        <?php
                        foreach ($columns as $column) :
                            $is_key = $column->Key === 'PRI' || $column->Key === 'UNI';
                        ?>
                            <label>
                                <input type="checkbox" name="key_columns[]" value="<?php echo esc_attr($column->Field); ?>"
                                    <?php echo $is_key ? 'checked' : ''; ?> />
                                <?php echo esc_html($column->Field); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <h4><?php esc_html_e('Import Options', 'aedc-importer'); ?></h4>
                    <label>
                        <input type="checkbox" name="allow_null" checked />
                        <?php esc_html_e('Allow NULL values for nullable fields', 'aedc-importer'); ?>
                    </label>
                    <label>
                        <input type="checkbox" name="dry_run" />
                        <?php esc_html_e('Dry run (validate without importing)', 'aedc-importer'); ?>
                    </label>
                </div>

                <div class="button-group">
                    <a href="<?php echo esc_url(add_query_arg('step', '3')); ?>" class="button button-secondary"><?php esc_html_e('Back to Mapping', 'aedc-importer'); ?></a>
                    <button type="submit" class="button button-primary" id="start-import"><?php esc_html_e('Start Import', 'aedc-importer'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Validate data on page load
    $.post(ajaxurl, {
        action: 'aedc_validate_import_data',
        nonce: $('#aedc_nonce').val()
    }, function(response) {
        if (response.success) {
            $('#validation-results').html(response.data);
        } else {
            $('#validation-results').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
        }
    });

    // Initialize validation summary
    function updateValidationSummary() {
        const errors = [];
        const warnings = [];
        
        $('.preview-table tbody tr').each(function() {
            const $row = $(this);
            const columnName = $row.find('td:first').text().trim();
            const status = $row.attr('class');
            
            if (status === 'invalid') {
                errors.push(columnName + ': ' + $row.find('.status-badge').text());
            } else if (status === 'required' && !$row.find('td:nth-child(2)').text().trim()) {
                warnings.push(columnName + ' is required but not mapped');
            }
        });

        const $errorContainer = $('.validation-errors');
        const $warningContainer = $('.validation-warnings');
        
        $errorContainer.empty();
        $warningContainer.empty();
        
        if (errors.length) {
            $errorContainer.append('<h4 class="error-title">' + 
                '<?php esc_html_e('Validation Errors', 'aedc-importer'); ?>' + '</h4>');
            $errorContainer.append('<ul><li>' + errors.join('</li><li>') + '</li></ul>');
        }
        
        if (warnings.length) {
            $warningContainer.append('<h4 class="warning-title">' + 
                '<?php esc_html_e('Warnings', 'aedc-importer'); ?>' + '</h4>');
            $warningContainer.append('<ul><li>' + warnings.join('</li><li>') + '</li></ul>');
        }

        // Enable/disable import button based on validation
        $('#start-import').prop('disabled', errors.length > 0);
    }

    // Run validation on page load
    updateValidationSummary();

    // Handle form submission
    $('#aedc-preview-form').on('submit', function(e) {
        e.preventDefault();
        
        if (!confirm('<?php esc_html_e('Are you sure you want to proceed with the import?', 'aedc-importer'); ?>')) {
            return;
        }

        // Get all form data
        const formData = $(this).serialize();

        // First validate data
        $.post(ajaxurl, {
            action: 'aedc_validate_import_data',
            nonce: $('#aedc_nonce').val()
        }, function(validateResponse) {
            if (!validateResponse.success) {
                alert('<?php esc_html_e('Data validation failed. Please fix the errors and try again.', 'aedc-importer'); ?>');
                return;
            }

            // Then save import options
            $.post(ajaxurl, {
                action: 'aedc_save_import_options',
                data: formData,
                nonce: $('#aedc_nonce').val()
            }, function(response) {
                if (response.success) {
                    window.location.href = window.location.href.replace(/step=\d/, 'step=5');
                } else {
                    alert(response.data || '<?php esc_html_e('Failed to save import options. Please try again.', 'aedc-importer'); ?>');
                }
            });
        });
    });

    // Handle import mode selection
    $('input[name="import_mode"]').on('change', function() {
        const mode = $(this).val();
        if (mode === 'update' || mode === 'upsert') {
            $('#key-columns-selection').slideDown();
            
            // Require at least one key column
            if (!$('input[name="key_columns[]"]:checked').length) {
                const primaryKey = $('input[name="key_columns[]"]').filter(function() {
                    return $(this).closest('label').text().trim().includes('(Primary)');
                }).first();
                
                if (primaryKey.length) {
                    primaryKey.prop('checked', true);
                } else {
                    $('input[name="key_columns[]"]:first').prop('checked', true);
                }
            }
        } else {
            $('#key-columns-selection').slideUp();
        }
    });

    // Ensure at least one key column is selected when needed
    $('input[name="key_columns[]"]').on('change', function() {
        const mode = $('input[name="import_mode"]:checked').val();
        if ((mode === 'update' || mode === 'upsert') && !$('input[name="key_columns[]"]:checked').length) {
            $(this).prop('checked', true);
            alert('<?php esc_html_e('At least one key column must be selected for Update or Insert & Update mode.', 'aedc-importer'); ?>');
        }
    });
});

// Add helper function for data type validation
<?php
function validate_field_type($value, $db_type) {
    global $mapping;
    
    // Handle special case for "Keep Current Data"
    if ($value === '[CURRENT DATA]') {
        return true;
    }
    
    // Handle special case for empty values
    if ($value === '' || $value === null) {
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
                return strtotime($value) !== false;
            
            case 'datetime':
            case 'timestamp':
                // Accept CURRENT_TIMESTAMP and other MySQL datetime functions
                $special_values = ['CURRENT_TIMESTAMP', 'NOW()', 'CURRENT_TIMESTAMP()', 'NOW'];
                if (in_array(strtoupper($value), $special_values)) {
                    return true;
                }
                return strtotime($value) !== false;
            
            case 'time':
                return preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $value);
            
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
?>
</script>

<style>
.validation-summary {
    margin: 20px 0;
}

.validation-errors, .validation-warnings {
    margin: 10px 0;
    padding: 15px;
    border-radius: 4px;
}

.validation-errors {
    background-color: #fbeaea;
    border-left: 4px solid #dc3232;
}

.validation-warnings {
    background-color: #fff8e5;
    border-left: 4px solid #ffb900;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
}

.status-badge.valid {
    background-color: #edfaef;
    color: #46b450;
}

.status-badge.invalid {
    background-color: #fbeaea;
    color: #dc3232;
}

.status-badge.required {
    background-color: #fff8e5;
    color: #ffb900;
}

.required-marker {
    color: #dc3232;
    margin-left: 3px;
}

tr.invalid {
    background-color: #fff5f5;
}

tr.required:not(.valid) {
    background-color: #fff8f5;
}

.import-mode-options {
    margin: 10px 0 20px;
}

.import-mode-options label,
#key-columns-selection label {
    display: block;
    margin: 8px 0;
}

.import-options h4 {
    margin: 20px 0 10px;
    color: #23282d;
}

.import-options .description {
    color: #666;
    font-style: italic;
    margin-bottom: 10px;
}

#key-columns-selection {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 4px;
    border: 1px solid #e5e5e5;
}
</style>
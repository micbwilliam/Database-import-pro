<?php
/**
 * Step 4: Preview & Confirm Template
 *
 * @since      1.0.0
 * @package    dbip_Importer
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get the mapping and preview data from transient
$mapping = dbip_get_import_data('mapping') ?: array();
$preview_data = dbip_get_import_data('preview_data') ?: array();
?>

<div class="dbip-step-content step-preview">
    <h2><?php esc_html_e('Preview & Confirm Import', 'database-import-pro'); ?></h2>
    
    <div class="preview-container">
        <div class="preview-summary">
            <h3><?php esc_html_e('Import Summary', 'database-import-pro'); ?></h3>
            <ul>
                <li>
                    <strong><?php esc_html_e('Target Table:', 'database-import-pro'); ?></strong>
                    <?php echo esc_html(dbip_get_import_data('target_table')); ?>
                </li>
                <li>
                    <strong><?php esc_html_e('Total Records:', 'database-import-pro'); ?></strong>
                    <?php echo esc_html(dbip_get_import_data('total_records') ?: 0); ?>
                </li>
                <li>
                    <strong><?php esc_html_e('Mapped Fields:', 'database-import-pro'); ?></strong>
                    <?php 
                    $mapping_data = dbip_get_import_data('mapping') ?: array();
                    echo esc_html(count(array_filter($mapping_data, function($m) { return !empty($m['csv_field']); })));
                    ?>
                </li>
            </ul>
        </div>

        <div class="preview-data">
            <h3><?php esc_html_e('Data Preview', 'database-import-pro'); ?></h3>
            <p class="description"><?php esc_html_e('Showing first 5 rows of data after applying mappings and transformations', 'database-import-pro'); ?></p>
            
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
            <h3><?php esc_html_e('Data Validation', 'database-import-pro'); ?></h3>
            <div class="validation-summary">
                <div class="validation-errors"></div>
                <div class="validation-warnings"></div>
            </div>
            <div class="preview-table-container">
                <table class="preview-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Column', 'database-import-pro'); ?></th>
                            <th><?php esc_html_e('Sample Data', 'database-import-pro'); ?></th>
                            <th><?php esc_html_e('Expected Type', 'database-import-pro'); ?></th>
                            <th><?php esc_html_e('Status', 'database-import-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        global $wpdb;
                        $table = dbip_get_import_data('target_table');
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
                                $valid = dbip_validate_field_type($sample_data, $column->Type);
                                $type_class = $valid ? 'valid' : 'invalid';
                                $status_message = $valid ? __('Valid', 'database-import-pro') : __('Invalid Type', 'database-import-pro');
                            } elseif ($is_required) {
                                $type_class = 'required';
                                $status_message = __('Required Field', 'database-import-pro');
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
            <h3><?php esc_html_e('Validation Results', 'database-import-pro'); ?></h3>
            <div id="validation-results">
                <p class="loading"><?php esc_html_e('Validating data...', 'database-import-pro'); ?></p>
            </div>
        </div>

        <div class="preview-actions">
            <form id="dbip-preview-form" method="post">
                <?php wp_nonce_field('dbip_importer_nonce', 'dbip_nonce'); ?>
                
                <div class="import-options">
                    <h4><?php esc_html_e('Import Mode', 'database-import-pro'); ?></h4>
                    <div class="import-mode-options">
                        <label>
                            <input type="radio" name="import_mode" value="insert" checked />
                            <?php esc_html_e('Insert Only (Skip existing records)', 'database-import-pro'); ?>
                        </label>
                        <label>
                            <input type="radio" name="import_mode" value="update" />
                            <?php esc_html_e('Update Only (Update existing records)', 'database-import-pro'); ?>
                        </label>
                        <label>
                            <input type="radio" name="import_mode" value="upsert" />
                            <?php esc_html_e('Insert & Update (Create new or update existing)', 'database-import-pro'); ?>
                        </label>
                    </div>

                    <div id="key-columns-selection" style="display: none; margin-top: 15px;">
                        <h4><?php esc_html_e('Key Columns', 'database-import-pro'); ?></h4>
                        <p class="description"><?php esc_html_e('Select columns to identify existing records', 'database-import-pro'); ?></p>
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

                    <h4><?php esc_html_e('Import Options', 'database-import-pro'); ?></h4>
                    <label>
                        <input type="checkbox" name="allow_null" checked />
                        <?php esc_html_e('Allow NULL values for nullable fields', 'database-import-pro'); ?>
                    </label>
                    <label>
                        <input type="checkbox" name="dry_run" />
                        <?php esc_html_e('Dry run (validate without importing)', 'database-import-pro'); ?>
                    </label>
                </div>

                <div class="button-group">
                    <a href="<?php echo esc_url(add_query_arg('step', '3')); ?>" class="button button-secondary"><?php esc_html_e('Back to Mapping', 'database-import-pro'); ?></a>
                    <button type="submit" class="button button-primary" id="start-import"><?php esc_html_e('Start Import', 'database-import-pro'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Validate data on page load
    $.post(dbipImporter.ajax_url, {
        action: 'dbip_validate_import_data',
        nonce: $('#dbip_nonce').val()
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
                '<?php esc_html_e('Validation Errors', 'database-import-pro'); ?>' + '</h4>');
            $errorContainer.append('<ul><li>' + errors.join('</li><li>') + '</li></ul>');
        }
        
        if (warnings.length) {
            $warningContainer.append('<h4 class="warning-title">' + 
                '<?php esc_html_e('Warnings', 'database-import-pro'); ?>' + '</h4>');
            $warningContainer.append('<ul><li>' + warnings.join('</li><li>') + '</li></ul>');
        }

        // Enable/disable import button based on validation
        $('#start-import').prop('disabled', errors.length > 0);
    }

    // Run validation on page load
    updateValidationSummary();

    // Handle form submission
    $('#dbip-preview-form').on('submit', function(e) {
        e.preventDefault();
        
        if (!confirm('<?php esc_html_e('Are you sure you want to proceed with the import?', 'database-import-pro'); ?>')) {
            return;
        }

        // Get all form data
        const formData = $(this).serialize();

        // First validate data
        $.post(dbipImporter.ajax_url, {
            action: 'dbip_validate_import_data',
            nonce: $('#dbip_nonce').val()
        }, function(validateResponse) {
            if (!validateResponse.success) {
                alert('<?php esc_html_e('Data validation failed. Please fix the errors and try again.', 'database-import-pro'); ?>');
                return;
            }

            // Then save import options
            $.post(dbipImporter.ajax_url, {
                action: 'dbip_save_import_options',
                data: formData,
                nonce: $('#dbip_nonce').val()
            }, function(response) {
                if (response.success) {
                    window.location.href = window.location.href.replace(/step=\d/, 'step=5');
                } else {
                    alert(response.data || '<?php esc_html_e('Failed to save import options. Please try again.', 'database-import-pro'); ?>');
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
            alert('<?php esc_html_e('At least one key column must be selected for Update or Insert & Update mode.', 'database-import-pro'); ?>');
        }
    });
});
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

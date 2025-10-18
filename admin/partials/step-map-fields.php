<?php
/**
 * Step 3: Field Mapping Template
 *
 * @since      1.0.0
 * @package    dbip_Importer
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get the selected table structure
global $wpdb;
$table = dbip_get_import_data('target_table') ?: '';
$columns = $wpdb->get_results("SHOW COLUMNS FROM {$table}");

// Get CSV headers from the uploaded file
$csv_headers = dbip_get_import_data('headers') ?: array();

// Add debug output
if (empty($csv_headers)) {
    $import_data = dbip_get_import_data();
    error_log('Database Import Pro Debug: CSV headers are empty. Import data: ' . print_r($import_data, true));
}
?>

<div class="dbip-step-content step-map-fields">
    <h2><?php esc_html_e('Map CSV Fields to Database Columns', 'database-import-pro'); ?></h2>
    
    <div class="mapping-container">
        <form id="dbip-mapping-form" method="post">
            <?php wp_nonce_field('dbip_importer_nonce', 'dbip_nonce'); ?>
            
            <div class="mapping-table-container">
                <table class="mapping-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Database Column', 'database-import-pro'); ?></th>
                            <th><?php esc_html_e('CSV Field', 'database-import-pro'); ?></th>
                            <th><?php esc_html_e('Default Value', 'database-import-pro'); ?></th>
                            <th><?php esc_html_e('Transform', 'database-import-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($columns as $column) : ?>
                            <tr>
                                <td class="column-name">
                                    <strong><?php echo esc_html($column->Field); ?></strong>
                                    <span class="column-type"><?php echo esc_html($column->Type); ?></span>
                                    <?php if ($column->Null === 'YES') : ?>
                                        <span class="column-nullable">(<?php esc_html_e('nullable', 'database-import-pro'); ?>)</span>
                                    <?php endif; ?>
                                    <?php if (strpos($column->Extra, 'auto_increment') !== false) : ?>
                                        <span class="column-auto-increment">(<?php esc_html_e('auto-increment', 'database-import-pro'); ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (strpos($column->Extra, 'auto_increment') !== false) : ?>
                                        <div class="auto-increment-notice">
                                            <?php esc_html_e('Auto-increment field - no mapping needed', 'database-import-pro'); ?>
                                            <input type="hidden" name="mapping[<?php echo esc_attr($column->Field); ?>][skip]" value="1" />
                                        </div>
                                    <?php else : ?>
                                        <select name="mapping[<?php echo esc_attr($column->Field); ?>][csv_field]" class="csv-field-select">
                                            <option value=""><?php esc_html_e('-- Select CSV Field --', 'database-import-pro'); ?></option>
                                            <option value="__keep_current__"><?php esc_html_e('Keep Current Data', 'database-import-pro'); ?></option>
                                            <?php foreach ($csv_headers as $header) : ?>
                                                <option value="<?php echo esc_attr($header); ?>"><?php echo esc_html($header); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if ($column->Null === 'YES') : ?>
                                            <label class="allow-null">
                                                <input type="checkbox" 
                                                       name="mapping[<?php echo esc_attr($column->Field); ?>][allow_null]" 
                                                       value="1" />
                                                <?php esc_html_e('Allow NULL', 'database-import-pro'); ?>
                                            </label>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (strpos($column->Extra, 'auto_increment') === false) : ?>
                                        <input type="text" 
                                               name="mapping[<?php echo esc_attr($column->Field); ?>][default_value]" 
                                               class="default-value" 
                                               placeholder="<?php esc_attr_e('Leave empty for no default', 'database-import-pro'); ?>" />
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (strpos($column->Extra, 'auto_increment') === false) : ?>
                                        <select name="mapping[<?php echo esc_attr($column->Field); ?>][transform]" class="transform-select">
                                            <option value=""><?php esc_html_e('No transformation', 'database-import-pro'); ?></option>
                                            <option value="trim"><?php esc_html_e('Trim whitespace', 'database-import-pro'); ?></option>
                                            <option value="uppercase"><?php esc_html_e('UPPERCASE', 'database-import-pro'); ?></option>
                                            <option value="lowercase"><?php esc_html_e('lowercase', 'database-import-pro'); ?></option>
                                            <option value="capitalize"><?php esc_html_e('Capitalize Words', 'database-import-pro'); ?></option>
                                            <option value="custom"><?php esc_html_e('Custom PHP', 'database-import-pro'); ?></option>
                                        </select>
                                        <div class="custom-transform" style="display: none;">
                                            <textarea name="mapping[<?php echo esc_attr($column->Field); ?>][custom_transform]" 
                                                      placeholder="<?php esc_attr_e('Enter PHP code. Use $value for the field value.', 'database-import-pro'); ?>"
                                                      rows="3"></textarea>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mapping-actions">
                <div class="template-controls">
                    <select id="load-template" class="template-select">
                        <option value=""><?php esc_html_e('Load Saved Template', 'database-import-pro'); ?></option>
                    </select>
                    <input type="text" id="template-name" placeholder="<?php esc_attr_e('Template Name', 'database-import-pro'); ?>" />
                    <button type="button" class="button button-secondary" id="save-template"><?php esc_html_e('Save as Template', 'database-import-pro'); ?></button>
                    <button type="button" class="button button-secondary" id="delete-template"><?php esc_html_e('Delete Template', 'database-import-pro'); ?></button>
                </div>
                <div class="action-buttons">
                    <button type="button" class="button button-secondary" id="auto-map"><?php esc_html_e('Auto-Map Fields', 'database-import-pro'); ?></button>
                    <button type="submit" class="button button-primary" id="mapping-submit"><?php esc_html_e('Save Mapping & Continue', 'database-import-pro'); ?></button>
                </div>
            </div>
        </form>
    </div>

    <div class="mapping-help">
        <h3><?php esc_html_e('Mapping Instructions', 'database-import-pro'); ?></h3>
        <ul>
            <li><?php esc_html_e('Match each database column with the corresponding CSV field.', 'database-import-pro'); ?></li>
            <li><?php esc_html_e('Set default values for columns that don\'t have a CSV field mapped.', 'database-import-pro'); ?></li>
            <li><?php esc_html_e('Apply transformations to clean or format the data during import.', 'database-import-pro'); ?></li>
            <li><?php esc_html_e('Use the Auto-Map feature to automatically match fields with similar names.', 'database-import-pro'); ?></li>
        </ul>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Show/hide custom transform textarea
    $('.transform-select').on('change', function() {
        const customTransform = $(this).closest('td').find('.custom-transform');
        if ($(this).val() === 'custom') {
            customTransform.slideDown();
        } else {
            customTransform.slideUp();
        }
    });

    // Load templates on page load
    loadTemplates();

    // Template management
    $('#save-template').on('click', function() {
        const templateName = $('#template-name').val().trim();
        if (!templateName) {
            alert('<?php esc_html_e('Please enter a template name', 'database-import-pro'); ?>');
            return;
        }

        const mappingData = collectMappingData();
        console.log('Saving template with data:', mappingData); // Debug log
        
        $.ajax({
            url: dbipImporter.ajax_url,
            type: 'POST',
            data: {
                action: 'dbip_save_mapping_template',
                nonce: $('#dbip_nonce').val(),
                template_name: templateName,
                mapping_data: JSON.stringify(mappingData),
                table_name: '<?php echo esc_js($table); ?>'
            },
            success: function(response) {
                console.log('Template save response:', response); // Debug log
                if (response.success) {
                    alert('<?php esc_html_e('Template saved successfully', 'database-import-pro'); ?>');
                    loadTemplates();
                    $('#template-name').val('');
                } else {
                    alert(response.data || '<?php esc_html_e('Failed to save template', 'database-import-pro'); ?>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Template save error:', {xhr, status, error}); // Debug log
                alert('<?php esc_html_e('Failed to save template. Please try again.', 'database-import-pro'); ?>');
            }
        });
    });

    $('#load-template').on('change', function() {
        const templateName = $(this).val();
        if (!templateName) return;

        $.post(dbipImporter.ajax_url, {
            action: 'dbip_load_mapping_template',
            nonce: $('#dbip_nonce').val(),
            template_name: templateName
        }, function(response) {
            if (response.success) {
                applyTemplate(response.data.mapping);
            } else {
                alert(response.data || '<?php esc_html_e('Failed to load template', 'database-import-pro'); ?>');
            }
        });
    });

    $('#delete-template').on('click', function() {
        const templateName = $('#load-template').val();
        if (!templateName) {
            alert('<?php esc_html_e('Please select a template to delete', 'database-import-pro'); ?>');
            return;
        }

        if (!confirm('<?php esc_html_e('Are you sure you want to delete this template?', 'database-import-pro'); ?>')) {
            return;
        }

        $.post(dbipImporter.ajax_url, {
            action: 'dbip_delete_mapping_template',
            nonce: $('#dbip_nonce').val(),
            template_name: templateName
        }, function(response) {
            if (response.success) {
                alert('<?php esc_html_e('Template deleted successfully', 'database-import-pro'); ?>');
                loadTemplates();
            } else {
                alert(response.data || '<?php esc_html_e('Failed to delete template', 'database-import-pro'); ?>');
            }
        });
    });

    // Enhanced auto-mapping with similarity suggestions
    $('#auto-map').on('click', function() {
        const dbColumns = [];
        const csvHeaders = [];
        
        // Collect DB columns and CSV headers
        $('.column-name strong').each(function() {
            dbColumns.push($(this).text());
        });
        
        $('.csv-field-select option').each(function() {
            if ($(this).val()) {
                csvHeaders.push($(this).val());
            }
        });

        // Get suggestions from server
        $.post(dbipImporter.ajax_url, {
            action: 'dbip_auto_suggest_mapping',
            nonce: $('#dbip_nonce').val(),
            db_columns: JSON.stringify(dbColumns),
            csv_headers: JSON.stringify(csvHeaders)
        }, function(response) {
            if (response.success) {
                applyAutoMapping(response.data);
            } else {
                alert(response.data || '<?php esc_html_e('Failed to generate mapping suggestions', 'database-import-pro'); ?>');
            }
        });
    });

    // Form submission with validation
    $('#dbip-mapping-form').on('submit', function(e) {
        e.preventDefault();
        
        const mappingData = collectMappingData();
        console.log('Submitting mapping data:', mappingData); // Debug log
        
        const validationResult = validateMapping(mappingData);
        if (!validationResult.isValid) {
            alert(validationResult.message);
            return;
        }

        // Show loading state
        const submitButton = $('#mapping-submit');
        submitButton.prop('disabled', true).text('<?php esc_html_e('Saving...', 'database-import-pro'); ?>');

        $.ajax({
            url: dbipImporter.ajax_url,
            type: 'POST',
            data: {
                action: 'dbip_save_field_mapping',
                nonce: $('#dbip_nonce').val(),
                mapping: JSON.stringify(mappingData)
            },
            success: function(response) {
                console.log('Save mapping response:', response); // Debug log
                if (response.success) {
                    window.location.href = window.location.href.replace(/step=\d/, 'step=4');
                } else {
                    alert(response.data || '<?php esc_html_e('Failed to save mapping', 'database-import-pro'); ?>');
                    submitButton.prop('disabled', false).text('<?php esc_html_e('Save Mapping & Continue', 'database-import-pro'); ?>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Save mapping error:', {xhr, status, error}); // Debug log
                alert('<?php esc_html_e('Failed to save mapping. Please try again.', 'database-import-pro'); ?>');
                submitButton.prop('disabled', false).text('<?php esc_html_e('Save Mapping & Continue', 'database-import-pro'); ?>');
            }
        });
    });

    // Helper functions
    function loadTemplates() {
        console.log('Loading templates...'); // Debug log
        
        $.ajax({
            url: dbipImporter.ajax_url,
            type: 'POST',
            data: {
                action: 'dbip_get_mapping_templates',
                nonce: $('#dbip_nonce').val()
            },
            success: function(response) {
                console.log('Load templates response:', response); // Debug log
                if (response.success && response.data) {
                    const select = $('#load-template');
                    select.find('option:not(:first)').remove();
                    
                    Object.keys(response.data).forEach(function(templateName) {
                        const template = response.data[templateName];
                        select.append($('<option>', {
                            value: templateName,
                            text: templateName + ' (' + template.table + ')'
                        }));
                    });
                } else {
                    console.error('Failed to load templates:', response); // Debug log
                }
            },
            error: function(xhr, status, error) {
                console.error('Load templates error:', {xhr, status, error}); // Debug log
            }
        });
    }

    function collectMappingData() {
        const mapping = {};
        
        $('.mapping-table tbody tr').each(function() {
            const columnName = $(this).find('.column-name strong').text();
            const csvField = $(this).find('.csv-field-select').val();
            const defaultValue = $(this).find('.default-value').val();
            const transform = $(this).find('.transform-select').val();
            const customTransform = $(this).find('.custom-transform textarea').val();
            const allowNull = $(this).find('.allow-null input[type="checkbox"]').is(':checked');
            
            if (csvField || defaultValue || transform) { // Only include if there's actual mapping
                mapping[columnName] = {
                    csv_field: csvField || '',
                    default_value: defaultValue || '',
                    transform: transform || '',
                    custom_transform: transform === 'custom' ? customTransform : '',
                    allow_null: allowNull
                };
            }
        });
        
        console.log('Collected mapping data:', mapping); // Debug log
        return mapping;
    }

    function applyTemplate(mapping) {
        console.log('Applying template mapping:', mapping); // Debug log
        
        Object.keys(mapping).forEach(function(columnName) {
            const row = $(`.column-name strong:contains("${columnName}")`).closest('tr');
            if (row.length === 0) {
                console.warn(`Column ${columnName} from template not found in current table`); // Debug log
                return;
            }
            
            const data = mapping[columnName];
            
            row.find('.csv-field-select').val(data.csv_field || '');
            row.find('.default-value').val(data.default_value || '');
            row.find('.transform-select').val(data.transform || '').trigger('change');
            if (data.transform === 'custom') {
                row.find('.custom-transform textarea').val(data.custom_transform || '');
            }
            row.find('.allow-null input[type="checkbox"]').prop('checked', !!data.allow_null);
        });
    }

    function applyAutoMapping(suggestions) {
        Object.keys(suggestions).forEach(function(columnName) {
            const csvField = suggestions[columnName];
            if (csvField) {
                $(`.column-name strong:contains("${columnName}")`)
                    .closest('tr')
                    .find('.csv-field-select')
                    .val(csvField);
            }
        });
    }

    function validateMapping(mapping) {
        const requiredColumns = [];
        let hasMapping = false;

        $('.mapping-table tbody tr').each(function() {
            const columnName = $(this).find('.column-name strong').text();
            const isNullable = $(this).find('.allow-null').length > 0;
            const isAutoIncrement = $(this).find('.column-auto-increment').length > 0;
            const data = mapping[columnName] || {};
            const isKeepCurrent = data.csv_field === '__keep_current__';

            // Skip validation for auto-increment fields and keep-current fields
            if (isAutoIncrement || isKeepCurrent) {
                return;
            }

            if (!isNullable && !data.csv_field && !data.default_value) {
                requiredColumns.push(columnName);
            }

            if (data.csv_field || data.default_value) {
                hasMapping = true;
            }
        });

        if (!hasMapping) {
            return {
                isValid: false,
                message: '<?php esc_html_e('Please map at least one field', 'database-import-pro'); ?>'
            };
        }

        if (requiredColumns.length > 0) {
            return {
                isValid: false,
                message: '<?php esc_html_e('The following required columns are not mapped: ', 'database-import-pro'); ?>' + 
                        requiredColumns.join(', ')
            };
        }

        return { isValid: true };
    }
});
</script>

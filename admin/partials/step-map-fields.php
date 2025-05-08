<?php
/**
 * Step 3: Field Mapping Template
 *
 * @since      1.0.0
 * @package    AEDC_Importer
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get the selected table structure
global $wpdb;
$table = isset($_SESSION['aedc_importer']['target_table']) ? $_SESSION['aedc_importer']['target_table'] : '';
$columns = $wpdb->get_results("SHOW COLUMNS FROM {$table}");

// Get CSV headers from the uploaded file
$csv_headers = isset($_SESSION['aedc_importer']['headers']) ? $_SESSION['aedc_importer']['headers'] : array();

// Add debug output
if (empty($csv_headers)) {
    error_log('AEDC Importer Debug: CSV headers are empty. Session data: ' . print_r($_SESSION['aedc_importer'], true));
}
?>

<div class="aedc-step-content step-map-fields">
    <h2><?php esc_html_e('Map CSV Fields to Database Columns', 'aedc-importer'); ?></h2>
    
    <div class="mapping-container">
        <form id="aedc-mapping-form" method="post">
            <?php wp_nonce_field('aedc_importer_nonce', 'aedc_nonce'); ?>
            
            <div class="mapping-table-container">
                <table class="mapping-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Database Column', 'aedc-importer'); ?></th>
                            <th><?php esc_html_e('CSV Field', 'aedc-importer'); ?></th>
                            <th><?php esc_html_e('Default Value', 'aedc-importer'); ?></th>
                            <th><?php esc_html_e('Transform', 'aedc-importer'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($columns as $column) : ?>
                            <tr>
                                <td class="column-name">
                                    <strong><?php echo esc_html($column->Field); ?></strong>
                                    <span class="column-type"><?php echo esc_html($column->Type); ?></span>
                                    <?php if ($column->Null === 'YES') : ?>
                                        <span class="column-nullable">(<?php esc_html_e('nullable', 'aedc-importer'); ?>)</span>
                                    <?php endif; ?>
                                    <?php if (strpos($column->Extra, 'auto_increment') !== false) : ?>
                                        <span class="column-auto-increment">(<?php esc_html_e('auto-increment', 'aedc-importer'); ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (strpos($column->Extra, 'auto_increment') !== false) : ?>
                                        <div class="auto-increment-notice">
                                            <?php esc_html_e('Auto-increment field - no mapping needed', 'aedc-importer'); ?>
                                            <input type="hidden" name="mapping[<?php echo esc_attr($column->Field); ?>][skip]" value="1" />
                                        </div>
                                    <?php else : ?>
                                        <select name="mapping[<?php echo esc_attr($column->Field); ?>][csv_field]" class="csv-field-select">
                                            <option value=""><?php esc_html_e('-- Select CSV Field --', 'aedc-importer'); ?></option>
                                            <option value="__keep_current__"><?php esc_html_e('Keep Current Data', 'aedc-importer'); ?></option>
                                            <?php foreach ($csv_headers as $header) : ?>
                                                <option value="<?php echo esc_attr($header); ?>"><?php echo esc_html($header); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if ($column->Null === 'YES') : ?>
                                            <label class="allow-null">
                                                <input type="checkbox" 
                                                       name="mapping[<?php echo esc_attr($column->Field); ?>][allow_null]" 
                                                       value="1" />
                                                <?php esc_html_e('Allow NULL', 'aedc-importer'); ?>
                                            </label>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!strpos($column->Extra, 'auto_increment') !== false) : ?>
                                        <input type="text" 
                                               name="mapping[<?php echo esc_attr($column->Field); ?>][default]" 
                                               class="default-value" 
                                               placeholder="<?php esc_attr_e('Leave empty for no default', 'aedc-importer'); ?>" />
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!strpos($column->Extra, 'auto_increment') !== false) : ?>
                                        <select name="mapping[<?php echo esc_attr($column->Field); ?>][transform]" class="transform-select">
                                            <option value=""><?php esc_html_e('No transformation', 'aedc-importer'); ?></option>
                                            <option value="trim"><?php esc_html_e('Trim whitespace', 'aedc-importer'); ?></option>
                                            <option value="uppercase"><?php esc_html_e('UPPERCASE', 'aedc-importer'); ?></option>
                                            <option value="lowercase"><?php esc_html_e('lowercase', 'aedc-importer'); ?></option>
                                            <option value="capitalize"><?php esc_html_e('Capitalize Words', 'aedc-importer'); ?></option>
                                            <option value="custom"><?php esc_html_e('Custom PHP', 'aedc-importer'); ?></option>
                                        </select>
                                        <div class="custom-transform" style="display: none;">
                                            <textarea name="mapping[<?php echo esc_attr($column->Field); ?>][custom_transform]" 
                                                      placeholder="<?php esc_attr_e('Enter PHP code. Use $value for the field value.', 'aedc-importer'); ?>"
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
                        <option value=""><?php esc_html_e('Load Saved Template', 'aedc-importer'); ?></option>
                    </select>
                    <input type="text" id="template-name" placeholder="<?php esc_attr_e('Template Name', 'aedc-importer'); ?>" />
                    <button type="button" class="button button-secondary" id="save-template"><?php esc_html_e('Save as Template', 'aedc-importer'); ?></button>
                    <button type="button" class="button button-secondary" id="delete-template"><?php esc_html_e('Delete Template', 'aedc-importer'); ?></button>
                </div>
                <div class="action-buttons">
                    <button type="button" class="button button-secondary" id="auto-map"><?php esc_html_e('Auto-Map Fields', 'aedc-importer'); ?></button>
                    <button type="submit" class="button button-primary" id="mapping-submit"><?php esc_html_e('Save Mapping & Continue', 'aedc-importer'); ?></button>
                </div>
            </div>
        </form>
    </div>

    <div class="mapping-help">
        <h3><?php esc_html_e('Mapping Instructions', 'aedc-importer'); ?></h3>
        <ul>
            <li><?php esc_html_e('Match each database column with the corresponding CSV field.', 'aedc-importer'); ?></li>
            <li><?php esc_html_e('Set default values for columns that don\'t have a CSV field mapped.', 'aedc-importer'); ?></li>
            <li><?php esc_html_e('Apply transformations to clean or format the data during import.', 'aedc-importer'); ?></li>
            <li><?php esc_html_e('Use the Auto-Map feature to automatically match fields with similar names.', 'aedc-importer'); ?></li>
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
            alert('<?php esc_html_e('Please enter a template name', 'aedc-importer'); ?>');
            return;
        }

        const mappingData = collectMappingData();
        console.log('Saving template with data:', mappingData); // Debug log
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aedc_save_mapping_template',
                nonce: $('#aedc_nonce').val(),
                template_name: templateName,
                mapping_data: JSON.stringify(mappingData),
                table_name: '<?php echo esc_js($table); ?>'
            },
            success: function(response) {
                console.log('Template save response:', response); // Debug log
                if (response.success) {
                    alert('<?php esc_html_e('Template saved successfully', 'aedc-importer'); ?>');
                    loadTemplates();
                    $('#template-name').val('');
                } else {
                    alert(response.data || '<?php esc_html_e('Failed to save template', 'aedc-importer'); ?>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Template save error:', {xhr, status, error}); // Debug log
                alert('<?php esc_html_e('Failed to save template. Please try again.', 'aedc-importer'); ?>');
            }
        });
    });

    $('#load-template').on('change', function() {
        const templateName = $(this).val();
        if (!templateName) return;

        $.post(ajaxurl, {
            action: 'aedc_load_mapping_template',
            nonce: $('#aedc_nonce').val(),
            template_name: templateName
        }, function(response) {
            if (response.success) {
                applyTemplate(response.data.mapping);
            } else {
                alert(response.data || '<?php esc_html_e('Failed to load template', 'aedc-importer'); ?>');
            }
        });
    });

    $('#delete-template').on('click', function() {
        const templateName = $('#load-template').val();
        if (!templateName) {
            alert('<?php esc_html_e('Please select a template to delete', 'aedc-importer'); ?>');
            return;
        }

        if (!confirm('<?php esc_html_e('Are you sure you want to delete this template?', 'aedc-importer'); ?>')) {
            return;
        }

        $.post(ajaxurl, {
            action: 'aedc_delete_mapping_template',
            nonce: $('#aedc_nonce').val(),
            template_name: templateName
        }, function(response) {
            if (response.success) {
                alert('<?php esc_html_e('Template deleted successfully', 'aedc-importer'); ?>');
                loadTemplates();
            } else {
                alert(response.data || '<?php esc_html_e('Failed to delete template', 'aedc-importer'); ?>');
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
        $.post(ajaxurl, {
            action: 'aedc_auto_suggest_mapping',
            nonce: $('#aedc_nonce').val(),
            db_columns: JSON.stringify(dbColumns),
            csv_headers: JSON.stringify(csvHeaders)
        }, function(response) {
            if (response.success) {
                applyAutoMapping(response.data);
            } else {
                alert(response.data || '<?php esc_html_e('Failed to generate mapping suggestions', 'aedc-importer'); ?>');
            }
        });
    });

    // Form submission with validation
    $('#aedc-mapping-form').on('submit', function(e) {
        e.preventDefault();
        
        const mappingData = collectMappingData();
        const validationResult = validateMapping(mappingData);
        
        if (!validationResult.isValid) {
            alert(validationResult.message);
            return;
        }

        $.post(ajaxurl, {
            action: 'aedc_save_field_mapping',
            nonce: $('#aedc_nonce').val(),
            mapping: JSON.stringify(mappingData)
        }, function(response) {
            if (response.success) {
                window.location.href = window.location.href.replace(/step=\d/, 'step=4');
            } else {
                alert(response.data || '<?php esc_html_e('Failed to save mapping', 'aedc-importer'); ?>');
            }
        });
    });

    // Helper functions
    function loadTemplates() {
        console.log('Loading templates...'); // Debug log
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aedc_get_mapping_templates',
                nonce: $('#aedc_nonce').val()
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
            const data = mapping[columnName];

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
                message: '<?php esc_html_e('Please map at least one field', 'aedc-importer'); ?>'
            };
        }

        if (requiredColumns.length > 0) {
            return {
                isValid: false,
                message: '<?php esc_html_e('The following required columns are not mapped: ', 'aedc-importer'); ?>' + 
                        requiredColumns.join(', ')
            };
        }

        return { isValid: true };
    }
});
</script>
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
$csv_headers = isset($_SESSION['aedc_importer']['csv_headers']) ? $_SESSION['aedc_importer']['csv_headers'] : array();
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
                                </td>
                                <td>
                                    <select name="mapping[<?php echo esc_attr($column->Field); ?>][csv_field]" class="csv-field-select">
                                        <option value=""><?php esc_html_e('-- Select CSV Field --', 'aedc-importer'); ?></option>
                                        <?php foreach ($csv_headers as $header) : ?>
                                            <option value="<?php echo esc_attr($header); ?>"><?php echo esc_html($header); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" 
                                           name="mapping[<?php echo esc_attr($column->Field); ?>][default]" 
                                           class="default-value" 
                                           placeholder="<?php esc_attr_e('Leave empty for no default', 'aedc-importer'); ?>" />
                                </td>
                                <td>
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
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mapping-actions">
                <button type="button" class="button button-secondary" id="auto-map"><?php esc_html_e('Auto-Map Fields', 'aedc-importer'); ?></button>
                <button type="submit" class="button button-primary" id="mapping-submit"><?php esc_html_e('Save Mapping & Continue', 'aedc-importer'); ?></button>
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

    // Auto-map fields
    $('#auto-map').on('click', function() {
        $('.csv-field-select').each(function() {
            const columnName = $(this).closest('tr').find('.column-name strong').text().toLowerCase();
            
            $(this).find('option').each(function() {
                const csvField = $(this).text().toLowerCase();
                if (csvField === columnName) {
                    $(this).prop('selected', true);
                    return false;
                }
            });
        });
    });

    // Form submission
    $('#aedc-mapping-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        
        $.post(ajaxurl, {
            action: 'aedc_save_field_mapping',
            data: formData,
            nonce: $('#aedc_nonce').val()
        }, function(response) {
            if (response.success) {
                window.location.href = window.location.href.replace(/step=\d/, 'step=4');
            } else {
                alert(response.data || '<?php esc_html_e('Failed to save field mapping. Please try again.', 'aedc-importer'); ?>');
            }
        });
    });
});
</script> 
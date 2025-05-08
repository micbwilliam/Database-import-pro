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
                    <?php echo esc_html(count($preview_data)); ?>
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
            <h3><?php esc_html_e('Validation Results', 'aedc-importer'); ?></h3>
            <div id="validation-results">
                <p class="loading"><?php esc_html_e('Validating data...', 'aedc-importer'); ?></p>
            </div>
        </div>

        <div class="preview-actions">
            <form id="aedc-preview-form" method="post">
                <?php wp_nonce_field('aedc_importer_nonce', 'aedc_nonce'); ?>
                
                <div class="import-options">
                    <label>
                        <input type="checkbox" name="skip_duplicates" checked />
                        <?php esc_html_e('Skip duplicate records', 'aedc-importer'); ?>
                    </label>
                    <label>
                        <input type="checkbox" name="dry_run" />
                        <?php esc_html_e('Perform dry run (no actual import)', 'aedc-importer'); ?>
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

    // Handle form submission
    $('#aedc-preview-form').on('submit', function(e) {
        e.preventDefault();
        
        if (!confirm('<?php esc_html_e('Are you sure you want to proceed with the import?', 'aedc-importer'); ?>')) {
            return;
        }

        const formData = $(this).serialize();
        
        $.post(ajaxurl, {
            action: 'aedc_start_import',
            data: formData,
            nonce: $('#aedc_nonce').val()
        }, function(response) {
            if (response.success) {
                window.location.href = window.location.href.replace(/step=\d/, 'step=5');
            } else {
                alert(response.data || '<?php esc_html_e('Failed to start import. Please try again.', 'aedc-importer'); ?>');
            }
        });
    });
});
</script> 
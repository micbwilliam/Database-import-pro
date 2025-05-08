<?php
/**
 * Step 2: Select Database Table Template
 *
 * @since      1.0.0
 * @package    AEDC_Importer
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

global $wpdb;
$tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
?>

<div class="aedc-step-content step-select-table">
    <h2><?php esc_html_e('Select Target Database Table', 'aedc-importer'); ?></h2>
    
    <div class="table-selection-container">
        <form id="aedc-table-selection-form" method="post">
            <?php wp_nonce_field('aedc_importer_nonce', 'aedc_nonce'); ?>
            
            <div class="table-list">
                <div class="table-search">
                    <input type="text" id="table-search" placeholder="<?php esc_attr_e('Search tables...', 'aedc-importer'); ?>" />
                </div>

                <div class="table-options">
                    <?php foreach ($tables as $table) : ?>
                        <div class="table-option">
                            <label>
                                <input type="radio" name="target_table" value="<?php echo esc_attr($table[0]); ?>" />
                                <?php echo esc_html($table[0]); ?>
                            </label>
                            <button type="button" class="button button-secondary preview-structure" data-table="<?php echo esc_attr($table[0]); ?>">
                                <?php esc_html_e('Preview Structure', 'aedc-importer'); ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="table-preview">
                <h3><?php esc_html_e('Table Structure', 'aedc-importer'); ?></h3>
                <div id="structure-preview">
                    <p class="description"><?php esc_html_e('Select a table to view its structure', 'aedc-importer'); ?></p>
                </div>
            </div>

            <div class="table-actions">
                <button type="submit" class="button button-primary" id="table-select-submit"><?php esc_html_e('Continue to Field Mapping', 'aedc-importer'); ?></button>
            </div>
        </form>
    </div>

    <div class="table-info">
        <h3><?php esc_html_e('Important Notes', 'aedc-importer'); ?></h3>
        <ul>
            <li><?php esc_html_e('Select the table where you want to import the CSV data.', 'aedc-importer'); ?></li>
            <li><?php esc_html_e('Make sure the table structure matches your CSV data format.', 'aedc-importer'); ?></li>
            <li><?php esc_html_e('The next step will allow you to map CSV columns to table fields.', 'aedc-importer'); ?></li>
        </ul>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Table search functionality
    $('#table-search').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        $('.table-option').each(function() {
            const tableName = $(this).find('label').text().toLowerCase();
            $(this).toggle(tableName.includes(searchTerm));
        });
    });

    // Preview table structure
    $('.preview-structure').on('click', function() {
        const table = $(this).data('table');
        const preview = $('#structure-preview');
        
        preview.html('<p class="loading"><?php esc_html_e('Loading structure...', 'aedc-importer'); ?></p>');
        
        $.post(ajaxurl, {
            action: 'aedc_get_table_structure',
            table: table,
            nonce: $('#aedc_nonce').val()
        }, function(response) {
            if (response.success) {
                preview.html(response.data);
            } else {
                preview.html('<p class="error">' + response.data + '</p>');
            }
        });
    });

    // Form submission
    $('#aedc-table-selection-form').on('submit', function(e) {
        e.preventDefault();
        
        if (!$('input[name="target_table"]:checked').length) {
            alert('<?php esc_html_e('Please select a target table.', 'aedc-importer'); ?>');
            return;
        }

        const formData = $(this).serialize();
        formData.append('action', 'aedc_save_target_table');

        $.post(ajaxurl, formData, function(response) {
            if (response.success) {
                window.location.href = window.location.href.replace(/step=\d/, 'step=3');
            } else {
                alert(response.data || '<?php esc_html_e('Failed to save target table. Please try again.', 'aedc-importer'); ?>');
            }
        });
    });
});
</script> 
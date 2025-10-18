<?php
/**
 * Step 2: Select Database Table Template
 *
 * @since      1.0.0
 * @package    dbip_Importer
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

global $wpdb;
$tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
?>

<div class="dbip-step-content step-select-table">
    <h2><?php esc_html_e('Select Target Database Table', 'database-import-pro'); ?></h2>
    
    <div class="table-selection-container">
        <form id="dbip-table-selection-form" method="post">
            <?php wp_nonce_field('dbip_importer_nonce', 'dbip_nonce'); ?>
            
            <div class="table-list">
                <div class="table-search">
                    <input type="text" id="table-search" placeholder="<?php esc_attr_e('Search tables...', 'database-import-pro'); ?>" />
                </div>

                <div class="table-options">
                    <?php foreach ($tables as $table) : ?>
                        <div class="table-option">
                            <label>
                                <input type="radio" name="target_table" value="<?php echo esc_attr($table[0]); ?>" />
                                <?php echo esc_html($table[0]); ?>
                            </label>
                            <button type="button" class="button button-secondary preview-structure" data-table="<?php echo esc_attr($table[0]); ?>">
                                <?php esc_html_e('Preview Structure', 'database-import-pro'); ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="table-preview">
                <h3><?php esc_html_e('Table Structure', 'database-import-pro'); ?></h3>
                <div id="structure-preview">
                    <p class="description"><?php esc_html_e('Select a table to view its structure', 'database-import-pro'); ?></p>
                </div>
            </div>

            <div class="table-actions">
                <button type="submit" class="button button-primary" id="table-select-submit" disabled>
                    <?php esc_html_e('Continue to Field Mapping', 'database-import-pro'); ?>
                </button>
            </div>
        </form>
    </div>

    <div class="table-info">
        <h3><?php esc_html_e('Important Notes', 'database-import-pro'); ?></h3>
        <ul>
            <li><?php esc_html_e('Select the table where you want to import the CSV data.', 'database-import-pro'); ?></li>
            <li><?php esc_html_e('Make sure the table structure matches your CSV data format.', 'database-import-pro'); ?></li>
            <li><?php esc_html_e('The next step will allow you to map CSV columns to table fields.', 'database-import-pro'); ?></li>
        </ul>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const form = $('#dbip-table-selection-form');
    const submitButton = $('#table-select-submit');
    const structurePreview = $('#structure-preview');

    // Table search functionality
    $('#table-search').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        $('.table-option').each(function() {
            const tableName = $(this).find('label').text().toLowerCase();
            $(this).toggle(tableName.includes(searchTerm));
        });
    });

    // Enable submit button when a table is selected
    $('input[name="target_table"]').on('change', function() {
        submitButton.prop('disabled', false);
    });

    // Preview table structure
    $('.preview-structure').on('click', function() {
        const table = $(this).data('table');
        structurePreview.html('<p class="loading"><?php esc_html_e('Loading structure...', 'database-import-pro'); ?></p>');
        
        $.post(dbipImporter.ajax_url, {
            action: 'dbip_get_table_structure',
            nonce: $('#dbip_nonce').val(),
            table: table
        }, function(response) {
            if (response.success) {
                structurePreview.html(response.data);
            } else {
                structurePreview.html('<p class="error">' + response.data + '</p>');
            }
        });
    });

    // Handle form submission
    form.on('submit', function(e) {
        e.preventDefault();
        
        const selectedTable = $('input[name="target_table"]:checked').val();
        if (!selectedTable) {
            alert('<?php esc_html_e('Please select a target table.', 'database-import-pro'); ?>');
            return;
        }

        $.post(dbipImporter.ajax_url, {
            action: 'dbip_save_target_table',
            nonce: $('#dbip_nonce').val(),
            table: selectedTable
        }, function(response) {
            if (response.success) {
                window.location.href = window.location.href.replace(/step=\d/, 'step=3');
            } else {
                alert(response.data || '<?php esc_html_e('Failed to save target table. Please try again.', 'database-import-pro'); ?>');
            }
        });
    });
});
</script> 

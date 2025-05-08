<?php
/**
 * Step 1: CSV Upload Template
 *
 * @since      1.0.0
 * @package    AEDC_Importer
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>

<div class="aedc-step-content step-upload">
    <h2><?php esc_html_e('Upload CSV File', 'aedc-importer'); ?></h2>
    
    <div class="upload-container">
        <form id="aedc-upload-form" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('aedc_importer_nonce', 'aedc_nonce'); ?>
            
            <div class="upload-area" id="drop-area">
                <div class="upload-instructions">
                    <span class="dashicons dashicons-upload"></span>
                    <p><?php esc_html_e('Drag and drop your CSV file here', 'aedc-importer'); ?></p>
                    <p><?php esc_html_e('or', 'aedc-importer'); ?></p>
                    <input type="file" name="csv_file" id="csv-file" accept=".csv" class="file-input" />
                    <label for="csv-file" class="button button-primary"><?php esc_html_e('Select File', 'aedc-importer'); ?></label>
                </div>
                <div class="upload-preview" style="display: none;">
                    <p class="file-name"></p>
                    <button type="button" class="button button-secondary remove-file"><?php esc_html_e('Remove', 'aedc-importer'); ?></button>
                </div>
            </div>

            <div class="upload-options">
                <label>
                    <input type="checkbox" name="has_headers" checked />
                    <?php esc_html_e('First row contains column headers', 'aedc-importer'); ?>
                </label>
            </div>

            <div class="upload-actions">
                <button type="submit" class="button button-primary" id="upload-submit"><?php esc_html_e('Upload and Continue', 'aedc-importer'); ?></button>
            </div>
        </form>
    </div>

    <div class="upload-requirements">
        <h3><?php esc_html_e('Requirements', 'aedc-importer'); ?></h3>
        <ul>
            <li><?php esc_html_e('File must be in CSV format', 'aedc-importer'); ?></li>
            <li><?php esc_html_e('Maximum file size: 10MB', 'aedc-importer'); ?></li>
            <li><?php esc_html_e('UTF-8 encoding recommended', 'aedc-importer'); ?></li>
        </ul>
    </div>
</div> 
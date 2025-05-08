<?php
/**
 * Step 1: File Upload Template
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
    <h2><?php esc_html_e('Upload File', 'aedc-importer'); ?></h2>
    
    <!-- Add an error message container -->
    <div id="upload-error" class="notice notice-error" style="display: none;">
        <p></p>
    </div>

    <div class="upload-container">
        <form id="aedc-upload-form" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('aedc_importer_nonce', 'aedc_nonce'); ?>
            
            <div class="upload-area" id="drop-area">
                <div class="upload-instructions">
                    <span class="dashicons dashicons-upload"></span>
                    <p><?php esc_html_e('Drag and drop your file here', 'aedc-importer'); ?></p>
                    <p><?php esc_html_e('or', 'aedc-importer'); ?></p>
                    <input type="file" name="file" id="file-input" accept=".csv,.xls,.xlsx" class="file-input" />
                    <label for="file-input" class="button button-primary"><?php esc_html_e('Select File', 'aedc-importer'); ?></label>
                </div>
                <div class="upload-preview" style="display: none;">
                    <div class="file-info">
                        <p class="file-name"></p>
                        <p class="file-size"></p>
                    </div>
                    <button type="button" class="button button-secondary remove-file"><?php esc_html_e('Remove', 'aedc-importer'); ?></button>
                </div>
            </div>

            <div class="preview-container" style="display: none;">
                <h3><?php esc_html_e('Column Preview', 'aedc-importer'); ?></h3>
                <div class="column-list"></div>
            </div>

            <div class="upload-options">
                <label>
                    <input type="checkbox" name="has_headers" checked />
                    <?php esc_html_e('First row contains column headers', 'aedc-importer'); ?>
                </label>
            </div>

            <div class="upload-actions">
                <button type="submit" class="button button-primary" id="upload-submit" disabled><?php esc_html_e('Upload and Continue', 'aedc-importer'); ?></button>
            </div>
        </form>
    </div>

    <div class="upload-requirements">
        <h3><?php esc_html_e('File Requirements', 'aedc-importer'); ?></h3>
        <ul>
            <li><?php esc_html_e('Accepted formats: CSV, XLS, XLSX', 'aedc-importer'); ?></li>
            <li><?php esc_html_e('Maximum file size: 10MB', 'aedc-importer'); ?></li>
            <li><?php esc_html_e('UTF-8 encoding recommended for CSV files', 'aedc-importer'); ?></li>
            <li><?php esc_html_e('First row should contain column headers', 'aedc-importer'); ?></li>
        </ul>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const dropArea = $('#drop-area');
    const fileInput = $('#file-input');
    const uploadForm = $('#aedc-upload-form');
    const uploadPreview = $('.upload-preview');
    const previewContainer = $('.preview-container');
    const fileNameDisplay = $('.file-name');
    const fileSizeDisplay = $('.file-size');
    const columnList = $('.column-list');
    const submitButton = $('#upload-submit');

    // Prevent default drag behaviors
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropArea.on(eventName, preventDefaults);
        $(document).on(eventName, preventDefaults);
    });

    // Highlight drop zone when item is dragged over it
    ['dragenter', 'dragover'].forEach(eventName => {
        dropArea.on(eventName, highlight);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropArea.on(eventName, unhighlight);
    });

    // Handle dropped files
    dropArea.on('drop', handleDrop);
    
    // Handle file input change
    fileInput.on('change', handleFileSelect);

    // Handle remove file button
    $('.remove-file').on('click', removeFile);

    // Handle form submission
    uploadForm.on('submit', function(e) {
        e.preventDefault();
        if (!fileInput[0].files.length) {
            showError('Please select a file to upload.');
            return;
        }
        window.location.href = window.location.href.replace(/step=\d/, 'step=2');
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    function highlight(e) {
        dropArea.addClass('dragover');
    }

    function unhighlight(e) {
        dropArea.removeClass('dragover');
    }

    function handleDrop(e) {
        const dt = e.originalEvent.dataTransfer;
        const files = dt.files;
        handleFiles(files);
    }

    function handleFileSelect(e) {
        const files = e.target.files;
        handleFiles(files);
    }

    function handleFiles(files) {
        if (files.length > 0) {
            const file = files[0];
            if (validateFile(file)) {
                showFilePreview(file);
                uploadFile(file);
            }
        }
    }

    function validateFile(file) {
        const allowedTypes = [
            'text/csv',
            'text/plain',
            'application/csv',
            'application/excel',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
        const maxSize = 10 * 1024 * 1024; // 10MB

        const ext = file.name.split('.').pop().toLowerCase();
        if (!['csv', 'xls', 'xlsx'].includes(ext)) {
            showError('Please upload a CSV, XLS, or XLSX file.');
            return false;
        }

        if (file.size > maxSize) {
            showError('File size must be less than 10MB.');
            return false;
        }

        return true;
    }

    function showFilePreview(file) {
        $('.upload-instructions').hide();
        fileNameDisplay.text(file.name);
        fileSizeDisplay.text(formatFileSize(file.size));
        uploadPreview.show();
        submitButton.prop('disabled', true);
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function removeFile() {
        fileInput.val('');
        uploadPreview.hide();
        previewContainer.hide();
        $('.upload-instructions').show();
        submitButton.prop('disabled', true);
        $('#upload-error').hide();
    }

    function uploadFile(file) {
        const formData = new FormData();
        formData.append('action', 'aedc_upload_file');
        formData.append('nonce', aedcImporter.nonce);
        formData.append('file', file);

        // Show loading state
        submitButton.prop('disabled', true).text('Uploading...');
        $('#upload-error').hide();

        $.ajax({
            url: aedcImporter.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('Upload response:', response); // Debug log
                if (response.success) {
                    if (response.data && response.data.headers) {
                        showColumnPreview(response.data.headers);
                        // Store headers in session via another AJAX call
                        $.post(ajaxurl, {
                            action: 'aedc_store_headers',
                            nonce: aedcImporter.nonce,
                            headers: JSON.stringify(response.data.headers)
                        });
                        submitButton.prop('disabled', false).text('Upload and Continue');
                    } else {
                        showError('No headers found in the uploaded file');
                        removeFile();
                    }
                } else {
                    const errorMessage = response.data || 'Upload failed. Please try again.';
                    showError(errorMessage);
                    removeFile();
                }
            },
            error: function(xhr, status, error) {
                console.error('Upload error:', {xhr, status, error}); // Debug log
                let errorMessage = 'Upload failed. ';
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMessage += xhr.responseJSON.data;
                } else if (error) {
                    errorMessage += error;
                } else {
                    errorMessage += 'Please try again.';
                }
                showError(errorMessage);
                removeFile();
            },
            complete: function() {
                submitButton.prop('disabled', false).text('Upload and Continue');
            }
        });
    }

    function showError(message) {
        const errorDiv = $('#upload-error');
        errorDiv.find('p').text(message);
        errorDiv.show();
        $('html, body').animate({
            scrollTop: errorDiv.offset().top - 50
        }, 500);
    }

    function showColumnPreview(headers) {
        columnList.empty();
        headers.forEach(function(header) {
            columnList.append($('<div class="column-item">').text(header));
        });
        previewContainer.show();
    }
});
</script> 
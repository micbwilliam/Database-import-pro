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
                    <input type="file" name="file" id="file-input" accept=".csv" class="file-input" />
                    <label for="file-input" class="button button-primary"><?php esc_html_e('Select File', 'aedc-importer'); ?></label>
                </div>
                <div class="upload-preview" style="display: none;">
                    <div class="file-info">
                        <p class="file-name"></p>
                        <p class="file-size"></p>
                    </div>
                    <div class="upload-progress" style="display: none;">
                        <div class="progress-bar">
                            <div class="progress-bar-fill"></div>
                        </div>
                        <p class="progress-text">0%</p>
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
            <li><?php esc_html_e('Accepted format: CSV', 'aedc-importer'); ?></li>
            <li><?php esc_html_e('Maximum file size: 50MB', 'aedc-importer'); ?></li>
            <li><?php esc_html_e('UTF-8 encoding recommended', 'aedc-importer'); ?></li>
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
    const progressBar = $('.progress-bar-fill');
    const progressText = $('.progress-text');

    // Add CSS to ensure progress bar is always visible
    const style = `
        <style>
            .upload-progress {
                margin: 15px 0;
                padding: 10px;
                background: #f0f0f0;
                border-radius: 4px;
                border: 1px solid #ddd;
            }
            .progress-bar {
                width: 100%;
                height: 24px;
                background: #f0f0f0;
                border-radius: 12px;
                overflow: hidden;
                position: relative;
                box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
            }
            .progress-bar-fill {
                height: 100%;
                background: linear-gradient(90deg, #2271b1, #72aee6);
                transition: width 0.3s ease;
                min-width: 5%;
                position: relative;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .progress-text {
                position: absolute;
                left: 50%;
                top: 50%;
                transform: translate(-50%, -50%);
                color: #fff;
                text-shadow: 0 1px 1px rgba(0,0,0,0.2);
                font-weight: bold;
                font-size: 13px;
                z-index: 1;
            }
            .upload-status {
                margin-top: 8px;
                text-align: center;
                color: #666;
                font-size: 13px;
            }
            .upload-complete .progress-bar-fill {
                background: linear-gradient(90deg, #46b450, #6cc677);
            }
        </style>
    `;
    $('head').append(style);

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

        // Check if file is already uploaded successfully
        if (submitButton.data('upload-complete')) {
            window.location.href = window.location.href.replace(/step=\d/, 'step=2');
            return;
        }

        // If file is not uploaded yet, trigger the upload
        uploadFile(fileInput[0].files[0]);
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
            'application/csv'
        ];
        const maxSize = 50 * 1024 * 1024; // 50MB

        const ext = file.name.split('.').pop().toLowerCase();
        if (ext !== 'csv') {
            showError('Please upload a CSV file.');
            return false;
        }

        if (file.size > maxSize) {
            showError('File size must be less than 50MB.');
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
        $('.upload-options').show(); // Show options for all file types
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
        $('.upload-progress').removeClass('upload-complete');
        $('.upload-status').text('');
    }

    function uploadFile(file) {
        const formData = new FormData();
        formData.append('action', 'aedc_upload_file');
        formData.append('nonce', aedcImporter.nonce);
        formData.append('file', file);
        formData.append('has_headers', $('input[name="has_headers"]').prop('checked'));

        // Reset and show upload state
        submitButton.prop('disabled', true).text('Uploading...');
        $('#upload-error').hide();
        $('.upload-progress').show().removeClass('upload-complete');
        progressBar.css('width', '5%');
        progressText.text('Starting upload...');
        
        // Add status message container if it doesn't exist
        if ($('.upload-status').length === 0) {
            $('.upload-progress').append('<div class="upload-status">Starting upload...</div>');
        }
        const statusText = $('.upload-status');

        // Create upload timeout warning
        let uploadTimeout = setTimeout(() => {
            showError('Upload is taking longer than expected. Please wait...');
        }, 10000);

        $.ajax({
            url: aedcImporter.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        clearTimeout(uploadTimeout);
                        const percentComplete = Math.max((e.loaded / e.total) * 100, 5);
                        progressBar.css('width', percentComplete + '%');
                        progressText.text(Math.round(percentComplete) + '%');
                        
                        if (percentComplete === 100) {
                            statusText.text('Processing file...');
                        } else {
                            statusText.text('Uploading: ' + Math.round(percentComplete) + '%');
                        }
                    }
                }, false);
                
                xhr.upload.addEventListener('loadstart', function() {
                    console.log('Upload started');
                    progressBar.css('width', '5%');
                    progressText.text('Starting upload...');
                    statusText.text('Upload started');
                });
                
                xhr.upload.addEventListener('loadend', function() {
                    console.log('Upload ended');
                    progressBar.css('width', '100%');
                    progressText.text('Processing...');
                    statusText.text('Processing file...');
                });
                
                return xhr;
            },
            success: function(response) {
                clearTimeout(uploadTimeout);
                console.log('Upload completed:', response);
                
                // Keep progress bar at 100% and show completion
                progressBar.css('width', '100%');
                progressText.text('100%');
                $('.upload-progress').addClass('upload-complete');
                statusText.text('Upload completed successfully!');
                
                if (response.success) {
                    if (response.data && response.data.headers) {
                        showColumnPreview(response.data.headers);
                        // Store headers in session
                        $.post(ajaxurl, {
                            action: 'aedc_store_headers',
                            nonce: aedcImporter.nonce,
                            headers: JSON.stringify(response.data.headers)
                        }, function(headerResponse) {
                            console.log('Headers stored:', headerResponse);
                            // Keep showing success state
                            $('.upload-progress').show().addClass('upload-complete');
                            progressBar.css('width', '100%');
                            progressText.text('100%');
                            statusText.text('Upload completed successfully!');
                            // Enable the continue button
                            submitButton.prop('disabled', false)
                                      .text('Continue to Next Step')
                                      .data('upload-complete', true);
                        }).fail(function(xhr, status, error) {
                            console.error('Failed to store headers:', error);
                            showError('Failed to process file headers. Please try again.');
                            removeFile();
                        });
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
                clearTimeout(uploadTimeout);
                console.error('Upload error:', {xhr, status, error});
                statusText.text('Upload failed');
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
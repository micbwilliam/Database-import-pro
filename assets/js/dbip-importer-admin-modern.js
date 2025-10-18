/**
 * Database Import Pro - Modern ES6+ JavaScript
 * Version: 1.1.0
 * 
 * @package DatabaseImportPro
 */

(function($) {
    'use strict';

    /**
     * Main DBIP Importer Class
     */
    class DBIPImporter {
        constructor() {
            this.ajax_url = dbipImporter.ajax_url;
            this.nonce = dbipImporter.nonce;
            this.strings = dbipImporter.strings;
            
            // DOM Elements
            this.elements = {
                dropArea: $('#drop-area'),
                fileInput: $('#csv-file'),
                uploadForm: $('#dbip-upload-form'),
                uploadPreview: $('.upload-preview'),
                fileNameDisplay: $('.file-name'),
                removeFileBtn: $('.remove-file')
            };

            this.init();
        }

        /**
         * Initialize the importer
         */
        init() {
            this.setupFileUpload();
            this.setupDragAndDrop();
        }

        /**
         * Setup file upload handlers
         */
        setupFileUpload() {
            const { fileInput, removeFileBtn, uploadForm } = this.elements;

            fileInput.on('change', (e) => this.handleFileSelect(e));
            removeFileBtn.on('click', () => this.removeFile());
            uploadForm.on('submit', (e) => this.handleSubmit(e));
        }

        /**
         * Setup drag and drop functionality
         */
        setupDragAndDrop() {
            const { dropArea } = this.elements;

            // Prevent default drag behaviors
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropArea.on(eventName, (e) => this.preventDefaults(e));
                $(document).on(eventName, (e) => this.preventDefaults(e));
            });

            // Highlight drop zone when item is dragged over it
            ['dragenter', 'dragover'].forEach(eventName => {
                dropArea.on(eventName, () => this.highlight());
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropArea.on(eventName, () => this.unhighlight());
            });

            // Handle dropped files
            dropArea.on('drop', (e) => this.handleDrop(e));
        }

        /**
         * Prevent default event behaviors
         */
        preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        /**
         * Highlight drop area
         */
        highlight() {
            this.elements.dropArea.addClass('dragover');
        }

        /**
         * Remove highlight from drop area
         */
        unhighlight() {
            this.elements.dropArea.removeClass('dragover');
        }

        /**
         * Handle file drop event
         */
        handleDrop(e) {
            try {
                const dt = e.originalEvent.dataTransfer;
                const files = dt.files;
                this.handleFiles(files);
            } catch (error) {
                console.error('Error handling file drop:', error);
                this.showError('Failed to process dropped file');
            }
        }

        /**
         * Handle file selection from input
         */
        handleFileSelect(e) {
            try {
                const files = e.target.files;
                this.handleFiles(files);
            } catch (error) {
                console.error('Error handling file selection:', error);
                this.showError('Failed to process selected file');
            }
        }

        /**
         * Process selected files
         */
        handleFiles(files) {
            if (files.length > 0) {
                const file = files[0];
                
                if (this.validateFile(file)) {
                    this.showFilePreview(file);
                }
            }
        }

        /**
         * Validate uploaded file
         */
        validateFile(file) {
            const allowedExtensions = ['csv', 'xlsx', 'xls'];
            const maxSize = 50 * 1024 * 1024; // 50MB
            
            // Get file extension
            const fileName = file.name.toLowerCase();
            const extension = fileName.substring(fileName.lastIndexOf('.') + 1);

            // Check file type
            if (!allowedExtensions.includes(extension)) {
                this.showError(`Please upload a valid file (${allowedExtensions.join(', ').toUpperCase()})`);
                return false;
            }

            // Check file size
            if (file.size > maxSize) {
                const maxSizeMB = Math.round(maxSize / (1024 * 1024));
                this.showError(`File size must be less than ${maxSizeMB}MB`);
                return false;
            }

            return true;
        }

        /**
         * Show file preview
         */
        showFilePreview(file) {
            const { fileNameDisplay, uploadPreview } = this.elements;
            
            $('.upload-instructions').hide();
            fileNameDisplay.text(file.name);
            uploadPreview.show();
        }

        /**
         * Remove selected file
         */
        removeFile() {
            const { fileInput, uploadPreview } = this.elements;
            
            fileInput.val('');
            uploadPreview.hide();
            $('.upload-instructions').show();
        }

        /**
         * Handle form submission
         */
        async handleSubmit(e) {
            e.preventDefault();

            const { fileInput, uploadForm } = this.elements;
            const file = fileInput[0].files[0];

            if (!file) {
                this.showError('Please select a file to upload');
                return;
            }

            try {
                await this.uploadFile(file, uploadForm);
            } catch (error) {
                console.error('Upload error:', error);
                this.showError(error.message || 'Upload failed. Please try again.');
            }
        }

        /**
         * Upload file via AJAX
         */
        uploadFile(file, form) {
            return new Promise((resolve, reject) => {
                const formData = new FormData(form[0]);
                
                $.ajax({
                    url: this.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    beforeSend: () => {
                        this.showProgress(0);
                    },
                    xhr: () => {
                        const xhr = new window.XMLHttpRequest();
                        xhr.upload.addEventListener('progress', (e) => {
                            if (e.lengthComputable) {
                                const percent = Math.round((e.loaded / e.total) * 100);
                                this.showProgress(percent);
                            }
                        }, false);
                        return xhr;
                    },
                    success: (response) => {
                        if (response.success) {
                            resolve(response);
                            window.location.href = response.data.redirect || '#step-2';
                        } else {
                            reject(new Error(response.data || 'Upload failed'));
                        }
                    },
                    error: (xhr, status, error) => {
                        reject(new Error(this.strings.network_error || 'Network error occurred'));
                    }
                });
            });
        }

        /**
         * Show upload progress
         */
        showProgress(percent) {
            $('.upload-progress-bar').css('width', `${percent}%`);
            $('.upload-progress-text').text(`${percent}%`);
            
            if (percent === 0) {
                $('.upload-progress').show();
            } else if (percent === 100) {
                $('.upload-progress-text').text('Processing...');
            }
        }

        /**
         * Show error message
         */
        showError(message) {
            // Remove any existing error messages
            $('.dbip-error-message').remove();
            
            // Create and show new error message
            const errorDiv = $('<div>')
                .addClass('notice notice-error dbip-error-message')
                .html(`<p><strong>Error:</strong> ${message}</p>`);
            
            $('.wrap > h1').after(errorDiv);
            
            // Scroll to error message
            $('html, body').animate({
                scrollTop: errorDiv.offset().top - 50
            }, 300);
            
            // Auto-remove after 10 seconds
            setTimeout(() => errorDiv.fadeOut(() => errorDiv.remove()), 10000);
        }

        /**
         * Show success message
         */
        showSuccess(message) {
            // Remove any existing messages
            $('.dbip-success-message').remove();
            
            // Create and show success message
            const successDiv = $('<div>')
                .addClass('notice notice-success dbip-success-message')
                .html(`<p><strong>Success:</strong> ${message}</p>`);
            
            $('.wrap > h1').after(successDiv);
            
            // Auto-remove after 5 seconds
            setTimeout(() => successDiv.fadeOut(() => successDiv.remove()), 5000);
        }
    }

    /**
     * Initialize when document is ready
     */
    $(() => {
        const importer = new DBIPImporter();
    });

})(jQuery);

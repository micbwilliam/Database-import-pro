jQuery(document).ready(function($) {
    // Global AJAX Error Handler
    $(document).ajaxError(function(event, jqxhr, settings, thrownError) {
        // Don't handle errors for status checks (they fail gracefully)
        if (settings.data && settings.data.includes('dbip_get_import_status')) {
            return;
        }
        
        console.error('AJAX Error:', {
            url: settings.url,
            status: jqxhr.status,
            statusText: jqxhr.statusText,
            error: thrownError,
            response: jqxhr.responseText
        });
        
        // Show user-friendly error message
        if (jqxhr.status === 0) {
            alert('Network error. Please check your connection and try again.');
        } else if (jqxhr.status === 403) {
            alert('Session expired. Please refresh the page and try again.');
        } else if (jqxhr.status === 500) {
            alert('Server error. Please try again or contact support.');
        }
    });
    
    // File Upload Handling
    const dropArea = $('#drop-area');
    const fileInput = $('#csv-file');
    const uploadForm = $('#dbip-upload-form');
    const uploadPreview = $('.upload-preview');
    const fileNameDisplay = $('.file-name');
    const removeFileBtn = $('.remove-file');

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
    removeFileBtn.on('click', removeFile);

    // Handle form submission
    uploadForm.on('submit', handleSubmit);

    // Utility Functions
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
            }
        }
    }

    function validateFile(file) {
        // Check file type
        if (!file.name.toLowerCase().endsWith('.csv')) {
            alert('Please upload a CSV file.');
            return false;
        }

        // Check file size (10MB max)
        if (file.size > 10 * 1024 * 1024) {
            alert('File size must be less than 10MB.');
            return false;
        }

        return true;
    }

    function showFilePreview(file) {
        $('.upload-instructions').hide();
        fileNameDisplay.text(file.name);
        uploadPreview.show();
    }

    function removeFile() {
        fileInput.val('');
        uploadPreview.hide();
        $('.upload-instructions').show();
    }

    function handleSubmit(e) {
        e.preventDefault();

        if (!fileInput[0].files.length) {
            alert('Please select a file to upload.');
            return;
        }

        const formData = new FormData(uploadForm[0]);
        formData.append('action', 'dbip_upload_csv');

        $.ajax({
            url: dbipImporter.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                // Show loading state
                $('#upload-submit').prop('disabled', true).text('Uploading...');
            },
            success: function(response) {
                if (response.success) {
                    // Redirect to next step
                    window.location.href = window.location.href.replace(/step=\d/, 'step=2');
                } else {
                    alert(response.data || 'Upload failed. Please try again.');
                }
            },
            error: function() {
                alert('Upload failed. Please try again.');
            },
            complete: function() {
                // Reset button state
                $('#upload-submit').prop('disabled', false).text('Upload and Continue');
            }
        });
    }
}); 
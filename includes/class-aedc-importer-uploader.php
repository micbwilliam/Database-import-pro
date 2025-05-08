<?php
/**
 * File uploader and validator class
 *
 * @since      1.0.0
 * @package    AEDC_Importer
 */

class AEDC_Importer_Uploader {
    /**
     * Allowed file types and their MIME types
     *
     * @var array
     */
    private $allowed_types = array(
        'csv' => array(
            'text/csv',
            'text/plain',
            'application/csv',
            'text/comma-separated-values',
            'text/x-comma-separated-values',
            'text/x-csv'
        )
    );

    /**
     * Maximum file size in bytes (50MB)
     *
     * @var int
     */
    private $max_file_size = 52428800; // 50MB in bytes

    /**
     * Upload directory
     *
     * @var string
     */
    private $upload_dir;

    /**
     * Initialize the uploader
     */
    public function __construct() {
        $upload_base = wp_upload_dir();
        $this->upload_dir = trailingslashit($upload_base['basedir']) . 'aedc-importer';

        // Create directory if it doesn't exist
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
        }

        // Add AJAX handlers
        add_action('wp_ajax_aedc_upload_file', array($this, 'handle_upload'));
        add_action('wp_ajax_aedc_get_headers', array($this, 'get_file_headers'));
        add_action('wp_ajax_aedc_store_headers', array($this, 'store_headers'));
    }

    /**
     * Handle file upload
     */
    public function handle_upload() {
        try {
            check_ajax_referer('aedc_importer_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                throw new Exception(__('Unauthorized access', 'aedc-importer'));
            }

            if (empty($_FILES['file'])) {
                throw new Exception(__('No file uploaded', 'aedc-importer'));
            }

            $file = $_FILES['file'];
            error_log('AEDC Importer: Starting file upload processing: ' . print_r($file, true));
            
            // Check for PHP upload errors
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error_message = $this->get_upload_error_message($file['error']);
                throw new Exception($error_message);
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            error_log('AEDC Importer: File extension: ' . $ext);

            // Validate file extension
            if (!array_key_exists($ext, $this->allowed_types)) {
                throw new Exception(__('Invalid file type. Please upload a CSV file.', 'aedc-importer'));
            }

            // Validate file size
            if ($file['size'] > $this->max_file_size) {
                throw new Exception(sprintf(
                    __('File size (%s) exceeds limit of %s.', 'aedc-importer'),
                    size_format($file['size']),
                    size_format($this->max_file_size)
                ));
            }

            // Clean up any existing uploaded files
            $this->cleanup();

            // Ensure upload directory exists and is writable
            if (!wp_mkdir_p($this->upload_dir)) {
                throw new Exception(__('Failed to create upload directory', 'aedc-importer'));
            }

            if (!is_writable($this->upload_dir)) {
                throw new Exception(__('Upload directory is not writable', 'aedc-importer'));
            }

            // Generate unique filename
            $filename = uniqid('import_') . '.' . $ext;
            $filepath = $this->upload_dir . '/' . $filename;

            error_log('AEDC Importer: Moving file to: ' . $filepath);

            // Move uploaded file with chunking support
            if ($this->move_uploaded_file_chunked($file['tmp_name'], $filepath)) {
                error_log('AEDC Importer: File moved successfully');
                
                // Store file info in session
                $_SESSION['aedc_importer']['file'] = array(
                    'name' => $file['name'],
                    'path' => $filepath,
                    'type' => $ext,
                    'size' => $file['size']
                );

                // Get file headers based on type
                error_log('AEDC Importer: Getting headers for file type: ' . $ext);
                $headers = $this->get_file_headers($filepath, $ext);
                
                if (is_wp_error($headers)) {
                    error_log('AEDC Importer Error: ' . $headers->get_error_message());
                    throw new Exception($headers->get_error_message());
                }

                if (empty($headers)) {
                    error_log('AEDC Importer Error: No headers found in file');
                    throw new Exception(__('No headers found in the uploaded file', 'aedc-importer'));
                }

                $_SESSION['aedc_importer']['headers'] = $headers;
                error_log('AEDC Importer: Headers extracted successfully: ' . print_r($headers, true));

                wp_send_json_success(array(
                    'filename' => $file['name'],
                    'size' => size_format($file['size']),
                    'headers' => $headers,
                    'message' => __('File uploaded successfully', 'aedc-importer')
                ));
            } else {
                throw new Exception(__('Failed to move uploaded file', 'aedc-importer'));
            }

        } catch (Exception $e) {
            error_log("AEDC Importer Error: " . $e->getMessage());
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Move uploaded file with chunking support for large files
     */
    private function move_uploaded_file_chunked($source, $dest, $chunk_size = 1048576) {
        $handle_in = @fopen($source, 'rb');
        $handle_out = @fopen($dest, 'wb');

        if (!$handle_in || !$handle_out) {
            return false;
        }

        while (!feof($handle_in)) {
            if (fwrite($handle_out, fread($handle_in, $chunk_size)) === false) {
                fclose($handle_in);
                fclose($handle_out);
                @unlink($dest);
                return false;
            }
        }

        fclose($handle_in);
        fclose($handle_out);

        return true;
    }

    /**
     * Get upload error message
     *
     * @param int $error_code PHP upload error code
     * @return string Error message
     */
    private function get_upload_error_message($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return __('The uploaded file exceeds the upload_max_filesize directive in php.ini', 'aedc-importer');
            case UPLOAD_ERR_FORM_SIZE:
                return __('The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form', 'aedc-importer');
            case UPLOAD_ERR_PARTIAL:
                return __('The uploaded file was only partially uploaded', 'aedc-importer');
            case UPLOAD_ERR_NO_FILE:
                return __('No file was uploaded', 'aedc-importer');
            case UPLOAD_ERR_NO_TMP_DIR:
                return __('Missing a temporary folder', 'aedc-importer');
            case UPLOAD_ERR_CANT_WRITE:
                return __('Failed to write file to disk', 'aedc-importer');
            case UPLOAD_ERR_EXTENSION:
                return __('A PHP extension stopped the file upload', 'aedc-importer');
            default:
                return __('Unknown upload error', 'aedc-importer');
        }
    }

    /**
     * Get MIME type of a file
     *
     * @param string $file File path
     * @return string MIME type
     */
    private function get_mime_type($file) {
        $mime_type = '';

        // Try fileinfo first
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file);
            finfo_close($finfo);
        }

        // If fileinfo fails or returns generic type, try mime_content_type
        if (empty($mime_type) || $mime_type === 'text/plain' || $mime_type === 'application/octet-stream') {
            if (function_exists('mime_content_type')) {
                $mime_type = mime_content_type($file);
            }
        }

        return $mime_type;
    }

    /**
     * Validate if a file is a valid CSV
     *
     * @param string $file File path
     * @return boolean True if valid CSV
     */
    private function is_valid_csv($file) {
        if (($handle = fopen($file, 'r')) !== false) {
            // Try to read the first line
            $first_line = fgets($handle);
            fclose($handle);

            // Check if the line contains a comma or semicolon (common CSV delimiters)
            if (strpos($first_line, ',') !== false || strpos($first_line, ';') !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get file headers
     *
     * @param string $filepath File path
     * @param string $type File type
     * @return array|WP_Error Headers array or error
     */
    private function get_file_headers($filepath, $type) {
        return $this->get_csv_headers($filepath);
    }

    /**
     * Get headers from CSV file
     *
     * @param string $filepath File path
     * @return array|WP_Error Headers array or error
     */
    private function get_csv_headers($filepath) {
        $handle = fopen($filepath, 'r');
        if (!$handle) {
            return new WP_Error('file_error', __('Could not open file', 'aedc-importer'));
        }

        // Detect encoding and convert to UTF-8 if necessary
        $content = fgets($handle);
        $encoding = mb_detect_encoding($content, array('UTF-8', 'ISO-8859-1', 'ASCII'));
        if ($encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        // Parse CSV line
        $headers = str_getcsv($content);
        $headers = array_map('trim', $headers);
        $headers = array_map('sanitize_text_field', $headers);

        fclose($handle);
        return $headers;
    }

    /**
     * Store CSV headers in session
     */
    public function store_headers() {
        check_ajax_referer('aedc_importer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'aedc-importer'));
        }

        $headers = isset($_POST['headers']) ? json_decode(stripslashes($_POST['headers']), true) : array();
        
        if (empty($headers)) {
            wp_send_json_error(__('No headers provided', 'aedc-importer'));
        }

        // Store headers in session
        $_SESSION['aedc_importer']['headers'] = $headers;
        
        error_log('AEDC Importer Debug: Stored headers in session: ' . print_r($headers, true));
        
        wp_send_json_success();
    }

    /**
     * Clean up temporary files
     */
    public function cleanup() {
        if (isset($_SESSION['aedc_importer']['file']['path'])) {
            @unlink($_SESSION['aedc_importer']['file']['path']);
        }
    }
}
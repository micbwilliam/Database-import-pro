<?php
/**
 * File uploader and validator class
 *
 * @since      1.0.0
 * @package    DBIP_Importer
 */

class DBIP_Importer_Uploader {
    /**
     * Allowed file types and their MIME types
     * Dynamically populated based on server capabilities
     *
     * @var array
     */
    private $allowed_types = array();

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
        $this->upload_dir = trailingslashit($upload_base['basedir']) . 'database-import-pro';

        // Load system checker
        require_once DBIP_IMPORTER_PLUGIN_DIR . 'includes/class-dbip-importer-system-check.php';

        // Set allowed types based on server capabilities
        $this->set_allowed_types();

        // Create directory if it doesn't exist
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
        }

        // Add AJAX handlers
        add_action('wp_ajax_dbip_upload_file', array($this, 'handle_upload'));
        add_action('wp_ajax_dbip_get_headers', array($this, 'get_file_headers'));
        add_action('wp_ajax_dbip_store_headers', array($this, 'store_headers'));
        add_action('wp_ajax_dbip_get_system_capabilities', array($this, 'get_system_capabilities'));
    }

    /**
     * Set allowed file types based on server capabilities
     *
     * @return void
     */
    private function set_allowed_types(): void {
        $formats = DBIP_Importer_System_Check::get_supported_formats();
        
        foreach ($formats as $key => $format) {
            if ($format['available']) {
                $this->allowed_types[$key] = $format['mime_types'];
            }
        }
    }

    /**
     * Get system capabilities for AJAX
     *
     * @return void
     */
    public function get_system_capabilities(): void {
        check_ajax_referer('dbip_importer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'database-import-pro'));
        }

        $notice = DBIP_Importer_System_Check::get_capability_notice();
        $formats = DBIP_Importer_System_Check::get_supported_formats();
        $extensions = array();
        
        foreach ($formats as $format) {
            if ($format['available']) {
                $extensions[] = $format['extension'];
            }
        }

        wp_send_json_success(array(
            'notice' => $notice,
            'formats' => $formats,
            'extensions' => $extensions,
            'accept_attribute' => DBIP_Importer_System_Check::get_accept_attribute(),
            'supported_list' => DBIP_Importer_System_Check::get_supported_extensions_string()
        ));
    }

    /**
     * Handle file upload
     * 
     * @return void
     */
    public function handle_upload(): void {
        try {
            check_ajax_referer('dbip_importer_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                throw new Exception(__('Unauthorized access', 'database-import-pro'));
            }

            if (empty($_FILES['file'])) {
                throw new Exception(__('No file uploaded', 'database-import-pro'));
            }

            $file = $_FILES['file'];
            error_log('Database Import Pro Importer: Starting file upload processing: ' . print_r($file, true));
            
            // Check for PHP upload errors
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error_message = $this->get_upload_error_message($file['error']);
                throw new Exception($error_message);
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            error_log('Database Import Pro Importer: File extension: ' . $ext);

            // Validate file extension
            if (!array_key_exists($ext, $this->allowed_types)) {
                $supported = DBIP_Importer_System_Check::get_supported_extensions_string();
                throw new Exception(sprintf(
                    __('Invalid file type. Supported formats: %s', 'database-import-pro'),
                    $supported
                ));
            }

            // Validate file size
            if ($file['size'] > $this->max_file_size) {
                throw new Exception(sprintf(
                    __('File size (%s) exceeds limit of %s.', 'database-import-pro'),
                    size_format($file['size']),
                    size_format($this->max_file_size)
                ));
            }

            // Clean up any existing uploaded files
            $this->cleanup();

            // Ensure upload directory exists and is writable
            if (!wp_mkdir_p($this->upload_dir)) {
                error_log('Database Import Pro Error: Failed to create upload directory: ' . $this->upload_dir);
                throw new Exception(__('Failed to create upload directory. Please check server permissions.', 'database-import-pro'));
            }

            // Verify directory was created successfully
            if (!is_dir($this->upload_dir)) {
                error_log('Database Import Pro Error: Upload directory does not exist after creation: ' . $this->upload_dir);
                throw new Exception(__('Upload directory creation succeeded but directory is not accessible.', 'database-import-pro'));
            }

            // Check if directory is writable
            if (!is_writable($this->upload_dir)) {
                error_log('Database Import Pro Error: Upload directory is not writable: ' . $this->upload_dir);
                throw new Exception(sprintf(
                    __('Upload directory is not writable: %s. Please check directory permissions (should be 755 or 775).', 'database-import-pro'),
                    $this->upload_dir
                ));
            }

            // Check available disk space
            $free_space = @disk_free_space($this->upload_dir);
            if ($free_space !== false && $free_space < $file['size']) {
                error_log('Database Import Pro Error: Insufficient disk space. Required: ' . size_format($file['size']) . ', Available: ' . size_format($free_space));
                throw new Exception(sprintf(
                    __('Insufficient disk space. Required: %s, Available: %s', 'database-import-pro'),
                    size_format($file['size']),
                    size_format($free_space)
                ));
            }

            // Generate unique filename
            $filename = uniqid('import_') . '.' . $ext;
            $filepath = $this->upload_dir . '/' . $filename;

            error_log('Database Import Pro Importer: Moving file to: ' . $filepath);

            // Verify source file exists and is readable
            if (!is_uploaded_file($file['tmp_name'])) {
                error_log('Database Import Pro Error: Source file is not a valid uploaded file: ' . $file['tmp_name']);
                throw new Exception(__('Invalid upload: file failed security check.', 'database-import-pro'));
            }

            if (!is_readable($file['tmp_name'])) {
                error_log('Database Import Pro Error: Source file is not readable: ' . $file['tmp_name']);
                throw new Exception(__('Cannot read uploaded file. File may be corrupted.', 'database-import-pro'));
            }

            // Move uploaded file with chunking support
            if ($this->move_uploaded_file_chunked($file['tmp_name'], $filepath)) {
                error_log('Database Import Pro Importer: File moved successfully');
                
                // Verify destination file was created and is readable
                if (!file_exists($filepath)) {
                    error_log('Database Import Pro Error: File was not created at destination: ' . $filepath);
                    throw new Exception(__('File upload succeeded but file cannot be found at destination.', 'database-import-pro'));
                }

                if (!is_readable($filepath)) {
                    error_log('Database Import Pro Error: Destination file is not readable: ' . $filepath);
                    throw new Exception(__('File uploaded but cannot be read. Please check file permissions.', 'database-import-pro'));
                }

                $actual_size = filesize($filepath);
                if ($actual_size === false) {
                    error_log('Database Import Pro Error: Cannot determine file size: ' . $filepath);
                    throw new Exception(__('File uploaded but size cannot be determined.', 'database-import-pro'));
                }

                if ($actual_size !== $file['size']) {
                    error_log('Database Import Pro Error: File size mismatch. Expected: ' . $file['size'] . ', Got: ' . $actual_size);
                    @unlink($filepath); // Clean up incomplete file
                    throw new Exception(__('File upload incomplete. File size mismatch detected.', 'database-import-pro'));
                }
                
                // Store file info in transient
                dbip_set_import_data('file', array(
                    'name' => $file['name'],
                    'path' => $filepath,
                    'type' => $ext,
                    'size' => $file['size']
                ));

                // Get file headers based on type
                error_log('Database Import Pro Importer: Getting headers for file type: ' . $ext);
                $headers = $this->get_file_headers($filepath, $ext);
                
                if (is_wp_error($headers)) {
                    error_log('Database Import Pro Importer Error: ' . $headers->get_error_message());
                    throw new Exception($headers->get_error_message());
                }

                if (empty($headers)) {
                    error_log('Database Import Pro Importer Error: No headers found in file');
                    throw new Exception(__('No headers found in the uploaded file', 'database-import-pro'));
                }

                dbip_set_import_data('headers', $headers);
                error_log('Database Import Pro Importer: Headers extracted successfully: ' . print_r($headers, true));

                wp_send_json_success(array(
                    'filename' => $file['name'],
                    'size' => size_format($file['size']),
                    'headers' => $headers,
                    'message' => __('File uploaded successfully', 'database-import-pro')
                ));
            } else {
                throw new Exception(__('Failed to move uploaded file', 'database-import-pro'));
            }

        } catch (Exception $e) {
            error_log("Database Import Pro Error: " . $e->getMessage());
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
                return __('The uploaded file exceeds the upload_max_filesize directive in php.ini', 'database-import-pro');
            case UPLOAD_ERR_FORM_SIZE:
                return __('The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form', 'database-import-pro');
            case UPLOAD_ERR_PARTIAL:
                return __('The uploaded file was only partially uploaded', 'database-import-pro');
            case UPLOAD_ERR_NO_FILE:
                return __('No file was uploaded', 'database-import-pro');
            case UPLOAD_ERR_NO_TMP_DIR:
                return __('Missing a temporary folder', 'database-import-pro');
            case UPLOAD_ERR_CANT_WRITE:
                return __('Failed to write file to disk', 'database-import-pro');
            case UPLOAD_ERR_EXTENSION:
                return __('A PHP extension stopped the file upload', 'database-import-pro');
            default:
                return __('Unknown upload error', 'database-import-pro');
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
        // Verify file exists
        if (!file_exists($filepath)) {
            error_log('Database Import Pro Error: File does not exist: ' . $filepath);
            return new WP_Error('file_not_found', __('File does not exist', 'database-import-pro'));
        }

        // Verify file is readable
        if (!is_readable($filepath)) {
            error_log('Database Import Pro Error: File is not readable: ' . $filepath);
            return new WP_Error('file_not_readable', __('File is not readable. Check file permissions.', 'database-import-pro'));
        }

        // Check if file is empty
        $filesize = filesize($filepath);
        if ($filesize === false) {
            error_log('Database Import Pro Error: Cannot determine file size: ' . $filepath);
            return new WP_Error('file_error', __('Cannot determine file size', 'database-import-pro'));
        }

        if ($filesize === 0) {
            error_log('Database Import Pro Error: File is empty: ' . $filepath);
            return new WP_Error('file_empty', __('File is empty', 'database-import-pro'));
        }

        $handle = @fopen($filepath, 'r');
        if (!$handle) {
            $error = error_get_last();
            error_log('Database Import Pro Error: Could not open file: ' . $filepath . ' - ' . ($error['message'] ?? 'Unknown error'));
            return new WP_Error('file_error', __('Could not open file. ' . ($error['message'] ?? ''), 'database-import-pro'));
        }

        // Try to detect the delimiter
        $first_line = fgets($handle);
        if ($first_line === false) {
            fclose($handle);
            error_log('Database Import Pro Error: Could not read first line from file: ' . $filepath);
            return new WP_Error('file_error', __('Could not read first line from file', 'database-import-pro'));
        }

        rewind($handle);
        
        // Detect common delimiters - FIXED: Use actual tab character, not string '\t'
        $delimiters = array(',', ';', "\t", '|');
        $delimiter = ','; // default
        $max_count = 0;
        
        foreach ($delimiters as $d) {
            $count = count(str_getcsv($first_line, $d));
            if ($count > $max_count) {
                $max_count = $count;
                $delimiter = $d;
            }
        }

        // Detect encoding and convert to UTF-8 if necessary
        $encoding = mb_detect_encoding($first_line, array('UTF-8', 'ISO-8859-1', 'ASCII', 'Windows-1252'));
        if ($encoding && $encoding !== 'UTF-8') {
            $first_line = mb_convert_encoding($first_line, 'UTF-8', $encoding);
        }

        // Parse headers with detected delimiter
        $headers = str_getcsv($first_line, $delimiter);
        
        // Clean up headers
        $headers = array_map(function($header) {
            // Remove BOM if present
            $header = str_replace("\xEF\xBB\xBF", '', $header);
            // Trim whitespace and quotes
            $header = trim($header, " \t\n\r\0\x0B\"'");
            // Sanitize
            return sanitize_text_field($header);
        }, $headers);

        // Store total number of columns for validation
        dbip_set_import_data('total_columns', count($headers));
        
        error_log('Database Import Pro Importer: Detected ' . count($headers) . ' columns with delimiter "' . $delimiter . '"');
        error_log('Database Import Pro Importer: Headers: ' . print_r($headers, true));

        fclose($handle);
        return $headers;
    }

    /**
     * Store CSV headers in session
     * 
     * @return void
     */
    public function store_headers(): void {
        check_ajax_referer('dbip_importer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'database-import-pro'));
        }

        $headers = isset($_POST['headers']) ? json_decode(stripslashes($_POST['headers']), true) : array();
        
        if (empty($headers)) {
            wp_send_json_error(__('No headers provided', 'database-import-pro'));
        }

        // Store headers in transient
        dbip_set_import_data('headers', $headers);
        
        error_log('Database Import Pro Importer Debug: Stored headers in transient: ' . print_r($headers, true));
        
        wp_send_json_success();
    }

    /**
     * Clean up temporary files
     * 
     * @return void
     */
    public function cleanup(): void {
        $file_info = dbip_get_import_data('file');
        if ($file_info && isset($file_info['path'])) {
            @unlink($file_info['path']);
        }
    }
}
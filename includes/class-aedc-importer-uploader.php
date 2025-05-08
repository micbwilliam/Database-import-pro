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
            'application/excel',
            'application/vnd.ms-excel',
            'application/vnd.msexcel',
            'text/comma-separated-values',
            'text/x-comma-separated-values',
            'text/x-csv'
        ),
        'xls' => array(
            'application/vnd.ms-excel',
            'application/msexcel',
            'application/x-msexcel',
            'application/x-ms-excel',
            'application/x-excel',
            'application/x-dos_ms_excel',
            'application/xls',
            'application/x-xls'
        ),
        'xlsx' => array(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'application/xml',
            'application/zip',
            'application/vnd.ms-office'
        )
    );

    /**
     * Maximum file size in bytes (10MB)
     *
     * @var int
     */
    private $max_file_size = 10485760;

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
            
            // Check for PHP upload errors
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error_message = $this->get_upload_error_message($file['error']);
                throw new Exception($error_message);
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            // Validate file extension
            if (!array_key_exists($ext, $this->allowed_types)) {
                throw new Exception(__('Invalid file type. Please upload a CSV, XLS, or XLSX file.', 'aedc-importer'));
            }

            // Validate MIME type
            $file_mime = $this->get_mime_type($file['tmp_name']);
            error_log("File MIME type: " . $file_mime); // Debug log

            if (!in_array($file_mime, $this->allowed_types[$ext])) {
                // Double check for CSV files with generic MIME types
                if ($ext === 'csv' && $this->is_valid_csv($file['tmp_name'])) {
                    error_log("CSV validation passed despite MIME type mismatch"); // Debug log
                } else {
                    throw new Exception(sprintf(
                        __('Invalid file format (MIME type: %s). Please upload a valid spreadsheet file.', 'aedc-importer'),
                        $file_mime
                    ));
                }
            }

            // Validate file size
            if ($file['size'] > $this->max_file_size) {
                throw new Exception(sprintf(
                    __('File size (%s) exceeds limit of %s.', 'aedc-importer'),
                    size_format($file['size']),
                    size_format($this->max_file_size)
                ));
            }

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

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                throw new Exception(__('Failed to move uploaded file', 'aedc-importer'));
            }

            // Store file info in session
            $_SESSION['aedc_importer']['file'] = array(
                'name' => $file['name'],
                'path' => $filepath,
                'type' => $ext
            );

            // Get file headers
            $headers = $this->get_file_headers($filepath, $ext);
            if (is_wp_error($headers)) {
                throw new Exception($headers->get_error_message());
            }

            $_SESSION['aedc_importer']['headers'] = $headers;

            wp_send_json_success(array(
                'filename' => $file['name'],
                'size' => size_format($file['size']),
                'headers' => $headers
            ));

        } catch (Exception $e) {
            error_log("AEDC Importer Error: " . $e->getMessage()); // Debug log
            wp_send_json_error($e->getMessage());
        }
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
     * Get headers from uploaded file
     *
     * @param string $filepath File path
     * @param string $type File type
     * @return array|WP_Error Headers array or error
     */
    private function get_file_headers($filepath, $type) {
        if ($type === 'csv') {
            return $this->get_csv_headers($filepath);
        } else {
            return $this->get_excel_headers($filepath);
        }
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
     * Get headers from Excel file
     *
     * @param string $filepath File path
     * @return array|WP_Error Headers array or error
     */
    private function get_excel_headers($filepath) {
        if (!class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
            return new WP_Error('missing_dependency', __('PhpSpreadsheet library is required for Excel files', 'aedc-importer'));
        }

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filepath);
            $worksheet = $spreadsheet->getActiveSheet();
            $headers = array();

            foreach ($worksheet->getRowIterator(1, 1) as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $value = trim($cell->getValue());
                    if (!empty($value)) {
                        $headers[] = sanitize_text_field($value);
                    }
                }
            }

            return $headers;
        } catch (Exception $e) {
            return new WP_Error('excel_error', $e->getMessage());
        }
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
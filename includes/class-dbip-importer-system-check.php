<?php
/**
 * System capability checker
 *
 * Checks server capabilities for optional features like Excel support
 *
 * @since      1.1.0
 * @package    DBIP_Importer
 */

class DBIP_Importer_System_Check {
    /**
     * Check if PHPSpreadsheet library is available
     *
     * @return bool True if PHPSpreadsheet is available
     */
    public static function has_excel_support(): bool {
        // Check if PHPSpreadsheet is loaded via Composer
        if (class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory')) {
            return true;
        }

        // Try to load via plugin's vendor directory
        $vendor_autoload = DBIP_IMPORTER_PLUGIN_DIR . 'vendor/autoload.php';
        if (file_exists($vendor_autoload)) {
            require_once $vendor_autoload;
            return class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory');
        }

        return false;
    }

    /**
     * Check if required PHP extensions are available for Excel support
     *
     * @return array Array of extension statuses
     */
    public static function get_excel_requirements(): array {
        return array(
            'zip' => array(
                'name' => 'ZIP Extension',
                'status' => extension_loaded('zip'),
                'required' => true,
                'description' => __('Required for reading .xlsx files', 'database-import-pro')
            ),
            'xml' => array(
                'name' => 'XML Extension',
                'status' => extension_loaded('xml'),
                'required' => true,
                'description' => __('Required for parsing Excel files', 'database-import-pro')
            ),
            'xmlreader' => array(
                'name' => 'XMLReader Extension',
                'status' => extension_loaded('xmlreader'),
                'required' => true,
                'description' => __('Required for reading Excel files', 'database-import-pro')
            ),
            'gd' => array(
                'name' => 'GD Extension',
                'status' => extension_loaded('gd'),
                'required' => false,
                'description' => __('Optional: For image support in Excel files', 'database-import-pro')
            )
        );
    }

    /**
     * Check if all required extensions for Excel support are available
     *
     * @return bool True if all required extensions are available
     */
    public static function has_excel_extensions(): bool {
        $requirements = self::get_excel_requirements();
        
        foreach ($requirements as $requirement) {
            if ($requirement['required'] && !$requirement['status']) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get a list of supported file formats
     *
     * @return array Array of supported formats with labels
     */
    public static function get_supported_formats(): array {
        $formats = array(
            'csv' => array(
                'label' => 'CSV',
                'extension' => '.csv',
                'mime_types' => array(
                    'text/csv',
                    'text/plain',
                    'application/csv',
                    'text/comma-separated-values',
                    'text/x-comma-separated-values',
                    'text/x-csv'
                ),
                'available' => true,
                'description' => __('Comma-Separated Values', 'database-import-pro')
            )
        );

        // Add Excel formats if supported
        if (self::has_excel_support()) {
            $formats['xlsx'] = array(
                'label' => 'XLSX',
                'extension' => '.xlsx',
                'mime_types' => array(
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                ),
                'available' => true,
                'description' => __('Excel 2007+ format', 'database-import-pro')
            );

            $formats['xls'] = array(
                'label' => 'XLS',
                'extension' => '.xls',
                'mime_types' => array(
                    'application/vnd.ms-excel',
                    'application/msexcel',
                    'application/x-msexcel',
                    'application/x-ms-excel',
                    'application/x-excel',
                    'application/x-dos_ms_excel'
                ),
                'available' => true,
                'description' => __('Excel 97-2003 format', 'database-import-pro')
            );
        }

        return $formats;
    }

    /**
     * Get formatted list of supported file extensions
     *
     * @return string Comma-separated list of extensions
     */
    public static function get_supported_extensions_string(): string {
        $formats = self::get_supported_formats();
        $extensions = array();
        
        foreach ($formats as $format) {
            if ($format['available']) {
                $extensions[] = strtoupper($format['label']);
            }
        }
        
        return implode(', ', $extensions);
    }

    /**
     * Get HTML accept attribute for file input
     *
     * @return string Accept attribute value
     */
    public static function get_accept_attribute(): string {
        $formats = self::get_supported_formats();
        $extensions = array();
        
        foreach ($formats as $format) {
            if ($format['available']) {
                $extensions[] = $format['extension'];
            }
        }
        
        return implode(',', $extensions);
    }

    /**
     * Get system capability status message
     *
     * @return array Array with 'type' and 'message' keys
     */
    public static function get_capability_notice(): array {
        if (self::has_excel_support()) {
            return array(
                'type' => 'success',
                'message' => sprintf(
                    /* translators: %s: supported file extensions */
                    __('Excel support is <strong>enabled</strong>. You can import %s files.', 'database-import-pro'),
                    self::get_supported_extensions_string()
                )
            );
        }

        if (!self::has_excel_extensions()) {
            $missing = array();
            $requirements = self::get_excel_requirements();
            
            foreach ($requirements as $requirement) {
                if ($requirement['required'] && !$requirement['status']) {
                    $missing[] = $requirement['name'];
                }
            }
            
            return array(
                'type' => 'warning',
                'message' => sprintf(
                    /* translators: %s: missing PHP extensions */
                    __('Excel support is <strong>unavailable</strong>. Missing required PHP extensions: %s. Only CSV files are currently supported. Contact your hosting provider to enable these extensions.', 'database-import-pro'),
                    implode(', ', $missing)
                )
            );
        }

        return array(
            'type' => 'info',
            'message' => sprintf(
                /* translators: %s: URL to documentation */
                __('Excel support is <strong>not installed</strong>. Only CSV files are currently supported. To enable Excel (.xlsx, .xls) support, run <code>composer install</code> in the plugin directory. <a href="%s" target="_blank">Learn more</a>', 'database-import-pro'),
                'https://github.com/michaelbwilliam/database-import-pro#excel-support'
            )
        );
    }

    /**
     * Get system information for debugging
     *
     * @return array System information array
     */
    public static function get_system_info(): array {
        return array(
            'php_version' => phpversion(),
            'excel_support' => self::has_excel_support(),
            'excel_extensions' => self::has_excel_extensions(),
            'extensions' => self::get_excel_requirements(),
            'supported_formats' => array_keys(self::get_supported_formats()),
            'memory_limit' => ini_get('memory_limit'),
            'max_upload_size' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_execution_time' => ini_get('max_execution_time')
        );
    }
}

<?php
/**
 * System Status View
 *
 * Displays system capabilities and requirements
 *
 * @since      1.1.0
 * @package    DBIP_Importer
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Load system checker
require_once DBIP_IMPORTER_PLUGIN_DIR . 'includes/class-dbip-importer-system-check.php';

$notice = DBIP_Importer_System_Check::get_capability_notice();
$formats = DBIP_Importer_System_Check::get_supported_formats();
$requirements = DBIP_Importer_System_Check::get_excel_requirements();
$system_info = DBIP_Importer_System_Check::get_system_info();
$has_excel = DBIP_Importer_System_Check::has_excel_support();
?>

<div class="wrap">
    <h1><?php esc_html_e('System Status', 'database-import-pro'); ?></h1>
    
    <!-- Capability Notice -->
    <div class="notice notice-<?php echo esc_attr($notice['type']); ?>">
        <p><?php echo wp_kses_post($notice['message']); ?></p>
    </div>

    <!-- Supported File Formats -->
    <div class="card">
        <h2><?php esc_html_e('Supported File Formats', 'database-import-pro'); ?></h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Format', 'database-import-pro'); ?></th>
                    <th><?php esc_html_e('Extension', 'database-import-pro'); ?></th>
                    <th><?php esc_html_e('Description', 'database-import-pro'); ?></th>
                    <th><?php esc_html_e('Status', 'database-import-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($formats as $key => $format): ?>
                <tr>
                    <td><strong><?php echo esc_html($format['label']); ?></strong></td>
                    <td><code><?php echo esc_html($format['extension']); ?></code></td>
                    <td><?php echo esc_html($format['description']); ?></td>
                    <td>
                        <?php if ($format['available']): ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                            <?php esc_html_e('Available', 'database-import-pro'); ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span>
                            <?php esc_html_e('Unavailable', 'database-import-pro'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- PHP Extensions -->
    <div class="card" style="margin-top: 20px;">
        <h2><?php esc_html_e('PHP Extensions', 'database-import-pro'); ?></h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Extension', 'database-import-pro'); ?></th>
                    <th><?php esc_html_e('Description', 'database-import-pro'); ?></th>
                    <th><?php esc_html_e('Required', 'database-import-pro'); ?></th>
                    <th><?php esc_html_e('Status', 'database-import-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requirements as $key => $requirement): ?>
                <tr>
                    <td><strong><?php echo esc_html($requirement['name']); ?></strong></td>
                    <td><?php echo esc_html($requirement['description']); ?></td>
                    <td>
                        <?php if ($requirement['required']): ?>
                            <span class="dashicons dashicons-yes" style="color: #dc3232;"></span>
                            <?php esc_html_e('Required', 'database-import-pro'); ?>
                        <?php else: ?>
                            <?php esc_html_e('Optional', 'database-import-pro'); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($requirement['status']): ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                            <?php esc_html_e('Installed', 'database-import-pro'); ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span>
                            <?php esc_html_e('Not Installed', 'database-import-pro'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- System Information -->
    <div class="card" style="margin-top: 20px;">
        <h2><?php esc_html_e('System Information', 'database-import-pro'); ?></h2>
        <table class="widefat striped">
            <tbody>
                <tr>
                    <td><strong><?php esc_html_e('PHP Version', 'database-import-pro'); ?></strong></td>
                    <td><?php echo esc_html($system_info['php_version']); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Memory Limit', 'database-import-pro'); ?></strong></td>
                    <td><?php echo esc_html($system_info['memory_limit']); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Max Upload Size', 'database-import-pro'); ?></strong></td>
                    <td><?php echo esc_html($system_info['max_upload_size']); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Post Max Size', 'database-import-pro'); ?></strong></td>
                    <td><?php echo esc_html($system_info['post_max_size']); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Max Execution Time', 'database-import-pro'); ?></strong></td>
                    <td><?php echo esc_html($system_info['max_execution_time']); ?> <?php esc_html_e('seconds', 'database-import-pro'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Excel Support', 'database-import-pro'); ?></strong></td>
                    <td>
                        <?php if ($system_info['excel_support']): ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                            <?php esc_html_e('Enabled', 'database-import-pro'); ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span>
                            <?php esc_html_e('Disabled', 'database-import-pro'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- How to Enable Excel Support -->
    <?php if (!$has_excel): ?>
    <div class="card" style="margin-top: 20px;">
        <h2><?php esc_html_e('How to Enable Excel Support', 'database-import-pro'); ?></h2>
        
        <?php if (!DBIP_Importer_System_Check::has_excel_extensions()): ?>
            <div class="notice notice-warning inline">
                <p>
                    <strong><?php esc_html_e('Missing Required PHP Extensions', 'database-import-pro'); ?></strong><br>
                    <?php esc_html_e('Contact your hosting provider to enable the missing PHP extensions listed above.', 'database-import-pro'); ?>
                </p>
            </div>
        <?php else: ?>
            <p><?php esc_html_e('Your server meets all requirements. Follow these steps to enable Excel support:', 'database-import-pro'); ?></p>
            
            <h3><?php esc_html_e('Option 1: Using Composer (Recommended)', 'database-import-pro'); ?></h3>
            <ol>
                <li><?php esc_html_e('Connect to your server via SSH or FTP', 'database-import-pro'); ?></li>
                <li>
                    <?php esc_html_e('Navigate to the plugin directory:', 'database-import-pro'); ?>
                    <br><code><?php echo esc_html(DBIP_IMPORTER_PLUGIN_DIR); ?></code>
                </li>
                <li>
                    <?php esc_html_e('Run the following command:', 'database-import-pro'); ?>
                    <br><code>composer install --no-dev</code>
                </li>
                <li><?php esc_html_e('Refresh this page to verify Excel support is enabled', 'database-import-pro'); ?></li>
            </ol>

            <h3><?php esc_html_e('Option 2: Manual Installation', 'database-import-pro'); ?></h3>
            <ol>
                <li><?php esc_html_e('Download PHPSpreadsheet from:', 'database-import-pro'); ?>
                    <br><a href="https://github.com/PHPOffice/PhpSpreadsheet" target="_blank">https://github.com/PHPOffice/PhpSpreadsheet</a>
                </li>
                <li><?php esc_html_e('Extract the files to the plugin vendor directory', 'database-import-pro'); ?></li>
                <li><?php esc_html_e('Ensure proper autoloading is configured', 'database-import-pro'); ?></li>
            </ol>

            <h3><?php esc_html_e('Need Help?', 'database-import-pro'); ?></h3>
            <p>
                <?php
                // Format the documentation link safely. Allow only <a> with href and target attributes.
                $doc_html = sprintf(
                    /* translators: %s: URL to documentation */
                    __('See the <a href="%s" target="_blank">Developer Documentation</a> for detailed instructions.', 'database-import-pro'),
                    esc_url('https://github.com/michaelbwilliam/database-import-pro#excel-support')
                );

                echo wp_kses(
                    $doc_html,
                    array(
                        'a' => array(
                            'href' => array(),
                            'target' => array()
                        ),
                    )
                );
                ?>
            </p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Debug Information -->
    <div class="card" style="margin-top: 20px;">
        <h2><?php esc_html_e('Debug Information', 'database-import-pro'); ?></h2>
        <p><?php esc_html_e('Copy and paste this information when reporting issues:', 'database-import-pro'); ?></p>
        <textarea readonly style="width: 100%; height: 200px; font-family: monospace; font-size: 12px;"><?php
            echo esc_textarea(json_encode($system_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        ?></textarea>
    </div>
</div>

<style>
.card {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    padding: 20px;
}

.card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.card h3 {
    margin-top: 20px;
}

.card code {
    background: #f0f0f0;
    padding: 2px 6px;
    border-radius: 3px;
}

.card ol {
    margin-left: 20px;
}

.card ol li {
    margin-bottom: 10px;
}
</style>

<?php
/**
 * Step 6: Completion & Logs Template
 *
 * @since      1.0.0
 * @package    dbip_Importer
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

$import_stats = dbip_get_import_data('import_stats') ?: array(
    'processed' => 0,
    'inserted' => 0,
    'updated' => 0,
    'skipped' => 0,
    'failed' => 0,
    'total_rows' => 0,
    'duration' => 0
);

$duration = isset($import_stats['duration']) ? $import_stats['duration'] : 0;
$has_errors = isset($import_stats['failed']) && $import_stats['failed'] > 0;
?>

<div class="dbip-step-content step-completion">
    <h2><?php esc_html_e('Import Completed', 'database-import-pro'); ?></h2>
    
    <div class="completion-container">
        <div class="completion-summary">
            <div class="summary-header">
                <span class="dashicons dashicons-yes-alt"></span>
                <h3><?php esc_html_e('Import Summary', 'database-import-pro'); ?></h3>
            </div>

            <div class="summary-stats">
                <div class="stat-box total">
                    <span class="stat-number"><?php echo esc_html($import_stats['processed']); ?></span>
                    <span class="stat-label"><?php esc_html_e('Total Records', 'database-import-pro'); ?></span>
                </div>
                <div class="stat-box success">
                    <span class="stat-number"><?php echo esc_html($import_stats['inserted'] + $import_stats['updated']); ?></span>
                    <span class="stat-label"><?php esc_html_e('Successfully Imported', 'database-import-pro'); ?></span>
                </div>
                <div class="stat-box error">
                    <span class="stat-number"><?php echo esc_html($import_stats['failed']); ?></span>
                    <span class="stat-label"><?php esc_html_e('Failed', 'database-import-pro'); ?></span>
                </div>
                <div class="stat-box warning">
                    <span class="stat-number"><?php echo esc_html($import_stats['skipped']); ?></span>
                    <span class="stat-label"><?php esc_html_e('Skipped', 'database-import-pro'); ?></span>
                </div>
                <?php
                // Calculate success rate
                $success_rate = 0;
                if ($import_stats['processed'] > 0) {
                    $successful_records = $import_stats['inserted'] + $import_stats['updated'];
                    $attempted_records = $import_stats['processed'] - $import_stats['skipped'];
                    $success_rate = ($attempted_records > 0) ? round(($successful_records / $attempted_records) * 100, 1) : 100;
                }
                ?>
                <div class="stat-box rate">
                    <span class="stat-number"><?php echo esc_html($success_rate); ?>%</span>
                    <span class="stat-label"><?php esc_html_e('Success Rate', 'database-import-pro'); ?></span>
                </div>
            </div>

            <div class="summary-details">
                <p>
                    <strong><?php esc_html_e('Import Duration:', 'database-import-pro'); ?></strong>
                    <?php echo esc_html(human_time_diff(0, $duration)); ?>
                </p>
                <p>
                    <strong><?php esc_html_e('Target Table:', 'database-import-pro'); ?></strong>
                    <?php echo esc_html(dbip_get_import_data('target_table')); ?>
                </p>
            </div>
        </div>

        <?php if ($has_errors && dbip_get_import_data('error_log')) : ?>
            <div class="completion-errors">
                <h3>
                    <span class="dashicons dashicons-warning"></span>
                    <?php esc_html_e('Error Log', 'database-import-pro'); ?>
                </h3>
                
                <div class="error-log">
                    <table class="error-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Row', 'database-import-pro'); ?></th>
                                <th><?php esc_html_e('Error Message', 'database-import-pro'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $error_log = dbip_get_import_data('error_log') ?: array();
                            foreach ($error_log as $error) : 
                            ?>
                                <tr>
                                    <td><?php echo esc_html($error['row']); ?></td>
                                    <td><?php echo esc_html($error['message']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="error-actions">
                    <button type="button" class="button button-secondary" id="download-error-log">
                        <?php esc_html_e('Download Error Log', 'database-import-pro'); ?>
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <div class="completion-actions">
            <a href="<?php echo esc_url(remove_query_arg('step')); ?>" class="button button-primary">
                <?php esc_html_e('Start New Import', 'database-import-pro'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=database-import-pro-logs')); ?>" class="button button-secondary">
                <?php esc_html_e('View All Logs', 'database-import-pro'); ?>
            </a>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#download-error-log').on('click', function() {
        $.post(dbipImporter.ajax_url, {
            action: 'dbip_download_error_log',
            nonce: '<?php echo wp_create_nonce('dbip_importer_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                // Create and download the file
                const blob = new Blob([response.data], { type: 'text/csv' });
                const link = document.createElement('a');
                link.href = window.URL.createObjectURL(blob);
                link.download = 'import-error-log.csv';
                link.click();
            } else {
                alert(response.data || '<?php esc_html_e('Failed to download error log.', 'database-import-pro'); ?>');
            }
        });
    });
});
</script>

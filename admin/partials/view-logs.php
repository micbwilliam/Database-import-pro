<?php
/**
 * Import Logs View Template
 *
 * @since      1.0.0
 * @package    dbip_Importer
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?> - <?php esc_html_e('Import Logs', 'database-import-pro'); ?></h1>
    
    <div class="import-logs-container">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Date/Time', 'database-import-pro'); ?></th>
                    <th><?php esc_html_e('User', 'database-import-pro'); ?></th>
                    <th><?php esc_html_e('File Name', 'database-import-pro'); ?></th>
                    <th><?php esc_html_e('Table', 'database-import-pro'); ?></th>
                    <th><?php esc_html_e('Total Rows', 'database-import-pro'); ?></th>
                    <th><?php esc_html_e('Status', 'database-import-pro'); ?></th>
                    <th><?php esc_html_e('Success Rate', 'database-import-pro'); ?></th>
                    <th><?php esc_html_e('Duration', 'database-import-pro'); ?></th>
                    <th><?php esc_html_e('Actions', 'database-import-pro'); ?></th>
                </tr>
            </thead>
            <tbody id="import-logs-list">
                <tr>
                    <td colspan="9"><?php esc_html_e('Loading...', 'database-import-pro'); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Load import logs
    function loadImportLogs() {
        $.post(dbipImporter.ajax_url, {
            action: 'dbip_get_import_logs',
            nonce: '<?php echo esc_attr(wp_create_nonce('dbip_importer_nonce')); ?>'
        }, function(response) {
            if (response.success && response.data) {
                const tbody = $('#import-logs-list');
                tbody.empty();
                
                response.data.forEach(function(log) {
                    const successRate = calculateSuccessRate(log);
                    const status = getStatusBadge(log.status);
                    const actions = getActionButtons(log);
                    
                    tbody.append(`
                        <tr>
                            <td>${formatDate(log.import_date)}</td>
                            <td>${log.user}</td>
                            <td>${log.file_name}</td>
                            <td>${log.table_name}</td>
                            <td>${log.total_rows}</td>
                            <td>${status}</td>
                            <td>${successRate}%</td>
                            <td>${formatDuration(log.duration)}</td>
                            <td>${actions}</td>
                        </tr>
                    `);
                });
            } else {
                $('#import-logs-list').html(
                    '<tr><td colspan="9"><?php esc_html_e('No import logs found.', 'database-import-pro'); ?></td></tr>'
                );
            }
        }).fail(function() {
            $('#import-logs-list').html(
                '<tr><td colspan="9"><?php esc_html_e('Error loading import logs.', 'database-import-pro'); ?></td></tr>'
            );
        });
    }

    function calculateSuccessRate(log) {
        const successful = parseInt(log.inserted) + parseInt(log.updated);
        const total = parseInt(log.total_rows);
        return total > 0 ? Math.round((successful / total) * 100) : 0;
    }

    function getStatusBadge(status) {
        const badges = {
            'completed': '<span class="status-badge success">Completed</span>',
            'completed_with_errors': '<span class="status-badge warning">Completed with Errors</span>',
            'failed': '<span class="status-badge error">Failed</span>'
        };
        return badges[status] || status;
    }

    function getActionButtons(log) {
        let buttons = `
            <button type="button" class="button button-small view-details" data-id="${log.id}">
                <?php esc_html_e('View Details', 'database-import-pro'); ?>
            </button>
        `;
        
        if (log.failed > 0) {
            buttons += `
                <button type="button" class="button button-small export-errors" data-id="${log.id}">
                    <?php esc_html_e('Export Errors', 'database-import-pro'); ?>
                </button>
            `;
        }
        
        return buttons;
    }

    function formatDate(dateString) {
        return new Date(dateString).toLocaleString();
    }

    function formatDuration(seconds) {
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;
        return `${minutes}m ${remainingSeconds}s`;
    }

    // Handle error export
    $(document).on('click', '.export-errors', function() {
        const logId = $(this).data('id');
        
        $.post(dbipImporter.ajax_url, {
            action: 'dbip_export_error_log',
            nonce: '<?php echo esc_attr(wp_create_nonce('dbip_importer_nonce')); ?>',
            log_id: logId
        }, function(response) {
            if (response.success && response.data) {
                // Create and download CSV file
                const blob = new Blob([response.data], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'import-errors.csv';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            } else {
                alert('<?php esc_html_e('Failed to export error log.', 'database-import-pro'); ?>');
            }
        }).fail(function() {
            alert('<?php esc_html_e('Error exporting log file.', 'database-import-pro'); ?>');
        });
    });

    // Handle view details
    $(document).on('click', '.view-details', function() {
        const logId = $(this).data('id');
        // TODO: Implement details view in modal
    });

    // Load logs on page load
    loadImportLogs();
});
</script>

<style>
.import-logs-container {
    margin-top: 20px;
}

.status-badge {
    padding: 5px 10px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
}

.status-badge.success {
    background: #46b450;
    color: white;
}

.status-badge.warning {
    background: #ffb900;
    color: #32373c;
}

.status-badge.error {
    background: #dc3232;
    color: white;
}

.button.button-small {
    margin: 0 5px;
}

.export-errors {
    color: #dc3232;
    border-color: #dc3232;
}

.export-errors:hover {
    background: #dc3232;
    color: white;
}
</style>

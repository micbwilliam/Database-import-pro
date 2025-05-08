<?php
/**
 * Step 5: Import Progress Template
 *
 * @since      1.0.0
 * @package    AEDC_Importer
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

$total_records = isset($_SESSION['aedc_importer']['total_records']) ? $_SESSION['aedc_importer']['total_records'] : 0;
?>

<div class="aedc-step-content step-import">
    <h2><?php esc_html_e('Import Progress', 'aedc-importer'); ?></h2>
    
    <div class="import-container">
        <div class="import-progress">
            <div class="progress-bar">
                <div class="progress-bar-fill" style="width: 0%;">
                    <span class="progress-text">0%</span>
                </div>
            </div>
            
            <div class="progress-stats">
                <div class="stat-item">
                    <span class="stat-label"><?php esc_html_e('Processed:', 'aedc-importer'); ?></span>
                    <span class="stat-value" id="processed-count">0</span>
                    <span class="stat-total">/ <?php echo esc_html($total_records); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label"><?php esc_html_e('Success:', 'aedc-importer'); ?></span>
                    <span class="stat-value success" id="success-count">0</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label"><?php esc_html_e('Failed:', 'aedc-importer'); ?></span>
                    <span class="stat-value error" id="error-count">0</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label"><?php esc_html_e('Skipped:', 'aedc-importer'); ?></span>
                    <span class="stat-value warning" id="skipped-count">0</span>
                </div>
            </div>
        </div>

        <div class="import-log">
            <h3><?php esc_html_e('Import Log', 'aedc-importer'); ?></h3>
            <div class="log-container" id="import-log">
                <div class="log-entry info">
                    <?php esc_html_e('Import process started...', 'aedc-importer'); ?>
                </div>
            </div>
        </div>

        <div class="import-actions">
            <button type="button" class="button button-secondary" id="pause-import" style="display: none;">
                <?php esc_html_e('Pause Import', 'aedc-importer'); ?>
            </button>
            <button type="button" class="button button-secondary" id="resume-import" style="display: none;">
                <?php esc_html_e('Resume Import', 'aedc-importer'); ?>
            </button>
            <button type="button" class="button button-secondary" id="cancel-import">
                <?php esc_html_e('Cancel Import', 'aedc-importer'); ?>
            </button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    let importPaused = false;
    let importCancelled = false;
    let currentBatch = 0;
    const batchSize = 100;
    const totalRecords = <?php echo esc_js($total_records); ?>;

    function updateProgress(processed, success, errors, skipped) {
        const percentage = Math.round((processed / totalRecords) * 100);
        
        $('.progress-bar-fill').css('width', percentage + '%');
        $('.progress-text').text(percentage + '%');
        
        $('#processed-count').text(processed);
        $('#success-count').text(success);
        $('#error-count').text(errors);
        $('#skipped-count').text(skipped);
    }

    function addLogEntry(message, type = 'info') {
        const timestamp = new Date().toLocaleTimeString();
        const entry = $('<div class="log-entry ' + type + '">[' + timestamp + '] ' + message + '</div>');
        $('#import-log').prepend(entry);
    }

    function processBatch() {
        if (importPaused || importCancelled) {
            return;
        }

        $.post(ajaxurl, {
            action: 'aedc_process_import_batch',
            batch: currentBatch,
            batch_size: batchSize,
            nonce: '<?php echo wp_create_nonce('aedc_importer_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                updateProgress(
                    response.data.processed,
                    response.data.success,
                    response.data.errors,
                    response.data.skipped
                );

                if (response.data.messages) {
                    response.data.messages.forEach(function(msg) {
                        addLogEntry(msg.message, msg.type);
                    });
                }

                if (response.data.completed) {
                    importCompleted();
                } else {
                    currentBatch++;
                    processBatch();
                }
            } else {
                addLogEntry(response.data, 'error');
                importFailed();
            }
        }).fail(function() {
            addLogEntry('<?php esc_html_e('Network error occurred. Please try again.', 'aedc-importer'); ?>', 'error');
            importFailed();
        });
    }

    function importCompleted() {
        addLogEntry('<?php esc_html_e('Import completed successfully!', 'aedc-importer'); ?>', 'success');
        $('.import-actions').html(
            '<a href="<?php echo esc_url(add_query_arg('step', '6')); ?>" class="button button-primary"><?php esc_html_e('View Results', 'aedc-importer'); ?></a>'
        );
    }

    function importFailed() {
        $('#pause-import, #resume-import').hide();
        $('#cancel-import').text('<?php esc_html_e('Close', 'aedc-importer'); ?>');
    }

    // Start the import process
    processBatch();

    // Handle pause/resume
    $('#pause-import').on('click', function() {
        importPaused = true;
        $(this).hide();
        $('#resume-import').show();
        addLogEntry('<?php esc_html_e('Import paused by user', 'aedc-importer'); ?>', 'warning');
    });

    $('#resume-import').on('click', function() {
        importPaused = false;
        $(this).hide();
        $('#pause-import').show();
        addLogEntry('<?php esc_html_e('Import resumed by user', 'aedc-importer'); ?>', 'info');
        processBatch();
    });

    // Handle cancel
    $('#cancel-import').on('click', function() {
        if (confirm('<?php esc_html_e('Are you sure you want to cancel the import?', 'aedc-importer'); ?>')) {
            importCancelled = true;
            addLogEntry('<?php esc_html_e('Import cancelled by user', 'aedc-importer'); ?>', 'error');
            
            $.post(ajaxurl, {
                action: 'aedc_cancel_import',
                nonce: '<?php echo wp_create_nonce('aedc_importer_nonce'); ?>'
            }, function() {
                window.location.href = '<?php echo esc_url(add_query_arg('step', '4')); ?>';
            });
        }
    });
});
</script> 
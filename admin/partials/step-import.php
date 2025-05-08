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

// Debug session data
error_log('AEDC Importer Debug - Session data at import start: ' . print_r($_SESSION['aedc_importer'], true));

$total_records = isset($_SESSION['aedc_importer']['total_records']) ? $_SESSION['aedc_importer']['total_records'] : 0;
$import_mode = isset($_SESSION['aedc_importer']['import_mode']) ? $_SESSION['aedc_importer']['import_mode'] : 'insert';

// Verify required session data
if (!isset($_SESSION['aedc_importer']['file']) || 
    !isset($_SESSION['aedc_importer']['mapping']) || 
    !isset($_SESSION['aedc_importer']['target_table'])) {
    wp_die(__('Missing required import data. Please go back and complete all previous steps.', 'aedc-importer'));
}

// Verify file exists
if (!file_exists($_SESSION['aedc_importer']['file']['path'])) {
    wp_die(__('Import file not found. Please restart the import process.', 'aedc-importer'));
}
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
                    <span class="stat-label"><?php esc_html_e('Inserted:', 'aedc-importer'); ?></span>
                    <span class="stat-value success" id="inserted-count">0</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label"><?php esc_html_e('Updated:', 'aedc-importer'); ?></span>
                    <span class="stat-value info" id="updated-count">0</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label"><?php esc_html_e('Skipped:', 'aedc-importer'); ?></span>
                    <span class="stat-value warning" id="skipped-count">0</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label"><?php esc_html_e('Failed:', 'aedc-importer'); ?></span>
                    <span class="stat-value error" id="failed-count">0</span>
                </div>
            </div>
        </div>

        <div class="import-details">
            <div class="detail-item">
                <span class="detail-label"><?php esc_html_e('Import Mode:', 'aedc-importer'); ?></span>
                <span class="detail-value"><?php echo esc_html(ucfirst($import_mode)); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label"><?php esc_html_e('Batch Size:', 'aedc-importer'); ?></span>
                <span class="detail-value">100</span>
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

<?php
// Initialize variables for JavaScript
$ajaxurl = admin_url('admin-ajax.php');
$nonce = wp_create_nonce('aedc_importer_nonce');
?>

<script>
jQuery(document).ready(function($) {
    // Initialize import process
    let importPaused = false;
    let importCancelled = false;
    let currentBatch = 0;
    const batchSize = 100;
    const totalRecords = <?php echo esc_js($total_records); ?>;
    let totalStats = {
        processed: 0,
        inserted: 0,
        updated: 0,
        skipped: 0,
        failed: 0
    };

    console.log('AEDC Importer: Starting import process');
    console.log('Total records:', totalRecords);

    function processBatch() {
        if (importPaused || importCancelled) {
            return;
        }

        console.log('AEDC Importer: Processing batch', currentBatch);

        $.ajax({
            url: '<?php echo $ajaxurl; ?>',
            type: 'POST',
            data: {
                action: 'aedc_process_import_batch',
                batch: currentBatch,
                nonce: '<?php echo $nonce; ?>'
            },
            success: function(response) {
                console.log('AEDC Importer: Batch response', response);
                
                if (response.success) {
                    updateProgress(response.data);

                    if (response.data.messages) {
                        response.data.messages.forEach(function(msg) {
                            addLogEntry(msg.message, msg.type);
                        });
                    }

                    if (response.data.completed) {
                        importCompleted();
                    } else {
                        currentBatch++;
                        setTimeout(processBatch, 100); // Add small delay between batches
                    }
                } else {
                    console.error('AEDC Importer: Batch failed', response);
                    addLogEntry(response.data || 'Import failed. Please try again.', 'error');
                    importFailed();
                }
            },
            error: function(xhr, status, error) {
                console.error('AEDC Importer: AJAX error', {xhr, status, error});
                addLogEntry('Network error occurred. Please try again.', 'error');
                importFailed();
            }
        });
    }

    function updateProgress(stats) {
        const percentage = Math.round((stats.processed / totalRecords) * 100);
        
        $('.progress-bar-fill').css('width', percentage + '%');
        $('.progress-text').text(percentage + '%');
        
        totalStats.processed += stats.processed;
        totalStats.inserted += stats.inserted;
        totalStats.updated += stats.updated;
        totalStats.skipped += stats.skipped;
        totalStats.failed += stats.failed;
        
        $('#processed-count').text(totalStats.processed);
        $('#inserted-count').text(totalStats.inserted);
        $('#updated-count').text(totalStats.updated);
        $('#skipped-count').text(totalStats.skipped);
        $('#failed-count').text(totalStats.failed);
    }

    function addLogEntry(message, type = 'info') {
        const timestamp = new Date().toLocaleTimeString();
        const entry = $('<div class="log-entry ' + type + '">[' + timestamp + '] ' + message + '</div>');
        $('#import-log').prepend(entry);
    }

    function importCompleted() {
        addLogEntry('<?php esc_html_e('Import completed successfully!', 'aedc-importer'); ?>', 'success');
        $('.import-actions').html(
            '<a href="<?php echo esc_url(add_query_arg('step', '6')); ?>" class="button button-primary">' +
            '<?php esc_html_e('View Results', 'aedc-importer'); ?></a>'
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

<style>
.import-progress {
    margin-bottom: 30px;
}

.progress-bar {
    height: 20px;
    background: #f1f1f1;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 20px;
    position: relative;
}

.progress-bar-fill {
    height: 100%;
    background: #2271b1;
    transition: width 0.3s ease;
    position: relative;
}

.progress-text {
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    color: #fff;
    font-size: 12px;
    font-weight: bold;
    text-shadow: 0 1px 1px rgba(0,0,0,0.2);
}

.progress-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.stat-item {
    background: #fff;
    padding: 15px;
    border-radius: 4px;
    border: 1px solid #e5e5e5;
}

.stat-label {
    display: block;
    color: #666;
    margin-bottom: 5px;
}

.stat-value {
    font-size: 24px;
    font-weight: bold;
}

.stat-value.success { color: #46b450; }
.stat-value.info { color: #2271b1; }
.stat-value.warning { color: #ffb900; }
.stat-value.error { color: #dc3232; }

.stat-total {
    color: #666;
    font-size: 14px;
}

.import-details {
    background: #fff;
    padding: 20px;
    border-radius: 4px;
    border: 1px solid #e5e5e5;
    margin-bottom: 30px;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.detail-item:last-child {
    margin-bottom: 0;
}

.detail-label {
    color: #666;
}

.import-log {
    background: #fff;
    padding: 20px;
    border-radius: 4px;
    border: 1px solid #e5e5e5;
    margin-bottom: 30px;
}

.log-container {
    max-height: 300px;
    overflow-y: auto;
    padding: 10px;
    background: #f9f9f9;
    border-radius: 4px;
}

.log-entry {
    padding: 8px;
    border-bottom: 1px solid #f0f0f0;
    font-family: monospace;
}

.log-entry:last-child {
    border-bottom: none;
}

.log-entry.info { color: #2271b1; }
.log-entry.success { color: #46b450; }
.log-entry.warning { color: #ffb900; }
.log-entry.error { color: #dc3232; }

.import-actions {
    text-align: right;
}

.import-actions .button {
    margin-left: 10px;
}
</style>
<?php
/**
 * Provide a admin area view for the plugin
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
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="database-import-pro-wizard">
        <!-- Progress Bar -->
        <div class="dbip-wizard-steps">
            <?php
            $steps = array(
                1 => 'Upload CSV',
                2 => 'Select DB Table',
                3 => 'Map Fields',
                4 => 'Preview & Confirm',
                5 => 'Import & Progress',
                6 => 'Completion & Logs'
            );

            foreach ($steps as $step => $label) {
                $active_class = ($step === $this->current_step) ? 'active' : '';
                $completed_class = ($step < $this->current_step) ? 'completed' : '';
                ?>
                <div class="step <?php echo esc_attr($active_class . ' ' . $completed_class); ?>">
                    <div class="step-number"><?php echo esc_html($step); ?></div>
                    <div class="step-label"><?php echo esc_html($label); ?></div>
                </div>
                <?php
            }
            ?>
        </div>

        <!-- Step Content -->
        <div class="dbip-wizard-content">
            <?php
            switch ($this->current_step) {
                case 1:
                    include_once DBIP_IMPORTER_PLUGIN_DIR . 'admin/partials/step-upload.php';
                    break;
                case 2:
                    include_once DBIP_IMPORTER_PLUGIN_DIR . 'admin/partials/step-select-table.php';
                    break;
                case 3:
                    include_once DBIP_IMPORTER_PLUGIN_DIR . 'admin/partials/step-map-fields.php';
                    break;
                case 4:
                    include_once DBIP_IMPORTER_PLUGIN_DIR . 'admin/partials/step-preview.php';
                    break;
                case 5:
                    include_once DBIP_IMPORTER_PLUGIN_DIR . 'admin/partials/step-import.php';
                    break;
                case 6:
                    include_once DBIP_IMPORTER_PLUGIN_DIR . 'admin/partials/step-completion.php';
                    break;
            }
            ?>
        </div>

        <!-- Navigation Buttons -->
        <div class="dbip-wizard-navigation">
            <?php if ($this->current_step > 1) : ?>
                <a href="<?php echo esc_url(add_query_arg('step', $this->current_step - 1)); ?>" class="button button-secondary"><?php esc_html_e('Previous', 'database-import-pro'); ?></a>
            <?php endif; ?>

            <?php if ($this->current_step < 6) : ?>
                <a href="<?php echo esc_url(add_query_arg('step', $this->current_step + 1)); ?>" class="button button-primary"><?php esc_html_e('Next', 'database-import-pro'); ?></a>
            <?php endif; ?>
        </div>
    </div>
</div> 
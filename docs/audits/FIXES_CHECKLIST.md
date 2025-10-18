# Database Import Pro - Critical Fixes Checklist

**Priority Order:** Complete in this exact sequence for fastest recovery

---

## 🔴 Phase 1: Data Access Layer (CRITICAL - 2 hours)

### Fix 1.1: Convert Session to Transient in step-map-fields.php
**File:** `admin/partials/step-map-fields.php`

**Lines to change:**

**Line 18-19:**
```php
// BEFORE:
$table = isset($_SESSION['dbip_importer']['target_table']) ? $_SESSION['dbip_importer']['target_table'] : '';

// AFTER:
$table = dbip_get_import_data('target_table') ?: '';
```

**Line 24:**
```php
// BEFORE:
$csv_headers = isset($_SESSION['dbip_importer']['headers']) ? $_SESSION['dbip_importer']['headers'] : array();

// AFTER:
$csv_headers = dbip_get_import_data('headers') ?: array();
```

- [ ] Line 18-19 fixed
- [ ] Line 24 fixed
- [ ] File tested

---

### Fix 1.2: Convert Session to Transient in step-preview.php
**File:** `admin/partials/step-preview.php`

**Lines to change:**

**Line 18-19:**
```php
// BEFORE:
$mapping = isset($_SESSION['dbip_importer']['mapping']) ? $_SESSION['dbip_importer']['mapping'] : array();
$preview_data = isset($_SESSION['dbip_importer']['preview_data']) ? $_SESSION['dbip_importer']['preview_data'] : array();

// AFTER:
$mapping = dbip_get_import_data('mapping') ?: array();
$preview_data = dbip_get_import_data('preview_data') ?: array();
```

**Line 34:**
```php
// BEFORE:
<?php echo esc_html($_SESSION['dbip_importer']['target_table']); ?>

// AFTER:
<?php echo esc_html(dbip_get_import_data('target_table')); ?>
```

**Line 37:**
```php
// BEFORE:
<?php echo esc_html(isset($_SESSION['dbip_importer']['total_records']) ? $_SESSION['dbip_importer']['total_records'] : 0); ?>

// AFTER:
<?php echo esc_html(dbip_get_import_data('total_records') ?: 0); ?>
```

**Line 43:**
```php
// BEFORE (inside loop):
// Count from mapping using $_SESSION

// AFTER:
<?php 
$mapping_data = dbip_get_import_data('mapping') ?: array();
echo esc_html(count(array_filter($mapping_data, function($m) { return !empty($m['csv_field']); })));
?>
```

**Line 62:**
```php
// BEFORE:
$table = $_SESSION['dbip_importer']['target_table'];

// AFTER:
$table = dbip_get_import_data('target_table');
```

- [ ] Line 18-19 fixed
- [ ] Line 34 fixed
- [ ] Line 37 fixed
- [ ] Line 43 fixed
- [ ] Line 62 fixed
- [ ] File tested

---

### Fix 1.3: Convert Session to Transient in step-import.php
**File:** `admin/partials/step-import.php`

**Lines to change:**

**Line 14-16:**
```php
// BEFORE:
error_log('Database Import Pro Debug - Session data at import start: ' . print_r($_SESSION['dbip_importer'], true));

// AFTER:
error_log('Database Import Pro Debug - Import data at start: ' . print_r(dbip_get_import_data(), true));
```

**Line 18-19:**
```php
// BEFORE:
$total_records = isset($_SESSION['dbip_importer']['total_records']) ? $_SESSION['dbip_importer']['total_records'] : 0;
$import_mode = isset($_SESSION['dbip_importer']['import_mode']) ? $_SESSION['dbip_importer']['import_mode'] : 'insert';

// AFTER:
$total_records = dbip_get_import_data('total_records') ?: 0;
$import_mode = dbip_get_import_data('import_mode') ?: 'insert';
```

**Line 22-24:**
```php
// BEFORE:
if (!isset($_SESSION['dbip_importer']['file']) || 
    !isset($_SESSION['dbip_importer']['mapping']) || 
    !isset($_SESSION['dbip_importer']['target_table'])) {

// AFTER:
if (!dbip_get_import_data('file') || 
    !dbip_get_import_data('mapping') || 
    !dbip_get_import_data('target_table')) {
```

**Line 29:**
```php
// BEFORE:
if (!file_exists($_SESSION['dbip_importer']['file']['path'])) {

// AFTER:
$file_info = dbip_get_import_data('file');
if (!$file_info || !file_exists($file_info['path'])) {
```

**Line 42-44:**
```php
// BEFORE:
<?php
// Initialize variables for JavaScript
$dbipImporter.ajax_url = admin_url('admin-ajax.php');
$nonce = wp_create_nonce('dbip_importer_nonce');
?>

// AFTER:
<?php
// JavaScript localization is handled by wp_localize_script in main plugin file
$nonce = wp_create_nonce('dbip_importer_nonce');
?>
```

- [ ] Line 14-16 fixed
- [ ] Line 18-19 fixed
- [ ] Line 22-24 fixed
- [ ] Line 29 fixed
- [ ] Line 42-44 fixed
- [ ] File tested

---

### Fix 1.4: Convert Session to Transient in step-completion.php
**File:** `admin/partials/step-completion.php`

**Lines to change:**

**Line 13-22:**
```php
// BEFORE:
$import_stats = isset($_SESSION['dbip_importer']['import_stats']) ? $_SESSION['dbip_importer']['import_stats'] : array(
    'processed' => 0,
    'inserted' => 0,
    'updated' => 0,
    'skipped' => 0,
    'failed' => 0,
    'total_rows' => 0,
    'duration' => 0
);

// AFTER:
$import_stats = dbip_get_import_data('import_stats') ?: array(
    'processed' => 0,
    'inserted' => 0,
    'updated' => 0,
    'skipped' => 0,
    'failed' => 0,
    'total_rows' => 0,
    'duration' => 0
);
```

**Line 61:**
```php
// BEFORE:
<?php echo esc_html($_SESSION['dbip_importer']['target_table']); ?>

// AFTER:
<?php echo esc_html(dbip_get_import_data('target_table')); ?>
```

**Line 68:**
```php
// BEFORE:
<?php if ($has_errors && isset($_SESSION['dbip_importer']['error_log'])) : ?>

// AFTER:
<?php if ($has_errors && dbip_get_import_data('error_log')) : ?>
```

**Line 79:**
```php
// BEFORE:
<?php foreach ($_SESSION['dbip_importer']['error_log'] as $error) : ?>

// AFTER:
<?php 
$error_log = dbip_get_import_data('error_log') ?: array();
foreach ($error_log as $error) : 
?>
```

- [ ] Line 13-22 fixed
- [ ] Line 61 fixed
- [ ] Line 68 fixed
- [ ] Line 79 fixed
- [ ] File tested

---

## 🔴 Phase 2: Missing AJAX Handlers (CRITICAL - 2 hours)

### Fix 2.1: Register Missing Handlers in Processor
**File:** `includes/class-dbip-importer-processor.php`

**Add to constructor after line 21:**
```php
add_action('wp_ajax_dbip_save_import_progress', array($this, 'save_import_progress'));
add_action('wp_ajax_dbip_save_import_start', array($this, 'save_import_start'));
add_action('wp_ajax_dbip_download_error_log', array($this, 'download_error_log'));
add_action('wp_ajax_dbip_get_import_logs', array($this, 'get_import_logs'));
add_action('wp_ajax_dbip_export_error_log', array($this, 'export_error_log'));
```

- [ ] Added to constructor
- [ ] Methods already exist (check lines 732-763)
- [ ] File tested

---

### Fix 2.2: Create Download Error Log Method
**File:** `includes/class-dbip-importer-processor.php`

**Add new method after `save_import_start()` method (around line 763):**
```php
/**
 * Download error log from completed import
 * 
 * @return void
 */
public function download_error_log(): void {
    check_ajax_referer('dbip_importer_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Unauthorized access', 'database-import-pro'));
    }

    // Get error log from transient
    $error_log = dbip_get_import_data('error_log');
    
    if (empty($error_log)) {
        wp_send_json_error(__('No error log found', 'database-import-pro'));
        return;
    }

    // Format as CSV
    $csv_content = "Row,Error Message\n";
    foreach ($error_log as $error) {
        $csv_content .= '"' . esc_attr($error['row']) . '","' . esc_attr($error['message']) . "\"\n";
    }

    wp_send_json_success($csv_content);
}
```

- [ ] Method created
- [ ] Tested with error log

---

### Fix 2.3: Implement or Remove get_status() Method
**File:** `includes/class-dbip-importer-processor.php`

**Option A: Remove the registration (recommended if not needed):**
```php
// Remove line 20:
// add_action('wp_ajax_dbip_get_import_status', array($this, 'get_status'));
```

**Option B: Implement the method (if status checking needed):**
```php
/**
 * Get current import status
 * 
 * @return void
 */
public function get_status(): void {
    check_ajax_referer('dbip_importer_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Unauthorized access', 'database-import-pro'));
    }

    $status = array(
        'is_running' => $this->is_import_locked(),
        'progress' => dbip_get_import_data('progress') ?: 0,
        'stats' => dbip_get_import_data('import_stats') ?: array()
    );

    wp_send_json_success($status);
}
```

- [ ] Decision made (remove or implement)
- [ ] Change applied
- [ ] Tested

---

## 🟠 Phase 3: Code Cleanup (HIGH - 1 hour)

### Fix 3.1: Remove Unused Handlers from Admin Class
**File:** `includes/class-dbip-importer-admin.php`

**Remove lines 114-116:**
```php
// DELETE THESE:
add_action('wp_ajax_dbip_upload_csv', array($this, 'handle_csv_upload'));
add_action('wp_ajax_dbip_save_mapping', array($this, 'save_field_mapping'));
add_action('wp_ajax_dbip_process_import', array($this, 'process_import'));
```

**Remove lines 124-146 (stub methods):**
```php
// DELETE THESE THREE METHODS:
public function handle_csv_upload() { ... }
public function save_field_mapping() { ... }
public function process_import() { ... }
```

- [ ] Line 114-116 removed
- [ ] Line 124-146 removed
- [ ] File tested

---

## 🟠 Phase 4: Add Step Validation (HIGH - 1 hour)

### Fix 4.1: Add Step Access Validation
**File:** `includes/class-dbip-importer-admin.php`

**Add new method before `display_plugin_admin_page()` (around line 90):**
```php
/**
 * Check if user can access a specific step
 * 
 * @param int $step Step number to check
 * @return bool True if accessible, false otherwise
 */
private function can_access_step(int $step): bool {
    switch ($step) {
        case 1:
            return true; // Always accessible
            
        case 2:
            return (bool) dbip_get_import_data('file');
            
        case 3:
            return (bool) (dbip_get_import_data('file') && dbip_get_import_data('target_table'));
            
        case 4:
            return (bool) (
                dbip_get_import_data('file') && 
                dbip_get_import_data('target_table') && 
                dbip_get_import_data('mapping')
            );
            
        case 5:
            return (bool) (
                dbip_get_import_data('file') && 
                dbip_get_import_data('target_table') && 
                dbip_get_import_data('mapping') && 
                dbip_get_import_data('import_mode')
            );
            
        case 6:
            return (bool) dbip_get_import_data('import_stats');
            
        default:
            return false;
    }
}
```

**Update `display_plugin_admin_page()` method (around line 91):**
```php
public function display_plugin_admin_page() {
    // Validate step access
    if (!$this->can_access_step($this->current_step)) {
        // Redirect to step 1
        $redirect_url = remove_query_arg('step');
        wp_safe_redirect($redirect_url);
        exit;
    }
    
    include_once DBIP_IMPORTER_PLUGIN_DIR . 'admin/partials/dbip-importer-admin-display.php';
}
```

- [ ] Method `can_access_step()` added
- [ ] `display_plugin_admin_page()` updated
- [ ] Tested step navigation
- [ ] Tested URL manipulation protection

---

## 🟡 Phase 5: Fix Preview Validation (MEDIUM - 1 hour)

### Fix 5.1: Remove PHP Function from JavaScript Context
**File:** `admin/partials/step-preview.php`

**Remove lines 248-304 (PHP function inside script tag):**
```php
// DELETE THIS ENTIRE BLOCK:
<?php
function validate_field_type($value, $db_type) {
    // ... entire function ...
}
?>
```

**Replace with AJAX-based validation or move server-side**

The validation is already handled by:
- `class-dbip-importer-mapping.php` method `validate_import_data()` (line 530)
- Already has AJAX action registered: `dbip_validate_import_data`
- Already called in step-preview.php JavaScript (line 199)

So just remove the PHP function - validation already works via AJAX!

- [ ] Lines 248-304 removed
- [ ] Tested validation still works via AJAX
- [ ] No JavaScript errors in console

---

## 🟡 Phase 6: Error Handling Improvements (MEDIUM - 1 hour)

### Fix 6.1: Add Global Error Handler
**File:** `assets/js/dbip-importer-admin.js` (or create new common JS file)

**Add at bottom of file:**
```javascript
// Global AJAX error handler for database-import-pro
jQuery(document).ready(function($) {
    $(document).ajaxError(function(event, jqxhr, settings, thrownError) {
        // Only handle our plugin's AJAX calls
        if (settings.url && settings.url.indexOf('admin-ajax.php') !== -1 && 
            settings.data && settings.data.indexOf('dbip_') !== -1) {
            
            console.error('Database Import Pro AJAX Error:', {
                action: settings.data,
                status: jqxhr.status,
                error: thrownError,
                response: jqxhr.responseText
            });
            
            // Show user-friendly message for common errors
            if (jqxhr.status === 0) {
                alert('Network error: Please check your internet connection.');
            } else if (jqxhr.status === 403) {
                alert('Permission denied: Please refresh the page and try again.');
            } else if (jqxhr.status === 500) {
                alert('Server error: Please contact support if this persists.');
            } else if (jqxhr.status === 504) {
                alert('Request timeout: The file may be too large. Try a smaller file.');
            }
        }
    });
});
```

- [ ] Error handler added
- [ ] Tested with network offline
- [ ] Tested with invalid nonce
- [ ] User-friendly messages appear

---

### Fix 6.2: Add Cleanup on Cancel/Error
**File:** `includes/class-dbip-importer-processor.php`

**Update `cancel_import()` method (around line 647):**
```php
public function cancel_import(): void {
    check_ajax_referer('dbip_importer_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Unauthorized access', 'database-import-pro'));
    }

    // Clean up all import data
    dbip_delete_import_data('import_mode');
    dbip_delete_import_data('key_columns');
    dbip_delete_import_data('allow_null');
    dbip_delete_import_data('dry_run');
    dbip_delete_import_data('import_stats');
    dbip_delete_import_data('progress');
    dbip_delete_import_data('start_time');
    dbip_delete_import_data('error_log');
    
    // Cleanup the uploaded file
    $this->cleanup_import_file();
    
    // Release import lock
    $this->release_import_lock();

    wp_send_json_success();
}
```

**Add new method for complete cleanup:**
```php
/**
 * Complete cleanup - reset all import data
 * 
 * @return void
 */
public function cleanup_all_import_data(): void {
    dbip_delete_import_data(); // Deletes entire transient
    $this->cleanup_import_file();
    $this->release_import_lock();
}
```

- [ ] `cancel_import()` updated
- [ ] `cleanup_all_import_data()` added
- [ ] Tested cancel button
- [ ] Verified file deletion
- [ ] Verified transient cleanup

---

## ✅ Testing Phase (3-4 hours)

### Test 1: Complete Workflow Test
- [ ] Start at Step 1
- [ ] Upload valid CSV file
- [ ] Verify headers extracted
- [ ] Proceed to Step 2
- [ ] Select database table
- [ ] View table structure
- [ ] Proceed to Step 3
- [ ] Verify CSV headers appear
- [ ] Verify DB columns appear
- [ ] Map fields manually
- [ ] Test auto-mapping
- [ ] Save mapping template
- [ ] Load mapping template
- [ ] Proceed to Step 4
- [ ] Verify preview data displays
- [ ] Run validation
- [ ] Select import mode
- [ ] Configure options
- [ ] Proceed to Step 5
- [ ] Watch progress update
- [ ] Verify stats update
- [ ] Verify log entries
- [ ] Wait for completion
- [ ] Proceed to Step 6
- [ ] Verify stats match
- [ ] Check duration
- [ ] Start new import

### Test 2: Error Handling Test
- [ ] Upload invalid file
- [ ] Try to skip steps via URL
- [ ] Cancel during import
- [ ] Test with network offline
- [ ] Test with very large file
- [ ] Test with malformed CSV
- [ ] Test with missing required fields

### Test 3: View Logs Test
- [ ] Open View Logs page
- [ ] Verify logs load
- [ ] Test pagination
- [ ] Export error log
- [ ] View log details

### Test 4: Edge Cases
- [ ] Empty CSV file
- [ ] CSV with special characters
- [ ] Table with all auto-increment
- [ ] Mapping with no fields selected
- [ ] Import with all rows failing
- [ ] Concurrent import attempts

---

## 🎯 Success Criteria

**Phase 1-2 (Critical):**
- [ ] No JavaScript console errors
- [ ] All steps display correct data
- [ ] Can navigate Step 1 → 6 without errors
- [ ] Import completes successfully
- [ ] Stats are accurate

**Phase 3-4 (High Priority):**
- [ ] Cannot skip steps via URL
- [ ] Old AJAX handlers removed
- [ ] Code is cleaner

**Phase 5-6 (Medium Priority):**
- [ ] Validation works correctly
- [ ] Error messages are user-friendly
- [ ] Cleanup happens on cancel
- [ ] No orphaned files

---

## 📊 Progress Tracking

**Phase 1:** ⬜⬜⬜⬜ (0/4 files)  
**Phase 2:** ⬜⬜⬜ (0/3 tasks)  
**Phase 3:** ⬜ (0/1 task)  
**Phase 4:** ⬜ (0/1 task)  
**Phase 5:** ⬜ (0/1 task)  
**Phase 6:** ⬜⬜ (0/2 tasks)  
**Testing:** ⬜⬜⬜⬜ (0/4 suites)

**Overall:** 0% Complete

---

## 🚀 Quick Start

1. **Backup your database and files**
2. Start with Phase 1 (Data Access Layer)
3. Complete each file in order
4. Test after each phase
5. Move to Phase 2 only after Phase 1 works
6. Continue sequentially

**Estimated Total Time:** 10-12 hours including testing

---

## 📝 Notes

- Keep original files backed up
- Test thoroughly after each phase
- Check error logs frequently
- Use browser dev tools to debug JavaScript
- Monitor PHP error logs for backend issues

**Last Updated:** October 18, 2025

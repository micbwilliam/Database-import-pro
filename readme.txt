=== Database Import Pro ===
Contributors: michaelbwilliam
Donate link: https://michaelbwilliam.com/donate
Tags: csv, import, database, data, importer, bulk, batch, migration, upload, admin
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Advanced CSV to database importer with smart field mapping, data transformations, and batch processing for WordPress databases.

== Description ==

**Database Import Pro** is a powerful, user-friendly tool for importing CSV data into any WordPress database table. Perfect for data migration, bulk updates, or regular data imports with an intuitive multi-step wizard interface.

= Key Features =

**Intuitive Multi-Step Wizard**

* Drag & drop CSV file upload
* Visual database table selection
* Interactive field mapping interface
* Live data preview before import
* Real-time progress tracking
* Detailed completion reports

**Smart Field Mapping**

* Auto-suggest field matches based on similarity
* Save and reuse mapping templates
* Default values support
* NULL value handling
* Skip or auto-increment fields
* Keep existing data option

**Flexible Import Modes**

* **Insert:** Add new records only (skip duplicates)
* **Update:** Update existing records based on key columns
* **Upsert:** Insert new records or update existing ones

**Data Transformations**

* Trim whitespace
* Uppercase/Lowercase conversion
* Capitalize text
* Custom transformations
* Automatic date format detection and conversion

**Enterprise-Grade Processing**

* Batch processing for large CSV files
* Support for files up to 50MB
* Progress tracking with live statistics
* Comprehensive error handling
* Row-level error logging
* Export error logs as CSV

**Comprehensive Logging**

* Complete import history
* Detailed statistics (inserted, updated, skipped, failed)
* Row-level error tracking
* Export error logs for analysis
* User attribution
* Import duration tracking

**Security & Performance**

* Nonce verification for all AJAX requests
* User capability checks (manage_options)
* File type and size validation
* SQL injection prevention
* Sanitized data output
* Session-based data storage

= Perfect For =

* Data migration from other systems
* Bulk product/user imports
* Regular data synchronization
* Custom database tables
* WooCommerce product updates
* Member data imports
* Any CSV to database import needs

= Developer Friendly =

* Clean, well-documented code
* Action and filter hooks
* Session-based processing
* WordPress coding standards
* GPL licensed

= Requirements =

* WordPress 5.0 or higher
* PHP 7.2 or higher
* MySQL 5.6 or higher

= Support =

For support, documentation, or feature requests, please visit [michaelbwilliam.com](https://michaelbwilliam.com)

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Navigate to Plugins > Add New
3. Search for "Database Import Pro"
4. Click "Install Now" and then "Activate"

= Manual Installation =

1. Download the plugin zip file
2. Log in to your WordPress admin panel
3. Navigate to Plugins > Add New
4. Click "Upload Plugin" at the top
5. Choose the downloaded zip file and click "Install Now"
6. Click "Activate Plugin"

= After Activation =

1. Navigate to "Database Import Pro" in the WordPress admin menu
2. Follow the intuitive multi-step wizard:
   * **Step 1:** Upload your CSV file
   * **Step 2:** Select the target database table
   * **Step 3:** Map CSV fields to database columns
   * **Step 4:** Preview your data before import
   * **Step 5:** Monitor the import progress
   * **Step 6:** Review import results and logs

== Frequently Asked Questions ==

= What file formats are supported? =

Currently, the plugin supports CSV (Comma-Separated Values) files. The plugin automatically detects various delimiters including comma, semicolon, tab, and pipe.

= What is the maximum file size I can upload? =

The plugin supports CSV files up to 50MB. The plugin uses batch processing to handle large files efficiently without timing out.

= Can I import data into custom database tables? =

Yes! Database Import Pro works with any WordPress database table, including custom tables created by other plugins or themes.

= Can I update existing records? =

Absolutely! The plugin offers three import modes:
* **Insert:** Add new records only
* **Update:** Update existing records based on key columns
* **Upsert:** Insert new or update existing records

= What happens if the import fails? =

The plugin provides comprehensive error logging. If rows fail to import, you can:
* View detailed error messages for each failed row
* Export error logs as CSV for analysis
* Fix the data and re-import only the failed rows

= Can I save my field mappings for future use? =

Yes! You can save field mapping templates and reuse them for subsequent imports of similar data.

= Does the plugin support data transformations? =

Yes! The plugin includes several built-in transformations:
* Trim whitespace
* Convert to uppercase/lowercase
* Capitalize text
* Custom PHP transformations
* Automatic date format conversion

= Is the plugin translation-ready? =

Yes! The plugin is fully translation-ready with proper text domain implementation.

= Can I schedule automated imports? =

The current version focuses on manual imports through the admin interface. Scheduled imports may be added in a future version.

= What permissions do I need to use this plugin? =

You need the "manage_options" capability (typically Administrator role) to use this plugin.

== Screenshots ==

1. Upload wizard - Drag & drop CSV file upload interface
2. Table selection - Choose your target database table with structure preview
3. Field mapping - Intelligent field mapping with auto-suggestions
4. Data preview - See exactly what will be imported before proceeding
5. Import progress - Real-time progress tracking with statistics
6. Import logs - Complete history with detailed statistics and error logs
7. Mapping templates - Save and reuse field mappings

== Changelog ==

= 1.0.1 - 2025-10-18 =
**SECURITY UPDATE - UPGRADE IMMEDIATELY**
* **Security:** Removed eval() remote code execution vulnerability
* **Security:** Fixed nonce validation inconsistency across AJAX endpoints
* **Security:** Added SQL injection protection with esc_sql()
* **Security:** Improved error handling with proper logging
* **Bug Fix:** Fixed duplicate AJAX handler registration
* **Bug Fix:** Fixed CSV tab delimiter detection
* **Bug Fix:** Added database transaction support for data integrity
* **Bug Fix:** Implemented automatic file cleanup after imports
* **Enhancement:** Added database performance indexes to logs table
* **Enhancement:** Added helper functions for future transient support
* Documentation: Added comprehensive audit report and fix documentation

= 1.0.0 - 2025-10-18 =
* Initial release
* Multi-step import wizard
* Smart field mapping with auto-suggestions
* Three import modes: Insert, Update, Upsert
* Data transformations (trim, case conversion, custom)
* Batch processing for large files
* Comprehensive import logging
* Error handling and export
* Mapping template system
* Support for files up to 50MB
* Automatic delimiter detection
* Encoding detection and conversion
* Preview before import
* Real-time progress tracking

== Upgrade Notice ==

= 1.0.1 =
**CRITICAL SECURITY UPDATE** - This version fixes multiple security vulnerabilities including a remote code execution issue. All users should upgrade immediately. No data migration required.

= 1.0.0 =
Initial release of Database Import Pro. Import CSV data into any WordPress database table with ease!

== Additional Information ==

= Credits =

Developed by [Michael B. William](https://michaelbwilliam.com)

= Privacy Policy =

Database Import Pro does not collect or store any personal data. All import operations are performed locally on your WordPress installation. Uploaded CSV files are stored temporarily in your WordPress uploads directory and can be automatically cleaned up on plugin deactivation.

= Support & Documentation =

* Documentation: https://michaelbwilliam.com/docs/database-import-pro
* Support: https://michaelbwilliam.com/support
* GitHub: https://github.com/michaelbwilliam/database-import-pro

= Future Enhancements =

We're continuously improving Database Import Pro. Planned features include:

* Excel file support (.xlsx, .xls)
* XML import support
* JSON import support
* Scheduled/automated imports
* Import via URL
* Export functionality
* More data transformation options
* Field validation rules
* Import presets for popular plugins

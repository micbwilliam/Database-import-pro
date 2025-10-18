# Database Import Pro

A WordPress plugin that provides an advanced, user-friendly interface for importing CSV data into WordPress database tables.

**Version:** 1.0.0  
**Author:** Michael B. William  
**Author URI:** [michaelbwilliam.com](https://michaelbwilliam.com)  
**License:** GPL-2.0+

## Description

Database Import Pro is a powerful CSV to database importer that makes data migration and bulk imports simple and efficient. With an intuitive multi-step wizard, smart field mapping, and comprehensive error handling, it's the perfect tool for managing your WordPress data imports.

## Features

### Multi-Step Wizard Interface
- Drag and drop CSV file upload
- Database table selection with structure preview
- Flexible field mapping with transformations
- Real-time import progress tracking
- Mobile-responsive design
- Support for large datasets with batch processing

### Smart Field Mapping
- Auto-suggest field matches based on field name similarity
- Save and load mapping templates for reuse
- Default values support
- NULL value handling
- Skip auto-increment fields
- Keep existing data option

### Import Modes
- **Insert Only:** Add new records and skip duplicates
- **Update Only:** Update existing records based on key columns
- **Upsert:** Insert new records or update existing ones (insert or update)

### Data Transformations
- Trim whitespace
- Uppercase conversion
- Lowercase conversion
- Capitalize text
- Custom PHP transformations
- Automatic date format detection and conversion

### Enterprise Features
- Batch processing (100 rows per batch)
- Files up to 50MB supported
- CSV delimiter auto-detection (comma, semicolon, tab, pipe)
- Encoding detection and conversion (UTF-8, ISO-8859-1, Windows-1252)
- Comprehensive error logging
- Export error logs as CSV
- Import history tracking
- User attribution
- Duration tracking

### Security
- File type validation
- File size limits
- SQL injection prevention
- Nonce verification
- User capability checks (manage_options)
- Sanitized data output

## Installation

1. Download the plugin zip file
2. Go to WordPress admin panel > Plugins > Add New
3. Click "Upload Plugin" and select the downloaded zip file
4. Click "Install Now" and then "Activate"

## Usage

1. In WordPress admin panel, go to "Database Import Pro"
2. Follow the step-by-step wizard:
   - **Step 1:** Upload your CSV file
   - **Step 2:** Select the target database table
   - **Step 3:** Map CSV fields to database columns (with auto-suggestions)
   - **Step 4:** Preview and validate the data
   - **Step 5:** Monitor the import progress
   - **Step 6:** View import results and logs

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- MySQL 5.6 or higher

## Perfect For

- Data migration from other systems
- Bulk product imports for WooCommerce
- User data imports
- Custom database table management
- Regular data synchronization
- Any CSV to database import needs

## Support

For support, documentation, or feature requests:

- **Website:** [michaelbwilliam.com](https://michaelbwilliam.com)
- **Documentation:** [michaelbwilliam.com/docs/database-import-pro](https://michaelbwilliam.com/docs/database-import-pro)
- **Support:** [michaelbwilliam.com/support](https://michaelbwilliam.com/support)
- **GitHub:** [github.com/michaelbwilliam/database-import-pro](https://github.com/michaelbwilliam/database-import-pro)

## Changelog

### 1.0.0 - 2025-10-18
* Initial release
* Multi-step import wizard
* Smart field mapping with auto-suggestions
* Three import modes (Insert, Update, Upsert)
* Data transformations
* Batch processing for large files
* Comprehensive logging and error handling
* Mapping template system

## License

This plugin is licensed under the GPL v2 or later.

```
Database Import Pro
Copyright (C) 2025 Michael B. William

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
```

## Credits

Developed by [Michael B. William](https://michaelbwilliam.com)

---

**Enjoying Database Import Pro?** Please consider leaving a review on WordPress.org!
 
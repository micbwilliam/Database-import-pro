# Database Import Pro - Developer Guide
**Version:** 1.1.0  
**Last Updated:** October 18, 2025

---

## 🚀 Quick Start for Developers

### Prerequisites
- PHP 7.0 or higher (7.4+ recommended)
- Composer
- Node.js and npm (for JavaScript development)
- WordPress 5.0+
- MySQL 5.6+ or MariaDB 10.0+

### Installation

1. **Clone the Repository**
   ```bash
   git clone https://github.com/michaelbwilliam/database-import-pro.git
   cd database-import-pro
   ```

2. **Install PHP Dependencies**
   ```bash
   composer install
   ```

3. **Install Development Dependencies**
   ```bash
   composer install --dev
   ```

4. **Run Tests**
   ```bash
   composer test
   ```

---

## 📦 Project Structure

```
database-import-pro/
├── admin/
│   └── partials/          # Admin view templates
├── assets/
│   ├── css/              # Stylesheets
│   └── js/               # JavaScript files
├── includes/             # Core PHP classes
│   ├── class-dbip-importer.php
│   ├── class-dbip-importer-admin.php
│   ├── class-dbip-importer-uploader.php
│   ├── class-dbip-importer-table.php
│   ├── class-dbip-importer-mapping.php
│   └── class-dbip-importer-processor.php
├── tests/                # PHPUnit tests
│   ├── bootstrap.php
│   ├── mocks/
│   └── *Test.php
├── vendor/               # Composer dependencies
├── composer.json         # PHP dependencies
├── phpunit.xml          # PHPUnit configuration
└── README_DEV.md        # This file
```

---

## 🧪 Testing

### Running PHPUnit Tests

**Run all tests:**
```bash
composer test
```

**Run specific test file:**
```bash
vendor/bin/phpunit tests/UploaderTest.php
```

**Generate code coverage report:**
```bash
composer test:coverage
```

Coverage reports will be generated in `coverage/index.html`

### Writing Tests

Tests are located in the `tests/` directory and follow PHPUnit conventions:

```php
<?php
namespace DatabaseImportPro\Tests;

use PHPUnit\Framework\TestCase;

class MyTest extends TestCase {
    public function test_example(): void {
        $this->assertTrue(true);
    }
}
```

---

## 🎨 Code Quality

### Running PHP CodeSniffer

**Check coding standards:**
```bash
composer phpcs
```

**Auto-fix coding standards:**
```bash
composer phpcbf
```

### Running PHPStan

**Static analysis:**
```bash
composer phpstan
```

---

## 📚 Development Dependencies

### Required (Production)
- **phpoffice/phpspreadsheet** (^1.29) - Excel file support

### Development Only
- **phpunit/phpunit** (^9.0) - Unit testing framework
- **mockery/mockery** (^1.5) - Mocking framework
- **brain/monkey** (^2.6) - WordPress function mocking
- **squizlabs/php_codesniffer** (^3.7) - Coding standards
- **phpstan/phpstan** (^1.10) - Static analysis

---

## 🔧 Configuration

### PHPUnit Configuration
Edit `phpunit.xml` to customize test settings:
- Test directories
- Code coverage paths
- Bootstrap file

### Composer Scripts
Available scripts in `composer.json`:
- `composer test` - Run PHPUnit tests
- `composer test:coverage` - Generate coverage report
- `composer phpcs` - Check coding standards
- `composer phpcbf` - Fix coding standards
- `composer phpstan` - Run static analysis

---

## 📝 Coding Standards

### PHP Standards
- Follow WordPress Coding Standards
- Use PHP 7+ type hints
- Add PHPDoc blocks for all methods
- Keep functions focused and testable

### Example:
```php
/**
 * Process a batch of records
 * 
 * @param array $data The data to process
 * @return bool True on success, false on failure
 */
public function process_batch(array $data): bool {
    // Implementation
}
```

### JavaScript Standards
- Use ES6+ features (const, let, arrow functions, classes)
- Add JSDoc comments
- Use async/await for asynchronous operations
- Handle errors with try-catch blocks

### Example:
```javascript
/**
 * Upload file via AJAX
 * @param {File} file - The file to upload
 * @returns {Promise} Upload promise
 */
async uploadFile(file) {
    try {
        const response = await this.makeRequest(file);
        return response;
    } catch (error) {
        console.error('Upload failed:', error);
        throw error;
    }
}
```

---

## 🆕 New in Version 1.1.0

### Excel File Support
- PHPSpreadsheet integration
- Support for .xlsx and .xls files
- Sheet selection interface
- Automatic format detection

### Unit Test Suite
- PHPUnit setup complete
- Test structure established
- WordPress function mocking
- Code coverage reporting

### JavaScript Modernization
- ES6+ syntax (classes, arrow functions, const/let)
- Async/await for AJAX
- Better error handling
- Modern code structure

---

## 🔌 Excel File Integration

### Usage Example

```php
<?php
// Check if PHPSpreadsheet is available
if (class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory')) {
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
    $worksheet = $spreadsheet->getActiveSheet();
    $data = $worksheet->toArray();
}
```

### Installing PHPSpreadsheet

If not using Composer, PHPSpreadsheet can be manually included:

```bash
composer require phpoffice/phpspreadsheet
```

---

## 🐛 Debugging

### Enable WordPress Debug Mode

Add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Plugin Logging

Check logs in:
- WordPress debug.log: `wp-content/debug.log`
- PHP error log: Server-specific location
- Plugin logs: Database table `{prefix}dbip_import_logs`

### Common Issues

**Issue:** PHPSpreadsheet not found  
**Solution:** Run `composer install` in plugin directory

**Issue:** Tests fail with WordPress function errors  
**Solution:** Ensure `tests/bootstrap.php` is properly configured

**Issue:** Memory errors during large imports  
**Solution:** Increase PHP memory_limit in `php.ini`

---

## 🤝 Contributing

### Pull Request Process

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Write tests for new functionality
4. Ensure all tests pass (`composer test`)
5. Check coding standards (`composer phpcs`)
6. Commit your changes (`git commit -m 'Add AmazingFeature'`)
7. Push to the branch (`git push origin feature/AmazingFeature`)
8. Open a Pull Request

### Code Review Checklist

- [ ] All tests pass
- [ ] Code follows WordPress standards
- [ ] PHPDoc blocks added
- [ ] Type hints used where appropriate
- [ ] No security vulnerabilities
- [ ] Performance considered
- [ ] Documentation updated

---

## 📖 Additional Resources

### Documentation
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [PHPSpreadsheet Documentation](https://phpspreadsheet.readthedocs.io/)

### Tools
- [PHP CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer)
- [PHPStan](https://phpstan.org/)
- [Composer](https://getcomposer.org/)

---

## 📞 Support

For development questions:
- **Documentation:** https://michaelbwilliam.com/docs/database-import-pro
- **GitHub Issues:** https://github.com/michaelbwilliam/database-import-pro/issues
- **Developer Chat:** https://michaelbwilliam.com/dev-chat

---

## 📄 License

GPL-2.0-or-later - See LICENSE.txt for details

---

**Happy Coding!** 🚀

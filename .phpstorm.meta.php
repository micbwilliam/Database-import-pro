<?php
/**
 * PhpStorm Metadata
 * 
 * This file helps PhpStorm understand optional dependencies and dynamic types.
 * These classes are loaded conditionally via Composer and may not be present.
 * 
 * @since 1.1.0
 * @package DBIP_Importer
 */

namespace PHPSTORM_META {
    // Tell PhpStorm about optional dependencies that are loaded via Composer
    // These are only available after running: composer install
    
    // PHPSpreadsheet (optional - for Excel support)
    override(\class_exists(0), map([
        'PhpOffice\\PhpSpreadsheet\\IOFactory' => \PhpOffice\PhpSpreadsheet\IOFactory::class,
        'PhpOffice\\PhpSpreadsheet\\Spreadsheet' => \PhpOffice\PhpSpreadsheet\Spreadsheet::class,
    ]));
    
    // PHPUnit (dev dependency - for testing)
    override(\class_exists(0), map([
        'PHPUnit\\Framework\\TestCase' => \PHPUnit\Framework\TestCase::class,
    ]));
    
    // Brain\Monkey (dev dependency - for WordPress function mocking)
    override(\function_exists(0), map([
        'Brain\\Monkey\\setUp' => true,
        'Brain\\Monkey\\tearDown' => true,
    ]));
    
    // Mockery (dev dependency - for mocking)
    override(\class_exists(0), map([
        'Mockery' => \Mockery::class,
    ]));
}

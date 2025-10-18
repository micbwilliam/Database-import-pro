<?php
/**
 * Tests for DBIP_Importer_Uploader class
 *
 * @package DatabaseImportPro\Tests
 */

namespace DatabaseImportPro\Tests;

use PHPUnit\Framework\TestCase;
use Mockery;

class UploaderTest extends TestCase {
    
    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();
        
        // Load the class file
        require_once dirname(__DIR__) . '/../includes/class-dbip-importer-uploader.php';
    }

    protected function tearDown(): void {
        Mockery::close();
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test that uploader class exists
     */
    public function test_class_exists(): void {
        $this->assertTrue(
            class_exists('DBIP_Importer_Uploader'),
            'DBIP_Importer_Uploader class should exist'
        );
    }

    /**
     * Test uploader class instantiation
     */
    public function test_class_instantiation(): void {
        $uploader = new \DBIP_Importer_Uploader();
        $this->assertInstanceOf(
            'DBIP_Importer_Uploader',
            $uploader,
            'Should create an instance of DBIP_Importer_Uploader'
        );
    }

    /**
     * Test allowed file types constant
     */
    public function test_allowed_types(): void {
        $uploader = new \DBIP_Importer_Uploader();
        
        // Use reflection to access private property
        $reflection = new \ReflectionClass($uploader);
        $property = $reflection->getProperty('allowed_types');
        $property->setAccessible(true);
        $allowed_types = $property->getValue($uploader);
        
        $this->assertIsArray($allowed_types, 'Allowed types should be an array');
        $this->assertArrayHasKey('csv', $allowed_types, 'Should support CSV files');
    }

    /**
     * Test max file size constant
     */
    public function test_max_file_size(): void {
        $uploader = new \DBIP_Importer_Uploader();
        
        // Use reflection to access private property
        $reflection = new \ReflectionClass($uploader);
        $property = $reflection->getProperty('max_file_size');
        $property->setAccessible(true);
        $max_size = $property->getValue($uploader);
        
        $this->assertIsInt($max_size, 'Max file size should be an integer');
        $this->assertGreaterThan(0, $max_size, 'Max file size should be positive');
    }

    /**
     * Test upload directory path
     */
    public function test_upload_directory(): void {
        $uploader = new \DBIP_Importer_Uploader();
        
        // Use reflection to access private property
        $reflection = new \ReflectionClass($uploader);
        $property = $reflection->getProperty('upload_dir');
        $property->setAccessible(true);
        $upload_dir = $property->getValue($uploader);
        
        $this->assertIsString($upload_dir, 'Upload directory should be a string');
        $this->assertStringContainsString('dbip-importer', $upload_dir, 'Upload dir should contain plugin name');
    }

    /**
     * Test CSV delimiter detection
     */
    public function test_csv_delimiter_detection(): void {
        $this->markTestIncomplete('CSV delimiter detection test needs to be implemented');
    }

    /**
     * Test file validation
     */
    public function test_file_validation(): void {
        $this->markTestIncomplete('File validation test needs to be implemented');
    }
}

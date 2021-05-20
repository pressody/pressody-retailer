<?php
declare ( strict_types = 1 );

namespace PixelgradeLT\Retailer\Tests\Unit\Validator;

use PixelgradeLT\Retailer\Exception\InvalidPackageArtifact;
use PixelgradeLT\Retailer\Release;
use PixelgradeLT\Retailer\Tests\Unit\TestCase;
use PixelgradeLT\Retailer\Validator\ZipValidator;

class ZipValidatorTest extends TestCase {
	public function setUp(): void {
		parent::setUp();

		$this->directory = PIXELGRADELT_RETAILER_TESTS_DIR . '/Fixture/wp-content/uploads/pixelgradelt-retailer/packages/validate';

		$this->release = $this->getMockBuilder( Release::class )
			->disableOriginalConstructor()
			->getMock();

		$this->validator = new ZipValidator();
	}

	public function test_artifact_is_valid_zip() {
		$filename = $this->directory . '/valid-zip.zip';
		$result = $this->validator->validate( $filename, $this->release );
		$this->assertTrue( $result );
	}

	public function test_validator_throws_exception_for_invalid_artifact() {
		$this->expectException( InvalidPackageArtifact::class );
		$filename = $this->directory . '/invalid-zip.zip';
		$this->validator->validate( $filename, $this->release );
	}
}

<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Deploy;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Deploy\DeployResult;

final class DeployResultTest extends TestCase {

	public function test_ok_creates_success(): void {
		$result = DeployResult::ok( 'Deployed successfully.' );

		$this->assertTrue( $result->success );
		$this->assertSame( 'Deployed successfully.', $result->message );
		$this->assertSame( [], $result->context );
	}

	public function test_fail_creates_failure(): void {
		$result = DeployResult::fail( 'Something went wrong.' );

		$this->assertFalse( $result->success );
		$this->assertSame( 'Something went wrong.', $result->message );
	}

	public function test_ok_preserves_context(): void {
		$result = DeployResult::ok( 'Done.', [ 'deploy_id' => '123', 'url' => 'https://example.com' ] );

		$this->assertTrue( $result->success );
		$this->assertSame( [ 'deploy_id' => '123', 'url' => 'https://example.com' ], $result->context );
	}

	public function test_fail_preserves_context(): void {
		$result = DeployResult::fail( 'Error.', [ 'exit_code' => 1 ] );

		$this->assertFalse( $result->success );
		$this->assertSame( [ 'exit_code' => 1 ], $result->context );
	}
}

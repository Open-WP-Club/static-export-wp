<?php

declare(strict_types=1);

namespace StaticExportWP\Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use StaticExportWP\Export\ContentHashStore;

final class ContentHashStoreTest extends TestCase {

	public function test_hash_content_returns_sha256(): void {
		$hash = ContentHashStore::hash_content( 'hello world' );

		$this->assertSame( hash( 'sha256', 'hello world' ), $hash );
		$this->assertSame( 64, strlen( $hash ) );
	}

	public function test_same_input_same_hash(): void {
		$hash1 = ContentHashStore::hash_content( 'test content' );
		$hash2 = ContentHashStore::hash_content( 'test content' );

		$this->assertSame( $hash1, $hash2 );
	}

	public function test_different_input_different_hash(): void {
		$hash1 = ContentHashStore::hash_content( 'content A' );
		$hash2 = ContentHashStore::hash_content( 'content B' );

		$this->assertNotSame( $hash1, $hash2 );
	}
}

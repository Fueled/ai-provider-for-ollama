<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Tests\Integration\Metadata;

use Fueled\AiProviderForOllama\Metadata\OllamaModelDetailsCache;
use WP_UnitTestCase;

/**
 * Tests for OllamaModelDetailsCache.
 *
 * @covers \Fueled\AiProviderForOllama\Metadata\OllamaModelDetailsCache
 */
class OllamaModelDetailsCacheTest extends WP_UnitTestCase {

	/**
	 * Test host URL.
	 *
	 * @var string
	 */
	private const TEST_HOST = 'http://localhost:11434';

	/**
	 * Secondary test host URL.
	 *
	 * @var string
	 */
	private const SECONDARY_HOST = 'https://ollama.example.com';

	/**
	 * Cleans up transients after each test.
	 */
	public function tear_down(): void {
		OllamaModelDetailsCache::flush( self::TEST_HOST );
		OllamaModelDetailsCache::flush( self::SECONDARY_HOST );
		parent::tear_down();
	}

	/**
	 * Returns the expected transient name for a host.
	 *
	 * @param string $host The host URL.
	 * @return string The transient name.
	 */
	private function get_transient_name( string $host ): string {
		return 'ai_provider_for_ollama_model_details_' . substr( md5( $host ), 0, 12 );
	}

	/**
	 * Tests that load() returns an empty cache when no transient exists.
	 */
	public function test_load_returns_empty_cache_when_transient_does_not_exist(): void {
		$cache = OllamaModelDetailsCache::load( self::TEST_HOST );

		$this->assertNull( $cache->get( 'sha256:nonexistent' ) );
	}

	/**
	 * Tests that load() correctly retrieves and parses valid cached entries.
	 */
	public function test_load_retrieves_valid_cached_entries(): void {
		$stored_data = array(
			'sha256:abc123' => array(
				'capabilities' => array( 'tools', 'completion' ),
				'families'     => array( 'llama' ),
			),
		);
		set_transient( $this->get_transient_name( self::TEST_HOST ), $stored_data );

		$cache   = OllamaModelDetailsCache::load( self::TEST_HOST );
		$details = $cache->get( 'sha256:abc123' );

		$this->assertNotNull( $details );
		$this->assertSame( array( 'tools', 'completion' ), $details['capabilities'] );
		$this->assertSame( array( 'llama' ), $details['families'] );
	}

	/**
	 * Tests that load() normalizes malformed entries and filters non-string items.
	 */
	public function test_load_normalizes_and_discards_malformed_entries(): void {
		$stored_data = array(
			''                 => array(
				'capabilities' => array( 'tools' ),
				'families'     => array( 'llama' ),
			),
			123                => array(
				'capabilities' => array( 'tools' ),
				'families'     => array( 'llama' ),
			),
			'sha256:not_array' => 'string_data',
			'sha256:mixed'     => array(
				'capabilities' => array( 'tools', 456, null, 'completion' ),
				'families'     => array( 'qwen', false, array() ),
			),
			'sha256:no_lists'  => array(
				'capabilities' => 'not_an_array',
				'families'     => null,
			),
		);
		set_transient( $this->get_transient_name( self::TEST_HOST ), $stored_data );

		$cache = OllamaModelDetailsCache::load( self::TEST_HOST );

		$this->assertNull( $cache->get( '' ) );
		$this->assertNull( $cache->get( '123' ) );
		$this->assertNull( $cache->get( 'sha256:not_array' ) );

		$mixed = $cache->get( 'sha256:mixed' );
		$this->assertNotNull( $mixed );
		$this->assertSame( array( 'tools', 'completion' ), $mixed['capabilities'] );
		$this->assertSame( array( 'qwen' ), $mixed['families'] );

		$no_lists = $cache->get( 'sha256:no_lists' );
		$this->assertNotNull( $no_lists );
		$this->assertSame( array(), $no_lists['capabilities'] );
		$this->assertSame( array(), $no_lists['families'] );
	}

	/**
	 * Tests get() and set() in-memory operations.
	 */
	public function test_get_and_set(): void {
		$cache = OllamaModelDetailsCache::load( self::TEST_HOST );

		$this->assertNull( $cache->get( 'sha256:sample' ) );

		$details = array(
			'capabilities' => array( 'vision' ),
			'families'     => array( 'clip' ),
		);
		$cache->set( 'sha256:sample', $details );

		$this->assertSame( $details, $cache->get( 'sha256:sample' ) );
	}

	/**
	 * Tests save() persists entries and prunes digests that are no longer in use.
	 */
	public function test_save_persists_entries_and_prunes_unused_digests(): void {
		$cache = OllamaModelDetailsCache::load( self::TEST_HOST );
		$cache->set(
			'sha256:keep',
			array(
				'capabilities' => array( 'tools' ),
				'families'     => array( 'llama' ),
			)
		);
		$cache->set(
			'sha256:prune',
			array(
				'capabilities' => array( 'vision' ),
				'families'     => array( 'clip' ),
			)
		);

		$cache->save( array( 'sha256:keep' ) );

		$fresh_cache = OllamaModelDetailsCache::load( self::TEST_HOST );
		$this->assertNotNull( $fresh_cache->get( 'sha256:keep' ) );
		$this->assertNull( $fresh_cache->get( 'sha256:prune' ) );
	}

	/**
	 * Tests save() deletes the transient when no entries remain in use.
	 */
	public function test_save_deletes_transient_when_no_entries_remain(): void {
		$cache = OllamaModelDetailsCache::load( self::TEST_HOST );
		$cache->set(
			'sha256:old',
			array(
				'capabilities' => array( 'tools' ),
				'families'     => array( 'llama' ),
			)
		);
		$cache->save( array( 'sha256:old' ) );

		$this->assertNotEmpty( get_transient( $this->get_transient_name( self::TEST_HOST ) ) );

		$cache->save( array() );

		$this->assertFalse( get_transient( $this->get_transient_name( self::TEST_HOST ) ) );
	}

	/**
	 * Tests save() does not re-write transient when entries have not changed.
	 */
	public function test_save_does_not_rewrite_when_entries_unchanged(): void {
		$cache = OllamaModelDetailsCache::load( self::TEST_HOST );
		$cache->set(
			'sha256:unchanged',
			array(
				'capabilities' => array( 'tools' ),
				'families'     => array( 'llama' ),
			)
		);
		$cache->save( array( 'sha256:unchanged' ) );

		$write_count = 0;
		$filter      = static function ( $value ) use ( &$write_count ) {
			++$write_count;
			return $value;
		};

		$transient_name = $this->get_transient_name( self::TEST_HOST );
		add_filter( "pre_set_transient_{$transient_name}", $filter );

		$cache->save( array( 'sha256:unchanged' ) );

		remove_filter( "pre_set_transient_{$transient_name}", $filter );

		$this->assertSame( 0, $write_count );
	}

	/**
	 * Tests flush() deletes the host transient.
	 */
	public function test_flush_deletes_transient_for_host(): void {
		$cache = OllamaModelDetailsCache::load( self::TEST_HOST );
		$cache->set(
			'sha256:entry',
			array(
				'capabilities' => array( 'tools' ),
				'families'     => array( 'llama' ),
			)
		);
		$cache->save( array( 'sha256:entry' ) );

		$this->assertNotEmpty( get_transient( $this->get_transient_name( self::TEST_HOST ) ) );

		OllamaModelDetailsCache::flush( self::TEST_HOST );

		$this->assertFalse( get_transient( $this->get_transient_name( self::TEST_HOST ) ) );
	}

	/**
	 * Tests that different Ollama hosts have isolated caches.
	 */
	public function test_hosts_have_isolated_caches(): void {
		$cache1 = OllamaModelDetailsCache::load( self::TEST_HOST );
		$cache1->set(
			'sha256:common',
			array(
				'capabilities' => array( 'tools' ),
				'families'     => array( 'host1' ),
			)
		);
		$cache1->save( array( 'sha256:common' ) );

		$cache2 = OllamaModelDetailsCache::load( self::SECONDARY_HOST );
		$this->assertNull( $cache2->get( 'sha256:common' ) );

		$cache2->set(
			'sha256:common',
			array(
				'capabilities' => array( 'vision' ),
				'families'     => array( 'host2' ),
			)
		);
		$cache2->save( array( 'sha256:common' ) );

		$fresh1 = OllamaModelDetailsCache::load( self::TEST_HOST );
		$fresh2 = OllamaModelDetailsCache::load( self::SECONDARY_HOST );

		$this->assertSame( array( 'host1' ), $fresh1->get( 'sha256:common' )['families'] );
		$this->assertSame( array( 'host2' ), $fresh2->get( 'sha256:common' )['families'] );

		OllamaModelDetailsCache::flush( self::TEST_HOST );
		$this->assertNull( OllamaModelDetailsCache::load( self::TEST_HOST )->get( 'sha256:common' ) );
		$this->assertNotNull( OllamaModelDetailsCache::load( self::SECONDARY_HOST )->get( 'sha256:common' ) );
	}
}

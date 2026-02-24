<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Tests\Integration\Provider;

use Fueled\AiProviderForOllama\Metadata\OllamaModelMetadataDirectory;
use Fueled\AiProviderForOllama\Provider\OllamaProvider;
use Fueled\AiProviderForOllama\Provider\OllamaProviderAvailability;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Providers\AbstractProvider;

/**
 * Tests for OllamaProvider.
 *
 * @covers \Fueled\AiProviderForOllama\Provider\OllamaProvider
 */
class OllamaProviderTest extends TestCase {

	/**
	 * The original OLLAMA_HOST value before each test.
	 *
	 * @var string|false
	 */
	private $original_ollama_host;

	protected function setUp(): void {
		parent::setUp();
		$this->original_ollama_host = getenv( 'OLLAMA_HOST' );
		$this->clear_provider_caches();
	}

	protected function tearDown(): void {
		$this->restore_ollama_host();
		$this->clear_provider_caches();
		parent::tearDown();
	}

	/**
	 * Clears all static caches on AbstractProvider to ensure test isolation.
	 */
	private function clear_provider_caches(): void {
		$reflection = new \ReflectionClass( AbstractProvider::class );
		foreach ( array( 'metadataCache', 'availabilityCache', 'modelMetadataDirectoryCache' ) as $prop_name ) {
			$prop = $reflection->getProperty( $prop_name );
			$prop->setAccessible( true );
			$prop->setValue( null, array() );
		}
	}

	/**
	 * Restores OLLAMA_HOST to its pre-test value.
	 */
	private function restore_ollama_host(): void {
		if ( false === $this->original_ollama_host ) {
			putenv( 'OLLAMA_HOST' );
		} else {
			putenv( 'OLLAMA_HOST=' . $this->original_ollama_host );
		}
	}

	// -----------------------------------------------------------------------
	// url() / baseUrl() tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that url() falls back to localhost when OLLAMA_HOST is not set.
	 */
	public function test_url_falls_back_to_localhost_when_env_var_not_set(): void {
		putenv( 'OLLAMA_HOST' ); // Remove env var entirely
		$url = OllamaProvider::url( '' );
		$this->assertStringStartsWith( 'http://localhost:11434', $url );
	}

	/**
	 * Tests that url() uses the OLLAMA_HOST environment variable when set.
	 */
	public function test_url_uses_ollama_host_env_var(): void {
		putenv( 'OLLAMA_HOST=http://my-server:11434' );
		$url = OllamaProvider::url( '' );
		$this->assertStringStartsWith( 'http://my-server:11434', $url );
	}

	/**
	 * Tests that url() strips a trailing slash from the OLLAMA_HOST env var.
	 */
	public function test_url_strips_trailing_slash_from_env_var(): void {
		putenv( 'OLLAMA_HOST=http://my-server:11434/' );
		$url = OllamaProvider::url( 'path' );
		$this->assertSame( 'http://my-server:11434/path', $url );
	}

	/**
	 * Tests that url() falls back to localhost when OLLAMA_HOST is an empty string.
	 */
	public function test_url_falls_back_when_env_var_is_empty_string(): void {
		putenv( 'OLLAMA_HOST=' );
		$url = OllamaProvider::url( '' );
		$this->assertStringStartsWith( 'http://localhost:11434', $url );
	}

	// -----------------------------------------------------------------------
	// metadata() tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that the provider metadata has the correct provider ID.
	 */
	public function test_metadata_has_correct_provider_id(): void {
		$metadata = OllamaProvider::metadata();
		$this->assertSame( 'ollama', $metadata->getId() );
	}

	/**
	 * Tests that the provider metadata has the correct display name.
	 */
	public function test_metadata_has_correct_name(): void {
		$metadata = OllamaProvider::metadata();
		$this->assertSame( 'Ollama', $metadata->getName() );
	}

	/**
	 * Tests that the provider metadata specifies API key as the authentication method.
	 */
	public function test_metadata_auth_method_is_api_key(): void {
		$metadata     = OllamaProvider::metadata();
		$auth_method  = $metadata->getAuthenticationMethod();
		$this->assertNotNull( $auth_method );
		$this->assertTrue( $auth_method->isApiKey() );
	}

	// -----------------------------------------------------------------------
	// Factory method tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that availability() returns an OllamaProviderAvailability instance.
	 */
	public function test_availability_returns_ollama_provider_availability(): void {
		$availability = OllamaProvider::availability();
		$this->assertInstanceOf( OllamaProviderAvailability::class, $availability );
	}

	/**
	 * Tests that modelMetadataDirectory() returns an OllamaModelMetadataDirectory instance.
	 */
	public function test_model_metadata_directory_returns_correct_type(): void {
		$directory = OllamaProvider::modelMetadataDirectory();
		$this->assertInstanceOf( OllamaModelMetadataDirectory::class, $directory );
	}
}

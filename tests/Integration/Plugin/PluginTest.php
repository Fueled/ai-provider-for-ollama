<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Tests\Integration\Plugin;

use Fueled\AiProviderForOllama\Plugin;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\AbstractProvider;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;

/**
 * Tests for Plugin.
 *
 * @covers \WordPress\AiProviderOllama\Plugin
 */
class PluginTest extends \WP_UnitTestCase {

	/**
	 * Plugin instance under test.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * The original OLLAMA_HOST value before each test.
	 *
	 * @var string|false
	 */
	private $original_ollama_host;

	protected function setUp(): void {
		parent::setUp();
		$this->plugin             = new Plugin();
		$this->original_ollama_host = getenv( 'OLLAMA_HOST' );
		putenv( 'OLLAMA_HOST=' );
		delete_option( 'wp_ai_client_ollama_settings' );
	}

	protected function tearDown(): void {
		$this->restore_ollama_host();
		delete_option( 'wp_ai_client_ollama_settings' );
		parent::tearDown();
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

	/**
	 * Resets the AiClient default registry and all AbstractProvider static caches
	 * so each test starts with a clean slate when registry state matters.
	 */
	private function reset_registry(): void {
		// Reset the singleton registry.
		$ai_client_reflection = new \ReflectionClass( AiClient::class );
		$registry_prop        = $ai_client_reflection->getProperty( 'defaultRegistry' );
		$registry_prop->setAccessible( true );
		$registry_prop->setValue( null, null );

		// Clear AbstractProvider static caches so providers re-create fresh instances.
		$provider_reflection = new \ReflectionClass( AbstractProvider::class );
		foreach ( array( 'metadataCache', 'availabilityCache', 'modelMetadataDirectoryCache' ) as $prop_name ) {
			$prop = $provider_reflection->getProperty( $prop_name );
			$prop->setAccessible( true );
			$prop->setValue( null, array() );
		}
	}

	// -----------------------------------------------------------------------
	// init() hook-registration tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that init() registers register_provider at priority 5 on the init hook.
	 */
	public function test_init_registers_register_provider_at_priority_5(): void {
		$this->plugin->init();
		$this->assertSame(
			5,
			has_action( 'init', array( $this->plugin, 'register_provider' ) )
		);
	}

	/**
	 * Tests that init() registers ensure_http_transporter at priority 15 on the init hook.
	 */
	public function test_init_registers_ensure_http_transporter_at_priority_15(): void {
		$this->plugin->init();
		$this->assertSame(
			15,
			has_action( 'init', array( $this->plugin, 'ensure_http_transporter' ) )
		);
	}

	/**
	 * Tests that init() registers register_fallback_auth at priority 20 on the init hook.
	 */
	public function test_init_registers_register_fallback_auth_at_priority_20(): void {
		$this->plugin->init();
		$this->assertSame(
			20,
			has_action( 'init', array( $this->plugin, 'register_fallback_auth' ) )
		);
	}

	// -----------------------------------------------------------------------
	// register_provider() tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that register_provider() registers the ollama provider with the registry.
	 */
	public function test_register_provider_registers_ollama_with_registry(): void {
		$this->reset_registry();
		$this->plugin->register_provider();
		$this->assertTrue( AiClient::defaultRegistry()->hasProvider( 'ollama' ) );
	}

	/**
	 * Tests that register_provider() sets OLLAMA_HOST from the WordPress option when not already set.
	 */
	public function test_register_provider_sets_env_var_from_option(): void {
		update_option( 'wp_ai_client_ollama_settings', array( 'host' => 'http://my-server:11434' ) );
		putenv( 'OLLAMA_HOST=' );

		$this->plugin->register_provider();

		$this->assertSame( 'http://my-server:11434', getenv( 'OLLAMA_HOST' ) );
	}

	/**
	 * Tests that register_provider() does not override an already-set OLLAMA_HOST env var.
	 */
	public function test_register_provider_does_not_override_existing_env_var(): void {
		putenv( 'OLLAMA_HOST=http://existing:11434' );
		update_option( 'wp_ai_client_ollama_settings', array( 'host' => 'http://different:11434' ) );

		$this->plugin->register_provider();

		$this->assertSame( 'http://existing:11434', getenv( 'OLLAMA_HOST' ) );
	}

	/**
	 * Tests that calling register_provider() twice does not throw an exception.
	 */
	public function test_register_provider_is_idempotent(): void {
		$this->reset_registry();
		$this->plugin->register_provider();
		$this->plugin->register_provider(); // Second call should be a no-op.
		$this->assertTrue( AiClient::defaultRegistry()->hasProvider( 'ollama' ) );
	}

	// -----------------------------------------------------------------------
	// register_fallback_auth() tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that register_fallback_auth() sets an empty API key when no auth is configured yet.
	 */
	public function test_register_fallback_auth_sets_empty_api_key_for_local_ollama(): void {
		$this->reset_registry();
		$this->plugin->register_provider();

		// Confirm no auth is set yet (registry did not find OLLAMA_API_KEY env var).
		$registry = AiClient::defaultRegistry();
		$auth      = $registry->getProviderRequestAuthentication( 'ollama' );
		if ( null !== $auth ) {
			// If default auth was already set by the registry, skip this test gracefully.
			$this->markTestSkipped( 'Registry already set default auth; cannot test fallback.' );
		}

		$this->plugin->register_fallback_auth();

		$auth = $registry->getProviderRequestAuthentication( 'ollama' );
		$this->assertInstanceOf( ApiKeyRequestAuthentication::class, $auth );
		$this->assertSame( '', $auth->getApiKey() );
	}

	/**
	 * Tests that register_fallback_auth() does not overwrite existing authentication.
	 */
	public function test_register_fallback_auth_does_not_override_existing_auth(): void {
		$this->reset_registry();
		$this->plugin->register_provider();

		$registry = AiClient::defaultRegistry();
		$real_auth = new ApiKeyRequestAuthentication( 'real-api-key' );
		$registry->setProviderRequestAuthentication( 'ollama', $real_auth );

		$this->plugin->register_fallback_auth();

		$auth = $registry->getProviderRequestAuthentication( 'ollama' );
		$this->assertInstanceOf( ApiKeyRequestAuthentication::class, $auth );
		$this->assertSame( 'real-api-key', $auth->getApiKey() );
	}
}

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
 * @covers \Fueled\AiProviderForOllama\Plugin
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
		delete_option( 'ai_provider_for_ollama_settings' );
	}

	protected function tearDown(): void {
		$this->restore_ollama_host();
		delete_option( 'ai_provider_for_ollama_settings' );
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
		$registry_prop->setValue( null, null );

		// Clear AbstractProvider static caches so providers re-create fresh instances.
		$provider_reflection = new \ReflectionClass( AbstractProvider::class );
		foreach ( array( 'metadataCache', 'availabilityCache', 'modelMetadataDirectoryCache' ) as $prop_name ) {
			$prop = $provider_reflection->getProperty( $prop_name );
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
	 * Tests that init() registers register_fallback_auth at priority 15 on the init hook.
	 */
	public function test_init_registers_register_fallback_auth_at_priority_15(): void {
		$this->plugin->init();
		$this->assertSame(
			15,
			has_action( 'init', array( $this->plugin, 'register_fallback_auth' ) )
		);
	}

	/**
	 * Tests that init() registers initialize_settings on the init hook.
	 */
	public function test_init_registers_initialize_settings(): void {
		$this->plugin->init();
		$this->assertNotFalse( has_action( 'init', array( $this->plugin, 'initialize_settings' ) ) );
	}

	/**
	 * Tests that init() registers plugin_action_links for this plugin.
	 */
	public function test_init_registers_plugin_action_links_filter(): void {
		$this->plugin->init();
		$this->assertNotFalse(
			has_filter(
				'plugin_action_links_' . plugin_basename( AI_PROVIDER_FOR_OLLAMA_PLUGIN_FILE ),
				array( $this->plugin, 'plugin_action_links' )
			)
		);
	}

	/**
	 * Tests that init() registers localhost and safe-port HTTP filters.
	 */
	public function test_init_registers_http_filters(): void {
		$this->plugin->init();
		$this->assertNotFalse( has_filter( 'http_request_host_is_external', array( $this->plugin, 'allow_localhost_requests' ) ) );
		$this->assertNotFalse( has_filter( 'http_allowed_safe_ports', array( $this->plugin, 'allow_ollama_ports' ) ) );
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
		update_option( 'ai_provider_for_ollama_settings', array( 'host' => 'http://my-server:11434' ) );
		putenv( 'OLLAMA_HOST=' );

		$this->plugin->register_provider();

		$this->assertSame( 'http://my-server:11434', getenv( 'OLLAMA_HOST' ) );
	}

	/**
	 * Tests that register_provider() does not override an already-set OLLAMA_HOST env var.
	 */
	public function test_register_provider_does_not_override_existing_env_var(): void {
		putenv( 'OLLAMA_HOST=http://existing:11434' );
		update_option( 'ai_provider_for_ollama_settings', array( 'host' => 'http://different:11434' ) );

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
		$this->assertSame( $real_auth, $auth );
	}

	/**
	 * Tests that plugin_action_links prepends a settings link.
	 */
	public function test_plugin_action_links_prepends_settings_link(): void {
		$links  = array( '<a href="plugins.php">Plugins</a>' );
		$result = $this->plugin->plugin_action_links( $links );

		$this->assertCount( 2, $result );
		$this->assertStringContainsString( 'options-general.php?page=ai-provider-for-ollama', $result[0] );
	}

	/**
	 * Tests that allow_localhost_requests returns true for Ollama host URLs.
	 */
	public function test_allow_localhost_requests_returns_true_for_ollama_host(): void {
		putenv( 'OLLAMA_HOST=http://localhost:11434' );
		$result = $this->plugin->allow_localhost_requests( false, 'localhost', 'http://localhost:11434/api/tags' );

		$this->assertTrue( $result );
	}

	/**
	 * Tests that allow_localhost_requests keeps the original value for other URLs.
	 */
	public function test_allow_localhost_requests_returns_original_for_other_hosts(): void {
		putenv( 'OLLAMA_HOST=http://localhost:11434' );
		$result = $this->plugin->allow_localhost_requests( false, 'example.com', 'https://example.com/api' );

		$this->assertFalse( $result );
	}

	/**
	 * Tests that allow_localhost_requests only allows URLs that point at the Ollama host.
	 *
	 * @dataProvider data_allow_localhost_requests
	 *
	 * @param string $ollama_host The configured Ollama host.
	 * @param string $url         The requested URL.
	 * @param bool   $expected    Whether the request should be allowed.
	 */
	public function test_allow_localhost_requests( string $ollama_host, string $url, bool $expected ): void {
		putenv( 'OLLAMA_HOST=' . $ollama_host );
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );

		$this->assertSame( $expected, $this->plugin->allow_localhost_requests( false, $host, $url ) );
	}

	/**
	 * Data provider for test_allow_localhost_requests().
	 *
	 * @return array<string, array{string, string, bool}>
	 */
	public function data_allow_localhost_requests(): array {
		return array(
			'default host'                        => array( 'http://localhost:11434', 'http://localhost:11434/api/tags', true ),
			'host is case-insensitive'            => array( 'http://localhost:11434', 'http://LOCALHOST:11434/api/tags', true ),
			'LAN host with port'                  => array( 'http://10.0.0.9:11434', 'http://10.0.0.9:11434/api/show', true ),
			'LAN host without port'               => array( 'http://10.0.0.9', 'http://10.0.0.9/api/tags', true ),
			'host without scheme'                 => array( 'localhost:11434', 'http://localhost:11434/api/tags', true ),
			'Ollama host in query string'         => array( 'http://localhost:11434', 'http://127.0.0.1/?x=http://localhost:11434', false ),
			'Ollama host in path'                 => array( 'http://localhost:11434', 'http://192.168.1.1/http://localhost:11434', false ),
			'Ollama host in fragment'             => array( 'http://localhost:11434', 'http://10.0.0.1/#http://localhost:11434', false ),
			'Ollama Cloud host in query string'   => array( 'https://ollama.com', 'http://127.0.0.1/?u=https://ollama.com', false ),
			'same host, different port'           => array( 'http://localhost:11434', 'http://localhost:8080/', false ),
			'same host, default port'             => array( 'http://localhost:11434', 'http://localhost/', false ),
			'Ollama host as subdomain prefix'     => array( 'http://localhost:11434', 'http://localhost.evil.test:11434/', false ),
			'other local host'                    => array( 'http://localhost:11434', 'http://192.168.1.1/', false ),
			'subdirectory host'                   => array( 'http://localhost/ollama-ai', 'http://localhost/ollama-ai/api/tags', true ),
			'subdirectory host, trailing slash'   => array( 'http://localhost/ollama-ai/', 'http://localhost/ollama-ai/api/tags', true ),
			'subdirectory host, exact path'       => array( 'http://localhost/ollama-ai', 'http://localhost/ollama-ai', true ),
			'subdirectory host, nested path'      => array( 'http://localhost:8080/proxy/ollama', 'http://localhost:8080/proxy/ollama/v1/chat/completions', true ),
			'subdirectory host, other path'       => array( 'http://localhost/ollama-ai', 'http://localhost/wp-admin/', false ),
			'subdirectory host, root path'        => array( 'http://localhost/ollama-ai', 'http://localhost/', false ),
			'subdirectory host, no path'          => array( 'http://localhost/ollama-ai', 'http://localhost', false ),
			'subdirectory host, path prefix'      => array( 'http://localhost/ollama-ai', 'http://localhost/ollama-ai-evil/api/tags', false ),
			'subdirectory host, parent path'      => array( 'http://localhost/proxy/ollama', 'http://localhost/proxy/api/tags', false ),
			'subdirectory host, dot segment'      => array( 'http://localhost/ollama-ai', 'http://localhost/ollama-ai/../wp-admin/', false ),
			'subdirectory host, single dot'       => array( 'http://localhost/ollama-ai', 'http://localhost/ollama-ai/./api/tags', false ),
			'subdirectory host, encoded dots'     => array( 'http://localhost/ollama-ai', 'http://localhost/ollama-ai/%2e%2e/wp-admin/', false ),
			'subdirectory host, double encoded'   => array( 'http://localhost/ollama-ai', 'http://localhost/ollama-ai/%252e%252e/wp-admin/', false ),
			'subdirectory host, encoded slash'    => array( 'http://localhost/ollama-ai', 'http://localhost/ollama-ai/..%2fwp-admin/', false ),
			'subdirectory host, backslash'        => array( 'http://localhost/ollama-ai', 'http://localhost/ollama-ai/..\\wp-admin/', false ),
			'subdirectory host, path parameter'   => array( 'http://localhost/ollama-ai', 'http://localhost/ollama-ai/..;x/wp-admin/', false ),
			'subdirectory host, path in query'    => array( 'http://localhost/ollama-ai', 'http://localhost/?x=/ollama-ai/', false ),
			'subdirectory host, dots in name'     => array( 'http://localhost/ollama-ai', 'http://localhost/ollama-ai/api/show/llama3.2...', true ),
		);
	}

	/**
	 * Tests that allow_localhost_requests keeps an already-allowed request allowed.
	 */
	public function test_allow_localhost_requests_keeps_existing_true(): void {
		putenv( 'OLLAMA_HOST=http://localhost:11434' );
		$result = $this->plugin->allow_localhost_requests( true, '127.0.0.1', 'http://127.0.0.1/' );

		$this->assertTrue( $result );
	}

	/**
	 * Tests that allow_ollama_ports appends the configured Ollama port for Ollama requests.
	 */
	public function test_allow_ollama_ports_appends_configured_port(): void {
		putenv( 'OLLAMA_HOST=http://localhost:11434' );
		$ports = $this->plugin->allow_ollama_ports( array( 80, 443 ), 'localhost', 'http://localhost:11434/api/tags' );

		$this->assertContains( 11434, $ports );
	}

	/**
	 * Tests that allow_ollama_ports does not add the Ollama port for other hosts.
	 */
	public function test_allow_ollama_ports_keeps_ports_for_other_hosts(): void {
		putenv( 'OLLAMA_HOST=http://localhost:11434' );
		$ports = $this->plugin->allow_ollama_ports( array( 80, 443 ), 'example.com', 'http://example.com:11434/?x=http://localhost:11434' );

		$this->assertSame( array( 80, 443 ), $ports );
	}

	/**
	 * Tests that allow_ollama_ports only adds the port for requests within the Ollama host's path.
	 */
	public function test_allow_ollama_ports_respects_host_path(): void {
		putenv( 'OLLAMA_HOST=http://localhost:8080/ollama-ai' );

		$this->assertContains( 8080, $this->plugin->allow_ollama_ports( array( 80, 443 ), 'localhost', 'http://localhost:8080/ollama-ai/api/tags' ) );
		$this->assertSame( array( 80, 443 ), $this->plugin->allow_ollama_ports( array( 80, 443 ), 'localhost', 'http://localhost:8080/other/' ) );
	}

	/**
	 * Tests that allow_ollama_ports keeps ports unchanged when host has no port.
	 */
	public function test_allow_ollama_ports_keeps_ports_when_no_host_port(): void {
		putenv( 'OLLAMA_HOST=http://localhost' );
		$ports = $this->plugin->allow_ollama_ports( array( 80, 443 ), 'localhost', 'http://localhost/api/tags' );

		$this->assertSame( array( 80, 443 ), $ports );
	}

	/**
	 * Tests that wp_http_validate_url() accepts the Ollama host but rejects
	 * local URLs that only mention it.
	 */
	public function test_wp_http_validate_url_with_filters(): void {
		putenv( 'OLLAMA_HOST=http://localhost:11434' );
		$this->plugin->init();

		$this->assertSame( 'http://localhost:11434/api/tags', wp_http_validate_url( 'http://localhost:11434/api/tags' ) );
		$this->assertFalse( wp_http_validate_url( 'http://127.0.0.1/?x=http://localhost:11434' ) );
		$this->assertFalse( wp_http_validate_url( 'http://127.0.0.1:11434/' ) );
	}
}

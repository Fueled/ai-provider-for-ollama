<?php

/**
 * Plugin initializer class.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Fueled\AiProviderForOllama\Provider\OllamaProvider;
use Fueled\AiProviderForOllama\Settings\OllamaSettings;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;

/**
 * Plugin class.
 *
 * @since 1.0.0
 */
class Plugin {

	/**
	 * Initializes the plugin.
	 *
	 * @since 1.0.0
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register_provider' ), 5 );
		add_action( 'init', array( $this, 'register_fallback_auth' ), 15 );
		add_action( 'init', array( $this, 'initialize_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( AI_PROVIDER_FOR_OLLAMA_PLUGIN_FILE ), array( $this, 'plugin_action_links' ) );
		add_filter( 'http_request_host_is_external', array( $this, 'allow_localhost_requests' ), 10, 3 );
		add_filter( 'http_allowed_safe_ports', array( $this, 'allow_ollama_ports' ), 10, 3 );
	}

	/**
	 * Gets the Ollama host.
	 *
	 * @since 1.0.0
	 *
	 * @return string The Ollama host.
	 */
	private function get_ollama_host(): string {
		// Get the OLLAMA_HOST environment variable if set.
		$host = getenv( 'OLLAMA_HOST' );
		if ( false !== $host && '' !== $host ) {
			return $host;
		}

		// Get the Ollama host from the WordPress option if set.
		$settings = OllamaSettings::get_settings();
		if ( isset( $settings['host'] ) && '' !== $settings['host'] ) {
			return $settings['host'];
		}

		return 'http://localhost:11434';
	}

	/**
	 * Sets the OLLAMA_HOST environment variable.
	 *
	 * @since 1.0.0
	 */
	private function set_ollama_host(): void {
		$host = $this->get_ollama_host();

		if ( '' === $host ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Required to set OLLAMA_HOST for the provider SDK.
		putenv( 'OLLAMA_HOST=' . $host );
	}

	/**
	 * Registers the Ollama provider with the AI Client.
	 *
	 * @since 1.0.0
	 */
	public function register_provider(): void {
		if ( ! class_exists( AiClient::class ) ) {
			return;
		}

		$this->set_ollama_host();

		$registry = AiClient::defaultRegistry();

		if ( $registry->hasProvider( OllamaProvider::class ) ) {
			return;
		}

		$registry->registerProvider( OllamaProvider::class );
	}

	/**
	 * Registers fallback authentication for the Ollama provider.
	 *
	 * If no API key was provided via wp-ai-client (which passes credentials at priority 10),
	 * this registers an empty API key so that local Ollama instances work without configuration.
	 *
	 * @since 1.0.0
	 */
	public function register_fallback_auth(): void {
		if ( ! class_exists( AiClient::class ) ) {
			return;
		}

		$registry = AiClient::defaultRegistry();

		if ( ! $registry->hasProvider( 'ollama' ) ) {
			return;
		}

		// Only set fallback if no authentication has been configured yet.
		$auth = $registry->getProviderRequestAuthentication( 'ollama' );
		if ( null !== $auth ) {
			return;
		}

		$registry->setProviderRequestAuthentication(
			'ollama',
			new ApiKeyRequestAuthentication( '' )
		);
	}

	/**
	 * Initializes the Ollama settings.
	 *
	 * @since 1.0.0
	 */
	public function initialize_settings(): void {
		$settings = new OllamaSettings();
		$settings->init();
	}

	/**
	 * Adds action links to the plugin list table.
	 *
	 * This adds "Settings" link to the plugin's action links
	 * on the Plugins page.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string> $links Existing action links.
	 * @return array<string> Modified action links.
	 */
	public function plugin_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			admin_url( 'options-general.php?page=ai-provider-for-ollama' ),
			esc_html__( 'Settings', 'ai-provider-for-ollama' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Allows requests to the Ollama host, even when it resolves to a local address.
	 *
	 * @since 1.0.0
	 * @since x.x.x Only matches requests whose host and port are the Ollama host's.
	 *
	 * @param bool   $external Whether the request is external.
	 * @param string $host     The host of the request.
	 * @param string $url      The URL of the request.
	 * @return bool Whether the request is allowed.
	 */
	public function allow_localhost_requests( $external, $host, $url ): bool {
		if ( $this->is_ollama_url( (string) $url ) ) {
			return true;
		}

		return (bool) $external;
	}

	/**
	 * Allows the Ollama port for requests to the Ollama host.
	 *
	 * @since 1.0.0
	 * @since x.x.x Only adds the port for requests to the Ollama host.
	 *
	 * @param array<int> $ports The allowed ports.
	 * @param string     $host  The host of the request.
	 * @param string     $url   The URL of the request.
	 * @return array<int> The allowed ports.
	 */
	public function allow_ollama_ports( $ports, $host, $url ): array {
		if ( ! $this->is_ollama_url( (string) $url ) ) {
			return $ports;
		}

		$ollama_port = wp_parse_url( $this->get_ollama_host(), PHP_URL_PORT );

		if ( ! $ollama_port ) {
			return $ports;
		}

		return array_merge( $ports, array( $ollama_port ) );
	}

	/**
	 * Checks whether a URL points at the Ollama host.
	 *
	 * @since x.x.x
	 *
	 * @param string $url The URL to check.
	 * @return bool Whether the URL points at the Ollama host.
	 */
	private function is_ollama_url( string $url ): bool {
		$ollama_url  = wp_parse_url( $this->get_ollama_host() );
		$request_url = wp_parse_url( $url );

		if ( empty( $ollama_url['host'] ) || empty( $request_url['host'] ) ) {
			return false;
		}

		if ( strtolower( $ollama_url['host'] ) !== strtolower( $request_url['host'] ) ) {
			return false;
		}

		if ( $this->get_url_port( $ollama_url ) !== $this->get_url_port( $request_url ) ) {
			return false;
		}

		$ollama_path = isset( $ollama_url['path'] ) ? rtrim( (string) $ollama_url['path'], '/' ) : '';

		if ( '' === $ollama_path ) {
			return true;
		}

		$request_path = isset( $request_url['path'] ) ? (string) $request_url['path'] : '';

		// Dot segments would let a URL that starts with the Ollama path resolve outside of it.
		if ( $this->has_dot_segment( $request_path ) ) {
			return false;
		}

		return $request_path === $ollama_path || 0 === strpos( $request_path, $ollama_path . '/' );
	}

	/**
	 * Checks whether a URL path contains a `.` or `..` segment, including encoded forms.
	 *
	 * @since x.x.x
	 *
	 * @param string $path The URL path.
	 * @return bool Whether the path contains a dot segment.
	 */
	private function has_dot_segment( string $path ): bool {
		// Decode until stable so double-encoded segments (e.g. %252e%252e) are caught too.
		do {
			$previous = $path;
			$path     = rawurldecode( $path );
		} while ( $path !== $previous );

		// Some servers treat backslashes as separators.
		$segments = explode( '/', str_replace( '\\', '/', $path ) );

		foreach ( $segments as $segment ) {
			// Some servers ignore path parameters, so `..;foo` is treated as `..`.
			$segment = explode( ';', $segment )[0];

			if ( '.' === $segment || '..' === $segment ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Gets the port of a parsed URL, falling back to the default port for its scheme.
	 *
	 * @since x.x.x
	 *
	 * @param array<string, int|string> $parsed_url The URL, as returned by wp_parse_url().
	 * @return int|null The port, or null if it cannot be determined.
	 */
	private function get_url_port( array $parsed_url ): ?int {
		if ( isset( $parsed_url['port'] ) ) {
			return (int) $parsed_url['port'];
		}

		$scheme = isset( $parsed_url['scheme'] ) ? strtolower( (string) $parsed_url['scheme'] ) : 'http';

		if ( 'https' === $scheme ) {
			return 443;
		}

		if ( 'http' === $scheme ) {
			return 80;
		}

		return null;
	}
}

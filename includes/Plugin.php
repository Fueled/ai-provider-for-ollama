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
		add_filter( 'http_allowed_safe_ports', array( $this, 'allow_ollama_ports' ) );
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
	 * Allows localhost requests to the Ollama host.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $external Whether the request is external.
	 * @param string $host The host of the request.
	 * @param string $url The URL of the request.
	 * @return bool Whether the request is allowed.
	 */
	public function allow_localhost_requests( $external, $host, $url ): bool {
		if ( strpos( $url, $this->get_ollama_host() ) !== false ) {
			return true;
		}

		return $external;
	}

	/**
	 * Allows Ollama ports.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int> $ports The ports.
	 * @return array<int> The allowed ports.
	 */
	public function allow_ollama_ports( $ports ): array {
		$ollama_host = $this->get_ollama_host();
		$ollama_port = wp_parse_url( $ollama_host, PHP_URL_PORT );

		if ( ! $ollama_port ) {
			return $ports;
		}

		return array_merge( $ports, array( $ollama_port ) );
	}
}

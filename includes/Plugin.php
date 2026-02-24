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
use WordPress\AiClient\Providers\Http\HttpTransporterFactory;

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
		add_action( 'init', array( $this, 'ensure_http_transporter' ), 15 );
		add_action( 'init', array( $this, 'register_fallback_auth' ), 20 );
		add_action( 'init', array( $this, 'initialize_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( AI_PROVIDER_FOR_OLLAMA_PLUGIN_FILE ), array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Sets the OLLAMA_HOST environment variable from the WordPress option.
	 *
	 * @since 1.0.0
	 */
	private function set_ollama_host_from_option(): void {
		// Check if the OLLAMA_HOST environment variable is already set.
		$env_host = getenv( 'OLLAMA_HOST' );
		if ( false !== $env_host && '' !== $env_host ) {
			return;
		}

		// Get the Ollama host from the WordPress option.
		$settings = get_option( 'wp_ai_client_ollama_settings', array() );
		if ( ! is_array( $settings ) || ! isset( $settings['host'] ) || '' === $settings['host'] ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Required to set OLLAMA_HOST for the provider SDK.
		putenv( 'OLLAMA_HOST=' . $settings['host'] );
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

		$this->set_ollama_host_from_option();

		$registry = AiClient::defaultRegistry();

		if ( $registry->hasProvider( OllamaProvider::class ) ) {
			return;
		}

		$registry->registerProvider( OllamaProvider::class );
	}

	/**
	 * Ensures the HTTP transporter is set on the registry.
	 *
	 * Providers register at priority 5, before the WordPress PSR-18 HTTP
	 * client discovery strategy is available (registered at priority 10 by
	 * wp-ai-client). This method runs at priority 15 to trigger transporter
	 * creation after the strategy is in place.
	 *
	 * @since 1.0.0
	 */
	public function ensure_http_transporter(): void {
		if ( ! class_exists( AiClient::class ) ) {
			return;
		}

		$registry = AiClient::defaultRegistry();

		try {
			$registry->getHttpTransporter();
		} catch ( \Throwable $e ) {
			try {
				$registry->setHttpTransporter( HttpTransporterFactory::createTransporter() );
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Graceful degradation when no PSR-18 client is available.
			}
		}
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
			admin_url( 'options-general.php?page=wp-ai-client-ollama' ),
			esc_html__( 'Settings', 'ai-provider-for-ollama' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}
}

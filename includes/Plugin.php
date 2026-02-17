<?php

/**
 * Plugin initializer class.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace WordPress\AiClientProviderOllama;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClientProviderOllama\Provider\OllamaProvider;
use WordPress\AiClientProviderOllama\Settings\OllamaSettings;

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
		add_action( 'init', array( $this, 'register_fallback_auth' ), 20 );
		add_action( 'init', array( $this, 'register_settings' ) );
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
	 * Registers fallback authentication for the Ollama provider.
	 *
	 * If no API key was provided via wp-ai-client (which passes credentials at priority 10),
	 * this registers an empty API key so that local Ollama instances work without configuration.
	 * Local Ollama ignores empty Bearer tokens.
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
	 * Registers the Ollama settings in the WordPress admin.
	 *
	 * @since 1.0.0
	 */
	public function register_settings(): void {
		$settings = new OllamaSettings();
		$settings->register();
	}
}

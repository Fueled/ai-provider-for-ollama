<?php
/**
 * Shared Ollama request options preparation.
 *
 * @package Fueled\AiProviderForOllama\Models\Traits
 * @since   1.1.0
 */

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Models\Traits;

use WordPress\AiClient\Providers\Http\DTO\RequestOptions;

/**
 * Trait for preparing request options with configurable timeout defaults.
 *
 * @since 1.1.0
 */
trait OllamaRequestOptionsTrait {

	/**
	 * Prepares request options with timeout defaults and custom overrides.
	 *
	 * Supported custom options:
	 *  - ollama.request_timeout (seconds)
	 *  - ollama.connect_timeout (seconds)
	 *
	 * The resolved timeout values can also be overridden using WordPress filters:
	 *  - ai_provider_for_ollama_request_timeout (float $timeout)
	 *  - ai_provider_for_ollama_connect_timeout (float $timeout)
	 *
	 * Custom options take precedence over filter defaults. Filters are applied
	 * after both the hard-coded default and any custom option value are resolved,
	 * so a filter receives the most-specific value set so far.
	 *
	 * @since 1.1.0
	 *
	 * @param float $default_request_timeout Default request timeout in seconds.
	 * @param float $default_connect_timeout Default connect timeout in seconds.
	 * @return \WordPress\AiClient\Providers\Http\DTO\RequestOptions Prepared request options.
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
	protected function prepareRequestOptions(
		float $default_request_timeout,
		float $default_connect_timeout
	): RequestOptions {
		$existing_options = $this->getRequestOptions();
		if ( null !== $existing_options ) {
			$request_options = RequestOptions::fromArray( $existing_options->toArray() );
		} else {
			$request_options = new RequestOptions();
		}

		$custom_options = $this->getConfig()->getCustomOptions();

		$request_timeout = $default_request_timeout;
		if ( isset( $custom_options['ollama.request_timeout'] ) && is_numeric( $custom_options['ollama.request_timeout'] ) ) {
			$request_timeout = (float) $custom_options['ollama.request_timeout'];
		}

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filters the request timeout for Ollama API requests.
			 *
			 * The value is resolved from the hard-coded default and any `ollama.request_timeout`
			 * custom option before this filter is applied, so the filter always receives the
			 * most-specific value that has been set so far.
			 *
			 * Example usage to set a 120-second timeout for all Ollama requests:
			 *
			 *     add_filter( 'ai_provider_for_ollama_request_timeout', function() {
			 *         return 120.0;
			 *     } );
			 *
			 * @since 1.3.0
			 *
			 * @param float $request_timeout The request timeout in seconds.
			 */
			$request_timeout = (float) apply_filters( 'ai_provider_for_ollama_request_timeout', $request_timeout );
		}

		$connect_timeout = $default_connect_timeout;
		if ( isset( $custom_options['ollama.connect_timeout'] ) && is_numeric( $custom_options['ollama.connect_timeout'] ) ) {
			$connect_timeout = (float) $custom_options['ollama.connect_timeout'];
		}

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filters the connection timeout for Ollama API requests.
			 *
			 * The value is resolved from the hard-coded default and any `ollama.connect_timeout`
			 * custom option before this filter is applied, so the filter always receives the
			 * most-specific value that has been set so far.
			 *
			 * Example usage to set a 20-second connect timeout for all Ollama requests:
			 *
			 *     add_filter( 'ai_provider_for_ollama_connect_timeout', function() {
			 *         return 20.0;
			 *     } );
			 *
			 * @since 1.3.0
			 *
			 * @param float $connect_timeout The connection timeout in seconds.
			 */
			$connect_timeout = (float) apply_filters( 'ai_provider_for_ollama_connect_timeout', $connect_timeout );
		}

		$request_options->setTimeout( $request_timeout );

		if ( null === $request_options->getConnectTimeout() ) {
			$request_options->setConnectTimeout( $connect_timeout );
		}

		return $request_options;
	}
}

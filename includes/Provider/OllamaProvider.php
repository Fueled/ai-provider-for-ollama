<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Provider;

use Fueled\AiProviderForOllama\Metadata\OllamaModelMetadataDirectory;
use Fueled\AiProviderForOllama\Models\OllamaTextGenerationModel;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

/**
 * Class for the Ollama provider.
 *
 * @since 1.0.0
 */
class OllamaProvider extends AbstractApiProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function baseUrl(): string {
		$host = getenv( 'OLLAMA_HOST' );
		if ( false !== $host && '' !== $host ) {
			return rtrim( $host, '/' );
		}

		return 'http://localhost:11434';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createModel(
		ModelMetadata $model_metadata,
		ProviderMetadata $provider_metadata
	): ModelInterface {
		$capabilities = $model_metadata->getSupportedCapabilities();
		foreach ( $capabilities as $capability ) {
			if ( $capability->isTextGeneration() ) {
				return new OllamaTextGenerationModel( $model_metadata, $provider_metadata );
			}
		}

		throw new RuntimeException(
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not output.
			'Unsupported model capabilities: ' . implode( ', ', $capabilities )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		$provider_meta = array(
			'ollama',
			'Ollama',
			ProviderTypeEnum::cloud(),
			'https://ollama.com/settings/keys',
			RequestAuthenticationMethod::apiKey()
		);

		// Provider description support was added in 1.2.0.
		if ( version_compare( AiClient::VERSION, '1.2.0', '>=' ) ) {
			if ( function_exists( '__' ) ) {
				$provider_meta[] = __( 'Text generation with Ollama, either running locally or on Ollama Cloud.', 'ai-provider-for-ollama' );
			} else {
				$provider_meta[] = 'Text generation with Ollama, either running locally or on Ollama Cloud.';
			}
		}

		return new ProviderMetadata( ...$provider_meta );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		return new OllamaProviderAvailability();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new OllamaModelMetadataDirectory();
	}
}

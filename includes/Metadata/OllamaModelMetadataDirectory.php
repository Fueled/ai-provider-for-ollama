<?php

declare( strict_types=1 );

namespace WordPress\AiClientProviderOllama\Metadata;

use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleModelMetadataDirectory;
use WordPress\AiClientProviderOllama\Provider\OllamaProvider;

/**
 * Class for the Ollama model metadata directory.
 *
 * @since 1.0.0
 *
 * @phpstan-type ModelsResponseData array{
 *     data: list<array{id: string}>
 * }
 */
class OllamaModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected function createRequest( HttpMethodEnum $method, string $path, array $headers = array(), $data = null ): Request {
		return new Request(
			$method,
			OllamaProvider::url( $path ),
			$headers,
			$data
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected function parseResponseToModelMetadataList( Response $response ): array {
		/** @var ModelsResponseData $response_data */
		$response_data = $response->getData();
		if ( ! isset( $response_data['data'] ) || ! $response_data['data'] ) {
			throw ResponseException::fromMissingData( 'Ollama', 'data' );
		}

		$capabilities = array(
			CapabilityEnum::textGeneration(),
			CapabilityEnum::chatHistory(),
		);
		$options      = array(
			new SupportedOption( OptionEnum::systemInstruction() ),
			new SupportedOption(OptionEnum::candidateCount()),
			new SupportedOption( OptionEnum::maxTokens() ),
			new SupportedOption( OptionEnum::temperature() ),
			new SupportedOption( OptionEnum::topP() ),
			new SupportedOption( OptionEnum::topK() ),
			new SupportedOption( OptionEnum::stopSequences() ),
			new SupportedOption( OptionEnum::frequencyPenalty() ),
			new SupportedOption( OptionEnum::presencePenalty() ),
			new SupportedOption( OptionEnum::outputMimeType(), array( 'text/plain', 'application/json' ) ),
			new SupportedOption( OptionEnum::outputSchema() ),
			new SupportedOption( OptionEnum::functionDeclarations() ),
			new SupportedOption( OptionEnum::customOptions() ),
			new SupportedOption( OptionEnum::inputModalities(), array( array( ModalityEnum::text() ) ) ),
			new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::text() ) ) ),
		);

		$models_data = (array) $response_data['data'];

		$models = array_values(
			array_map(
				static function ( array $model_data ) use ( $capabilities, $options ): ModelMetadata {
					$model_id = $model_data['id'];
					return new ModelMetadata(
						$model_id,
						$model_id,
						$capabilities,
						$options
					);
				},
				$models_data
			)
		);

		// Sort alphabetically by model ID.
		usort(
			$models,
			static function ( ModelMetadata $a, ModelMetadata $b ): int {
				return strcmp( $a->getId(), $b->getId() );
			}
		);

		return $models;
	}
}

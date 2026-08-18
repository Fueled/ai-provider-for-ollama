<?php
/**
 * Ollama embedding generation model.
 *
 * @package Fueled\AiProviderForOllama\Models
 * @since   x.x.x
 */

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Models;

use Fueled\AiProviderForOllama\Models\Traits\OllamaRequestOptionsTrait;
use Fueled\AiProviderForOllama\Provider\OllamaProvider;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\EmbeddingGeneration\Contracts\EmbeddingGenerationModelInterface;
use WordPress\AiClient\Results\DTO\Embedding;
use WordPress\AiClient\Results\DTO\EmbeddingResult;
use WordPress\AiClient\Results\DTO\TokenUsage;

/**
 * Class for an embedding generation model.
 *
 * Generates embeddings via Ollama's native /api/embed endpoint, which accepts a
 * batch of inputs and returns one vector per input. Works with any model
 * that reports the "embedding" capability (e.g. nomic-embed-text, mxbai-embed-large).
 *
 * @since x.x.x
 *
 * @phpstan-type ResponseData array{
 *     model?: string,
 *     embeddings?: list<list<float|int>>,
 *     total_duration?: int,
 *     load_duration?: int,
 *     prompt_eval_count?: int,
 *     ...
 * }
 */
class OllamaEmbeddingGenerationModel extends AbstractApiBasedModel implements EmbeddingGenerationModelInterface {
	use OllamaRequestOptionsTrait;

	/**
	 * Generates embeddings from one or more inputs using the Ollama API.
	 *
	 * @since x.x.x
	 *
	 * @param list<\WordPress\AiClient\Messages\DTO\MessagePart> $input The inputs to embed, one embedding generated per input.
	 * @return \WordPress\AiClient\Results\DTO\EmbeddingResult Result containing the generated embedding vectors.
	 */
	public function generateEmbeddingResult( array $input ): EmbeddingResult {
		$params          = $this->prepareGenerateEmbeddingsParams( $input );
		$request_options = $this->prepareRequestOptions( 60.0, 10.0 );

		$request = new Request(
			HttpMethodEnum::POST(),
			OllamaProvider::url( 'api/embed' ),
			array( 'Content-Type' => 'application/json' ),
			$params,
			$request_options
		);

		$request  = $this->getRequestAuthentication()->authenticateRequest( $request );
		$response = $this->getHttpTransporter()->send( $request );
		ResponseUtil::throwIfNotSuccessful( $response );

		return $this->parseResponseToEmbeddingResult( $response );
	}

	/**
	 * Prepares the given inputs and model configuration into API request parameters.
	 *
	 * @since x.x.x
	 *
	 * @param list<\WordPress\AiClient\Messages\DTO\MessagePart> $input The inputs to embed, one embedding generated per input.
	 * @return array<string, mixed> The parameters for the API request.
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the inputs are invalid or a custom option conflicts.
	 */
	private function prepareGenerateEmbeddingsParams( array $input ): array {
		if ( ! array_is_list( $input ) ) {
			throw new InvalidArgumentException( 'Embedding input must be provided as a list of message parts.' );
		}

		if ( empty( $input ) ) {
			throw new InvalidArgumentException( 'The API requires at least one input.' );
		}

		$texts = array();
		foreach ( $input as $index => $part ) {
			$texts[] = $this->preparePartInput( $part, $index );
		}

		$params = array(
			'model' => $this->metadata()->getId(),
			'input' => $texts,
		);

		$dimensions = $this->getConfig()->getDimensions();
		if ( null !== $dimensions ) {
			$params['dimensions'] = $dimensions;
		}

		// Transport-only timeout options are consumed by prepareRequestOptions(), not the payload.
		$transport_only_options = array( 'ollama.request_timeout', 'ollama.connect_timeout' );

		foreach ( $this->getConfig()->getCustomOptions() as $key => $value ) {
			if ( in_array( $key, $transport_only_options, true ) ) {
				continue;
			}

			if ( isset( $params[ $key ] ) ) {
				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw new InvalidArgumentException(
					sprintf(
						'The custom option "%s" conflicts with an existing parameter.',
						$key
					)
				);
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			$params[ $key ] = $value;
		}

		return $params;
	}

	/**
	 * Prepares a single input part into one embeddings input string.
	 *
	 * @since x.x.x
	 *
	 * @param mixed $part  The message part that makes up one embedding input.
	 * @param int   $index The index of the part within the input list, used for error messages.
	 * @return string The embedding input text.
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the part is not a non-empty text message part.
	 */
	private function preparePartInput( $part, int $index ): string {
		if ( ! $part instanceof MessagePart ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new InvalidArgumentException(
				sprintf( 'Embedding input at index %d must be a MessagePart.', $index )
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		if ( ! $part->getType()->isText() ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new InvalidArgumentException(
				sprintf( 'Ollama embedding input at index %d must be a text part.', $index )
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$text = $part->getText();
		if ( null === $text || '' === trim( $text ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new InvalidArgumentException(
				sprintf( 'Ollama embedding input at index %d must contain non-empty text.', $index )
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		return $text;
	}

	/**
	 * Parses an Ollama /api/embed response to an embedding result.
	 *
	 * @since x.x.x
	 *
	 * @param \WordPress\AiClient\Providers\Http\DTO\Response $response The Ollama API response.
	 * @return \WordPress\AiClient\Results\DTO\EmbeddingResult The parsed embedding result.
	 * @throws \WordPress\AiClient\Providers\Http\Exception\ResponseException If the response is missing or has invalid embeddings.
	 */
	private function parseResponseToEmbeddingResult( Response $response ): EmbeddingResult {
		/** @var ResponseData $response_data */
		$response_data = $response->getData();

		if ( ! isset( $response_data['embeddings'] ) || array() === $response_data['embeddings'] ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw ResponseException::fromMissingData( $this->providerMetadata()->getName(), 'embeddings' );
		}

		if ( ! is_array( $response_data['embeddings'] ) || ! array_is_list( $response_data['embeddings'] ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw ResponseException::fromInvalidData(
				$this->providerMetadata()->getName(),
				'embeddings',
				'The value must be an indexed array of embedding vectors.'
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$embeddings = array();
		foreach ( $response_data['embeddings'] as $index => $vector ) {
			if ( ! is_array( $vector ) || ! array_is_list( $vector ) || array() === $vector ) {
				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw ResponseException::fromInvalidData(
					$this->providerMetadata()->getName(),
					"embeddings[{$index}]",
					'The value must be a non-empty embedding vector.'
				);
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			$embeddings[] = new Embedding( $vector, count( $vector ) );
		}

		$prompt_tokens = isset( $response_data['prompt_eval_count'] ) && is_int( $response_data['prompt_eval_count'] )
			? $response_data['prompt_eval_count']
			: 0;
		$token_usage   = new TokenUsage( $prompt_tokens, 0, $prompt_tokens );

		$additional_data = $response_data;
		unset( $additional_data['embeddings'] );

		return new EmbeddingResult(
			'',
			$embeddings,
			count( $embeddings[0]->getValues() ),
			$token_usage,
			$this->providerMetadata(),
			$this->metadata(),
			$additional_data
		);
	}
}

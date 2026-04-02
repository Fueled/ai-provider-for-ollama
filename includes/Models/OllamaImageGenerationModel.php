<?php
/**
 * Ollama image generation model.
 *
 * @package Fueled\AiProviderForOllama\Models
 * @since   x.x.x
 */

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Models;

use Fueled\AiProviderForOllama\Provider\OllamaProvider;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\ImageGeneration\Contracts\ImageGenerationModelInterface;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;

/**
 * Class for an Ollama image generation model.
 *
 * Generates images via Ollama's native /api/generate endpoint.
 *
 * @since x.x.x
 *
 * @phpstan-type ResponseData array{
 *     model?: string,
 *     created_at?: string,
 *     response?: string,
 *     done?: bool,
 *     done_reason?: string,
 *     image?: string,
 *     ...
 * }
 */
class OllamaImageGenerationModel extends AbstractApiBasedModel implements ImageGenerationModelInterface {

	/**
	 * Generates images from a prompt using the Ollama API.
	 *
	 * @since x.x.x
	 *
	 * @param array $prompt Array of messages containing the image generation prompt.
	 * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult Result containing the generated image.
	 */
	public function generateImageResult( array $prompt ): GenerativeAiResult {
		$prompt_text     = $this->extractPromptText( $prompt );
		$mime_type       = $this->getConfig()->getOutputMimeType() ?? 'image/png';
		$request_options = $this->prepareRequestOptionsForImageGeneration();

		$request = new Request(
			HttpMethodEnum::POST(),
			OllamaProvider::url( 'api/generate' ),
			array( 'Content-Type' => 'application/json' ),
			array(
				'model'  => $this->metadata()->getId(),
				'prompt' => $prompt_text,
				'stream' => false,
			),
			$request_options
		);

		$request  = $this->getRequestAuthentication()->authenticateRequest( $request );
		$response = $this->getHttpTransporter()->send( $request );
		ResponseUtil::throwIfNotSuccessful( $response );

		return $this->parseResponseToGenerativeAiResult( $response, $mime_type );
	}

	/**
	 * Prepares request options for image generation with a longer default timeout.
	 *
	 * Ensures image-generation requests use a sufficiently high timeout even when
	 * upstream layers apply shorter defaults.
	 *
	 * Supported custom options:
	 *  - ollama.request_timeout (seconds)
	 *  - ollama.connect_timeout (seconds)
	 *
	 * @since x.x.x
	 *
	 * @return \WordPress\AiClient\Providers\Http\DTO\RequestOptions Prepared request options.
	 */
	private function prepareRequestOptionsForImageGeneration(): RequestOptions {
		$existing_options = $this->getRequestOptions();
		if ( null !== $existing_options ) {
			$request_options = RequestOptions::fromArray( $existing_options->toArray() );
		} else {
			$request_options = new RequestOptions();
		}

		$custom_options = $this->getConfig()->getCustomOptions();

		$request_timeout = 300.0;
		if ( isset( $custom_options['ollama.request_timeout'] ) && is_numeric( $custom_options['ollama.request_timeout'] ) ) {
			$request_timeout = (float) $custom_options['ollama.request_timeout'];
		}

		$connect_timeout = 10.0;
		if ( isset( $custom_options['ollama.connect_timeout'] ) && is_numeric( $custom_options['ollama.connect_timeout'] ) ) {
			$connect_timeout = (float) $custom_options['ollama.connect_timeout'];
		}

		// Always enforce request timeout for image generation to avoid 90s defaults.
		$request_options->setTimeout( $request_timeout );

		if ( null === $request_options->getConnectTimeout() ) {
			$request_options->setConnectTimeout( $connect_timeout );
		}

		return $request_options;
	}

	/**
	 * Parses an Ollama /api/generate response to a generative AI result.
	 *
	 * @since x.x.x
	 *
	 * @param \WordPress\AiClient\Providers\Http\DTO\Response $response The Ollama API response.
	 * @param string $mime_type Expected output image MIME type.
	 * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult The parsed image generation result.
	 */
	private function parseResponseToGenerativeAiResult( Response $response, string $mime_type ): GenerativeAiResult {
		/** @var ResponseData $response_data */
		$response_data = $response->getData();

		if ( ! isset( $response_data['image'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw ResponseException::fromMissingData( $this->providerMetadata()->getName(), 'image' );
		}

		if ( ! is_string( $response_data['image'] ) || '' === trim( $response_data['image'] ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw ResponseException::fromInvalidData(
				$this->providerMetadata()->getName(),
				'image',
				'The value must be a non-empty base64 string.'
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$image_base64 = trim( $response_data['image'] );
		if ( ! str_starts_with( $image_base64, 'data:' ) ) {
			$image_base64 = sprintf( 'data:%s;base64,%s', $mime_type, $image_base64 );
		}

		$image_file = new File( $image_base64, $mime_type );
		$parts      = array( new MessagePart( $image_file ) );
		$message    = new Message( MessageRoleEnum::model(), $parts );
		$candidate  = new Candidate( $message, FinishReasonEnum::stop() );

		$id = '';
		if ( isset( $response_data['created_at'] ) && is_string( $response_data['created_at'] ) ) {
			$id = $response_data['created_at'];
		}

		$provider_metadata = $response_data;
		unset( $provider_metadata['image'] );

		return new GenerativeAiResult(
			$id,
			array( $candidate ),
			new TokenUsage( 0, 0, 0 ),
			$this->providerMetadata(),
			$this->metadata(),
			$provider_metadata
		);
	}

	/**
	 * Extracts the prompt text from a single-user-message array.
	 *
	 * @since x.x.x
	 *
	 * @param \WordPress\AiClient\Messages\DTO\Message[] $messages The messages array.
	 * @return string The extracted text prompt.
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the messages are not a single user message with a text part.
	 */
	private function extractPromptText( array $messages ): string {
		if ( count( $messages ) !== 1 ) {
			throw new InvalidArgumentException(
				'Image generation requires exactly one user message as the prompt.'
			);
		}

		$message = $messages[0];
		if ( ! $message->getRole()->isUser() ) {
			throw new InvalidArgumentException(
				'Image generation requires a user-role message as the prompt.'
			);
		}

		foreach ( $message->getParts() as $part ) {
			$text = $part->getText();
			if ( null !== $text ) {
				return $text;
			}
		}

		throw new InvalidArgumentException(
			'Image generation requires a text part in the prompt message.'
		);
	}
}

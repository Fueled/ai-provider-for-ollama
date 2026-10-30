<?php
/**
 * Ollama text-to-speech conversion model.
 *
 * @package Fueled\AiProviderForOllama\Models
 * @since   1.4.0
 */

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Models;

use Fueled\AiProviderForOllama\Models\Traits\OllamaRequestOptionsTrait;
use Fueled\AiProviderForOllama\Provider\OllamaProvider;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\TextToSpeechConversion\Contracts\TextToSpeechConversionModelInterface;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;

/**
 * Class for an Ollama text-to-speech conversion model.
 *
 * Synthesizes speech via Ollama's OpenAI-compatible /v1/audio/speech endpoint,
 * with speech models such as `phonon`.
 *
 * @since 1.4.0
 */
class OllamaTextToSpeechConversionModel extends AbstractApiBasedModel implements TextToSpeechConversionModelInterface {
	use OllamaRequestOptionsTrait;

	/**
	 * The only output MIME type supported.
	 *
	 * @since 1.4.0
	 *
	 * @var string
	 */
	private const MIME_TYPE = 'audio/wav';

	/**
	 * The most characters Ollama accepts in a single request.
	 *
	 * @since 1.4.0
	 *
	 * @var int
	 */
	private const MAX_INPUT_LENGTH = 4096;

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.4.0
	 */
	public function convertTextToSpeechResult( array $prompt ): GenerativeAiResult {
		$this->validateOutputMimeType();

		$request = new Request(
			HttpMethodEnum::POST(),
			OllamaProvider::url( 'v1/audio/speech' ),
			array( 'Content-Type' => 'application/json' ),
			$this->prepareConvertParams( $prompt ),
			$this->prepareRequestOptions( 120.0, 10.0 )
		);

		$request  = $this->getRequestAuthentication()->authenticateRequest( $request );
		$response = $this->getHttpTransporter()->send( $request );
		ResponseUtil::throwIfNotSuccessful( $response );

		return $this->parseResponseToGenerativeAiResult( $response );
	}

	/**
	 * Prepares the request parameters for the speech endpoint.
	 *
	 * @since 1.4.0
	 *
	 * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt The prompt messages containing the text.
	 * @return array<string, mixed> The parameters for the API request.
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If a custom option conflicts with a parameter.
	 */
	private function prepareConvertParams( array $prompt ): array {
		$params = array(
			'model'           => $this->metadata()->getId(),
			'input'           => $this->preparePromptText( $prompt ),
			'response_format' => 'wav',
		);

		// Without a voice, Ollama uses the model's default voice.
		$voice = $this->getConfig()->getOutputSpeechVoice();
		if ( null !== $voice && '' !== $voice ) {
			$params['voice'] = $voice;
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
	 * Extracts the text to synthesize from the prompt.
	 *
	 * @since 1.4.0
	 *
	 * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt The prompt messages.
	 * @return string The text to convert to speech.
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the prompt has no text or too much of it.
	 */
	private function preparePromptText( array $prompt ): string {
		$text = '';
		foreach ( $prompt as $message ) {
			foreach ( $message->getParts() as $part ) {
				$part_text = $part->getText();
				if ( null === $part_text || '' === $part_text ) {
					continue;
				}

				$text .= ( '' === $text ? '' : "\n" ) . $part_text;
			}
		}

		if ( '' === $text ) {
			throw new InvalidArgumentException( 'The prompt must contain text to convert to speech.' );
		}

		// Ollama counts characters, not bytes.
		if ( mb_strlen( $text, 'UTF-8' ) > self::MAX_INPUT_LENGTH ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new InvalidArgumentException(
				sprintf(
					'The text to convert to speech must be at most %d characters.',
					self::MAX_INPUT_LENGTH
				)
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		return $text;
	}

	/**
	 * Ensures the configured output MIME type, if any, is one Ollama can produce.
	 *
	 * @since 1.4.0
	 *
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the configured MIME type is unsupported.
	 */
	private function validateOutputMimeType(): void {
		$mime_type = $this->getConfig()->getOutputMimeType();
		if ( null === $mime_type || '' === $mime_type || self::MIME_TYPE === $mime_type ) {
			return;
		}

		// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		throw new InvalidArgumentException(
			sprintf(
				'The output MIME type "%s" is not supported for text to speech. Only "%s" is supported.',
				$mime_type,
				self::MIME_TYPE
			)
		);
		// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	/**
	 * Parses the binary audio response into a generative AI result.
	 *
	 * @since 1.4.0
	 *
	 * @param \WordPress\AiClient\Providers\Http\DTO\Response $response The HTTP response containing raw WAV bytes.
	 * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult The result containing the audio file.
	 * @throws \WordPress\AiClient\Providers\Http\Exception\ResponseException If the response body is empty.
	 */
	private function parseResponseToGenerativeAiResult( Response $response ): GenerativeAiResult {
		$body = $response->getBody();
		if ( null === $body || '' === $body ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw ResponseException::fromMissingData( $this->providerMetadata()->getName(), 'body' );
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encoding binary audio for a data URI.
		$audio_file = new File( base64_encode( self::fixWavSizes( $body ) ), self::MIME_TYPE );
		$message    = new Message( MessageRoleEnum::model(), array( new MessagePart( $audio_file ) ) );
		$candidate  = new Candidate( $message, FinishReasonEnum::stop() );

		return new GenerativeAiResult(
			uniqid( 'ollama-tts-', true ),
			array( $candidate ),
			new TokenUsage( 0, 0, 0 ),
			$this->providerMetadata(),
			$this->metadata()
		);
	}

	/**
	 * Writes the real sizes into a WAV header.
	 *
	 * @since 1.4.0
	 *
	 * @param string $wav The WAV file contents.
	 * @return string The WAV file with corrected sizes, or the input unchanged if it is not a WAV file.
	 */
	private static function fixWavSizes( string $wav ): string {
		$length = strlen( $wav );
		if ( $length < 12 || 'RIFF' !== substr( $wav, 0, 4 ) || 'WAVE' !== substr( $wav, 8, 4 ) ) {
			return $wav;
		}

		$wav = substr_replace( $wav, pack( 'V', $length - 8 ), 4, 4 );

		// Walk the chunks to find the audio data, which runs to the end of the file.
		$offset = 12;
		while ( $offset + 8 <= $length ) {
			$chunk_id = substr( $wav, $offset, 4 );
			if ( 'data' === $chunk_id ) {
				return substr_replace( $wav, pack( 'V', $length - $offset - 8 ), $offset + 4, 4 );
			}

			$chunk_size = unpack( 'V', substr( $wav, $offset + 4, 4 ) );
			if ( false === $chunk_size ) {
				break;
			}

			// Chunks are padded to an even size.
			$offset += 8 + $chunk_size[1] + ( $chunk_size[1] % 2 );
		}

		return $wav;
	}
}

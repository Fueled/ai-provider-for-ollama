<?php
/**
 * Ollama decision model.
 *
 * @package Fueled\AiProviderForOllama\Models
 * @since   1.3.0
 */

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Models;

use Fueled\AiProviderForOllama\Decisions\DecisionAnswer;
use Fueled\AiProviderForOllama\Decisions\DecisionQuestion;
use Fueled\AiProviderForOllama\Decisions\DecisionResult;
use Fueled\AiProviderForOllama\Models\Traits\OllamaRequestOptionsTrait;
use Fueled\AiProviderForOllama\Provider\OllamaProvider;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Results\DTO\TokenUsage;

/**
 * Ollama decision model.
 *
 * Answers typed questions about a piece of state via Ollama's /v1/systemone
 * endpoint, returning a choice, a probability, or a score with its distribution
 * instead of generated text.
 *
 * @since 1.3.0
 *
 * @phpstan-type ResponseData array{
 *     model?: string,
 *     answers?: array<string, mixed>,
 *     usage?: array{input_tokens?: int, output_tokens?: int}
 * }
 */
class OllamaDecisionModel extends AbstractApiBasedModel {
	use OllamaRequestOptionsTrait;

	/**
	 * The maximum number of questions in one request.
	 *
	 * @since 1.3.0
	 *
	 * @var int
	 */
	public const MAX_QUESTIONS = 64;

	/**
	 * The maximum request body size Ollama accepts, in bytes.
	 *
	 * @since 1.3.0
	 *
	 * @var int
	 */
	public const MAX_BODY_BYTES = 65536;

	/**
	 * Answers questions about the given state.
	 *
	 * @since 1.3.0
	 *
	 * @param string|array<mixed>                                                     $state     The content to judge: a
	 *                                                                                           non-empty string, or an
	 *                                                                                           array Ollama receives as
	 *                                                                                           JSON.
	 * @param array<string, \Fueled\AiProviderForOllama\Decisions\DecisionQuestion> $questions Between 1 and 64 questions,
	 *                                                                                           keyed by the name their
	 *                                                                                           answers are returned under.
	 * @return \Fueled\AiProviderForOllama\Decisions\DecisionResult The answers.
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the state or questions are invalid.
	 * @throws \WordPress\AiClient\Providers\Http\Exception\ResponseException If the request fails or the response is
	 *                                                                       malformed.
	 */
	public function decide( $state, array $questions ): DecisionResult {
		$params  = $this->prepareDecideParams( $state, $questions );
		$request = new Request(
			HttpMethodEnum::POST(),
			OllamaProvider::url( 'v1/systemone' ),
			array( 'Content-Type' => 'application/json' ),
			$params,
			$this->prepareRequestOptions( 60.0, 10.0 )
		);

		// Ollama rejects larger bodies outright; failing here gives the caller a clearer error.
		$body_bytes = strlen( (string) $request->getBody() );
		if ( $body_bytes > self::MAX_BODY_BYTES ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new InvalidArgumentException(
				sprintf(
					'The decision request is %1$d bytes; Ollama accepts at most %2$d.',
					$body_bytes,
					self::MAX_BODY_BYTES
				)
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$request  = $this->getRequestAuthentication()->authenticateRequest( $request );
		$response = $this->getHttpTransporter()->send( $request );
		ResponseUtil::throwIfNotSuccessful( $response );

		return $this->parseResponseToDecisionResult( $response, $questions );
	}

	/**
	 * Prepares the state, questions, and model configuration into API request parameters.
	 *
	 * @since 1.3.0
	 *
	 * @param mixed        $state     The content to judge.
	 * @param array<mixed> $questions The questions, keyed by name.
	 * @return array<string, mixed> The parameters for the API request.
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the state, questions, or a custom
	 *                                                                       option are invalid.
	 */
	private function prepareDecideParams( $state, array $questions ): array {
		if ( is_string( $state ) ? '' === trim( $state ) : ( ! is_array( $state ) || array() === $state ) ) {
			throw new InvalidArgumentException( 'Decision state must be a non-empty string or array.' );
		}

		$question_count = count( $questions );
		if ( 0 === $question_count || $question_count > self::MAX_QUESTIONS ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new InvalidArgumentException(
				sprintf( 'A decision request needs between 1 and %1$d questions, %2$d given.', self::MAX_QUESTIONS, $question_count )
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$questions_data = array();
		foreach ( $questions as $name => $question ) {
			// Integer keys would make PHP encode the map as a JSON list, which Ollama rejects.
			if ( ! is_string( $name ) || '' === $name ) {
				throw new InvalidArgumentException( 'Decision questions must be keyed by non-empty string names.' );
			}

			if ( ! $question instanceof DecisionQuestion ) {
				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw new InvalidArgumentException(
					sprintf( 'The decision question "%s" must be a DecisionQuestion instance.', $name )
				);
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			$questions_data[ $name ] = $question->toArray();
		}

		$params = array(
			'model'     => $this->metadata()->getId(),
			'state'     => $state,
			'questions' => $questions_data,
		);

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
	 * Parses an Ollama /v1/systemone response to a decision result.
	 *
	 * @since 1.3.0
	 *
	 * @param \WordPress\AiClient\Providers\Http\DTO\Response                        $response  The Ollama API response.
	 * @param array<string, \Fueled\AiProviderForOllama\Decisions\DecisionQuestion> $questions The questions asked.
	 * @return \Fueled\AiProviderForOllama\Decisions\DecisionResult The parsed result.
	 * @throws \WordPress\AiClient\Providers\Http\Exception\ResponseException If an answer is missing or malformed.
	 */
	private function parseResponseToDecisionResult( Response $response, array $questions ): DecisionResult {
		/** @var ResponseData $response_data */
		$response_data = $response->getData();

		if ( ! isset( $response_data['answers'] ) || ! is_array( $response_data['answers'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw ResponseException::fromMissingData( $this->providerMetadata()->getName(), 'answers' );
		}

		$answers = array();
		foreach ( $questions as $name => $question ) {
			if ( ! array_key_exists( $name, $response_data['answers'] ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw ResponseException::fromMissingData( $this->providerMetadata()->getName(), 'answers.' . $name );
			}

			$answers[ $name ] = DecisionAnswer::fromResponseData(
				$question->getType(),
				$response_data['answers'][ $name ],
				'answers.' . $name
			);
		}

		$usage         = isset( $response_data['usage'] ) && is_array( $response_data['usage'] ) ? $response_data['usage'] : array();
		$input_tokens  = isset( $usage['input_tokens'] ) && is_int( $usage['input_tokens'] ) ? $usage['input_tokens'] : 0;
		$output_tokens = isset( $usage['output_tokens'] ) && is_int( $usage['output_tokens'] ) ? $usage['output_tokens'] : 0;

		return new DecisionResult(
			isset( $response_data['model'] ) && is_string( $response_data['model'] ) ? $response_data['model'] : $this->metadata()->getId(),
			$answers,
			new TokenUsage( $input_tokens, $output_tokens, $input_tokens + $output_tokens )
		);
	}
}

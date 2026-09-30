<?php
/**
 * The result of a decision request.
 *
 * @package Fueled\AiProviderForOllama\Decisions
 * @since   x.x.x
 */

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Decisions;

use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Results\DTO\TokenUsage;

// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- camelCase, matching the AI Client DTOs.

/**
 * Class for the answers a decision model gave to a set of questions.
 *
 * @since x.x.x
 */
class DecisionResult {

	/**
	 * The model that answered.
	 *
	 * @since x.x.x
	 *
	 * @var string
	 */
	private string $model_id;

	/**
	 * The answers, keyed by question name.
	 *
	 * @since x.x.x
	 *
	 * @var array<string, \Fueled\AiProviderForOllama\Decisions\DecisionAnswer>
	 */
	private array $answers;

	/**
	 * The token usage.
	 *
	 * @since x.x.x
	 *
	 * @var \WordPress\AiClient\Results\DTO\TokenUsage
	 */
	private TokenUsage $token_usage;

	/**
	 * Constructor.
	 *
	 * @since x.x.x
	 *
	 * @param string                                                                $model_id    The model that answered.
	 * @param array<string, \Fueled\AiProviderForOllama\Decisions\DecisionAnswer> $answers     The answers, keyed by question name.
	 * @param \WordPress\AiClient\Results\DTO\TokenUsage                            $token_usage The token usage.
	 */
	public function __construct( string $model_id, array $answers, TokenUsage $token_usage ) {
		$this->model_id    = $model_id;
		$this->answers     = $answers;
		$this->token_usage = $token_usage;
	}

	/**
	 * Returns the model that answered.
	 *
	 * @since x.x.x
	 *
	 * @return string The model ID.
	 */
	public function getModelId(): string {
		return $this->model_id;
	}

	/**
	 * Returns every answer, keyed by question name.
	 *
	 * @since x.x.x
	 *
	 * @return array<string, \Fueled\AiProviderForOllama\Decisions\DecisionAnswer> The answers.
	 */
	public function getAnswers(): array {
		return $this->answers;
	}

	/**
	 * Checks whether there is an answer for the given question.
	 *
	 * @since x.x.x
	 *
	 * @param string $name The question name.
	 * @return bool True if the question was answered.
	 */
	public function hasAnswer( string $name ): bool {
		return isset( $this->answers[ $name ] );
	}

	/**
	 * Returns the answer to the given question.
	 *
	 * @since x.x.x
	 *
	 * @param string $name The question name.
	 * @return \Fueled\AiProviderForOllama\Decisions\DecisionAnswer The answer.
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If no question by that name was asked.
	 */
	public function getAnswer( string $name ): DecisionAnswer {
		if ( ! isset( $this->answers[ $name ] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new InvalidArgumentException( sprintf( 'No answer for the question "%s".', $name ) );
		}

		return $this->answers[ $name ];
	}

	/**
	 * Returns the token usage.
	 *
	 * @since x.x.x
	 *
	 * @return \WordPress\AiClient\Results\DTO\TokenUsage The token usage.
	 */
	public function getTokenUsage(): TokenUsage {
		return $this->token_usage;
	}

	/**
	 * Returns the result as an array, in the shape of the API response.
	 *
	 * @since x.x.x
	 *
	 * @return array{model: string, answers: array<string, array<string, mixed>>, usage: array{input_tokens: int, output_tokens: int}} The result data.
	 */
	public function toArray(): array {
		return array(
			'model'   => $this->model_id,
			'answers' => array_map(
				static function ( DecisionAnswer $answer ): array {
					return $answer->toArray();
				},
				$this->answers
			),
			'usage'   => array(
				'input_tokens'  => $this->token_usage->getPromptTokens(),
				'output_tokens' => $this->token_usage->getCompletionTokens(),
			),
		);
	}
}

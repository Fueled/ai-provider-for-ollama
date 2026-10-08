<?php
/**
 * An answer from a decision model.
 *
 * @package Fueled\AiProviderForOllama\Decisions
 * @since   1.3.0
 */

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Decisions;

use WordPress\AiClient\Providers\Http\Exception\ResponseException;

// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- camelCase, matching the AI Client DTOs.

/**
 * Class for the typed answer to a single decision question.
 *
 * Which getters return a value depends on the question type:
 *  - choice: getChoice(), getProbabilities(), getConfidence().
 *  - noul:   getProbability(). Ollama reports no separate confidence; the probability is it.
 *  - score:  getScore(), getProbabilities(), getConfidence(), getLegend().
 *
 * @since 1.3.0
 */
class DecisionAnswer {

	/**
	 * The question type.
	 *
	 * @since 1.3.0
	 *
	 * @var \Fueled\AiProviderForOllama\Decisions\DecisionTypeEnum
	 */
	private DecisionTypeEnum $type;

	/**
	 * The answer value: the option key for choice, a probability for noul, a position for score.
	 *
	 * @since 1.3.0
	 *
	 * @var string|float
	 */
	private $value;

	/**
	 * The probability of each option or level.
	 *
	 * @since 1.3.0
	 *
	 * @var array<string, float>
	 */
	private array $probabilities;

	/**
	 * How concentrated the distribution is, from 0 to 1, or null for noul answers.
	 *
	 * @since 1.3.0
	 *
	 * @var float|null
	 */
	private ?float $confidence;

	/**
	 * The score level descriptions, keyed by level index.
	 *
	 * @since 1.3.0
	 *
	 * @var array<string, string>
	 */
	private array $legend;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param \Fueled\AiProviderForOllama\Decisions\DecisionTypeEnum $type          The question type.
	 * @param string|float                                           $value         The answer value.
	 * @param array<string, float>                                   $probabilities The probability of each option or level.
	 * @param float|null                                             $confidence    The confidence, or null for noul answers.
	 * @param array<string, string>                                  $legend        The score level descriptions.
	 */
	public function __construct(
		DecisionTypeEnum $type,
		$value,
		array $probabilities = array(),
		?float $confidence = null,
		array $legend = array()
	) {
		$this->type          = $type;
		$this->value         = $value;
		$this->probabilities = $probabilities;
		$this->confidence    = $confidence;
		$this->legend        = $legend;
	}

	/**
	 * Creates an answer from the API data for one question.
	 *
	 * @since 1.3.0
	 *
	 * @param \Fueled\AiProviderForOllama\Decisions\DecisionTypeEnum $type The type of the question asked.
	 * @param mixed                                                  $data The answer data from the response.
	 * @param string                                                 $path Where the answer sits in the response, for error messages.
	 * @return self The answer.
	 * @throws \WordPress\AiClient\Providers\Http\Exception\ResponseException If the data does not match the type.
	 */
	public static function fromResponseData( DecisionTypeEnum $type, $data, string $path ): self {
		if ( ! is_array( $data ) ) {
			throw ResponseException::fromInvalidData( 'Ollama', $path, 'The value must be an object.' );
		}

		if ( $type->isNoul() ) {
			return new self( $type, self::readProbability( $data, 'noul', $path ) );
		}

		$confidence    = self::readProbability( $data, 'confidence', $path );
		$probabilities = self::readProbabilities( $data, $path );

		if ( $type->isChoice() ) {
			if ( ! isset( $data['choice'] ) || ! is_string( $data['choice'] ) ) {
				throw ResponseException::fromInvalidData( 'Ollama', $path . '.choice', 'The value must be a string.' );
			}

			return new self( $type, $data['choice'], $probabilities, $confidence );
		}

		if ( ! isset( $data['score'] ) || ! is_numeric( $data['score'] ) ) {
			throw ResponseException::fromInvalidData( 'Ollama', $path . '.score', 'The value must be a number.' );
		}

		$legend = array();
		if ( isset( $data['legend'] ) && is_array( $data['legend'] ) ) {
			foreach ( $data['legend'] as $level => $description ) {
				if ( ! is_string( $description ) ) {
					continue;
				}

				$legend[ (string) $level ] = $description;
			}
		}

		return new self( $type, (float) $data['score'], $probabilities, $confidence, $legend );
	}

	/**
	 * Returns the question type.
	 *
	 * @since 1.3.0
	 *
	 * @return \Fueled\AiProviderForOllama\Decisions\DecisionTypeEnum The question type.
	 */
	public function getType(): DecisionTypeEnum {
		return $this->type;
	}

	/**
	 * Returns the raw answer value.
	 *
	 * @since 1.3.0
	 *
	 * @return string|float The option key for choice, a probability for noul, a position for score.
	 */
	public function getValue() {
		return $this->value;
	}

	/**
	 * Returns the most probable option of a choice answer.
	 *
	 * @since 1.3.0
	 *
	 * @return string|null The option key, or null if this is not a choice answer.
	 */
	public function getChoice(): ?string {
		return $this->type->isChoice() ? (string) $this->value : null;
	}

	/**
	 * Returns the probability that the statement of a noul answer is true.
	 *
	 * @since 1.3.0
	 *
	 * @return float|null The probability from 0 to 1, or null if this is not a noul answer.
	 */
	public function getProbability(): ?float {
		return $this->type->isNoul() ? (float) $this->value : null;
	}

	/**
	 * Returns the probability-weighted position of a score answer.
	 *
	 * @since 1.3.0
	 *
	 * @return float|null The position from 0 to one less than the number of levels, or null if
	 *                    this is not a score answer.
	 */
	public function getScore(): ?float {
		return $this->type->isScore() ? (float) $this->value : null;
	}

	/**
	 * Returns the probability of each option (choice) or level index (score).
	 *
	 * @since 1.3.0
	 *
	 * @return array<string, float> The probabilities, empty for noul answers.
	 */
	public function getProbabilities(): array {
		return $this->probabilities;
	}

	/**
	 * Returns how concentrated the distribution of a choice or score answer is.
	 *
	 * @since 1.3.0
	 *
	 * @return float|null The confidence from 0 to 1, or null for noul answers.
	 */
	public function getConfidence(): ?float {
		return $this->confidence;
	}

	/**
	 * Returns the level descriptions of a score answer, keyed by level index.
	 *
	 * @since 1.3.0
	 *
	 * @return array<string, string> The legend, empty for other answers.
	 */
	public function getLegend(): array {
		return $this->legend;
	}

	/**
	 * Checks whether the answer is confident enough to act on.
	 *
	 * @since 1.3.0
	 *
	 * @param float $threshold The minimum confidence, from 0 to 1.
	 * @return bool True if the answer meets the threshold.
	 */
	public function isConfident( float $threshold ): bool {
		if ( $this->type->isNoul() ) {
			return abs( (float) $this->value - 0.5 ) * 2 >= $threshold;
		}

		return null !== $this->confidence && $this->confidence >= $threshold;
	}

	/**
	 * Returns the answer as an array, in the shape of the API response.
	 *
	 * @since 1.3.0
	 *
	 * @return array<string, mixed> The answer data.
	 */
	public function toArray(): array {
		$data = array(
			'type'             => $this->type->value,
			$this->type->value => $this->value,
		);

		if ( $this->type->isNoul() ) {
			return $data;
		}

		if ( $this->type->isScore() ) {
			$data['legend'] = $this->legend;
		}

		$data['probabilities'] = $this->probabilities;
		$data['confidence']    = $this->confidence;

		return $data;
	}

	/**
	 * Reads a number between 0 and 1 from answer data.
	 *
	 * @since 1.3.0
	 *
	 * @param array<mixed> $data  The answer data.
	 * @param string       $key   The key to read.
	 * @param string       $path  Where the answer sits in the response, for error messages.
	 * @return float The number.
	 * @throws \WordPress\AiClient\Providers\Http\Exception\ResponseException If the value is missing or out of range.
	 */
	private static function readProbability( array $data, string $key, string $path ): float {
		if ( ! isset( $data[ $key ] ) || ! is_numeric( $data[ $key ] ) ) {
			throw ResponseException::fromInvalidData( 'Ollama', $path . '.' . $key, 'The value must be a number.' );
		}

		$value = (float) $data[ $key ];
		if ( $value < 0.0 || $value > 1.0 ) {
			throw ResponseException::fromInvalidData( 'Ollama', $path . '.' . $key, 'The value must be between 0 and 1.' );
		}

		return $value;
	}

	/**
	 * Reads the probability distribution from answer data.
	 *
	 * @since 1.3.0
	 *
	 * @param array<mixed> $data The answer data.
	 * @param string       $path Where the answer sits in the response, for error messages.
	 * @return array<string, float> The probabilities, keyed by option or level index.
	 * @throws \WordPress\AiClient\Providers\Http\Exception\ResponseException If the distribution is missing or malformed.
	 */
	private static function readProbabilities( array $data, string $path ): array {
		if ( ! isset( $data['probabilities'] ) || ! is_array( $data['probabilities'] ) ) {
			throw ResponseException::fromInvalidData( 'Ollama', $path . '.probabilities', 'The value must be an object.' );
		}

		$probabilities = array();
		foreach ( $data['probabilities'] as $key => $probability ) {
			if ( ! is_numeric( $probability ) ) {
				throw ResponseException::fromInvalidData(
					'Ollama',
					$path . '.probabilities.' . $key,
					'The value must be a number.'
				);
			}

			$probabilities[ (string) $key ] = (float) $probability;
		}

		return $probabilities;
	}
}

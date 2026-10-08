<?php
/**
 * A question for a decision model.
 *
 * @package Fueled\AiProviderForOllama\Decisions
 * @since   1.3.0
 */

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Decisions;

use WordPress\AiClient\Common\Exception\InvalidArgumentException;

// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- camelCase, matching the AI Client DTOs.

/**
 * Class for a single typed question asked of a decision model.
 *
 * @since 1.3.0
 */
class DecisionQuestion {

	/**
	 * The minimum number of options or levels a choice or score question takes.
	 *
	 * @since 1.3.0
	 *
	 * @var int
	 */
	public const MIN_CRITERIA = 2;

	/**
	 * The maximum number of options or levels a choice or score question takes.
	 *
	 * @since 1.3.0
	 *
	 * @var int
	 */
	public const MAX_CRITERIA = 26;

	/**
	 * The question type.
	 *
	 * @since 1.3.0
	 *
	 * @var \Fueled\AiProviderForOllama\Decisions\DecisionTypeEnum
	 */
	private DecisionTypeEnum $type;

	/**
	 * The question text.
	 *
	 * @since 1.3.0
	 *
	 * @var string
	 */
	private string $instructions;

	/**
	 * The criteria in the shape the API expects, or null when none are sent.
	 *
	 * @since 1.3.0
	 *
	 * @var array<string, string>|list<string>|null
	 */
	private ?array $criteria;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param \Fueled\AiProviderForOllama\Decisions\DecisionTypeEnum $type         The question type.
	 * @param string                                                 $instructions The question text.
	 * @param array<string, string>|list<string>|null                $criteria     The criteria, if any.
	 */
	private function __construct( DecisionTypeEnum $type, string $instructions, ?array $criteria ) {
		if ( '' === trim( $instructions ) ) {
			throw new InvalidArgumentException( 'Decision question instructions must not be empty.' );
		}

		$this->type         = $type;
		$this->instructions = $instructions;
		$this->criteria     = $criteria;
	}

	/**
	 * Creates a question that picks one option from a named set.
	 *
	 * @since 1.3.0
	 *
	 * @param string                $instructions The question text.
	 * @param array<string, string> $options      Map of option key to its description, 2 to 26 entries.
	 * @return self The question.
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the options are invalid.
	 */
	public static function choice( string $instructions, array $options ): self {
		self::assertCriteriaCount( 'choice', count( $options ) );

		$criteria = array();
		foreach ( $options as $key => $description ) {
			// PHP turns numeric string keys into integers, so accept those and send them back as strings.
			$key = (string) $key;
			if ( '' === $key ) {
				throw new InvalidArgumentException( 'Choice option keys must not be empty.' );
			}

			$criteria[ $key ] = self::readDescription( $description, 'Choice option "' . $key . '"' );
		}

		return new self( DecisionTypeEnum::choice(), $instructions, $criteria );
	}

	/**
	 * Creates a question that estimates the probability that a statement is true.
	 *
	 * @since 1.3.0
	 *
	 * @param string      $instructions      The question text.
	 * @param string|null $false_description Optional. What a false outcome means. Default null.
	 * @param string|null $true_description  Optional. What a true outcome means. Default null.
	 * @return self The question.
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If a description is empty.
	 */
	public static function noul( string $instructions, ?string $false_description = null, ?string $true_description = null ): self {
		$criteria = array();
		if ( null !== $false_description ) {
			$criteria['false'] = self::readDescription( $false_description, 'The false description' );
		}
		if ( null !== $true_description ) {
			$criteria['true'] = self::readDescription( $true_description, 'The true description' );
		}

		return new self( DecisionTypeEnum::noul(), $instructions, empty( $criteria ) ? null : $criteria );
	}

	/**
	 * Creates a question that rates against ordered levels.
	 *
	 * @since 1.3.0
	 *
	 * @param string       $instructions The question text.
	 * @param list<string> $levels       Level descriptions from lowest to highest, 2 to 26 entries.
	 * @return self The question.
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the levels are invalid.
	 */
	public static function score( string $instructions, array $levels ): self {
		self::assertCriteriaCount( 'score', count( $levels ) );

		$criteria = array();
		foreach ( array_values( $levels ) as $index => $description ) {
			$criteria[] = self::readDescription( $description, 'Score level ' . $index );
		}

		return new self( DecisionTypeEnum::score(), $instructions, $criteria );
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
	 * Returns the question text.
	 *
	 * @since 1.3.0
	 *
	 * @return string The question text.
	 */
	public function getInstructions(): string {
		return $this->instructions;
	}

	/**
	 * Returns the criteria in the shape the API expects.
	 *
	 * @since 1.3.0
	 *
	 * @return array<string, string>|list<string>|null The option map for choice and noul
	 *                                                 questions, the level list for score
	 *                                                 questions, or null when none are set.
	 */
	public function getCriteria(): ?array {
		return $this->criteria;
	}

	/**
	 * Returns the question in the shape the /v1/systemone endpoint expects.
	 *
	 * @since 1.3.0
	 *
	 * @return array<string, mixed> The question data.
	 */
	public function toArray(): array {
		$data = array(
			'type'         => $this->type->value,
			'instructions' => $this->instructions,
		);

		if ( null !== $this->criteria ) {
			$data['criteria'] = $this->criteria;
		}

		return $data;
	}

	/**
	 * Checks that a choice or score question has an accepted number of criteria.
	 *
	 * @since 1.3.0
	 *
	 * @param string $type  The question type, for the error message.
	 * @param int    $count The number of criteria given.
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the count is out of range.
	 */
	private static function assertCriteriaCount( string $type, int $count ): void {
		if ( $count >= self::MIN_CRITERIA && $count <= self::MAX_CRITERIA ) {
			return;
		}

		// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		throw new InvalidArgumentException(
			sprintf(
				'A %1$s question needs between %2$d and %3$d criteria, %4$d given.',
				$type,
				self::MIN_CRITERIA,
				self::MAX_CRITERIA,
				$count
			)
		);
		// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	/**
	 * Reads a criterion description, rejecting anything but a non-empty string.
	 *
	 * @since 1.3.0
	 *
	 * @param mixed  $description The description.
	 * @param string $label       What the description belongs to, for the error message.
	 * @return string The description.
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the description is invalid.
	 */
	private static function readDescription( $description, string $label ): string {
		if ( ! is_string( $description ) || '' === trim( $description ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new InvalidArgumentException( $label . ' must have a non-empty description.' );
		}

		return $description;
	}
}

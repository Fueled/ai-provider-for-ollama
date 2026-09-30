<?php
/**
 * Enum for decision question types.
 *
 * @package Fueled\AiProviderForOllama\Decisions
 * @since   x.x.x
 */

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Decisions;

use WordPress\AiClient\Common\AbstractEnum;

/**
 * Enum for the question types a decision model answers.
 *
 * @since x.x.x
 *
 * @method static self choice() Creates an instance for the CHOICE type.
 * @method static self noul() Creates an instance for the NOUL type.
 * @method static self score() Creates an instance for the SCORE type.
 * @method bool isChoice() Checks if the type is CHOICE.
 * @method bool isNoul() Checks if the type is NOUL.
 * @method bool isScore() Checks if the type is SCORE.
 */
class DecisionTypeEnum extends AbstractEnum {

	/**
	 * Picks one option from a named set.
	 */
	public const CHOICE = 'choice';

	/**
	 * Estimates the probability that a statement is true.
	 */
	public const NOUL = 'noul';

	/**
	 * Rates against ordered levels.
	 */
	public const SCORE = 'score';
}

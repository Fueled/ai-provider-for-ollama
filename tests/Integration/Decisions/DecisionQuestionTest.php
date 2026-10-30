<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Tests\Integration\Decisions;

use Fueled\AiProviderForOllama\Decisions\DecisionQuestion;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;

/**
 * Tests for DecisionQuestion.
 *
 * @covers \Fueled\AiProviderForOllama\Decisions\DecisionQuestion
 */
class DecisionQuestionTest extends TestCase {

	/**
	 * Tests that a choice question serializes to the API shape.
	 */
	public function test_choice_serializes_options(): void {
		$question = DecisionQuestion::choice(
			'Which label fits this ticket?',
			array(
				'billing' => 'Payments and refunds',
				'bug'     => 'Software errors',
			)
		);

		$this->assertTrue( $question->getType()->isChoice() );
		$this->assertSame(
			array(
				'type'         => 'choice',
				'instructions' => 'Which label fits this ticket?',
				'criteria'     => array(
					'billing' => 'Payments and refunds',
					'bug'     => 'Software errors',
				),
			),
			$question->toArray()
		);
	}

	/**
	 * Tests that numeric option keys, which PHP stores as integers, are sent as strings.
	 */
	public function test_choice_casts_numeric_option_keys_to_strings(): void {
		$question = DecisionQuestion::choice(
			'Pick a rating.',
			array(
				'1' => 'One star',
				'5' => 'Five stars',
			)
		);

		$this->assertSame( array( '1', '5' ), array_map( 'strval', array_keys( (array) $question->getCriteria() ) ) );
		$this->assertSame( '{"1":"One star","5":"Five stars"}', json_encode( $question->getCriteria() ) );
	}

	/**
	 * Tests that a noul question without descriptions sends no criteria.
	 */
	public function test_noul_without_descriptions_omits_criteria(): void {
		$question = DecisionQuestion::noul( 'Is this comment spam?' );

		$this->assertTrue( $question->getType()->isNoul() );
		$this->assertNull( $question->getCriteria() );
		$this->assertSame(
			array(
				'type'         => 'noul',
				'instructions' => 'Is this comment spam?',
			),
			$question->toArray()
		);
	}

	/**
	 * Tests that noul descriptions are sent under the false and true keys, each optional.
	 */
	public function test_noul_descriptions_map_to_false_and_true(): void {
		$both = DecisionQuestion::noul( 'Is it urgent?', 'Can wait', 'Needs action today' );
		$this->assertSame(
			array(
				'false' => 'Can wait',
				'true'  => 'Needs action today',
			),
			$both->getCriteria()
		);

		$true_only = DecisionQuestion::noul( 'Is it urgent?', null, 'Needs action today' );
		$this->assertSame( array( 'true' => 'Needs action today' ), $true_only->getCriteria() );
	}

	/**
	 * Tests that score levels are sent as a list, whatever keys were given.
	 */
	public function test_score_sends_levels_as_a_list(): void {
		$question = DecisionQuestion::score(
			'How severe is this?',
			array(
				'low'  => 'Trivial',
				'high' => 'Critical',
			)
		);

		$this->assertTrue( $question->getType()->isScore() );
		$this->assertSame( array( 'Trivial', 'Critical' ), $question->getCriteria() );
	}

	/**
	 * Tests that choice and score questions accept the boundary criteria counts.
	 */
	public function test_criteria_count_boundaries_are_accepted(): void {
		$levels = array_fill( 0, DecisionQuestion::MAX_CRITERIA, 'Level' );

		$this->assertCount( 2, (array) DecisionQuestion::score( 'Rate it.', array( 'Low', 'High' ) )->getCriteria() );
		$this->assertCount( 26, (array) DecisionQuestion::score( 'Rate it.', $levels )->getCriteria() );

		$options = array();
		foreach ( range( 'a', 'z' ) as $letter ) {
			$options[ $letter ] = 'Option ' . $letter;
		}
		$this->assertCount( 26, (array) DecisionQuestion::choice( 'Pick one.', $options )->getCriteria() );
	}

	/**
	 * Provides invalid question constructions.
	 *
	 * @return array<string, array{callable(): mixed, string}>
	 */
	public function data_invalid_questions(): array {
		return array(
			'empty instructions'        => array(
				static function () {
					return DecisionQuestion::noul( '  ' );
				},
				'instructions must not be empty',
			),
			'choice with one option'    => array(
				static function () {
					return DecisionQuestion::choice( 'Pick.', array( 'a' => 'Only' ) );
				},
				'between 2 and 26 criteria, 1 given',
			),
			'choice with 27 options'    => array(
				static function () {
					$options = array();
					for ( $i = 0; $i < 27; $i++ ) {
						$options[ 'o' . $i ] = 'Option';
					}
					return DecisionQuestion::choice( 'Pick.', $options );
				},
				'27 given',
			),
			'choice with empty key'     => array(
				static function () {
					return DecisionQuestion::choice(
						'Pick.',
						array(
							''  => 'Blank',
							'b' => 'B',
						)
					);
				},
				'keys must not be empty',
			),
			'choice with empty value'   => array(
				static function () {
					return DecisionQuestion::choice(
						'Pick.',
						array(
							'a' => '',
							'b' => 'B',
						)
					);
				},
				'Choice option "a" must have a non-empty description',
			),
			'score with one level'      => array(
				static function () {
					return DecisionQuestion::score( 'Rate.', array( 'Only' ) );
				},
				'A score question needs between 2 and 26 criteria',
			),
			'score with non-string'     => array(
				static function () {
					return DecisionQuestion::score( 'Rate.', array( 'Low', 3 ) );
				},
				'Score level 1 must have a non-empty description',
			),
			'noul with empty true text' => array(
				static function () {
					return DecisionQuestion::noul( 'Is it?', null, '' );
				},
				'The true description must have a non-empty description',
			),
		);
	}

	/**
	 * Tests that invalid questions are rejected before any request is built.
	 *
	 * @dataProvider data_invalid_questions
	 *
	 * @param callable(): mixed $build            Builds the question.
	 * @param string            $expected_message Part of the expected exception message.
	 */
	public function test_invalid_questions_are_rejected( callable $build, string $expected_message ): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( $expected_message );

		$build();
	}
}

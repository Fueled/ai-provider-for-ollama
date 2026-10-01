<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Tests\Integration\Decisions;

use Fueled\AiProviderForOllama\Decisions\DecisionAnswer;
use Fueled\AiProviderForOllama\Decisions\DecisionTypeEnum;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;

/**
 * Tests for DecisionAnswer.
 *
 * Response shapes are taken from a live Ollama 0.35 /v1/systemone response.
 *
 * @covers \Fueled\AiProviderForOllama\Decisions\DecisionAnswer
 */
class DecisionAnswerTest extends TestCase {

	/**
	 * Tests that a choice answer exposes the choice, distribution and confidence.
	 */
	public function test_parses_choice_answer(): void {
		$answer = DecisionAnswer::fromResponseData(
			DecisionTypeEnum::choice(),
			array(
				'type'          => 'choice',
				'choice'        => 'bug',
				'probabilities' => array(
					'billing' => 0.0133,
					'bug'     => 0.9574,
					'account' => 0.0293,
				),
				'confidence'    => 0.8157,
			),
			'answers.label'
		);

		$this->assertSame( 'bug', $answer->getChoice() );
		$this->assertSame( 'bug', $answer->getValue() );
		$this->assertSame( 0.9574, $answer->getProbabilities()['bug'] );
		$this->assertSame( 0.8157, $answer->getConfidence() );
		$this->assertNull( $answer->getProbability() );
		$this->assertNull( $answer->getScore() );
		$this->assertSame( array(), $answer->getLegend() );
	}

	/**
	 * Tests that a noul answer exposes its probability and no confidence.
	 */
	public function test_parses_noul_answer(): void {
		$answer = DecisionAnswer::fromResponseData(
			DecisionTypeEnum::noul(),
			array(
				'type' => 'noul',
				'noul' => 0.9014,
			),
			'answers.urgent'
		);

		$this->assertSame( 0.9014, $answer->getProbability() );
		$this->assertNull( $answer->getConfidence() );
		$this->assertSame( array(), $answer->getProbabilities() );
		$this->assertNull( $answer->getChoice() );
	}

	/**
	 * Tests that a score answer exposes its position, legend and string-keyed distribution.
	 */
	public function test_parses_score_answer(): void {
		$answer = DecisionAnswer::fromResponseData(
			DecisionTypeEnum::score(),
			array(
				'type'          => 'score',
				'score'         => 2.2991,
				'legend'        => array(
					'0' => 'Trivial',
					'1' => 'Minor',
					'2' => 'Major',
					'3' => 'Critical',
				),
				'probabilities' => array(
					'0' => 0.0076,
					'1' => 0.1236,
					'2' => 0.4308,
					'3' => 0.4380,
				),
				'confidence'    => 0.2642,
			),
			'answers.severity'
		);

		$this->assertSame( 2.2991, $answer->getScore() );
		$this->assertSame( 0.2642, $answer->getConfidence() );
		$this->assertSame( 'Critical', $answer->getLegend()['3'] );
		$this->assertSame( array( '0', '1', '2', '3' ), array_map( 'strval', array_keys( $answer->getProbabilities() ) ) );
	}

	/**
	 * Tests that the type comes from the question asked, so a response without it still parses.
	 */
	public function test_parses_answer_without_type_field(): void {
		$answer = DecisionAnswer::fromResponseData( DecisionTypeEnum::noul(), array( 'noul' => 0.03 ), 'answers.spam' );

		$this->assertTrue( $answer->getType()->isNoul() );
		$this->assertSame( 0.03, $answer->getProbability() );
	}

	/**
	 * Tests that toArray() round-trips to the response shape.
	 */
	public function test_to_array_matches_response_shape(): void {
		$data   = array(
			'type'          => 'choice',
			'choice'        => 'approve',
			'probabilities' => array(
				'approve' => 0.91,
				'trash'   => 0.09,
			),
			'confidence'    => 0.8,
		);
		$answer = DecisionAnswer::fromResponseData( DecisionTypeEnum::choice(), $data, 'answers.route' );

		$this->assertSame( $data, $answer->toArray() );

		$noul = DecisionAnswer::fromResponseData( DecisionTypeEnum::noul(), array( 'noul' => 0.5 ), 'answers.q' );
		$this->assertSame(
			array(
				'type' => 'noul',
				'noul' => 0.5,
			),
			$noul->toArray()
		);
	}

	/**
	 * Tests that choice and score answers gate on their reported confidence.
	 */
	public function test_is_confident_uses_confidence_for_choice(): void {
		$answer = new DecisionAnswer( DecisionTypeEnum::choice(), 'bug', array( 'bug' => 0.95 ), 0.8 );

		$this->assertTrue( $answer->isConfident( 0.8 ) );
		$this->assertFalse( $answer->isConfident( 0.81 ) );
	}

	/**
	 * Tests that noul answers gate on distance from an even 0.5, in either direction.
	 */
	public function test_is_confident_uses_distance_from_even_for_noul(): void {
		$yes    = new DecisionAnswer( DecisionTypeEnum::noul(), 0.97 );
		$no     = new DecisionAnswer( DecisionTypeEnum::noul(), 0.03 );
		$unsure = new DecisionAnswer( DecisionTypeEnum::noul(), 0.52 );

		$this->assertTrue( $yes->isConfident( 0.9 ) );
		$this->assertTrue( $no->isConfident( 0.9 ) );
		$this->assertFalse( $unsure->isConfident( 0.5 ) );
	}

	/**
	 * Provides malformed answer data.
	 *
	 * @return array<string, array{\Fueled\AiProviderForOllama\Decisions\DecisionTypeEnum, mixed, string}>
	 */
	public function data_malformed_answers(): array {
		return array(
			'not an object'           => array( DecisionTypeEnum::noul(), 'yes', 'answers.q' ),
			'noul missing value'      => array( DecisionTypeEnum::noul(), array( 'type' => 'noul' ), 'answers.q.noul' ),
			'noul out of range'       => array( DecisionTypeEnum::noul(), array( 'noul' => 1.2 ), 'answers.q.noul' ),
			'choice missing choice'   => array(
				DecisionTypeEnum::choice(),
				array(
					'probabilities' => array( 'a' => 1.0 ),
					'confidence'    => 1.0,
				),
				'answers.q.choice',
			),
			'choice missing distrib.' => array(
				DecisionTypeEnum::choice(),
				array(
					'choice'     => 'a',
					'confidence' => 1.0,
				),
				'answers.q.probabilities',
			),
			'score non-numeric score' => array(
				DecisionTypeEnum::score(),
				array(
					'score'         => 'high',
					'probabilities' => array( '0' => 1.0 ),
					'confidence'    => 1.0,
				),
				'answers.q.score',
			),
			'score missing conf.'     => array(
				DecisionTypeEnum::score(),
				array(
					'score'         => 1.0,
					'probabilities' => array( '0' => 1.0 ),
				),
				'answers.q.confidence',
			),
		);
	}

	/**
	 * Tests that answers not matching the question type are rejected.
	 *
	 * @dataProvider data_malformed_answers
	 *
	 * @param \Fueled\AiProviderForOllama\Decisions\DecisionTypeEnum $type The question type.
	 * @param mixed            $data          The answer data.
	 * @param string           $expected_path The field the error should name.
	 */
	public function test_malformed_answers_throw( DecisionTypeEnum $type, $data, string $expected_path ): void {
		$this->expectException( ResponseException::class );
		$this->expectExceptionMessage( $expected_path );

		DecisionAnswer::fromResponseData( $type, $data, 'answers.q' );
	}
}

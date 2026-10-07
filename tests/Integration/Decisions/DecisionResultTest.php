<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Tests\Integration\Decisions;

use Fueled\AiProviderForOllama\Decisions\DecisionAnswer;
use Fueled\AiProviderForOllama\Decisions\DecisionResult;
use Fueled\AiProviderForOllama\Decisions\DecisionTypeEnum;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Results\DTO\TokenUsage;

/**
 * Tests for DecisionResult.
 *
 * @covers \Fueled\AiProviderForOllama\Decisions\DecisionResult
 */
class DecisionResultTest extends TestCase {

	/**
	 * Tests constructor initialization and getters.
	 */
	public function test_construct_and_getters(): void {
		$answer      = new DecisionAnswer( DecisionTypeEnum::choice(), 'bug', array( 'bug' => 0.95 ), 0.82 );
		$answers     = array( 'label' => $answer );
		$token_usage = new TokenUsage( 753, 4, 757 );

		$result = new DecisionResult( 'tev1:0.8b', $answers, $token_usage );

		$this->assertSame( 'tev1:0.8b', $result->getModelId() );
		$this->assertSame( $answers, $result->getAnswers() );
		$this->assertSame( $token_usage, $result->getTokenUsage() );
		$this->assertSame( 753, $result->getTokenUsage()->getPromptTokens() );
		$this->assertSame( 4, $result->getTokenUsage()->getCompletionTokens() );
		$this->assertSame( 757, $result->getTokenUsage()->getTotalTokens() );
	}

	/**
	 * Tests hasAnswer() for existing and non-existing question names.
	 */
	public function test_has_answer(): void {
		$answer = new DecisionAnswer( DecisionTypeEnum::noul(), 0.9 );
		$result = new DecisionResult(
			'tev1:0.8b',
			array( 'urgent' => $answer ),
			new TokenUsage( 100, 2, 102 )
		);

		$this->assertTrue( $result->hasAnswer( 'urgent' ) );
		$this->assertFalse( $result->hasAnswer( 'severity' ) );
		$this->assertFalse( $result->hasAnswer( '' ) );
	}

	/**
	 * Tests getAnswer() returns the matching DecisionAnswer instance.
	 */
	public function test_get_answer_returns_matching_answer(): void {
		$label_answer  = new DecisionAnswer( DecisionTypeEnum::choice(), 'billing', array( 'billing' => 0.98 ), 0.95 );
		$urgent_answer = new DecisionAnswer( DecisionTypeEnum::noul(), 0.15 );

		$result = new DecisionResult(
			'tev1:latest',
			array(
				'label'  => $label_answer,
				'urgent' => $urgent_answer,
			),
			new TokenUsage( 200, 4, 204 )
		);

		$this->assertSame( $label_answer, $result->getAnswer( 'label' ) );
		$this->assertSame( 'billing', $result->getAnswer( 'label' )->getChoice() );
		$this->assertSame( $urgent_answer, $result->getAnswer( 'urgent' ) );
		$this->assertSame( 0.15, $result->getAnswer( 'urgent' )->getProbability() );
	}

	/**
	 * Tests getAnswer() throws an InvalidArgumentException when question name is missing.
	 */
	public function test_get_answer_throws_for_missing_question(): void {
		$result = new DecisionResult(
			'tev1:0.8b',
			array( 'urgent' => new DecisionAnswer( DecisionTypeEnum::noul(), 0.5 ) ),
			new TokenUsage( 50, 1, 51 )
		);

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'No answer for the question "missing_question".' );

		$result->getAnswer( 'missing_question' );
	}

	/**
	 * Tests toArray() serializes the result into the expected API response shape.
	 */
	public function test_to_array_serializes_model_answers_and_usage(): void {
		$choice_data = array(
			'type'          => 'choice',
			'choice'        => 'bug',
			'probabilities' => array(
				'billing' => 0.0133,
				'bug'     => 0.9574,
			),
			'confidence'    => 0.8157,
		);
		$noul_data   = array(
			'type' => 'noul',
			'noul' => 0.9014,
		);

		$choice_answer = DecisionAnswer::fromResponseData( DecisionTypeEnum::choice(), $choice_data, 'answers.label' );
		$noul_answer   = DecisionAnswer::fromResponseData( DecisionTypeEnum::noul(), $noul_data, 'answers.urgent' );

		$result = new DecisionResult(
			'tev1:0.8b',
			array(
				'label'  => $choice_answer,
				'urgent' => $noul_answer,
			),
			new TokenUsage( 753, 4, 757 )
		);

		$expected = array(
			'model'   => 'tev1:0.8b',
			'answers' => array(
				'label'  => $choice_data,
				'urgent' => $noul_data,
			),
			'usage'   => array(
				'input_tokens'  => 753,
				'output_tokens' => 4,
			),
		);

		$this->assertSame( $expected, $result->toArray() );
	}

	/**
	 * Tests behavior with an empty answers array.
	 */
	public function test_handles_empty_answers(): void {
		$result = new DecisionResult(
			'empty-model',
			array(),
			new TokenUsage( 0, 0, 0 )
		);

		$this->assertSame( 'empty-model', $result->getModelId() );
		$this->assertSame( array(), $result->getAnswers() );
		$this->assertFalse( $result->hasAnswer( 'any' ) );
		$this->assertSame(
			array(
				'model'   => 'empty-model',
				'answers' => array(),
				'usage'   => array(
					'input_tokens'  => 0,
					'output_tokens' => 0,
				),
			),
			$result->toArray()
		);
	}
}

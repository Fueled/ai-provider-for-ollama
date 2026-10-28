<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Tests\Integration\Models;

use Fueled\AiProviderForOllama\Decisions\DecisionQuestion;
use Fueled\AiProviderForOllama\Models\OllamaDecisionModel;
use Fueled\AiProviderForOllama\Tests\Integration\Mocks\MockHttpTransporter;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Exception\ClientException;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

/**
 * Tests for OllamaDecisionModel.
 *
 * @covers \Fueled\AiProviderForOllama\Models\OllamaDecisionModel
 */
class OllamaDecisionModelTest extends TestCase {

	/**
	 * Model under test.
	 *
	 * @var \Fueled\AiProviderForOllama\Models\OllamaDecisionModel
	 */
	private OllamaDecisionModel $model;

	/**
	 * Shared mock transporter for request/response inspection.
	 *
	 * @var \Fueled\AiProviderForOllama\Tests\Integration\Mocks\MockHttpTransporter
	 */
	private MockHttpTransporter $transporter;

	protected function setUp(): void {
		parent::setUp();

		putenv( 'OLLAMA_HOST=http://localhost:11434' );

		$model_metadata    = new ModelMetadata( 'tev1', 'tev1', array(), array() );
		$provider_metadata = new ProviderMetadata( 'ollama', 'Ollama', ProviderTypeEnum::cloud(), null, null );

		$this->model       = new OllamaDecisionModel( $model_metadata, $provider_metadata );
		$this->transporter = new MockHttpTransporter();

		$this->model->setHttpTransporter( $this->transporter );
		$this->model->setRequestAuthentication( new ApiKeyRequestAuthentication( '' ) );
	}

	protected function tearDown(): void {
		putenv( 'OLLAMA_HOST' );
		parent::tearDown();
	}

	/**
	 * Builds a mock API response.
	 *
	 * @param array<string, mixed> $data Payload data.
	 * @param int                  $status HTTP status code.
	 * @return \WordPress\AiClient\Providers\Http\DTO\Response
	 */
	private function make_response( array $data, int $status = 200 ): Response {
		return new Response( $status, array(), (string) json_encode( $data ) );
	}

	/**
	 * Returns the three-question set used in the live test, one of each type.
	 *
	 * @return array<string, \Fueled\AiProviderForOllama\Decisions\DecisionQuestion>
	 */
	private function make_questions(): array {
		return array(
			'label'    => DecisionQuestion::choice(
				'Which label fits this ticket?',
				array(
					'billing' => 'Payments and refunds',
					'bug'     => 'Software errors',
				)
			),
			'urgent'   => DecisionQuestion::noul( 'Is this urgent?' ),
			'severity' => DecisionQuestion::score( 'How severe is this?', array( 'Minor', 'Major', 'Critical' ) ),
		);
	}

	/**
	 * Returns a response answering make_questions().
	 *
	 * @return \WordPress\AiClient\Providers\Http\DTO\Response
	 */
	private function make_answers_response(): Response {
		return $this->make_response(
			array(
				'model'   => 'tev1',
				'answers' => array(
					'label'    => array(
						'type'          => 'choice',
						'choice'        => 'bug',
						'probabilities' => array(
							'billing' => 0.04,
							'bug'     => 0.96,
						),
						'confidence'    => 0.82,
					),
					'urgent'   => array(
						'type' => 'noul',
						'noul' => 0.9,
					),
					'severity' => array(
						'type'          => 'score',
						'score'         => 1.4,
						'legend'        => array(
							'0' => 'Minor',
							'1' => 'Major',
							'2' => 'Critical',
						),
						'probabilities' => array(
							'0' => 0.1,
							'1' => 0.4,
							'2' => 0.5,
						),
						'confidence'    => 0.3,
					),
				),
				'usage'   => array(
					'input_tokens'  => 753,
					'output_tokens' => 4,
				),
			)
		);
	}

	/**
	 * Tests request construction and parsing of all three answer types.
	 */
	public function test_decide_sends_expected_request_and_parses_answers(): void {
		$this->transporter->set_response_to_return( $this->make_answers_response() );

		$result = $this->model->decide( 'Our checkout has returned 500 errors since 9am.', $this->make_questions() );

		$request = $this->transporter->get_last_request();
		$this->assertNotNull( $request );
		$this->assertTrue( $request->getMethod()->isPost() );
		$this->assertSame( 'http://localhost:11434/v1/systemone', $request->getUri() );
		$this->assertSame( 'application/json', $request->getHeaderAsString( 'Content-Type' ) );

		$data = $request->getData();
		$this->assertIsArray( $data );
		$this->assertSame( 'tev1', $data['model'] );
		$this->assertSame( 'Our checkout has returned 500 errors since 9am.', $data['state'] );
		$this->assertSame( array( 'label', 'urgent', 'severity' ), array_keys( $data['questions'] ) );
		$this->assertSame(
			array(
				'type'         => 'noul',
				'instructions' => 'Is this urgent?',
			),
			$data['questions']['urgent']
		);

		$options = $request->getOptions();
		$this->assertNotNull( $options );
		$this->assertSame( 60.0, $options->getTimeout() );
		$this->assertSame( 10.0, $options->getConnectTimeout() );

		$this->assertSame( 'tev1', $result->getModelId() );
		$this->assertSame( 'bug', $result->getAnswer( 'label' )->getChoice() );
		$this->assertSame( 0.9, $result->getAnswer( 'urgent' )->getProbability() );
		$this->assertSame( 1.4, $result->getAnswer( 'severity' )->getScore() );
		$this->assertSame( 753, $result->getTokenUsage()->getPromptTokens() );
		$this->assertSame( 4, $result->getTokenUsage()->getCompletionTokens() );
		$this->assertSame( 757, $result->getTokenUsage()->getTotalTokens() );
	}

	/**
	 * Tests that array state is sent as-is, so it reaches Ollama as a JSON object.
	 */
	public function test_decide_sends_array_state_as_json_object(): void {
		$this->transporter->set_response_to_return(
			$this->make_response( array( 'answers' => array( 'spam' => array( 'noul' => 0.1 ) ) ) )
		);

		$state = array(
			'author'  => 'Jane',
			'comment' => 'Great post!',
		);
		$this->model->decide( $state, array( 'spam' => DecisionQuestion::noul( 'Is this spam?' ) ) );

		$request = $this->transporter->get_last_request();
		$this->assertNotNull( $request );
		$this->assertStringContainsString( '"state":{"author":"Jane","comment":"Great post!"}', (string) $request->getBody() );
	}

	/**
	 * Tests that the result falls back to the requested model and zero usage when the response omits them.
	 */
	public function test_decide_tolerates_missing_model_and_usage(): void {
		$this->transporter->set_response_to_return(
			$this->make_response( array( 'answers' => array( 'spam' => array( 'noul' => 0.1 ) ) ) )
		);

		$result = $this->model->decide( 'Buy now!', array( 'spam' => DecisionQuestion::noul( 'Is this spam?' ) ) );

		$this->assertSame( 'tev1', $result->getModelId() );
		$this->assertSame( 0, $result->getTokenUsage()->getTotalTokens() );
	}

	/**
	 * Tests that custom options are sent as top-level parameters, minus the transport-only ones.
	 */
	public function test_decide_passes_custom_options_except_timeouts(): void {
		$config = new ModelConfig();
		$config->setCustomOptions(
			array(
				'keep_alive'             => '10m',
				'ollama.request_timeout' => 5,
			)
		);
		$this->model->setConfig( $config );
		$this->transporter->set_response_to_return(
			$this->make_response( array( 'answers' => array( 'q' => array( 'noul' => 0.5 ) ) ) )
		);

		$this->model->decide( 'State', array( 'q' => DecisionQuestion::noul( 'Is it?' ) ) );

		$request = $this->transporter->get_last_request();
		$this->assertNotNull( $request );
		$data = $request->getData();
		$this->assertIsArray( $data );
		$this->assertSame( '10m', $data['keep_alive'] );
		$this->assertArrayNotHasKey( 'ollama.request_timeout', $data );

		$options = $request->getOptions();
		$this->assertNotNull( $options );
		$this->assertSame( 5.0, $options->getTimeout() );
	}

	/**
	 * Tests that a custom option cannot overwrite the request's own parameters.
	 */
	public function test_decide_rejects_conflicting_custom_option(): void {
		$config = new ModelConfig();
		$config->setCustomOptions( array( 'state' => 'Overridden' ) );
		$this->model->setConfig( $config );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'The custom option "state" conflicts' );

		$this->model->decide( 'State', array( 'q' => DecisionQuestion::noul( 'Is it?' ) ) );
	}

	/**
	 * Provides invalid decide() arguments.
	 *
	 * @return array<string, array{mixed, array<mixed>, string}>
	 */
	public function data_invalid_arguments(): array {
		$question = DecisionQuestion::noul( 'Is it?' );

		return array(
			'empty string state' => array( ' ', array( 'q' => $question ), 'non-empty string or array' ),
			'empty array state'  => array( array(), array( 'q' => $question ), 'non-empty string or array' ),
			'integer state'      => array( 42, array( 'q' => $question ), 'non-empty string or array' ),
			'no questions'       => array( 'State', array(), 'between 1 and 64 questions, 0 given' ),
			'too many questions' => array(
				'State',
				array_combine(
					array_map(
						static function ( int $i ): string {
							return 'q' . $i;
						},
						range( 1, 65 )
					),
					array_fill( 0, 65, $question )
				),
				'65 given',
			),
			'list of questions'  => array( 'State', array( $question ), 'keyed by non-empty string names' ),
			'raw array question' => array(
				'State',
				array(
					'q' => array(
						'type'         => 'noul',
						'instructions' => 'Is it?',
					),
				),
				'"q" must be a DecisionQuestion instance',
			),
		);
	}

	/**
	 * Tests that invalid arguments are rejected without sending a request.
	 *
	 * @dataProvider data_invalid_arguments
	 *
	 * @param mixed        $state            The state.
	 * @param array<mixed> $questions        The questions.
	 * @param string       $expected_message Part of the expected exception message.
	 */
	public function test_decide_rejects_invalid_arguments( $state, array $questions, string $expected_message ): void {
		try {
			$this->model->decide( $state, $questions );
			$this->fail( 'Expected an InvalidArgumentException.' );
		} catch ( InvalidArgumentException $e ) {
			$this->assertStringContainsString( $expected_message, $e->getMessage() );
		}

		$this->assertSame( 0, $this->transporter->get_request_count() );
	}

	/**
	 * Tests that a body over Ollama's 64 KiB limit is rejected before it is sent.
	 */
	public function test_decide_rejects_oversized_body(): void {
		try {
			$this->model->decide( str_repeat( 'a', 70000 ), array( 'q' => DecisionQuestion::noul( 'Is it?' ) ) );
			$this->fail( 'Expected an InvalidArgumentException.' );
		} catch ( InvalidArgumentException $e ) {
			$this->assertStringContainsString( 'Ollama accepts at most 65536', $e->getMessage() );
		}

		$this->assertSame( 0, $this->transporter->get_request_count() );
	}

	/**
	 * Tests that a response without answers throws.
	 */
	public function test_decide_throws_when_answers_missing(): void {
		$this->transporter->set_response_to_return( $this->make_response( array( 'model' => 'tev1' ) ) );

		$this->expectException( ResponseException::class );
		$this->expectExceptionMessage( 'answers' );

		$this->model->decide( 'State', array( 'q' => DecisionQuestion::noul( 'Is it?' ) ) );
	}

	/**
	 * Tests that a response missing one of the questions asked throws, naming it.
	 */
	public function test_decide_throws_when_an_answer_is_missing(): void {
		$this->transporter->set_response_to_return(
			$this->make_response( array( 'answers' => array( 'other' => array( 'noul' => 0.5 ) ) ) )
		);

		$this->expectException( ResponseException::class );
		$this->expectExceptionMessage( 'answers.q' );

		$this->model->decide( 'State', array( 'q' => DecisionQuestion::noul( 'Is it?' ) ) );
	}

	/**
	 * Tests that Ollama's error message, e.g. for a model without decision support, reaches the caller.
	 */
	public function test_decide_surfaces_ollama_error_for_unsupported_model(): void {
		$this->transporter->set_response_to_return(
			$this->make_response(
				array( 'error' => 'model "qwen2.5:3b" is not supported by System One; use a local Nimble or Tev GGUF model' ),
				400
			)
		);

		$this->expectException( ClientException::class );
		$this->expectExceptionMessage( 'not supported by System One' );

		$this->model->decide( 'State', array( 'q' => DecisionQuestion::noul( 'Is it?' ) ) );
	}
}

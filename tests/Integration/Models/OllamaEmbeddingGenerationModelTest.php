<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Tests\Integration\Models;

use Fueled\AiProviderForOllama\Models\OllamaEmbeddingGenerationModel;
use Fueled\AiProviderForOllama\Tests\Integration\Mocks\MockHttpTransporter;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\EmbeddingGeneration\Contracts\EmbeddingGenerationModelInterface;

/**
 * Tests for OllamaEmbeddingGenerationModel.
 *
 * @covers \Fueled\AiProviderForOllama\Models\OllamaEmbeddingGenerationModel
 */
class OllamaEmbeddingGenerationModelTest extends TestCase {

	/**
	 * Model under test.
	 *
	 * @var \Fueled\AiProviderForOllama\Models\OllamaEmbeddingGenerationModel
	 */
	private OllamaEmbeddingGenerationModel $model;

	/**
	 * Shared mock transporter for request/response inspection.
	 *
	 * @var \Fueled\AiProviderForOllama\Tests\Integration\Mocks\MockHttpTransporter
	 */
	private MockHttpTransporter $transporter;

	protected function setUp(): void {
		parent::setUp();

		// The embedding contracts are unreleased in some SDK versions; skip before referencing the class.
		if ( ! interface_exists( EmbeddingGenerationModelInterface::class ) ) {
			$this->markTestSkipped( 'SDK does not support embedding generation.' );
		}

		putenv( 'OLLAMA_HOST=http://localhost:11434' );

		$model_metadata    = new ModelMetadata( 'nomic-embed-text', 'Nomic Embed Text', array(), array() );
		$provider_metadata = new ProviderMetadata( 'ollama', 'Ollama', ProviderTypeEnum::cloud(), null, null );

		$this->model       = new OllamaEmbeddingGenerationModel( $model_metadata, $provider_metadata );
		$this->transporter = new MockHttpTransporter();

		$this->model->setHttpTransporter( $this->transporter );
		$this->model->setRequestAuthentication( new ApiKeyRequestAuthentication( '' ) );
	}

	protected function tearDown(): void {
		putenv( 'OLLAMA_HOST' );
		parent::tearDown();
	}

	/**
	 * Builds a single text message part for one embedding input.
	 *
	 * @param string $text Input text.
	 * @return \WordPress\AiClient\Messages\DTO\MessagePart
	 */
	private function make_input( string $text ): MessagePart {
		return new MessagePart( $text );
	}

	/**
	 * Builds a mock API response payload for /api/embed.
	 *
	 * @param array<string, mixed> $data Payload data.
	 * @return \WordPress\AiClient\Providers\Http\DTO\Response
	 */
	private function make_response( array $data ): Response {
		return new Response( 200, array(), (string) json_encode( $data ) );
	}

	/**
	 * Tests request construction and embedding parsing for a single input.
	 */
	public function test_generate_embedding_result_sends_expected_request_and_parses_vectors(): void {
		$this->transporter->set_response_to_return(
			$this->make_response(
				array(
					'model'             => 'nomic-embed-text',
					'embeddings'        => array( array( 0.1, 0.2, 0.3 ) ),
					'prompt_eval_count' => 8,
				)
			)
		);

		$result = $this->model->generateEmbeddingResult( array( $this->make_input( 'PHP powers the web.' ) ) );

		$request = $this->transporter->get_last_request();
		$this->assertNotNull( $request );
		$this->assertTrue( $request->getMethod()->isPost() );
		$this->assertSame( 'http://localhost:11434/api/embed', $request->getUri() );
		$this->assertSame( 'application/json', $request->getHeaderAsString( 'Content-Type' ) );
		$this->assertSame(
			array(
				'model' => 'nomic-embed-text',
				'input' => array( 'PHP powers the web.' ),
			),
			$request->getData()
		);

		$options = $request->getOptions();
		$this->assertNotNull( $options );
		$this->assertSame( 60.0, $options->getTimeout() );
		$this->assertSame( 10.0, $options->getConnectTimeout() );

		$embeddings = $result->getEmbeddings();
		$this->assertCount( 1, $embeddings );
		$this->assertSame( array( 0.1, 0.2, 0.3 ), $embeddings[0]->getValues() );
		$this->assertSame( 3, $result->getDimensions() );
		$this->assertSame( 8, $result->getTokenUsage()->getPromptTokens() );
	}

	/**
	 * Tests that a batch of inputs produces one input string per input and vectors in order.
	 */
	public function test_generate_embedding_result_handles_batch_inputs(): void {
		$this->transporter->set_response_to_return(
			$this->make_response(
				array(
					'embeddings' => array(
						array( 0.1, 0.2 ),
						array( 0.3, 0.4 ),
					),
				)
			)
		);

		$result = $this->model->generateEmbeddingResult(
			array(
				$this->make_input( 'first text' ),
				$this->make_input( 'second text' ),
			)
		);

		$request = $this->transporter->get_last_request();
		$this->assertNotNull( $request );
		$data = $request->getData();
		$this->assertSame( array( 'first text', 'second text' ), $data['input'] );

		$embeddings = $result->getEmbeddings();
		$this->assertCount( 2, $embeddings );
		$this->assertSame( array( 0.1, 0.2 ), $embeddings[0]->getValues() );
		$this->assertSame( array( 0.3, 0.4 ), $embeddings[1]->getValues() );
	}

	/**
	 * Tests that the dimensions config value is sent in the request payload when set.
	 */
	public function test_generate_embedding_result_includes_dimensions_when_configured(): void {
		$this->model->setConfig( ModelConfig::fromArray( array( 'dimensions' => 512 ) ) );

		$this->transporter->set_response_to_return(
			$this->make_response( array( 'embeddings' => array( array( 0.1, 0.2 ) ) ) )
		);

		$this->model->generateEmbeddingResult( array( $this->make_input( 'text' ) ) );

		$request = $this->transporter->get_last_request();
		$this->assertNotNull( $request );
		$this->assertSame( 512, $request->getData()['dimensions'] );
	}

	/**
	 * Tests that dimensions is omitted from the payload when not configured.
	 */
	public function test_generate_embedding_result_omits_dimensions_when_not_configured(): void {
		$this->transporter->set_response_to_return(
			$this->make_response( array( 'embeddings' => array( array( 0.1, 0.2 ) ) ) )
		);

		$this->model->generateEmbeddingResult( array( $this->make_input( 'text' ) ) );

		$request = $this->transporter->get_last_request();
		$this->assertNotNull( $request );
		$this->assertArrayNotHasKey( 'dimensions', $request->getData() );
	}

	/**
	 * Tests that native custom options pass through while transport-only timeouts are stripped and applied.
	 */
	public function test_custom_options_pass_through_and_timeouts_are_stripped(): void {
		$this->model->setConfig(
			ModelConfig::fromArray(
				array(
					'customOptions' => array(
						'truncate'               => false,
						'ollama.request_timeout' => 45,
						'ollama.connect_timeout' => 2,
					),
				)
			)
		);

		$this->transporter->set_response_to_return(
			$this->make_response( array( 'embeddings' => array( array( 0.1, 0.2 ) ) ) )
		);

		$this->model->generateEmbeddingResult( array( $this->make_input( 'text' ) ) );

		$request = $this->transporter->get_last_request();
		$this->assertNotNull( $request );

		$data = $request->getData();
		$this->assertFalse( $data['truncate'] );
		$this->assertArrayNotHasKey( 'ollama.request_timeout', $data );
		$this->assertArrayNotHasKey( 'ollama.connect_timeout', $data );

		$options = $request->getOptions();
		$this->assertNotNull( $options );
		$this->assertSame( 45.0, $options->getTimeout() );
		$this->assertSame( 2.0, $options->getConnectTimeout() );
	}

	/**
	 * Tests that the embeddings key is stripped from additionalData while other fields remain.
	 */
	public function test_embeddings_key_is_stripped_from_additional_data(): void {
		$this->transporter->set_response_to_return(
			$this->make_response(
				array(
					'model'          => 'nomic-embed-text',
					'embeddings'     => array( array( 0.1, 0.2 ) ),
					'total_duration' => 1234,
				)
			)
		);

		$result          = $this->model->generateEmbeddingResult( array( $this->make_input( 'text' ) ) );
		$additional_data = $result->getAdditionalData();

		$this->assertArrayNotHasKey( 'embeddings', $additional_data );
		$this->assertArrayHasKey( 'total_duration', $additional_data );
	}

	/**
	 * Tests that a response missing the embeddings key throws a ResponseException.
	 */
	public function test_missing_embeddings_in_response_throws_exception(): void {
		$this->transporter->set_response_to_return(
			$this->make_response( array( 'model' => 'nomic-embed-text' ) )
		);

		$this->expectException( ResponseException::class );
		$this->model->generateEmbeddingResult( array( $this->make_input( 'text' ) ) );
	}

	/**
	 * Tests that a non-text (file) input part is rejected.
	 */
	public function test_non_text_input_part_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		$this->model->generateEmbeddingResult(
			array(
				new MessagePart( new File( 'data:image/png;base64,QUJDRA==', 'image/png' ) ),
			)
		);
	}

	/**
	 * Tests that a blank text input part is rejected.
	 */
	public function test_blank_text_input_part_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->model->generateEmbeddingResult( array( new MessagePart( '   ' ) ) );
	}

	/**
	 * Tests that an empty input list is rejected.
	 */
	public function test_empty_input_list_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->model->generateEmbeddingResult( array() );
	}
}

<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Tests\Integration\Models;

use Fueled\AiProviderForOllama\Models\OllamaImageGenerationModel;
use Fueled\AiProviderForOllama\Tests\Integration\Mocks\MockHttpTransporter;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

/**
 * Tests for OllamaImageGenerationModel.
 *
 * @covers \Fueled\AiProviderForOllama\Models\OllamaImageGenerationModel
 */
class OllamaImageGenerationModelTest extends TestCase {

	/**
	 * Model under test.
	 *
	 * @var OllamaImageGenerationModel
	 */
	private OllamaImageGenerationModel $model;

	/**
	 * Shared mock transporter for request/response inspection.
	 *
	 * @var MockHttpTransporter
	 */
	private MockHttpTransporter $transporter;

	protected function setUp(): void {
		parent::setUp();
		putenv( 'OLLAMA_HOST=http://localhost:11434' );

		$model_metadata    = new ModelMetadata( 'x/flux2-klein', 'x/z-image-turbo', array(), array() );
		$provider_metadata = new ProviderMetadata( 'ollama', 'Ollama', ProviderTypeEnum::cloud(), null, null );

		$this->model       = new OllamaImageGenerationModel( $model_metadata, $provider_metadata );
		$this->transporter = new MockHttpTransporter();

		$this->model->setHttpTransporter( $this->transporter );
		$this->model->setRequestAuthentication( new ApiKeyRequestAuthentication( '' ) );
	}

	protected function tearDown(): void {
		putenv( 'OLLAMA_HOST' );
		parent::tearDown();
	}

	/**
	 * Builds a single-message user prompt accepted by image generation.
	 *
	 * @param string $text Prompt text.
	 * @return array<Message>
	 */
	private function make_prompt( string $text ): array {
		return array(
			new Message(
				MessageRoleEnum::user(),
				array( new MessagePart( $text ) )
			),
		);
	}

	/**
	 * Builds a mock API response payload for /api/generate.
	 *
	 * @param array<string, mixed> $data Payload data.
	 * @return Response
	 */
	private function make_response( array $data ): Response {
		return new Response( 200, array(), (string) json_encode( $data ) );
	}

	/**
	 * Tests request construction and generated image parsing with default MIME and timeouts.
	 */
	public function test_generate_image_result_sends_expected_request_and_parses_image(): void {
		$this->transporter->set_response_to_return(
			$this->make_response(
				array(
					'created_at' => '2026-04-04T00:00:00Z',
					'image'      => 'QUJDRA==',
					'done'       => true,
				)
			)
		);

		$result = $this->model->generateImageResult( $this->make_prompt( 'A red fox in watercolor style' ) );

		$request = $this->transporter->get_last_request();
		$this->assertNotNull( $request );
		$this->assertTrue( $request->getMethod()->isPost() );
		$this->assertSame( 'http://localhost:11434/api/generate', $request->getUri() );
		$this->assertSame( 'application/json', $request->getHeaderAsString( 'Content-Type' ) );
		$this->assertSame(
			array(
				'model'  => 'x/flux2-klein',
				'prompt' => 'A red fox in watercolor style',
				'stream' => false,
			),
			$request->getData()
		);

		$options = $request->getOptions();
		$this->assertNotNull( $options );
		$this->assertSame( 300.0, $options->getTimeout() );
		$this->assertSame( 10.0, $options->getConnectTimeout() );

		$this->assertSame( '2026-04-04T00:00:00Z', $result->getId() );
		$file = $result->toImageFile();
		$this->assertTrue( $file->isImage() );
		$this->assertSame( 'image/png', $file->getMimeType() );
		$this->assertSame( 'QUJDRA==', $file->getBase64Data() );
	}

	/**
	 * Tests that custom timeouts from config are applied to the request.
	 */
	public function test_generate_image_result_applies_custom_timeouts(): void {
		$this->model->setConfig(
			ModelConfig::fromArray(
				array(
					'customOptions' => array(
						'ollama.request_timeout' => 45,
						'ollama.connect_timeout' => 2,
					),
				)
			)
		);

		$this->transporter->set_response_to_return(
			$this->make_response(
				array(
					'image' => 'Rk9PQkFS',
				)
			)
		);

		$this->model->generateImageResult( $this->make_prompt( 'A dramatic mountain sunset' ) );
		$request = $this->transporter->get_last_request();

		$this->assertNotNull( $request );
		$this->assertNotNull( $request->getOptions() );
		$this->assertSame( 45.0, $request->getOptions()->getTimeout() );
		$this->assertSame( 2.0, $request->getOptions()->getConnectTimeout() );
	}

	/**
	 * Tests that an existing connect timeout in request options is preserved.
	 */
	public function test_existing_connect_timeout_is_preserved_when_config_sets_connect_timeout(): void {
		$request_options = new RequestOptions();
		$request_options->setConnectTimeout( 6.0 );
		$request_options->setTimeout( 20.0 );
		$this->model->setRequestOptions( $request_options );

		$this->model->setConfig(
			ModelConfig::fromArray(
				array(
					'customOptions' => array(
						'ollama.request_timeout' => 90,
						'ollama.connect_timeout' => 2,
					),
				)
			)
		);

		$this->transporter->set_response_to_return(
			$this->make_response(
				array(
					'image' => 'Rk9PQkFS',
				)
			)
		);

		$this->model->generateImageResult( $this->make_prompt( 'A cinematic skyline at night' ) );
		$request = $this->transporter->get_last_request();

		$this->assertNotNull( $request );
		$this->assertNotNull( $request->getOptions() );
		$this->assertSame( 90.0, $request->getOptions()->getTimeout() );
		$this->assertSame( 6.0, $request->getOptions()->getConnectTimeout() );
	}

	/**
	 * Tests that generating with anything other than exactly one message is rejected.
	 */
	public function test_generate_image_result_rejects_multiple_messages(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Image generation requires exactly one user message as the prompt.' );

		$this->model->generateImageResult(
			array(
				new Message( MessageRoleEnum::user(), array( new MessagePart( 'Prompt one' ) ) ),
				new Message( MessageRoleEnum::user(), array( new MessagePart( 'Prompt two' ) ) ),
			)
		);
	}

	/**
	 * Tests that generating with a non-user message is rejected.
	 */
	public function test_generate_image_result_rejects_non_user_message(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Image generation requires a user-role message as the prompt.' );

		$this->model->generateImageResult(
			array(
				new Message( MessageRoleEnum::model(), array( new MessagePart( 'Prompt text' ) ) ),
			)
		);
	}

	/**
	 * Tests that generating with a user message missing a text part is rejected.
	 */
	public function test_generate_image_result_rejects_prompt_without_text_part(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Image generation requires a text part in the prompt message.' );

		$this->model->generateImageResult(
			array(
				new Message( MessageRoleEnum::user(), array( new MessagePart( new File( 'data:image/png;base64,QUJDRA==', 'image/png' ) ) ) ),
			)
		);
	}

	/**
	 * Tests that a successful response without an image key throws an exception.
	 */
	public function test_missing_image_in_response_throws_exception(): void {
		$this->transporter->set_response_to_return(
			$this->make_response(
				array(
					'created_at' => '2026-04-04T00:00:00Z',
					'done'       => true,
				)
			)
		);

		$this->expectException( ResponseException::class );
		$this->model->generateImageResult( $this->make_prompt( 'A black cat sketch' ) );
	}

	/**
	 * Tests that an empty string for the image field throws a ResponseException.
	 */
	public function test_empty_image_string_in_response_throws_exception(): void {
		$this->transporter->set_response_to_return(
			$this->make_response( array( 'image' => '' ) )
		);

		$this->expectException( ResponseException::class );
		$this->model->generateImageResult( $this->make_prompt( 'A black cat sketch' ) );
	}

	/**
	 * Tests that a whitespace-only string for the image field throws a ResponseException.
	 */
	public function test_whitespace_only_image_string_in_response_throws_exception(): void {
		$this->transporter->set_response_to_return(
			$this->make_response( array( 'image' => '   ' ) )
		);

		$this->expectException( ResponseException::class );
		$this->model->generateImageResult( $this->make_prompt( 'A mountain cliff' ) );
	}

	/**
	 * Tests that an image already supplied as a data URI is not double-prefixed.
	 */
	public function test_image_already_as_data_uri_is_not_double_prefixed(): void {
		$this->transporter->set_response_to_return(
			$this->make_response( array( 'image' => 'data:image/png;base64,QUJDRA==' ) )
		);

		$result = $this->model->generateImageResult( $this->make_prompt( 'A blue ocean' ) );
		$file   = $result->toImageFile();

		$this->assertSame( 'image/png', $file->getMimeType() );
		$this->assertSame( 'QUJDRA==', $file->getBase64Data() );
	}

	/**
	 * Tests that the result id is an empty string when created_at is absent from the response.
	 */
	public function test_result_id_is_empty_string_when_created_at_is_absent(): void {
		$this->transporter->set_response_to_return(
			$this->make_response( array( 'image' => 'QUJDRA==', 'done' => true ) )
		);

		$result = $this->model->generateImageResult( $this->make_prompt( 'A sunny field' ) );

		$this->assertSame( '', $result->getId() );
	}

	/**
	 * Tests that the image key is stripped from additionalData in the returned result.
	 */
	public function test_image_key_is_stripped_from_additional_data(): void {
		$this->transporter->set_response_to_return(
			$this->make_response(
				array(
					'created_at' => '2026-04-04T00:00:00Z',
					'image'      => 'QUJDRA==',
					'done'       => true,
				)
			)
		);

		$result          = $this->model->generateImageResult( $this->make_prompt( 'A mountain' ) );
		$additional_data = $result->getAdditionalData();

		$this->assertArrayNotHasKey( 'image', $additional_data );
		$this->assertArrayHasKey( 'done', $additional_data );
	}
}

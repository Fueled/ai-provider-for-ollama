<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Tests\Integration\Models;

use Fueled\AiProviderForOllama\Tests\Integration\Mocks\MockHttpTransporter;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

/**
 * Tests for OllamaTextGenerationModel request behavior.
 *
 * @covers \Fueled\AiProviderForOllama\Models\OllamaTextGenerationModel
 */
class OllamaTextGenerationModelTest extends TestCase {

	/**
	 * The model under test (via the expose_create_request() helper).
	 *
	 * @var MockOllamaTextGenerationModel
	 */
	private MockOllamaTextGenerationModel $model;

	protected function setUp(): void {
		parent::setUp();
		putenv( 'OLLAMA_HOST=http://localhost:11434' );

		$model_metadata    = new ModelMetadata( 'llama3.2', 'llama3.2', array(), array() );
		$provider_metadata = new ProviderMetadata( 'ollama', 'Ollama', ProviderTypeEnum::cloud(), null, null );

		$this->model = new MockOllamaTextGenerationModel( $model_metadata, $provider_metadata );
		$this->model->setHttpTransporter( new MockHttpTransporter() );
		$this->model->setRequestAuthentication( new ApiKeyRequestAuthentication( '' ) );
	}

	protected function tearDown(): void {
		putenv( 'OLLAMA_HOST' );
		parent::tearDown();
	}

	/**
	 * Tests that a path without the v1 prefix gets /v1/ prepended.
	 */
	public function test_path_without_v1_prefix_gets_v1_prepended(): void {
		$request = $this->model->expose_create_request(
			HttpMethodEnum::POST(),
			'chat/completions'
		);
		$this->assertStringContainsString( '/v1/chat/completions', $request->getUri() );
	}

	/**
	 * Tests that a path already starting with v1/ does not result in a doubled prefix.
	 */
	public function test_path_with_v1_prefix_is_not_doubled(): void {
		$request = $this->model->expose_create_request(
			HttpMethodEnum::POST(),
			'v1/chat/completions'
		);
		$uri = $request->getUri();
		$this->assertStringContainsString( '/v1/chat/completions', $uri );
		$this->assertStringNotContainsString( '/v1/v1/', $uri );
	}

	/**
	 * Tests that a path with a leading slash and v1 prefix is not doubled.
	 */
	public function test_path_with_leading_slash_v1_is_not_doubled(): void {
		$request = $this->model->expose_create_request(
			HttpMethodEnum::POST(),
			'/v1/chat/completions'
		);
		$uri = $request->getUri();
		$this->assertStringContainsString( '/v1/chat/completions', $uri );
		$this->assertStringNotContainsString( '/v1/v1/', $uri );
	}

	/**
	 * Tests that the request URI starts with the Ollama provider base URL.
	 */
	public function test_request_uses_provider_base_url(): void {
		$request = $this->model->expose_create_request(
			HttpMethodEnum::POST(),
			'chat/completions'
		);
		$this->assertStringStartsWith( 'http://localhost:11434', $request->getUri() );
	}

	/**
	 * Tests that JSON output without schema uses json_object response format.
	 */
	public function test_prepare_response_format_uses_json_object_without_schema(): void {
		$response_format = $this->model->expose_prepare_response_format_param( null );

		$this->assertSame(
			array(
				'type' => 'json_object',
			),
			$response_format
		);
	}

	/**
	 * Tests that JSON schema output is nested at json_schema.schema.
	 */
	public function test_prepare_response_format_wraps_schema_for_ollama_openai_compat(): void {
		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'name' => array( 'type' => 'string' ),
			),
			'required'   => array( 'name' ),
		);

		$response_format = $this->model->expose_prepare_response_format_param( $schema );

		$this->assertSame(
			array(
				'type'        => 'json_schema',
				'json_schema' => array(
					'name'   => 'response_schema',
					'schema' => $schema,
				),
			),
			$response_format
		);
	}
}

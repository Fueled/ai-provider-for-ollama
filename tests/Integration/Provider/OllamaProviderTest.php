<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Tests\Integration\Provider;

use Fueled\AiProviderForOllama\Metadata\OllamaModelMetadataDirectory;
use Fueled\AiProviderForOllama\Models\OllamaImageGenerationModel;
use Fueled\AiProviderForOllama\Models\OllamaTextGenerationModel;
use Fueled\AiProviderForOllama\Provider\OllamaProvider;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\AbstractProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;

/**
 * Tests for OllamaProvider.
 *
 * @covers \Fueled\AiProviderForOllama\Provider\OllamaProvider
 */
class OllamaProviderTest extends TestCase {

	/**
	 * The original OLLAMA_HOST value before each test.
	 *
	 * @var string|false
	 */
	private $original_ollama_host;

	protected function setUp(): void {
		parent::setUp();
		$this->original_ollama_host = getenv( 'OLLAMA_HOST' );
		$this->clear_provider_caches();
	}

	protected function tearDown(): void {
		$this->restore_ollama_host();
		$this->clear_provider_caches();
		parent::tearDown();
	}

	/**
	 * Clears all static caches on AbstractProvider to ensure test isolation.
	 */
	private function clear_provider_caches(): void {
		$reflection = new \ReflectionClass( AbstractProvider::class );
		foreach ( array( 'metadataCache', 'availabilityCache', 'modelMetadataDirectoryCache' ) as $prop_name ) {
			$prop = $reflection->getProperty( $prop_name );
			$prop->setAccessible( true );
			$prop->setValue( null, array() );
		}
	}

	/**
	 * Restores OLLAMA_HOST to its pre-test value.
	 */
	private function restore_ollama_host(): void {
		if ( false === $this->original_ollama_host ) {
			putenv( 'OLLAMA_HOST' );
		} else {
			putenv( 'OLLAMA_HOST=' . $this->original_ollama_host );
		}
	}

	// -----------------------------------------------------------------------
	// url() / baseUrl() tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that url() falls back to localhost when OLLAMA_HOST is not set.
	 */
	public function test_url_falls_back_to_localhost_when_env_var_not_set(): void {
		putenv( 'OLLAMA_HOST' ); // Remove env var entirely
		$url = OllamaProvider::url( '' );
		$this->assertStringStartsWith( 'http://localhost:11434', $url );
	}

	/**
	 * Tests that url() uses the OLLAMA_HOST environment variable when set.
	 */
	public function test_url_uses_ollama_host_env_var(): void {
		putenv( 'OLLAMA_HOST=http://my-server:11434' );
		$url = OllamaProvider::url( '' );
		$this->assertStringStartsWith( 'http://my-server:11434', $url );
	}

	/**
	 * Tests that url() strips a trailing slash from the OLLAMA_HOST env var.
	 */
	public function test_url_strips_trailing_slash_from_env_var(): void {
		putenv( 'OLLAMA_HOST=http://my-server:11434/' );
		$url = OllamaProvider::url( 'path' );
		$this->assertSame( 'http://my-server:11434/path', $url );
	}

	/**
	 * Tests that url() falls back to localhost when OLLAMA_HOST is an empty string.
	 */
	public function test_url_falls_back_when_env_var_is_empty_string(): void {
		putenv( 'OLLAMA_HOST=' );
		$url = OllamaProvider::url( '' );
		$this->assertStringStartsWith( 'http://localhost:11434', $url );
	}

	// -----------------------------------------------------------------------
	// metadata() tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that the provider metadata has the correct provider ID.
	 */
	public function test_metadata_has_correct_provider_id(): void {
		$metadata = OllamaProvider::metadata();
		$this->assertSame( 'ollama', $metadata->getId() );
	}

	/**
	 * Tests that the provider metadata has the correct display name.
	 */
	public function test_metadata_has_correct_name(): void {
		$metadata = OllamaProvider::metadata();
		$this->assertSame( 'Ollama', $metadata->getName() );
	}

	/**
	 * Tests that the provider metadata specifies API key as the authentication method.
	 */
	public function test_metadata_auth_method_is_api_key(): void {
		$metadata     = OllamaProvider::metadata();
		$auth_method  = $metadata->getAuthenticationMethod();
		$this->assertNotNull( $auth_method );
		$this->assertTrue( $auth_method->isApiKey() );
	}

	// -----------------------------------------------------------------------
	// Factory method tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that availability() returns a ListModelsApiBasedProviderAvailability instance.
	 */
	public function test_availability_returns_list_models_api_based_provider_availability(): void {
		$availability = OllamaProvider::availability();
		$this->assertInstanceOf( ListModelsApiBasedProviderAvailability::class, $availability );
	}

	/**
	 * Tests that modelMetadataDirectory() returns an OllamaModelMetadataDirectory instance.
	 */
	public function test_model_metadata_directory_returns_correct_type(): void {
		$directory = OllamaProvider::modelMetadataDirectory();
		$this->assertInstanceOf( OllamaModelMetadataDirectory::class, $directory );
	}

	// -----------------------------------------------------------------------
	// createModel() tests
	// -----------------------------------------------------------------------

	/**
	 * Invokes the protected static createModel() method via reflection.
	 *
	 * @param ModelMetadata $model_metadata
	 * @return \WordPress\AiClient\Providers\Models\Contracts\ModelInterface
	 */
	private function invoke_create_model( ModelMetadata $model_metadata ): \WordPress\AiClient\Providers\Models\Contracts\ModelInterface {
		$method = new \ReflectionMethod( OllamaProvider::class, 'createModel' );
		$method->setAccessible( true );
		return $method->invoke( null, $model_metadata, OllamaProvider::metadata() );
	}

	/**
	 * Tests that createModel() returns an OllamaImageGenerationModel for a model with imageGeneration capability.
	 */
	public function test_create_model_returns_image_generation_model_for_image_generation_capability(): void {
		$model_metadata = new ModelMetadata(
			'stable-diffusion',
			'Stable Diffusion',
			array( CapabilityEnum::imageGeneration() ),
			array()
		);

		$model = $this->invoke_create_model( $model_metadata );

		$this->assertInstanceOf( OllamaImageGenerationModel::class, $model );
	}

	/**
	 * Tests that createModel() returns an OllamaTextGenerationModel for a model with textGeneration capability.
	 */
	public function test_create_model_returns_text_generation_model_for_text_generation_capability(): void {
		$model_metadata = new ModelMetadata(
			'llama3.2',
			'Llama 3.2',
			array( CapabilityEnum::textGeneration() ),
			array()
		);

		$model = $this->invoke_create_model( $model_metadata );

		$this->assertInstanceOf( OllamaTextGenerationModel::class, $model );
	}

	/**
	 * Tests that createModel() prefers imageGeneration over textGeneration when both capabilities are present.
	 */
	public function test_create_model_prefers_image_generation_over_text_generation(): void {
		$model_metadata = new ModelMetadata(
			'multi-model',
			'Multi Model',
			array( CapabilityEnum::imageGeneration(), CapabilityEnum::textGeneration() ),
			array()
		);

		$model = $this->invoke_create_model( $model_metadata );

		$this->assertInstanceOf( OllamaImageGenerationModel::class, $model );
	}

	/**
	 * Tests that createModel() throws a RuntimeException for unsupported capabilities.
	 */
	public function test_create_model_throws_for_unsupported_capabilities(): void {
		$model_metadata = new ModelMetadata(
			'embed-model',
			'Embed Model',
			array( CapabilityEnum::chatHistory() ),
			array()
		);

		$this->expectException( RuntimeException::class );
		$this->invoke_create_model( $model_metadata );
	}
}

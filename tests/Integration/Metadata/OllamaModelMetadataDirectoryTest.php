<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Tests\Integration\Metadata;

use Fueled\AiProviderForOllama\Metadata\OllamaModelMetadataDirectory;
use Fueled\AiProviderForOllama\Tests\Integration\Mocks\MockHttpTransporter;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;

/**
 * Tests for OllamaModelMetadataDirectory.
 *
 * Uses a MockHttpTransporter with queued responses matching Ollama's
 * /api/tags and /api/show response shapes.
 *
 * @covers \Fueled\AiProviderForOllama\Metadata\OllamaModelMetadataDirectory
 */
class OllamaModelMetadataDirectoryTest extends TestCase {

	/**
	 * Directory under test.
	 *
	 * @var OllamaModelMetadataDirectory
	 */
	private OllamaModelMetadataDirectory $directory;

	/**
	 * Shared mock transporter (fresh instance per test).
	 *
	 * @var MockHttpTransporter
	 */
	private MockHttpTransporter $transporter;

	protected function setUp(): void {
		parent::setUp();
		putenv( 'OLLAMA_HOST=http://localhost:11434' );
		$this->transporter = new MockHttpTransporter();
		$this->directory   = new OllamaModelMetadataDirectory();
		$this->directory->setHttpTransporter( $this->transporter );
		$this->directory->setRequestAuthentication( new ApiKeyRequestAuthentication( '' ) );
		$this->directory->invalidateCaches();
	}

	protected function tearDown(): void {
		$this->directory->invalidateCaches();
		putenv( 'OLLAMA_HOST' );
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// Response helpers
	// -----------------------------------------------------------------------

	/**
	 * Builds a fake /api/tags 200 response containing the given model names.
	 *
	 * @param list<string> $model_names The model names to include.
	 * @return Response
	 */
	private function make_tags_response( array $model_names ): Response {
		$models = array_map(
			static function ( string $name ): array {
				return array( 'name' => $name );
			},
			$model_names
		);
		$body = (string) json_encode( array( 'models' => $models ) );
		return new Response( 200, array(), $body );
	}

	/**
	 * Builds a fake /api/show 200 response with the given capabilities and families.
	 *
	 * @param list<string> $capabilities Capability strings (e.g. 'completion', 'vision').
	 * @param list<string> $families     Model families (e.g. 'llama', 'clip').
	 * @return Response
	 */
	private function make_show_response( array $capabilities, array $families = array() ): Response {
		$data = array( 'capabilities' => $capabilities );
		if ( ! empty( $families ) ) {
			$data['details'] = array( 'families' => $families );
		}
		$body = (string) json_encode( $data );
		return new Response( 200, array(), $body );
	}

	/**
	 * Builds a fake error response.
	 *
	 * @param int $status HTTP status code.
	 * @return Response
	 */
	private function make_error_response( int $status = 500 ): Response {
		return new Response( $status, array(), '{"error":"Internal Server Error"}' );
	}

	/**
	 * Returns the SupportedOption whose OptionEnum passes the given is* check,
	 * or null if not found.
	 *
	 * @param list<\WordPress\AiClient\Providers\Models\DTO\SupportedOption> $options Supported options.
	 * @param string $is_method_name The is* method name, e.g. 'isInputModalities'.
	 * @return \WordPress\AiClient\Providers\Models\DTO\SupportedOption|null
	 */
	private function find_option( array $options, string $is_method_name ): ?\WordPress\AiClient\Providers\Models\DTO\SupportedOption {
		foreach ( $options as $opt ) {
			if ( $opt->getName()->$is_method_name() ) {
				return $opt;
			}
		}
		return null;
	}

	// -----------------------------------------------------------------------
	// Basic listing tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that listModelMetadata() returns models parsed from the API response.
	 */
	public function test_returns_models_from_api(): void {
		$this->transporter->queue_response( $this->make_tags_response( array( 'llama3.2' ) ) );
		$this->transporter->queue_response( $this->make_show_response( array( 'completion' ) ) );

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 1, $models );
		$this->assertSame( 'llama3.2', $models[0]->getId() );
	}

	/**
	 * Tests that returned models are sorted alphabetically by model ID.
	 */
	public function test_models_are_sorted_alphabetically(): void {
		// Tags returns models in reverse-alphabetical order.
		$this->transporter->queue_response( $this->make_tags_response( array( 'zmodel', 'amodel' ) ) );
		// show responses consumed in tags order: zmodel first, then amodel.
		$this->transporter->queue_response( $this->make_show_response( array( 'completion' ) ) );
		$this->transporter->queue_response( $this->make_show_response( array( 'completion' ) ) );

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 2, $models );
		$this->assertSame( 'amodel', $models[0]->getId() );
		$this->assertSame( 'zmodel', $models[1]->getId() );
	}

	// -----------------------------------------------------------------------
	// Capability / embedding-filter tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that embedding-only models (non-empty capabilities without 'completion') are excluded.
	 */
	public function test_embedding_only_model_is_excluded(): void {
		$this->transporter->queue_response( $this->make_tags_response( array( 'nomic-embed-text' ) ) );
		$this->transporter->queue_response( $this->make_show_response( array( 'embedding' ) ) );

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 0, $models );
	}

	/**
	 * Tests that a model with an empty capabilities array is included (no capabilities = not embedding-only).
	 */
	public function test_model_with_empty_capabilities_is_included(): void {
		$this->transporter->queue_response( $this->make_tags_response( array( 'llama3.2' ) ) );
		$this->transporter->queue_response( $this->make_show_response( array() ) );

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 1, $models );
	}

	/**
	 * Tests that a model with the 'completion' capability is included.
	 */
	public function test_model_with_completion_capability_is_included(): void {
		$this->transporter->queue_response( $this->make_tags_response( array( 'llama3.2' ) ) );
		$this->transporter->queue_response( $this->make_show_response( array( 'completion' ) ) );

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 1, $models );
	}

	// -----------------------------------------------------------------------
	// Vision-detection tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that a model with the 'vision' capability gets text+image input modalities.
	 */
	public function test_vision_model_detected_via_capability(): void {
		$this->transporter->queue_response( $this->make_tags_response( array( 'llava' ) ) );
		$this->transporter->queue_response( $this->make_show_response( array( 'completion', 'vision' ) ) );

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 1, $models );
		$input_modalities_opt = $this->find_option( $models[0]->getSupportedOptions(), 'isInputModalities' );
		$this->assertNotNull( $input_modalities_opt, 'Expected inputModalities supported option' );
		// Vision model: text-only AND text+image.
		$this->assertCount( 2, (array) $input_modalities_opt->getSupportedValues() );
	}

	/**
	 * Tests that a model whose details families contain 'clip' gets text+image input modalities.
	 */
	public function test_vision_model_detected_via_clip_family(): void {
		$this->transporter->queue_response( $this->make_tags_response( array( 'llava' ) ) );
		$this->transporter->queue_response( $this->make_show_response( array( 'completion' ), array( 'llama', 'clip' ) ) );

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 1, $models );
		$input_modalities_opt = $this->find_option( $models[0]->getSupportedOptions(), 'isInputModalities' );
		$this->assertNotNull( $input_modalities_opt, 'Expected inputModalities supported option' );
		$this->assertCount( 2, (array) $input_modalities_opt->getSupportedValues() );
	}

	/**
	 * Tests that a non-vision model has text-only input modalities.
	 */
	public function test_non_vision_model_has_text_only_modalities(): void {
		$this->transporter->queue_response( $this->make_tags_response( array( 'llama3.2' ) ) );
		$this->transporter->queue_response( $this->make_show_response( array( 'completion' ) ) );

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 1, $models );
		$input_modalities_opt = $this->find_option( $models[0]->getSupportedOptions(), 'isInputModalities' );
		$this->assertNotNull( $input_modalities_opt, 'Expected inputModalities supported option' );
		// Non-vision: text-only input only.
		$this->assertCount( 1, (array) $input_modalities_opt->getSupportedValues() );
	}

	// -----------------------------------------------------------------------
	// Graceful-degradation and error tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that a failed /api/show request causes a text-only fallback (model is still included).
	 */
	public function test_show_request_failure_falls_back_to_text_generation(): void {
		$this->transporter->queue_response( $this->make_tags_response( array( 'llama3.2' ) ) );
		$this->transporter->queue_response( $this->make_error_response() );

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 1, $models );
		// Fallback: text-only input modalities (not vision).
		$input_modalities_opt = $this->find_option( $models[0]->getSupportedOptions(), 'isInputModalities' );
		$this->assertNotNull( $input_modalities_opt );
		$this->assertCount( 1, (array) $input_modalities_opt->getSupportedValues() );
	}

	/**
	 * Tests that a /api/tags response missing the 'models' key throws a ResponseException.
	 */
	public function test_missing_models_key_throws_exception(): void {
		$this->transporter->set_response_to_return(
			new Response( 200, array(), (string) json_encode( array( 'not_models' => array() ) ) )
		);

		$this->expectException( ResponseException::class );
		$this->directory->listModelMetadata();
	}

	/**
	 * Tests that a failed /api/tags request propagates the exception.
	 */
	public function test_failed_tags_request_throws_exception(): void {
		$this->transporter->set_response_to_return( $this->make_error_response() );

		$this->expectException( \Throwable::class );
		$this->directory->listModelMetadata();
	}

	// -----------------------------------------------------------------------
	// Options completeness test
	// -----------------------------------------------------------------------

	/**
	 * Tests that all standard model options are present on a returned ModelMetadata.
	 */
	public function test_all_standard_options_are_present(): void {
		$this->transporter->queue_response( $this->make_tags_response( array( 'llama3.2' ) ) );
		$this->transporter->queue_response( $this->make_show_response( array( 'completion' ) ) );

		$models = $this->directory->listModelMetadata();
		$this->assertCount( 1, $models );

		$option_names = array_map(
			static function ( $opt ): string {
				return (string) $opt->getName();
			},
			$models[0]->getSupportedOptions()
		);

		$expected_options = array(
			'systemInstruction',
			'maxTokens',
			'temperature',
			'topP',
			'topK',
			'stopSequences',
			'frequencyPenalty',
			'presencePenalty',
			'outputMimeType',
			'outputSchema',
			'functionDeclarations',
			'customOptions',
		);

		foreach ( $expected_options as $expected ) {
			$this->assertContains(
				$expected,
				$option_names,
				sprintf( 'Expected option "%s" to be present in model metadata', $expected )
			);
		}
	}
}

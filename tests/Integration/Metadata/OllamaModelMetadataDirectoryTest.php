<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Tests\Integration\Metadata;

use Fueled\AiProviderForOllama\Metadata\OllamaModelMetadataDirectory;
use Fueled\AiProviderForOllama\Tests\Integration\Mocks\MockHttpTransporter;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\UserMessage;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\ModelRequirements;
use WordPress\AiClient\Providers\Models\EmbeddingGeneration\Contracts\EmbeddingGenerationModelInterface;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;

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
	 * Builds a fake /api/tags 200 response.
	 *
	 * Entries may be given as plain model names, or as full entry arrays to
	 * cover the Ollama versions that report capabilities in the tag listing
	 * itself.
	 *
	 * @param list<string|array<string, mixed>> $model_entries The entries to include.
	 * @return Response
	 */
	private function make_tags_response( array $model_entries ): Response {
		$models = array_map(
			static function ( $entry ): array {
				return is_array( $entry ) ? $entry : array( 'name' => $entry );
			},
			$model_entries
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

	/**
	 * Returns the supported option names for a model.
	 *
	 * @param ModelMetadata $model Model metadata.
	 * @return list<string> Option names.
	 */
	private function option_names( ModelMetadata $model ): array {
		return array_map(
			static function ( $opt ): string {
				return (string) $opt->getName();
			},
			$model->getSupportedOptions()
		);
	}

	/**
	 * Returns IDs of models that meet the given requirements.
	 *
	 * @param list<ModelMetadata> $models Models to filter.
	 * @param ModelRequirements   $requirements Requirements to check.
	 * @return list<string> Matching model IDs, in original order.
	 */
	private function matching_model_ids( array $models, ModelRequirements $requirements ): array {
		$ids = array();
		foreach ( $models as $model ) {
			if ( $requirements->areMetBy( $model ) ) {
				$ids[] = $model->getId();
			}
		}
		return $ids;
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
	 * Tests that embedding-only models are excluded when the SDK lacks embedding support.
	 */
	public function test_embedding_only_model_is_excluded_without_sdk_support(): void {
		if ( interface_exists( EmbeddingGenerationModelInterface::class ) ) {
			$this->markTestSkipped( 'SDK supports embedding generation; embedding-only models are included.' );
		}

		$this->transporter->queue_response( $this->make_tags_response( array( 'nomic-embed-text' ) ) );
		$this->transporter->queue_response( $this->make_show_response( array( 'embedding' ) ) );

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 0, $models );
	}

	/**
	 * Tests that embedding-capable models are included with the embeddingGeneration capability when the SDK supports it.
	 */
	public function test_embedding_only_model_is_included_with_embedding_capability(): void {
		if ( ! interface_exists( EmbeddingGenerationModelInterface::class ) ) {
			$this->markTestSkipped( 'SDK does not support embedding generation.' );
		}

		$this->transporter->queue_response( $this->make_tags_response( array( 'nomic-embed-text' ) ) );
		$this->transporter->queue_response( $this->make_show_response( array( 'embedding' ) ) );

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 1, $models );
		$this->assertSame( 'nomic-embed-text', $models[0]->getId() );

		$has_embedding_capability = false;
		foreach ( $models[0]->getSupportedCapabilities() as $capability ) {
			if ( $capability->isEmbeddingGeneration() ) {
				$has_embedding_capability = true;
				break;
			}
		}
		$this->assertTrue( $has_embedding_capability, 'Expected embeddingGeneration capability.' );

		$this->assertNotNull(
			$this->find_option( $models[0]->getSupportedOptions(), 'isDimensions' ),
			'Expected dimensions supported option.'
		);
		$this->assertNotNull(
			$this->find_option( $models[0]->getSupportedOptions(), 'isInputModalities' ),
			'Expected inputModalities supported option.'
		);
		$this->assertNotNull(
			$this->find_option( $models[0]->getSupportedOptions(), 'isCustomOptions' ),
			'Expected customOptions supported option.'
		);
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
	// Tools-detection tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that a completion-only model does not advertise functionDeclarations.
	 */
	public function test_completion_only_model_does_not_advertise_function_declarations(): void {
		$this->transporter->queue_response( $this->make_tags_response( array( 'gemma3:latest' ) ) );
		$this->transporter->queue_response( $this->make_show_response( array( 'completion' ) ) );

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 1, $models );
		$this->assertNotContains( 'functionDeclarations', $this->option_names( $models[0] ) );
	}

	/**
	 * Tests that a model with the 'tools' capability advertises functionDeclarations.
	 */
	public function test_tools_capability_advertises_function_declarations(): void {
		$this->transporter->queue_response( $this->make_tags_response( array( 'qwen2.5:3b' ) ) );
		$this->transporter->queue_response( $this->make_show_response( array( 'completion', 'tools' ) ) );

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 1, $models );
		$this->assertContains( 'functionDeclarations', $this->option_names( $models[0] ) );
	}

	/**
	 * Tests that vision support does not imply functionDeclarations.
	 */
	public function test_vision_model_without_tools_does_not_advertise_function_declarations(): void {
		$this->transporter->queue_response( $this->make_tags_response( array( 'gemma3:latest' ) ) );
		$this->transporter->queue_response( $this->make_show_response( array( 'completion', 'vision' ) ) );

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 1, $models );
		$this->assertNotContains( 'functionDeclarations', $this->option_names( $models[0] ) );
	}

	/**
	 * Tests that a vision model can still advertise functionDeclarations when it also reports tools.
	 */
	public function test_vision_and_tools_model_advertises_function_declarations(): void {
		$this->transporter->queue_response( $this->make_tags_response( array( 'qwen2.5-vl' ) ) );
		$this->transporter->queue_response( $this->make_show_response( array( 'completion', 'vision', 'tools' ) ) );

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 1, $models );
		$this->assertContains( 'functionDeclarations', $this->option_names( $models[0] ) );
	}

	/**
	 * Tests that an empty capabilities array does not advertise functionDeclarations.
	 */
	public function test_empty_capabilities_do_not_advertise_function_declarations(): void {
		$this->transporter->queue_response( $this->make_tags_response( array( 'llama3.2' ) ) );
		$this->transporter->queue_response( $this->make_show_response( array() ) );

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 1, $models );
		$this->assertNotContains( 'functionDeclarations', $this->option_names( $models[0] ) );
	}

	/**
	 * Tests that a failed /api/show fallback does not advertise functionDeclarations.
	 */
	public function test_show_request_failure_does_not_advertise_function_declarations(): void {
		$this->transporter->queue_response( $this->make_tags_response( array( 'llama3.2' ) ) );
		$this->transporter->queue_response( $this->make_error_response() );

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 1, $models );
		$this->assertNotContains( 'functionDeclarations', $this->option_names( $models[0] ) );
	}

	/**
	 * Tests that a function-declarations requirement matches only tool-capable models.
	 */
	public function test_function_declarations_requirement_matches_only_tool_capable_models(): void {
		$this->transporter->queue_response( $this->make_tags_response( array( 'gemma3:latest', 'qwen2.5:3b' ) ) );
		$this->transporter->queue_response( $this->make_show_response( array( 'completion', 'vision' ) ) );
		$this->transporter->queue_response( $this->make_show_response( array( 'completion', 'tools' ) ) );

		$models   = $this->directory->listModelMetadata();
		$messages = array(
			new UserMessage(
				array( new MessagePart( 'Improve the meta description of post 5.' ) )
			),
		);

		$plain_requirements = ModelRequirements::fromPromptData(
			CapabilityEnum::textGeneration(),
			$messages,
			new ModelConfig()
		);
		$this->assertSame(
			array( 'gemma3:latest', 'qwen2.5:3b' ),
			$this->matching_model_ids( $models, $plain_requirements )
		);

		$config = new ModelConfig();
		$config->setFunctionDeclarations(
			array( new FunctionDeclaration( 'update_post', 'Update a post', null ) )
		);
		$tool_requirements = ModelRequirements::fromPromptData(
			CapabilityEnum::textGeneration(),
			$messages,
			$config
		);
		$this->assertSame(
			array( 'qwen2.5:3b' ),
			$this->matching_model_ids( $models, $tool_requirements )
		);
	}

	// -----------------------------------------------------------------------
	// Image-generation detection tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that a model with the 'image' capability is treated as an image-generation model.
	 *
	 * Image-generation models are included even without 'completion', and they
	 * receive image-focused options (outputMimeType = image/png) rather than
	 * standard text-generation options (no systemInstruction, etc.).
	 */
	public function test_image_generation_model_detected_via_image_capability(): void {
		$this->transporter->queue_response( $this->make_tags_response( array( 'stable-diffusion' ) ) );
		$this->transporter->queue_response( $this->make_show_response( array( 'image' ) ) );

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 1, $models );

		$option_names = $this->option_names( $models[0] );

		// Image-generation models get image/png mime type, not text options.
		$output_mime_opt = $this->find_option( $models[0]->getSupportedOptions(), 'isOutputMimeType' );
		$this->assertNotNull( $output_mime_opt, 'Expected outputMimeType supported option' );
		$this->assertContains( 'image/png', (array) $output_mime_opt->getSupportedValues() );
		$this->assertNotContains( 'text/plain', (array) $output_mime_opt->getSupportedValues() );

		// Standard text-generation options should be absent.
		$this->assertNotContains( 'systemInstruction', $option_names );
		$this->assertNotContains( 'maxTokens', $option_names );
	}

	/**
	 * Tests that an image-generation model without 'completion' is not filtered out as embedding-only.
	 */
	public function test_image_generation_model_without_completion_is_not_excluded(): void {
		$this->transporter->queue_response( $this->make_tags_response( array( 'stable-diffusion' ) ) );
		// Only 'image' capability — no 'completion'.
		$this->transporter->queue_response( $this->make_show_response( array( 'image' ) ) );

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 1, $models );
		$this->assertSame( 'stable-diffusion', $models[0]->getId() );
	}

	/**
	 * Tests that a model with both 'image' and 'completion' capabilities is treated as image-generation.
	 */
	public function test_image_generation_model_with_completion_capability_is_detected(): void {
		$this->transporter->queue_response( $this->make_tags_response( array( 'stable-diffusion' ) ) );
		$this->transporter->queue_response( $this->make_show_response( array( 'completion', 'image' ) ) );

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 1, $models );

		$output_mime_opt = $this->find_option( $models[0]->getSupportedOptions(), 'isOutputMimeType' );
		$this->assertNotNull( $output_mime_opt );
		$this->assertContains( 'image/png', (array) $output_mime_opt->getSupportedValues() );
	}

	/**
	 * Tests that a null details (failed /api/show) is not treated as an image-generation model.
	 */
	public function test_null_details_does_not_produce_image_generation_model(): void {
		$this->transporter->queue_response( $this->make_tags_response( array( 'llama3.2' ) ) );
		$this->transporter->queue_response( $this->make_error_response() );

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 1, $models );

		// Fallback model should have text/plain mime type, not image/png.
		$output_mime_opt = $this->find_option( $models[0]->getSupportedOptions(), 'isOutputMimeType' );
		$this->assertNotNull( $output_mime_opt );
		$this->assertContains( 'text/plain', (array) $output_mime_opt->getSupportedValues() );
		$this->assertNotContains( 'image/png', (array) $output_mime_opt->getSupportedValues() );
	}

	/**
	 * Tests that a model with outputModalities image-only for image-generation models.
	 */
	public function test_image_generation_model_has_image_output_modality(): void {
		$this->transporter->queue_response( $this->make_tags_response( array( 'stable-diffusion' ) ) );
		$this->transporter->queue_response( $this->make_show_response( array( 'image' ) ) );

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 1, $models );

		$output_modalities_opt = $this->find_option( $models[0]->getSupportedOptions(), 'isOutputModalities' );
		$this->assertNotNull( $output_modalities_opt, 'Expected outputModalities supported option' );
		$output_modalities = (array) $output_modalities_opt->getSupportedValues();
		// Should have exactly one combination: [image].
		$this->assertCount( 1, $output_modalities );
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

		$option_names = $this->option_names( $models[0] );

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

	// -----------------------------------------------------------------------
	// Request-count tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that capabilities reported by /api/tags remove the need for /api/show.
	 */
	public function test_capabilities_in_tags_avoid_the_per_model_request(): void {
		$this->transporter->queue_response(
			$this->make_tags_response(
				array(
					array(
						'name'         => 'qwen2.5:3b',
						'capabilities' => array( 'completion', 'tools' ),
					),
				)
			)
		);

		$models = $this->directory->listModelMetadata();

		$this->assertSame( 1, $this->transporter->get_request_count(), 'Expected the tag listing to be the only request.' );
		$this->assertCount( 1, $models );
		$this->assertContains( 'functionDeclarations', $this->option_names( $models[0] ) );
	}

	/**
	 * Tests that the clip family in a tag entry is enough to detect vision support.
	 */
	public function test_vision_is_detected_from_tags_without_the_per_model_request(): void {
		$this->transporter->queue_response(
			$this->make_tags_response(
				array(
					array(
						'name'         => 'llava',
						'capabilities' => array( 'completion' ),
						'details'      => array( 'families' => array( 'llama', 'clip' ) ),
					),
				)
			)
		);

		$models = $this->directory->listModelMetadata();

		$this->assertSame( 1, $this->transporter->get_request_count() );
		$input_modalities_opt = $this->find_option( $models[0]->getSupportedOptions(), 'isInputModalities' );
		$this->assertNotNull( $input_modalities_opt, 'Expected inputModalities supported option' );
		$this->assertCount( 2, (array) $input_modalities_opt->getSupportedValues() );
	}

	/**
	 * Tests that only the models whose tag entry omits capabilities are looked up.
	 */
	public function test_only_models_without_tags_capabilities_are_looked_up(): void {
		$this->transporter->queue_response(
			$this->make_tags_response(
				array(
					array(
						'name'         => 'qwen2.5:3b',
						'capabilities' => array( 'completion', 'tools' ),
					),
					array( 'name' => 'gemma3:latest' ),
				)
			)
		);
		$this->transporter->queue_response( $this->make_show_response( array( 'completion' ) ) );

		$models = $this->directory->listModelMetadata();

		$this->assertSame( 2, $this->transporter->get_request_count(), 'Expected one tag listing plus one lookup.' );
		$this->assertCount( 2, $models );

		$lookup = $this->transporter->get_requests()[1];
		$this->assertStringEndsWith( 'api/show', $lookup->getUri() );
		$this->assertSame( array( 'model' => 'gemma3:latest' ), $lookup->getData() );
	}

	/**
	 * Tests that listModelTags() fetches the tag listing only once per instance.
	 */
	public function test_model_tags_are_fetched_once_per_instance(): void {
		$this->transporter->queue_response( $this->make_tags_response( array( 'llama3.2' ) ) );
		$this->transporter->queue_response( $this->make_show_response( array( 'completion' ) ) );

		$this->directory->listModelTags();
		$this->directory->listModelTags();
		$this->directory->listModelMetadata();

		$this->assertSame( 2, $this->transporter->get_request_count(), 'Expected one tag listing and one lookup.' );
	}
}

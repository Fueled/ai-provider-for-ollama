<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Tests\Integration\Settings;

use Fueled\AiProviderForOllama\Provider\OllamaProvider;
use Fueled\AiProviderForOllama\Settings\OllamaSettings;
use Fueled\AiProviderForOllama\Tests\Integration\Mocks\MockHttpTransporter;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Response;

/**
 * Tests that the cached Ollama model list is refreshed when it goes stale.
 *
 * Runs against the real Ollama provider with a mock HTTP transporter, and
 * relies on the WordPress object cache outliving a single listing, as a
 * persistent object cache does between page loads.
 *
 * @covers \Fueled\AiProviderForOllama\Settings\OllamaSettings
 */
class OllamaSettingsModelCacheTest extends \WP_UnitTestCase {

	/**
	 * Settings instance under test.
	 *
	 * @var OllamaSettings
	 */
	private OllamaSettings $settings;

	/**
	 * Mock transporter the provider sends its requests through.
	 *
	 * @var MockHttpTransporter
	 */
	private MockHttpTransporter $transporter;

	protected function setUp(): void {
		parent::setUp();
		putenv( 'OLLAMA_HOST=http://localhost:11434' );
		$this->reset_registry();

		$registry = AiClient::defaultRegistry();
		$registry->registerProvider( OllamaProvider::class );

		$this->transporter = new MockHttpTransporter();
		$registry->setHttpTransporter( $this->transporter );
		$registry->setProviderRequestAuthentication( 'ollama', new ApiKeyRequestAuthentication( '' ) );
		OllamaProvider::modelMetadataDirectory()->invalidateCaches();

		$this->settings = new OllamaSettings();
		$this->settings->init();
	}

	protected function tearDown(): void {
		OllamaProvider::modelMetadataDirectory()->invalidateCaches();
		$this->reset_registry();
		putenv( 'OLLAMA_HOST' );
		parent::tearDown();
	}

	/**
	 * Resets the AiClient default registry so each test starts clean.
	 */
	private function reset_registry(): void {
		$ai_client_reflection = new \ReflectionClass( AiClient::class );
		$registry_prop        = $ai_client_reflection->getProperty( 'defaultRegistry' );
		$registry_prop->setAccessible( true );
		$registry_prop->setValue( null, null );
	}

	/**
	 * Queues an /api/tags response listing the given models.
	 *
	 * @param list<string> $model_names The model names to list.
	 */
	private function queue_tags_response( array $model_names ): void {
		$models = array_map(
			static function ( string $name ): array {
				return array(
					'name'         => $name,
					'capabilities' => array( 'completion' ),
				);
			},
			$model_names
		);

		$this->transporter->queue_response( new Response( 200, array(), (string) wp_json_encode( array( 'models' => $models ) ) ) );
	}

	/**
	 * Lists the model IDs the settings screen would show.
	 *
	 * @return list<string>
	 */
	private function list_model_ids(): array {
		$models = $this->settings->get_models();
		$this->assertIsArray( $models );

		return array_map(
			static function ( $model ): string {
				return $model->getId();
			},
			$models
		);
	}

	/**
	 * Tests that the model list is served from the cache while nothing has changed.
	 */
	public function test_model_list_is_cached_between_listings(): void {
		$this->queue_tags_response( array( 'llama3.1', 'qwen3' ) );
		$this->queue_tags_response( array( 'llama3.1' ) );

		$this->assertSame( array( 'llama3.1', 'qwen3' ), $this->list_model_ids() );
		$this->assertSame( array( 'llama3.1', 'qwen3' ), $this->list_model_ids() );
		$this->assertSame( 1, $this->transporter->get_request_count() );
	}

	/**
	 * Tests that changing the Ollama API key refreshes the model list.
	 */
	public function test_changing_the_api_key_refreshes_the_model_list(): void {
		update_option( 'connectors_ai_ollama_api_key', 'key-that-sees-many-models' );
		$this->queue_tags_response( array( 'llama3.1', 'mistral', 'qwen3' ) );
		$this->queue_tags_response( array( 'llama3.1' ) );

		$this->assertSame( array( 'llama3.1', 'mistral', 'qwen3' ), $this->list_model_ids() );

		update_option( 'connectors_ai_ollama_api_key', 'key-that-sees-one-model' );

		$this->assertSame( array( 'llama3.1' ), $this->list_model_ids() );
		$this->assertSame( 2, $this->transporter->get_request_count() );
	}

	/**
	 * Tests that saving an API key for the first time refreshes the model list.
	 */
	public function test_adding_the_api_key_refreshes_the_model_list(): void {
		delete_option( 'connectors_ai_ollama_api_key' );
		$this->queue_tags_response( array() );
		$this->queue_tags_response( array( 'llama3.1' ) );

		$this->assertSame( array(), $this->list_model_ids() );

		add_option( 'connectors_ai_ollama_api_key', 'new-key' );

		$this->assertSame( array( 'llama3.1' ), $this->list_model_ids() );
	}

	/**
	 * Tests that removing the API key refreshes the model list.
	 */
	public function test_deleting_the_api_key_refreshes_the_model_list(): void {
		update_option( 'connectors_ai_ollama_api_key', 'old-key' );
		$this->queue_tags_response( array( 'llama3.1', 'qwen3' ) );
		$this->queue_tags_response( array( 'qwen3' ) );

		$this->assertSame( array( 'llama3.1', 'qwen3' ), $this->list_model_ids() );

		delete_option( 'connectors_ai_ollama_api_key' );

		$this->assertSame( array( 'qwen3' ), $this->list_model_ids() );
	}

	/**
	 * Tests that changing the host URL refreshes the model list.
	 */
	public function test_changing_the_host_refreshes_the_model_list(): void {
		update_option( 'ai_provider_for_ollama_settings', array( 'host' => 'http://localhost:11434' ) );
		$this->queue_tags_response( array( 'llama3.1' ) );
		$this->queue_tags_response( array( 'gemma3' ) );

		$this->assertSame( array( 'llama3.1' ), $this->list_model_ids() );

		update_option( 'ai_provider_for_ollama_settings', array( 'host' => 'http://gpu-box:11434' ) );

		$this->assertSame( array( 'gemma3' ), $this->list_model_ids() );
	}

	/**
	 * Tests that saving unrelated options leaves the cached model list alone.
	 */
	public function test_unrelated_option_changes_keep_the_cached_model_list(): void {
		$this->queue_tags_response( array( 'llama3.1' ) );
		$this->queue_tags_response( array( 'gemma3' ) );

		$this->assertSame( array( 'llama3.1' ), $this->list_model_ids() );

		update_option( 'blogname', 'Another name' );
		update_option( 'connectors_ai_openai_api_key', 'unrelated-key' );

		$this->assertSame( array( 'llama3.1' ), $this->list_model_ids() );
		$this->assertSame( 1, $this->transporter->get_request_count() );
	}

	/**
	 * Tests that invalidate_model_cache() makes the next listing ask Ollama again.
	 */
	public function test_invalidate_model_cache_refetches_the_model_list(): void {
		$this->queue_tags_response( array( 'llama3.1' ) );
		$this->queue_tags_response( array( 'llama3.1', 'qwen3' ) );

		$this->assertSame( array( 'llama3.1' ), $this->list_model_ids() );

		$this->settings->invalidate_model_cache();

		$this->assertSame( array( 'llama3.1', 'qwen3' ), $this->list_model_ids() );
		$this->assertSame( 2, $this->transporter->get_request_count() );
	}

	/**
	 * Calls the model-listing AJAX handler as an administrator and returns the decoded response.
	 *
	 * @param array<string, string> $query Extra query arguments for the request.
	 * @return array<string, mixed> The decoded JSON response.
	 */
	private function call_ajax_list_models( array $query = array() ): array {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$_GET     = $query;
		$_REQUEST = array_merge( $query, array( '_wpnonce' => wp_create_nonce( 'ai_provider_for_ollama_nonce' ) ) );

		// Run as an AJAX request, and turn its wp_die() into an exception rather than an exit.
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () {
				return static function (): void {
					throw new \WPDieException();
				};
			}
		);

		ob_start();
		try {
			$this->settings->ajax_list_models();
		} catch ( \WPDieException $e ) {
			// wp_send_json() ends the request with wp_die().
			unset( $e );
		} finally {
			$output = (string) ob_get_clean();
			$_GET     = array();
			$_REQUEST = array();
		}

		$response = json_decode( $output, true );
		$this->assertIsArray( $response );

		return $response;
	}

	/**
	 * Tests that the AJAX handler serves the cached model list by default.
	 */
	public function test_ajax_list_models_uses_the_cached_model_list(): void {
		$this->queue_tags_response( array( 'llama3.1' ) );
		$this->queue_tags_response( array( 'llama3.1', 'qwen3' ) );

		$this->assertSame( array( 'llama3.1' ), $this->list_model_ids() );

		$response = $this->call_ajax_list_models();

		$this->assertTrue( $response['success'] );
		$this->assertSame( array( 'llama3.1' ), array_column( $response['data'], 'id' ) );
		$this->assertSame( 1, $this->transporter->get_request_count() );
	}

	/**
	 * Tests that the AJAX handler fetches a fresh model list when asked to refresh.
	 */
	public function test_ajax_list_models_refresh_refetches_the_model_list(): void {
		$this->queue_tags_response( array( 'llama3.1' ) );
		$this->queue_tags_response( array( 'llama3.1', 'qwen3' ) );

		$this->assertSame( array( 'llama3.1' ), $this->list_model_ids() );

		$response = $this->call_ajax_list_models( array( 'refresh' => '1' ) );

		$this->assertTrue( $response['success'] );
		$this->assertSame( array( 'llama3.1', 'qwen3' ), array_column( $response['data'], 'id' ) );
		$this->assertSame( 2, $this->transporter->get_request_count() );
	}

	/**
	 * Tests that invalidate_model_cache() is a no-op when the provider is not registered.
	 */
	public function test_invalidate_model_cache_without_provider_does_nothing(): void {
		$this->reset_registry();

		$this->settings->invalidate_model_cache();

		$this->assertSame( 0, $this->transporter->get_request_count() );
		$this->assertFalse( AiClient::defaultRegistry()->hasProvider( 'ollama' ) );
	}
}

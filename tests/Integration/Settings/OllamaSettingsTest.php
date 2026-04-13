<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Tests\Integration\Settings;

use Fueled\AiProviderForOllama\Settings\OllamaSettings;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\Contracts\ProviderInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;

/**
 * Tests for OllamaSettings.
 *
 * @covers \Fueled\AiProviderForOllama\Settings\OllamaSettings
 */
class OllamaSettingsTest extends \WP_UnitTestCase {

	/**
	 * Settings instance under test.
	 *
	 * @var OllamaSettings
	 */
	private OllamaSettings $settings;

	protected function setUp(): void {
		parent::setUp();
		$this->reset_registry();
		$this->reset_mock_provider_state();
		$this->settings = new OllamaSettings();
	}

	protected function tearDown(): void {
		$this->reset_registry();
		$this->reset_mock_provider_state();
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
	 * Resets all mutable static state used by mock provider test doubles.
	 */
	private function reset_mock_provider_state(): void {
		MockOllamaProviderAvailability::$is_configured = true;
		MockOllamaModelMetadataDirectory::$throw_on_list = false;
		MockOllamaModelMetadataDirectory::$models        = array();
	}

	/**
	 * Registers the mock ollama provider in the default registry.
	 */
	private function register_mock_provider(): void {
		AiClient::defaultRegistry()->registerProvider( MockOllamaProvider::class );
	}

	/**
	 * Creates test model metadata for mock model directory responses.
	 *
	 * @param string $id Model identifier.
	 */
	private function create_mock_model_metadata( string $id = 'llama3.1' ): ModelMetadata {
		return new ModelMetadata(
			$id,
			'Ollama Test Model',
			array( CapabilityEnum::textGeneration() ),
			array()
		);
	}

	// -----------------------------------------------------------------------
	// sanitize_settings() tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that a valid host URL is returned unchanged.
	 */
	public function test_sanitize_settings_with_valid_host_url(): void {
		$result = $this->settings->sanitize_settings( array( 'host' => 'http://localhost:11434' ) );
		$this->assertSame( array( 'host' => 'http://localhost:11434' ), $result );
	}

	/**
	 * Tests that a trailing slash is stripped from the host URL.
	 */
	public function test_sanitize_settings_strips_trailing_slash(): void {
		$result = $this->settings->sanitize_settings( array( 'host' => 'http://localhost:11434/' ) );
		$this->assertSame( 'http://localhost:11434', $result['host'] );
	}

	/**
	 * Tests that a non-array input returns an empty array.
	 */
	public function test_sanitize_settings_with_non_array_returns_empty_array(): void {
		$result = $this->settings->sanitize_settings( 'not-an-array' );
		$this->assertSame( array(), $result );
	}

	/**
	 * Tests that an empty array input returns an array with an empty host key.
	 */
	public function test_sanitize_settings_with_empty_array_returns_host_key(): void {
		$result = $this->settings->sanitize_settings( array() );
		$this->assertArrayHasKey( 'host', $result );
		$this->assertSame( '', $result['host'] );
	}

	/**
	 * Tests that an explicitly empty host string is preserved.
	 */
	public function test_sanitize_settings_with_empty_host_preserves_empty_string(): void {
		$result = $this->settings->sanitize_settings( array( 'host' => '' ) );
		$this->assertSame( '', $result['host'] );
	}

	/**
	 * Tests that the host value is passed through esc_url_raw() for sanitization.
	 */
	public function test_sanitize_settings_sanitizes_url(): void {
		$input  = 'http://localhost:11434/path?q=1';
		$result = $this->settings->sanitize_settings( array( 'host' => $input ) );
		// esc_url_raw returns a sanitized URL; the result must be a non-empty string.
		$this->assertIsString( $result['host'] );
		$this->assertNotEmpty( $result['host'] );
		// The protocol must be preserved.
		$this->assertStringStartsWith( 'http', $result['host'] );
	}

	// -----------------------------------------------------------------------
	// Hook-registration tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that init() registers register_settings on admin_init.
	 */
	public function test_init_registers_admin_init_hook(): void {
		$this->settings->init();
		$this->assertNotFalse(
			has_action( 'admin_init', array( $this->settings, 'register_settings' ) )
		);
	}

	/**
	 * Tests that init() registers register_settings_screen on admin_menu.
	 */
	public function test_init_registers_admin_menu_hook(): void {
		$this->settings->init();
		$this->assertNotFalse(
			has_action( 'admin_menu', array( $this->settings, 'register_settings_screen' ) )
		);
	}

	/**
	 * Tests that init() registers ajax_list_models on the AJAX action hook.
	 */
	public function test_init_registers_ajax_hook(): void {
		$this->settings->init();
		$this->assertNotFalse(
			has_action(
				'wp_ajax_ai_provider_for_ollama_list_models',
				array( $this->settings, 'ajax_list_models' )
			)
		);
	}

	/**
	 * Tests that init() registers is_connected on wpai_has_ai_credentials.
	 */
	public function test_init_registers_wpai_has_ai_credentials_filter(): void {
		$this->settings->init();
		$this->assertNotFalse(
			has_filter( 'wpai_has_ai_credentials', array( $this->settings, 'is_connected' ) )
		);
	}

	/**
	 * Tests that get_models() returns an error if the provider is not registered.
	 */
	public function test_get_models_returns_error_when_provider_is_not_registered(): void {
		$result = $this->settings->get_models();

		$this->assertWPError( $result );
		$this->assertSame( 'ai_provider_not_found', $result->get_error_code() );
		$this->assertSame( 404, $result->get_error_data() );
	}

	/**
	 * Tests that get_models() returns an error if provider availability is unconfigured.
	 */
	public function test_get_models_returns_error_when_provider_is_not_configured(): void {
		MockOllamaProviderAvailability::$is_configured = false;
		$this->register_mock_provider();

		$result = $this->settings->get_models();

		$this->assertWPError( $result );
		$this->assertSame( 'ai_provider_not_configured', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data() );
	}

	/**
	 * Tests that get_models() returns listed models when provider is configured.
	 */
	public function test_get_models_returns_models_when_provider_is_configured(): void {
		$this->register_mock_provider();
		$model = $this->create_mock_model_metadata();
		MockOllamaModelMetadataDirectory::$models = array( $model );

		$result = $this->settings->get_models();

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertInstanceOf( ModelMetadata::class, $result[0] );
		$this->assertSame( $model->getId(), $result[0]->getId() );
	}

	/**
	 * Tests that get_models() returns an error when model listing throws.
	 */
	public function test_get_models_returns_error_when_model_listing_throws(): void {
		$this->register_mock_provider();
		MockOllamaModelMetadataDirectory::$throw_on_list = true;

		$result = $this->settings->get_models();

		$this->assertWPError( $result );
		$this->assertSame( 'could_not_list_models', $result->get_error_code() );
		$this->assertSame( 500, $result->get_error_data() );
		$this->assertStringContainsString( 'Could not list models for provider', $result->get_error_message() );
	}

	/**
	 * Tests that is_connected() returns false when model lookup errors.
	 */
	public function test_is_connected_returns_false_when_get_models_fails(): void {
		$this->assertFalse( $this->settings->is_connected() );
	}

	/**
	 * Tests that is_connected() returns true when model lookup succeeds.
	 */
	public function test_is_connected_returns_true_when_get_models_succeeds(): void {
		$this->register_mock_provider();
		MockOllamaModelMetadataDirectory::$models = array( $this->create_mock_model_metadata() );

		$this->assertTrue( $this->settings->is_connected() );
	}

	/**
	 * Tests that wpai_has_ai_credentials filter resolves to false when disconnected.
	 */
	public function test_wpai_has_ai_credentials_filter_returns_false_when_disconnected(): void {
		$this->settings->init();

		$result = apply_filters( 'wpai_has_ai_credentials', true );

		$this->assertFalse( $result );
	}

	/**
	 * Tests that wpai_has_ai_credentials filter resolves to true when connected.
	 */
	public function test_wpai_has_ai_credentials_filter_returns_true_when_connected(): void {
		$this->settings->init();
		$this->register_mock_provider();
		MockOllamaModelMetadataDirectory::$models = array( $this->create_mock_model_metadata() );

		$result = apply_filters( 'wpai_has_ai_credentials', false );

		$this->assertTrue( $result );
	}
}

/**
 * Mock provider for testing OllamaSettings::get_models() behavior.
 */
class MockOllamaProvider implements ProviderInterface {

	/**
	 * {@inheritDoc}
	 */
	public static function metadata(): ProviderMetadata {
		return new ProviderMetadata(
			'ollama',
			'Mock Ollama',
			ProviderTypeEnum::server()
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function model( string $model_id, ?ModelConfig $model_config = null ): ModelInterface {
		throw new InvalidArgumentException( 'Model loading is not used in this test.' );
	}

	/**
	 * {@inheritDoc}
	 */
	public static function availability(): ProviderAvailabilityInterface {
		return new MockOllamaProviderAvailability();
	}

	/**
	 * {@inheritDoc}
	 */
	public static function modelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new MockOllamaModelMetadataDirectory();
	}
}

/**
 * Mock provider availability used by MockOllamaProvider.
 */
class MockOllamaProviderAvailability implements ProviderAvailabilityInterface {

	/**
	 * Whether the provider should report as configured.
	 *
	 * @var bool
	 */
	public static bool $is_configured = true;

	/**
	 * {@inheritDoc}
	 */
	public function isConfigured(): bool {
		return self::$is_configured;
	}
}

/**
 * Mock model metadata directory used by MockOllamaProvider.
 */
class MockOllamaModelMetadataDirectory implements ModelMetadataDirectoryInterface {

	/**
	 * Whether listModelMetadata should throw.
	 *
	 * @var bool
	 */
	public static bool $throw_on_list = false;

	/**
	 * Models to return when listing model metadata.
	 *
	 * @var array<int, ModelMetadata>
	 */
	public static array $models = array();

	/**
	 * {@inheritDoc}
	 */
	public function listModelMetadata(): array {
		if ( self::$throw_on_list ) {
			throw new \RuntimeException( 'Mock listing error.' );
		}

		return self::$models;
	}

	/**
	 * {@inheritDoc}
	 */
	public function hasModelMetadata( string $model_id ): bool {
		foreach ( self::$models as $model_metadata ) {
			if ( $model_metadata->getId() === $model_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getModelMetadata( string $model_id ): ModelMetadata {
		foreach ( self::$models as $model_metadata ) {
			if ( $model_metadata->getId() === $model_id ) {
				return $model_metadata;
			}
		}

		throw new InvalidArgumentException( 'Model metadata not found.' );
	}
}

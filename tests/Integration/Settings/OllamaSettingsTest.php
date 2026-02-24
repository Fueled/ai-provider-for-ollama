<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Tests\Integration\Settings;

use Fueled\AiProviderForOllama\Settings\OllamaSettings;

/**
 * Tests for OllamaSettings.
 *
 * @covers \WordPress\AiProviderOllama\Settings\OllamaSettings
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
		$this->settings = new OllamaSettings();
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
				'wp_ajax_wp_ai_client_ollama_list_models',
				array( $this->settings, 'ajax_list_models' )
			)
		);
	}
}

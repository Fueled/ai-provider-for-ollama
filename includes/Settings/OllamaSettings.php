<?php

declare( strict_types=1 );

namespace WordPress\AiClientProviderOllama\Settings;

/**
 * Class for the Ollama settings in the WordPress admin.
 *
 * Provides a settings page under Settings > Ollama for configuring the Ollama
 * host URL and default model.
 *
 * @since 1.0.0
 */
class OllamaSettings {

	private const OPTION_GROUP = 'wp-ai-client-ollama-settings';
	private const OPTION_NAME  = 'wp_ai_client_ollama_settings';
	private const PAGE_SLUG    = 'wp-ai-client-ollama';
	private const SECTION_ID   = 'wp_ai_client_ollama_main';

	/**
	 * Initializes the settings.
	 *
	 * @since 1.0.0
	 */
	public function init(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_settings_screen' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_settings_script' ) );
	}

	/**
	 * Registers the setting and settings fields.
	 *
	 * @since 1.0.0
	 */
	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		add_settings_section(
			self::SECTION_ID,
			'',
			'__return_empty_string',
			self::PAGE_SLUG
		);

		add_settings_field(
			self::OPTION_NAME . '_host',
			__( 'Ollama Host URL', 'wordpress-ai-client-provider-ollama' ),
			array( $this, 'render_host_field' ),
			self::PAGE_SLUG,
			self::SECTION_ID,
			array( 'label_for' => self::OPTION_NAME . '-host' )
		);

		add_settings_field(
			self::OPTION_NAME . '_model',
			__( 'Default Model', 'wordpress-ai-client-provider-ollama' ),
			array( $this, 'render_model_field' ),
			self::PAGE_SLUG,
			self::SECTION_ID,
			array( 'label_for' => self::OPTION_NAME . '-model' )
		);
	}

	/**
	 * Registers the settings screen.
	 *
	 * @since 1.0.0
	 */
	public function register_settings_screen(): void {
		add_options_page(
			__( 'Ollama Settings', 'wordpress-ai-client-provider-ollama' ),
			__( 'Ollama', 'wordpress-ai-client-provider-ollama' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_screen' )
		);
	}

	/**
	 * Sanitizes the settings array.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The input value.
	 * @return array<string, string> The sanitized settings.
	 */
	public function sanitize_settings( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$host = isset( $value['host'] ) ? trim( (string) $value['host'] ) : '';
		if ( '' !== $host ) {
			$host = rtrim( esc_url_raw( $host ), '/' );
		}

		return array(
			'host'  => $host,
			'model' => isset( $value['model'] ) ? sanitize_text_field( (string) $value['model'] ) : '',
		);
	}

	/**
	 * Renders the settings screen.
	 *
	 * @since 1.0.0
	 */
	public function render_screen(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>

		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p>
				<?php
				echo sprintf(
					esc_html__( 'Configure the connection to your Ollama instance. If you want to use Ollama Cloud, enter the API key on the %1$sSettings > AI Credentials%2$s screen.', 'wordpress-ai-client-provider-ollama' ),
					'<a href="' . esc_url( admin_url( 'options-general.php?page=wp-ai-client' ) ) . '">',
					'</a>'
				);
				?>
			</p>
			<p>
				<?php
				echo sprintf(
					esc_html__( 'Leave the host URL empty to use the default (%1$shttp://localhost:11434%2$s). You can also set the %1$sOLLAMA_HOST%2$s environment variable to override this setting.', 'wordpress-ai-client-provider-ollama' ),
					'<code>',
					'</code>'
				);
				?>
			</p>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>

		<?php
	}

	/**
	 * Renders the host URL field.
	 *
	 * @since 1.0.0
	 */
	public function render_host_field(): void {
		$settings = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$value = isset( $settings['host'] ) ? $settings['host'] : '';
		?>

		<input
			type="url"
			id="<?php echo esc_attr( self::OPTION_NAME . '-host' ); ?>"
			name="<?php echo esc_attr( self::OPTION_NAME . '[host]' ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			placeholder="http://localhost:11434"
		/>
		<p class="description">
			<?php
			echo sprintf(
				esc_html__( 'The base URL of your Ollama instance (without /v1). Example: %1$shttp://localhost:11434%2$s', 'wordpress-ai-client-provider-ollama' ),
				'<code>',
				'</code>'
			);
			?>
		</p>

		<?php
	}

	/**
	 * Renders the model selector field.
	 *
	 * @since 1.0.0
	 */
	public function render_model_field(): void {
		$settings = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$saved_model = isset( $settings['model'] ) ? $settings['model'] : '';

		$select_id   = self::OPTION_NAME . '-model';
		$select_name = self::OPTION_NAME . '[model]';
		?>

		<select id="<?php echo esc_attr( $select_id ); ?>" name="<?php echo esc_attr( $select_name ); ?>">
			<option value=""><?php echo esc_html__( '— Select a model —', 'wordpress-ai-client-provider-ollama' ); ?></option>
			<?php if ( '' !== $saved_model ) : ?>
				<option value="<?php echo esc_attr( $saved_model ); ?>" selected>
					<?php echo esc_html( $saved_model ); ?>
				</option>
			<?php endif; ?>
		</select>
		<span id="ollama-model-status" style="margin-left:8px;"></span>
		<p class="description">
			<?php
			echo esc_html__( 'Select the default model to use with Ollama. Models are fetched from your Ollama instance.', 'wordpress-ai-client-provider-ollama' );
			?>
		</p>

		<?php
	}

	/**
	 * Enqueues the settings page script.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_settings_script( string $hook_suffix ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		$plugin_dir = WP_AI_CLIENT_PROVIDER_OLLAMA_PLUGIN_DIR;
		$asset_file = $plugin_dir . 'build/admin/settings.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : array(); // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- Asset file path is built from a known constant.

		$dependencies = isset( $asset['dependencies'] ) ? $asset['dependencies'] : array();
		$version      = isset( $asset['version'] ) ? $asset['version'] : false;

		wp_enqueue_script(
			'wp-ai-client-ollama-settings',
			plugins_url( 'build/admin/settings.js', $plugin_dir . 'plugin.php' ),
			$dependencies,
			$version,
			true
		);

		$settings = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		wp_localize_script(
			'wp-ai-client-ollama-settings',
			'wpAiClientOllamaSettings',
			array(
				'selectId'   => self::OPTION_NAME . '-model',
				'savedModel' => isset( $settings['model'] ) ? $settings['model'] : '',
			)
		);
	}
}

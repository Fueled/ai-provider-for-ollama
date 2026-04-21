/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';

interface Config {
	ajaxUrl: string;
}

declare global {
	interface Window {
		aiProviderForOllamaSettings: Config;
	}
}

interface AjaxResponse {
	success: boolean;
	data: ModelMetadata[] | string;
}

interface ModelMetadata {
	id: string;
	name: string;
	supportedCapabilities?: string[];
	supportedOptions?: SupportedOption[];
}

interface SupportedOption {
	name: string;
	supportedValues?: unknown[];
}

const ERROR_COLOR = '#d63638';

const CAPABILITY_LABELS: Record< string, string > = {
	text_generation: __( 'Text generation', 'ai-provider-for-ollama' ),
	image_generation: __( 'Image generation', 'ai-provider-for-ollama' ),
	text_to_speech_conversion: __( 'Text-to-speech', 'ai-provider-for-ollama' ),
	speech_generation: __( 'Speech generation', 'ai-provider-for-ollama' ),
	music_generation: __( 'Music generation', 'ai-provider-for-ollama' ),
	video_generation: __( 'Video generation', 'ai-provider-for-ollama' ),
	embedding_generation: __(
		'Embedding generation',
		'ai-provider-for-ollama'
	),
	chat_history: __( 'Chat history', 'ai-provider-for-ollama' ),
};

/**
 * Gets a display label for a capability value.
 *
 * @param {string} capability The raw capability value.
 * @return {string} A translated label.
 * @since x.x.x
 */
function getCapabilityLabel( capability: string ): string {
	if ( CAPABILITY_LABELS[ capability ] ) {
		return CAPABILITY_LABELS[ capability ];
	}

	return capability
		.split( '_' )
		.map( ( word ) => word.charAt( 0 ).toUpperCase() + word.slice( 1 ) )
		.join( ' ' );
}

/**
 * Checks if model supports image input (vision).
 *
 * @param {ModelMetadata} model The model metadata.
 * @return {boolean} Whether vision is supported.
 * @since x.x.x
 */
function supportsVision( model: ModelMetadata ): boolean {
	const inputModalities = model.supportedOptions?.find(
		( option ) => option.name === 'inputModalities'
	);
	if (
		! inputModalities ||
		! Array.isArray( inputModalities.supportedValues )
	) {
		return false;
	}

	return inputModalities.supportedValues.some(
		( modalitySet ) =>
			Array.isArray( modalitySet ) && modalitySet.includes( 'image' )
	);
}

/**
 * Gets displayable capability labels for a model.
 *
 * @param {ModelMetadata} model The model metadata.
 * @return {string[]} Capability labels to display.
 * @since x.x.x
 */
function getModelCapabilityLabels( model: ModelMetadata ): string[] {
	const labels = new Set(
		( model.supportedCapabilities ?? [] ).map( getCapabilityLabel )
	);

	if ( supportsVision( model ) ) {
		labels.add( __( 'Vision', 'ai-provider-for-ollama' ) );
	}

	return Array.from( labels );
}

/**
 * Loads and displays the available models in a list.
 *
 * @param {Config} config The configuration object.
 * @since 1.0.0
 */
async function loadModels( config: Config ): Promise< void > {
	const container = document.getElementById( 'ollama-models-container' );
	const status = document.getElementById( 'ollama-model-status' );

	if ( ! container || ! status ) {
		return;
	}

	status.textContent = __( 'Loading models\u2026', 'ai-provider-for-ollama' );

	let resp: AjaxResponse;

	try {
		resp = await apiFetch< AjaxResponse >( { url: config.ajaxUrl } );
	} catch ( error ) {
		const fallback = __(
			'Could not connect to load models.',
			'ai-provider-for-ollama'
		);
		status.textContent =
			error !== null &&
			typeof error === 'object' &&
			'message' in error &&
			typeof ( error as { message: unknown } ).message === 'string'
				? ( error as { message: string } ).message
				: fallback;
		status.style.color = ERROR_COLOR;
		return;
	}

	if ( ! resp.success || ! resp.data ) {
		status.textContent =
			typeof resp.data === 'string'
				? resp.data
				: __( 'Failed to load models.', 'ai-provider-for-ollama' );
		status.style.color = ERROR_COLOR;
		return;
	}

	const models = resp.data as ModelMetadata[];

	// Clear the container (removes the status span).
	container.innerHTML = '';

	if ( models.length === 0 ) {
		const empty = document.createElement( 'p' );
		empty.textContent = __(
			'No models found. Pull a model with ollama pull <model> and reload this page.',
			'ai-provider-for-ollama'
		);
		container.appendChild( empty );
		return;
	}

	const count = document.createElement( 'p' );
	count.textContent = sprintf(
		/* translators: %d: number of models */
		_n(
			'%d model available:',
			'%d models available:',
			models.length,
			'ai-provider-for-ollama'
		),
		models.length
	);
	container.appendChild( count );

	const list = document.createElement( 'ul' );
	for ( const model of models ) {
		const item = document.createElement( 'li' );
		const code = document.createElement( 'code' );
		code.textContent = model.id;
		item.appendChild( code );

		const capabilityLabels = getModelCapabilityLabels( model );
		if ( capabilityLabels.length > 0 ) {
			const capabilities = document.createElement( 'span' );
			capabilities.textContent = ` (${ capabilityLabels.join( ', ' ) })`;
			item.appendChild( capabilities );
		}

		list.appendChild( item );
	}
	container.appendChild( list );
}

/**
 * Initializes the settings page.
 *
 * @since 1.0.0
 */
document.addEventListener( 'DOMContentLoaded', () => {
	const config = window.aiProviderForOllamaSettings;
	if ( config ) {
		loadModels( config );
	}
} );

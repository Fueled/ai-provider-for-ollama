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
		wpAiClientOllamaSettings: Config;
	}
}

interface AjaxResponse {
	success: boolean;
	data: ModelMetadata[] | string;
}

interface ModelMetadata {
	id: string;
	name: string;
}

const ERROR_COLOR = '#d63638';

/**
 * Loads and displays the available models in a list.
 *
 * @param config The configuration object.
 * @since 1.0.0
 */
async function loadModels( config: Config ): Promise< void > {
	const container = document.getElementById( 'ollama-models-container' );
	const status = document.getElementById( 'ollama-model-status' );

	if ( ! container || ! status ) {
		return;
	}

	status.textContent = __(
		'Loading models\u2026',
		'ai-provider-for-ollama'
	);

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
				: __(
						'Failed to load models.',
						'ai-provider-for-ollama'
				  );
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
	const config = window.wpAiClientOllamaSettings;
	if ( config ) {
		loadModels( config );
	}
} );

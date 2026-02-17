/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

interface Config {
	selectId: string;
	savedModel: string;
}

declare global {
	interface Window {
		wpAiClientOllamaSettings: Config;
	}
}

interface ModelMetadata {
	id: string;
	name: string;
}

const ERROR_COLOR = '#d63638';

/**
 * Populates the models select element with the available models.
 *
 * @param config The configuration object.
 * @since 1.0.0
 */
async function populateModels( config: Config ): Promise< void > {
	const select = document.getElementById(
		config.selectId
	) as HTMLSelectElement | null;
	const status = document.getElementById( 'ollama-model-status' );

	if ( ! select || ! status ) {
		return;
	}

	status.textContent = __(
		'Loading models\u2026',
		'wordpress-ai-client-provider-ollama'
	);

	let models: string[];
	try {
		const response = await apiFetch< ModelMetadata[] >( {
			path: '/wp-ai/v1/providers/ollama/models',
		} );
		models = response.map( ( m ) => m.id );
	} catch ( error ) {
		const fallback = __(
			'Could not connect to load models.',
			'wordpress-ai-client-provider-ollama'
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

	status.textContent = '';

	select.innerHTML = '';

	const defaultOption = document.createElement( 'option' );
	defaultOption.value = '';
	defaultOption.textContent = __(
		'\u2014 Select a model \u2014',
		'wordpress-ai-client-provider-ollama'
	);
	select.appendChild( defaultOption );

	for ( const model of models ) {
		const option = document.createElement( 'option' );
		option.value = model;
		option.textContent = model;
		if ( model === config.savedModel ) {
			option.selected = true;
		}
		select.appendChild( option );
	}

	if ( config.savedModel && ! models.includes( config.savedModel ) ) {
		const savedOption = document.createElement( 'option' );
		savedOption.value = config.savedModel;
		savedOption.textContent =
			config.savedModel +
			' ' +
			__( '(not available)', 'wordpress-ai-client-provider-ollama' );
		savedOption.selected = true;
		select.insertBefore( savedOption, select.children[ 1 ] ?? null );
	}
}

/**
 * Initializes the settings page.
 *
 * @since 1.0.0
 */
document.addEventListener( 'DOMContentLoaded', () => {
	const config = window.wpAiClientOllamaSettings;
	if ( config ) {
		populateModels( config );
	}
} );

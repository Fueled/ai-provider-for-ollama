<?php
/**
 * Availability check for the Ollama provider.
 *
 * @package Fueled\AiProviderForOllama\Provider
 * @since   1.3.0
 */

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Provider;

use Fueled\AiProviderForOllama\Metadata\OllamaModelMetadataDirectory;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;

/**
 * Class to check availability for the Ollama provider.
 *
 * Answers the question the AI Client actually asks — "can this site reach a
 * working Ollama?" — with the single `GET /api/tags` request that answering it
 * requires.
 *
 * @since 1.3.0
 */
class OllamaProviderAvailability implements ProviderAvailabilityInterface {

	/**
	 * The model metadata directory to use for checking availability.
	 *
	 * @since 1.3.0
	 *
	 * @var \Fueled\AiProviderForOllama\Metadata\OllamaModelMetadataDirectory
	 */
	private OllamaModelMetadataDirectory $model_metadata_directory;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param \Fueled\AiProviderForOllama\Metadata\OllamaModelMetadataDirectory $model_metadata_directory The model
	 *                                                                                                   metadata
	 *                                                                                                   directory.
	 */
	public function __construct( OllamaModelMetadataDirectory $model_metadata_directory ) {
		$this->model_metadata_directory = $model_metadata_directory;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.3.0
	 */
	public function isConfigured(): bool {
		try {
			// Reaching the model tags means the host is up, speaks Ollama, and accepted our credentials.
			$this->model_metadata_directory->listModelTags();

			return true;
		} catch ( \Throwable $e ) {
			return false;
		}
	}
}

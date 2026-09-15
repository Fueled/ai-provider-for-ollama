<?php
/**
 * Availability check for the Ollama provider.
 *
 * @package Fueled\AiProviderForOllama\Provider
 * @since   x.x.x
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
 * The SDK's {@see \WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability}
 * answers it by listing every model instead. For Ollama that is far from free:
 * servers that do not report capabilities in `/api/tags` need one `/api/show`
 * request per model, so a yes/no reachability check turned into dozens of
 * sequential round trips — slow enough that ordinary latency variance made it
 * time out and report a perfectly healthy provider as not connected.
 *
 * The tag listing is memoized by the directory for the duration of the
 * request, so checking availability and then listing models costs one request
 * between them rather than one each.
 *
 * @since x.x.x
 */
class OllamaProviderAvailability implements ProviderAvailabilityInterface {

	/**
	 * The model metadata directory to use for checking availability.
	 *
	 * @since x.x.x
	 *
	 * @var \Fueled\AiProviderForOllama\Metadata\OllamaModelMetadataDirectory
	 */
	private OllamaModelMetadataDirectory $model_metadata_directory;

	/**
	 * Constructor.
	 *
	 * @since x.x.x
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
	 * @since x.x.x
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

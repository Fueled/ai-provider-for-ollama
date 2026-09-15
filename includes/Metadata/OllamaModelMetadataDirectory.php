<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Metadata;

use Fueled\AiProviderForOllama\Provider\OllamaProvider;
use WordPress\AiClient\Files\Enums\FileTypeEnum;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModelMetadataDirectory;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\EmbeddingGeneration\Contracts\EmbeddingGenerationModelInterface;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

/**
 * Class for the Ollama model metadata directory.
 *
 * Building the model list needs one `GET /api/tags` request, plus the
 * capabilities of each model listed. Recent Ollama versions report those
 * capabilities in the tag listing itself, in which case no further request is
 * made; otherwise they come from `POST /api/show`, and the answers are cached
 * per model digest by {@see \Fueled\AiProviderForOllama\Metadata\OllamaModelDetailsCache},
 * so that later requests also get away with the single tag listing.
 *
 * @since 1.0.0
 *
 * @phpstan-type TagsEntryData array{
 *     name?: string,
 *     digest?: string,
 *     capabilities?: list<string>,
 *     details?: array{families?: list<string>|null}
 * }
 * @phpstan-type ShowResponseData array{
 *     capabilities?: list<string>,
 *     details?: array{families?: list<string>|null}
 * }
 * @phpstan-type ModelDetails array{capabilities: list<string>, families: list<string>}
 */
class OllamaModelMetadataDirectory extends AbstractApiBasedModelMetadataDirectory {

	/**
	 * Default timeout for model discovery requests, in seconds.
	 *
	 * @since x.x.x
	 *
	 * @var float
	 */
	private const DEFAULT_DISCOVERY_REQUEST_TIMEOUT = 10.0;

	/**
	 * Default connection timeout for model discovery requests, in seconds.
	 *
	 * @since x.x.x
	 *
	 * @var float
	 */
	private const DEFAULT_DISCOVERY_CONNECT_TIMEOUT = 3.0;

	/**
	 * The model entries from /api/tags, once fetched.
	 *
	 * @since x.x.x
	 *
	 * @var list<TagsEntryData>|null
	 */
	private ?array $model_tags = null;

	/**
	 * Lists the models the Ollama host offers, as returned by /api/tags.
	 *
	 * This is the cheapest complete answer Ollama gives about itself: reaching it
	 * proves the host is up, speaks Ollama, and accepted the credentials, which
	 * is why {@see \Fueled\AiProviderForOllama\Provider\OllamaProviderAvailability}
	 * uses it as its availability probe. The result is memoized for the lifetime
	 * of this instance, so probing availability and then listing models costs one
	 * request between them.
	 *
	 * @since x.x.x
	 *
	 * @return list<TagsEntryData> The raw model entries.
	 * @throws \WordPress\AiClient\Providers\Http\Exception\ResponseException If the host is unreachable or the response
	 *                                                                       is not a model listing.
	 */
	public function listModelTags(): array {
		if ( null !== $this->model_tags ) {
			return $this->model_tags;
		}

		$request  = $this->createRequest( HttpMethodEnum::GET(), 'api/tags' );
		$request  = $this->getRequestAuthentication()->authenticateRequest( $request );
		$response = $this->getHttpTransporter()->send( $request );

		ResponseUtil::throwIfNotSuccessful( $response );

		$tags_data = $response->getData();
		if ( ! isset( $tags_data['models'] ) || ! is_array( $tags_data['models'] ) ) {
			throw ResponseException::fromMissingData( 'Ollama', 'models' );
		}

		/** @var list<TagsEntryData> $model_tags */
		$model_tags       = array_values( array_filter( $tags_data['models'], 'is_array' ) );
		$this->model_tags = $model_tags;

		return $this->model_tags;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected function sendListModelsRequest(): array {
		$details_cache  = OllamaModelDetailsCache::load( OllamaProvider::url( '' ) );
		$digests_in_use = array();
		$models_map     = array();

		foreach ( $this->listModelTags() as $model_entry ) {
			if ( ! isset( $model_entry['name'] ) || ! is_string( $model_entry['name'] ) || '' === $model_entry['name'] ) {
				continue;
			}

			$model_name = $model_entry['name'];
			$details    = $this->resolveModelDetails( $model_name, $model_entry, $details_cache, $digests_in_use );
			$metadata   = $this->buildModelMetadata( $model_name, $details );
			if ( null === $metadata ) {
				continue;
			}

			$models_map[ $model_name ] = $metadata;
		}

		$details_cache->save( $digests_in_use );

		ksort( $models_map );

		return $models_map;
	}

	/**
	 * Resolves the capability details of a single model, at the lowest cost available.
	 *
	 * In order of preference: the tag entry itself, the cache, and finally a
	 * request to /api/show. A failed request is left uncached, so a momentary
	 * error cannot pin a model's capabilities for the life of the cache.
	 *
	 * @since x.x.x
	 *
	 * @param string                  $model_name     The model name.
	 * @param TagsEntryData           $model_entry    The model's entry from /api/tags.
	 * @param \Fueled\AiProviderForOllama\Metadata\OllamaModelDetailsCache $details_cache The cache of details fetched for earlier listings.
	 * @param list<string>            $digests_in_use Digests resolved from the cache so far, appended to by reference.
	 * @return ModelDetails|null The model details, or null when they could not be determined.
	 */
	private function resolveModelDetails(
		string $model_name,
		array $model_entry,
		OllamaModelDetailsCache $details_cache,
		array &$digests_in_use
	): ?array {
		$families = $this->readStringList( $model_entry['details']['families'] ?? null );

		// Recent Ollama versions report capabilities in the tag listing, making the per-model request unnecessary.
		$capabilities = $this->readStringList( $model_entry['capabilities'] ?? null );
		if ( ! empty( $capabilities ) ) {
			return array(
				'capabilities' => $capabilities,
				'families'     => $families,
			);
		}

		// The digest identifies the model's content, so an entry stays valid until the model itself changes.
		$digest = isset( $model_entry['digest'] ) && is_string( $model_entry['digest'] ) ? $model_entry['digest'] : '';

		if ( '' !== $digest ) {
			$cached_details = $details_cache->get( $digest );
			if ( null !== $cached_details ) {
				$digests_in_use[] = $digest;

				return $cached_details;
			}
		}

		$show_data = $this->fetchModelDetails( $model_name );
		if ( null === $show_data ) {
			return null;
		}

		$show_families = $this->readStringList( $show_data['details']['families'] ?? null );
		$details       = array(
			'capabilities' => $this->readStringList( $show_data['capabilities'] ?? null ),
			'families'     => empty( $show_families ) ? $families : $show_families,
		);

		if ( '' !== $digest ) {
			$details_cache->set( $digest, $details );
			$digests_in_use[] = $digest;
		}

		return $details;
	}

	/**
	 * Builds a ModelMetadata object for a single model, or returns null if the model should be skipped.
	 *
	 * @since 1.0.0
	 *
	 * @param string $model_name The model name.
	 * @param ModelDetails|null $details The model's capability details, or null when they are unknown.
	 * @return \WordPress\AiClient\Providers\Models\DTO\ModelMetadata|null The model metadata, or null if the model should be excluded.
	 */
	private function buildModelMetadata( string $model_name, ?array $details ): ?ModelMetadata {
		$model_capabilities = null !== $details ? $details['capabilities'] : array();
		$model_families     = null !== $details ? $details['families'] : array();

		$is_image_generation_model = in_array( 'image', $model_capabilities, true );

		$is_embedding_model = in_array( 'embedding', $model_capabilities, true )
			&& ! in_array( 'completion', $model_capabilities, true );

		if ( $is_embedding_model && ! $is_image_generation_model ) {
			// The embedding contracts are unreleased in some SDK versions.
			if ( ! interface_exists( EmbeddingGenerationModelInterface::class ) ) {
				return null;
			}

			return $this->buildEmbeddingModelMetadata( $model_name );
		}

		// Skip other non-completion models, but keep image-generation models which may not report "completion".
		if (
			! empty( $model_capabilities ) &&
			! in_array( 'completion', $model_capabilities, true ) &&
			! $is_image_generation_model
		) {
			return null;
		}

		// Check for vision support via the capabilities array or the model families.
		$has_vision = in_array( 'vision', $model_capabilities, true )
			|| in_array( 'clip', $model_families, true );

		$has_tools = in_array( 'tools', $model_capabilities, true );

		if ( $has_vision ) {
			$input_modalities_option = new SupportedOption(
				OptionEnum::inputModalities(),
				array(
					array( ModalityEnum::text() ),
					array( ModalityEnum::text(), ModalityEnum::image() ),
				)
			);
		} else {
			$input_modalities_option = new SupportedOption(
				OptionEnum::inputModalities(),
				array( array( ModalityEnum::text() ) )
			);
		}

		if ( $is_image_generation_model ) {
			return new ModelMetadata(
				$model_name,
				$model_name,
				array(
					CapabilityEnum::imageGeneration(),
				),
				array(
					new SupportedOption( OptionEnum::inputModalities(), array( array( ModalityEnum::text() ) ) ),
					new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::image() ) ) ),
					new SupportedOption( OptionEnum::candidateCount() ),
					new SupportedOption( OptionEnum::outputMimeType(), array( 'image/png' ) ),
					new SupportedOption( OptionEnum::outputFileType(), array( FileTypeEnum::inline() ) ),
					new SupportedOption( OptionEnum::customOptions() ),
				)
			);
		}

		$options = array(
			new SupportedOption( OptionEnum::systemInstruction() ),
			new SupportedOption( OptionEnum::candidateCount() ),
			new SupportedOption( OptionEnum::maxTokens() ),
			new SupportedOption( OptionEnum::temperature() ),
			new SupportedOption( OptionEnum::topP() ),
			new SupportedOption( OptionEnum::topK() ),
			new SupportedOption( OptionEnum::stopSequences() ),
			new SupportedOption( OptionEnum::frequencyPenalty() ),
			new SupportedOption( OptionEnum::presencePenalty() ),
			new SupportedOption( OptionEnum::outputMimeType(), array( 'text/plain', 'application/json' ) ),
			new SupportedOption( OptionEnum::outputSchema() ),
			new SupportedOption( OptionEnum::customOptions() ),
			new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::text() ) ) ),
			$input_modalities_option,
		);

		if ( $has_tools ) {
			$options[] = new SupportedOption( OptionEnum::functionDeclarations() );
		}

		return new ModelMetadata(
			$model_name,
			$model_name,
			array(
				CapabilityEnum::textGeneration(),
				CapabilityEnum::chatHistory(),
			),
			$options
		);
	}

	/**
	 * Builds embedding-generation metadata for a model.
	 *
	 * @since 1.2.0
	 *
	 * @param string $model_name The model name.
	 * @return \WordPress\AiClient\Providers\Models\DTO\ModelMetadata The embedding model metadata.
	 */
	private function buildEmbeddingModelMetadata( string $model_name ): ModelMetadata {
		return new ModelMetadata(
			$model_name,
			$model_name,
			array(
				CapabilityEnum::embeddingGeneration(),
			),
			array(
				new SupportedOption( OptionEnum::inputModalities(), array( array( ModalityEnum::text() ) ) ),
				new SupportedOption( OptionEnum::dimensions() ),
				new SupportedOption( OptionEnum::customOptions() ),
			)
		);
	}

	/**
	 * Reads a list of strings out of an API payload.
	 *
	 * Ollama omits these keys on some versions and sends null for others, so
	 * anything that is not a list of strings is read as "none given".
	 *
	 * @since x.x.x
	 *
	 * @param mixed $value The raw value.
	 * @return list<string> The strings it contained, if any.
	 */
	private function readStringList( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_values( array_filter( $value, 'is_string' ) );
	}

	/**
	 * Fetches model details from the Ollama /api/show endpoint.
	 *
	 * Returns null if the request fails, in which case the caller falls back
	 * to default text-generation capabilities for the model.
	 *
	 * @since 1.0.0
	 *
	 * @param string $model_name The model name.
	 * @return ShowResponseData|null The response data, or null on failure.
	 */
	private function fetchModelDetails( string $model_name ): ?array {
		try {
			$request  = $this->createRequest(
				HttpMethodEnum::POST(),
				'api/show',
				array( 'Content-Type' => 'application/json' ),
				array( 'model' => $model_name )
			);
			$request  = $this->getRequestAuthentication()->authenticateRequest( $request );
			$response = $this->getHttpTransporter()->send( $request );

			ResponseUtil::throwIfNotSuccessful( $response );

			/** @var ShowResponseData $data */
			$data = $response->getData();
			return $data;
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * Creates a request object for the Ollama API.
	 *
	 * @since 1.0.0
	 *
	 * @param \WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum                     $method  The HTTP method.
	 * @param string                             $path    The API endpoint path, relative to the base URI.
	 * @param array<string, string|list<string>> $headers The request headers.
	 * @param string|array<string, mixed>|null   $data    The request data.
	 * @return \WordPress\AiClient\Providers\Http\DTO\Request The request object.
	 */
	private function createRequest( HttpMethodEnum $method, string $path, array $headers = array(), $data = null ): Request {
		return new Request(
			$method,
			OllamaProvider::url( $path ),
			$headers,
			$data,
			$this->discoveryRequestOptions()
		);
	}

	/**
	 * Builds the request options used for model discovery.
	 *
	 * Discovery runs while the admin waits for a screen to render, so it gets
	 * its own, tighter budget rather than the generous timeouts a generation
	 * request is allowed to take.
	 *
	 * @since x.x.x
	 *
	 * @return \WordPress\AiClient\Providers\Http\DTO\RequestOptions The prepared request options.
	 */
	private function discoveryRequestOptions(): RequestOptions {
		$request_timeout = self::DEFAULT_DISCOVERY_REQUEST_TIMEOUT;
		$connect_timeout = self::DEFAULT_DISCOVERY_CONNECT_TIMEOUT;

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filters the request timeout for Ollama model discovery requests.
			 *
			 * Applies to the `/api/tags` and `/api/show` requests behind the connection
			 * check and the model list, not to text, image, or embedding generation.
			 *
			 * @since x.x.x
			 *
			 * @param float $request_timeout The request timeout in seconds.
			 */
			$request_timeout = (float) apply_filters( 'ai_provider_for_ollama_discovery_request_timeout', $request_timeout );

			/**
			 * Filters the connection timeout for Ollama model discovery requests.
			 *
			 * @since x.x.x
			 *
			 * @param float $connect_timeout The connection timeout in seconds.
			 */
			$connect_timeout = (float) apply_filters( 'ai_provider_for_ollama_discovery_connect_timeout', $connect_timeout );
		}

		$request_options = new RequestOptions();
		$request_options->setTimeout( $request_timeout );
		$request_options->setConnectTimeout( $connect_timeout );

		return $request_options;
	}
}

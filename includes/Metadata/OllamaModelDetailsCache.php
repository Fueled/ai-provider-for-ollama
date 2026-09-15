<?php
/**
 * Persistent cache for per-model details fetched from Ollama.
 *
 * @package Fueled\AiProviderForOllama\Metadata
 * @since   x.x.x
 */

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Metadata;

/**
 * Caches the capability details of individual Ollama models.
 *
 * Ollama has no bulk endpoint for model capabilities, so servers that do not
 * report them in `/api/tags` require one `/api/show` request per model. This
 * cache keeps those answers between requests, which is what keeps the model
 * listing down to a single request on such servers.
 *
 * Two properties make it safe to keep entries for a long time:
 *
 * - Entries are keyed by the model digest, which changes whenever the model is
 *   re-pulled, so a changed model invalidates its own entry.
 * - The store is keyed by host, so pointing the provider at a different Ollama
 *   instance misses rather than reads another instance's answers.
 *
 * The store is a WordPress transient, which lives in the options table and
 * therefore survives between requests even on sites without a persistent
 * object cache. Outside WordPress the cache is inert and the directory simply
 * asks Ollama every time, as it did before.
 *
 * @since x.x.x
 *
 * @phpstan-type ModelDetails array{capabilities: list<string>, families: list<string>}
 */
final class OllamaModelDetailsCache {

	/**
	 * Prefix for the transient holding a host's cached model details.
	 *
	 * @since x.x.x
	 *
	 * @var string
	 */
	private const TRANSIENT_PREFIX = 'ai_provider_for_ollama_model_details_';

	/**
	 * How long a store stays cached, in seconds.
	 *
	 * Deliberately long: the digest key, not the TTL, is what invalidates an
	 * entry whose model has changed.
	 *
	 * @since x.x.x
	 *
	 * @var int
	 */
	private const TTL = 2592000;

	/**
	 * The transient name this store reads from and writes to.
	 *
	 * @since x.x.x
	 *
	 * @var string
	 */
	private string $transient_name;

	/**
	 * Cached details, keyed by model digest.
	 *
	 * @since x.x.x
	 *
	 * @var array<string, ModelDetails>
	 */
	private array $entries;

	/**
	 * The entries as they were read, to detect whether a write is needed.
	 *
	 * @since x.x.x
	 *
	 * @var array<string, ModelDetails>
	 */
	private array $stored_entries;

	/**
	 * Constructor.
	 *
	 * @since x.x.x
	 *
	 * @param string                      $transient_name The transient name to use.
	 * @param array<string, ModelDetails> $entries        The entries read from the transient.
	 */
	private function __construct( string $transient_name, array $entries ) {
		$this->transient_name = $transient_name;
		$this->entries        = $entries;
		$this->stored_entries = $entries;
	}

	/**
	 * Loads the store for the given Ollama host.
	 *
	 * @since x.x.x
	 *
	 * @param string $host The Ollama base URL the details belong to.
	 * @return self The store, empty when nothing is cached or when WordPress is unavailable.
	 */
	public static function load( string $host ): self {
		$transient_name = self::transient_name( $host );

		if ( ! function_exists( 'get_transient' ) ) {
			return new self( $transient_name, array() );
		}

		$stored = get_transient( $transient_name );

		return new self( $transient_name, is_array( $stored ) ? self::normalize( $stored ) : array() );
	}

	/**
	 * Discards everything cached for the given Ollama host.
	 *
	 * @since x.x.x
	 *
	 * @param string $host The Ollama base URL whose details should be forgotten.
	 */
	public static function flush( string $host ): void {
		if ( ! function_exists( 'delete_transient' ) ) {
			return;
		}

		delete_transient( self::transient_name( $host ) );
	}

	/**
	 * Builds the transient name for a host.
	 *
	 * The host is fingerprinted rather than embedded, so that the name stays a
	 * short, valid option name whatever the URL looks like.
	 *
	 * @since x.x.x
	 *
	 * @param string $host The Ollama base URL.
	 * @return string The transient name.
	 */
	private static function transient_name( string $host ): string {
		return self::TRANSIENT_PREFIX . substr( md5( $host ), 0, 12 );
	}

	/**
	 * Returns the cached details for a model digest.
	 *
	 * @since x.x.x
	 *
	 * @param string $digest The model digest.
	 * @return ModelDetails|null The cached details, or null when the digest is unknown.
	 */
	public function get( string $digest ): ?array {
		return $this->entries[ $digest ] ?? null;
	}

	/**
	 * Caches the details for a model digest.
	 *
	 * @since x.x.x
	 *
	 * @param string       $digest  The model digest.
	 * @param ModelDetails $details The details to cache.
	 */
	public function set( string $digest, array $details ): void {
		$this->entries[ $digest ] = $details;
	}

	/**
	 * Persists the store, dropping entries for models the host no longer offers.
	 *
	 * Nothing is written when the outcome matches what was read, so a steady
	 * state costs no database writes.
	 *
	 * @since x.x.x
	 *
	 * @param list<string> $digests_in_use Digests seen in the current model listing.
	 */
	public function save( array $digests_in_use ): void {
		$entries = array_intersect_key( $this->entries, array_flip( $digests_in_use ) );

		if ( $entries === $this->stored_entries ) {
			return;
		}

		$this->entries        = $entries;
		$this->stored_entries = $entries;

		if ( ! function_exists( 'set_transient' ) || ! function_exists( 'delete_transient' ) ) {
			return;
		}

		if ( empty( $entries ) ) {
			delete_transient( $this->transient_name );
			return;
		}

		set_transient( $this->transient_name, $entries, self::TTL );
	}

	/**
	 * Discards anything that does not look like details this class wrote.
	 *
	 * The transient is ordinary option data, so it is treated as untrusted.
	 *
	 * @since x.x.x
	 *
	 * @param array<mixed> $stored The raw transient value.
	 * @return array<string, ModelDetails> The usable entries.
	 */
	private static function normalize( array $stored ): array {
		$entries = array();

		foreach ( $stored as $digest => $details ) {
			if ( ! is_string( $digest ) || '' === $digest || ! is_array( $details ) ) {
				continue;
			}

			$entries[ $digest ] = array(
				'capabilities' => self::normalize_string_list( $details['capabilities'] ?? null ),
				'families'     => self::normalize_string_list( $details['families'] ?? null ),
			);
		}

		return $entries;
	}

	/**
	 * Coerces a stored value into a list of strings.
	 *
	 * @since x.x.x
	 *
	 * @param mixed $value The stored value.
	 * @return list<string> The strings it contained, if any.
	 */
	private static function normalize_string_list( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_values( array_filter( $value, 'is_string' ) );
	}
}

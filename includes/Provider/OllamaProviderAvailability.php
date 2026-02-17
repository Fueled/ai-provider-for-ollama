<?php

declare( strict_types=1 );

namespace WordPress\AiClientProviderOllama\Provider;

use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;

/**
 * Availability check for the Ollama provider.
 *
 * Ollama is a local server that does not require API credentials.
 * Unlike cloud providers, Ollama does not need a credential-based
 * availability check. Returns configured as long as the provider
 * is registered. Actual reachability errors surface when listing
 * models or generating text.
 *
 * @since 1.0.0
 */
class OllamaProviderAvailability implements ProviderAvailabilityInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function isConfigured(): bool {
		return true;
	}
}

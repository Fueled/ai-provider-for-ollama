<?php

declare( strict_types=1 );

namespace WordPress\AiProviderOllama\Tests\Integration\Provider;

use PHPUnit\Framework\TestCase;
use WordPress\AiProviderOllama\Provider\OllamaProviderAvailability;

/**
 * Tests for OllamaProviderAvailability.
 *
 * @covers \WordPress\AiProviderOllama\Provider\OllamaProviderAvailability
 */
class OllamaProviderAvailabilityTest extends TestCase {

	/**
	 * Tests that isConfigured() always returns true for the local Ollama provider.
	 */
	public function test_is_configured_always_returns_true(): void {
		$availability = new OllamaProviderAvailability();
		$this->assertTrue( $availability->isConfigured() );
	}
}

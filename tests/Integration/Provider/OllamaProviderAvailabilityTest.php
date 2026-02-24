<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Tests\Integration\Provider;

use Fueled\AiProviderForOllama\Provider\OllamaProviderAvailability;
use PHPUnit\Framework\TestCase;

/**
 * Tests for OllamaProviderAvailability.
 *
 * @covers \Fueled\AiProviderForOllama\Provider\OllamaProviderAvailability
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

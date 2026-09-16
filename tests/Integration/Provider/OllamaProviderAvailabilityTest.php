<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Tests\Integration\Provider;

use Fueled\AiProviderForOllama\Metadata\OllamaModelDetailsCache;
use Fueled\AiProviderForOllama\Metadata\OllamaModelMetadataDirectory;
use Fueled\AiProviderForOllama\Provider\OllamaProvider;
use Fueled\AiProviderForOllama\Provider\OllamaProviderAvailability;
use Fueled\AiProviderForOllama\Tests\Integration\Mocks\MockHttpTransporter;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Response;

/**
 * Tests for OllamaProviderAvailability.
 *
 * @covers \Fueled\AiProviderForOllama\Provider\OllamaProviderAvailability
 */
class OllamaProviderAvailabilityTest extends TestCase {

	/**
	 * Directory backing the availability check.
	 *
	 * @var OllamaModelMetadataDirectory
	 */
	private OllamaModelMetadataDirectory $directory;

	/**
	 * Mock transporter (fresh instance per test).
	 *
	 * @var MockHttpTransporter
	 */
	private MockHttpTransporter $transporter;

	protected function setUp(): void {
		parent::setUp();
		putenv( 'OLLAMA_HOST=http://localhost:11434' );

		$this->transporter = new MockHttpTransporter();
		$this->directory   = new OllamaModelMetadataDirectory();
		$this->directory->setHttpTransporter( $this->transporter );
		$this->directory->setRequestAuthentication( new ApiKeyRequestAuthentication( '' ) );
		$this->directory->invalidateCaches();

		OllamaModelDetailsCache::flush( OllamaProvider::url( '' ) );
	}

	protected function tearDown(): void {
		$this->directory->invalidateCaches();
		OllamaModelDetailsCache::flush( OllamaProvider::url( '' ) );
		putenv( 'OLLAMA_HOST' );
		parent::tearDown();
	}

	/**
	 * Builds a fake /api/tags 200 response.
	 *
	 * @param list<array<string, mixed>> $models The model entries to include.
	 * @return Response
	 */
	private function make_tags_response( array $models ): Response {
		return new Response( 200, array(), (string) json_encode( array( 'models' => $models ) ) );
	}

	/**
	 * Tests that a host which lists its models is reported as configured.
	 */
	public function test_is_configured_when_the_host_lists_models(): void {
		$this->transporter->queue_response( $this->make_tags_response( array( array( 'name' => 'llama3.2' ) ) ) );

		$availability = new OllamaProviderAvailability( $this->directory );

		$this->assertTrue( $availability->isConfigured() );
		$this->assertSame( 1, $this->transporter->get_request_count() );
	}

	/**
	 * Tests that a host with no models at all is still reported as configured.
	 */
	public function test_is_configured_when_the_host_has_no_models(): void {
		$this->transporter->queue_response( $this->make_tags_response( array() ) );

		$availability = new OllamaProviderAvailability( $this->directory );

		$this->assertTrue( $availability->isConfigured() );
	}

	/**
	 * Tests that an unreachable or failing host is reported as not configured.
	 */
	public function test_is_not_configured_when_the_request_fails(): void {
		$this->transporter->set_response_to_return(
			new Response( 500, array(), '{"error":"Internal Server Error"}' )
		);

		$availability = new OllamaProviderAvailability( $this->directory );

		$this->assertFalse( $availability->isConfigured() );
	}

	/**
	 * Tests that a host which answers with something other than a model listing is not configured.
	 */
	public function test_is_not_configured_when_the_response_is_not_a_model_listing(): void {
		$this->transporter->set_response_to_return(
			new Response( 200, array(), (string) json_encode( array( 'not_models' => array() ) ) )
		);

		$availability = new OllamaProviderAvailability( $this->directory );

		$this->assertFalse( $availability->isConfigured() );
	}

	/**
	 * Tests that a directory with nothing wired up is not configured, rather than fatal.
	 */
	public function test_is_not_configured_when_the_directory_cannot_send_requests(): void {
		$availability = new OllamaProviderAvailability( new OllamaModelMetadataDirectory() );

		$this->assertFalse( $availability->isConfigured() );
	}

	/**
	 * Tests that the check never enumerates per-model capabilities.
	 *
	 * This is the regression guard for the connection check taking one request
	 * per installed model: the models here carry no capabilities, so the old
	 * behaviour would have issued an /api/show request for each of them.
	 */
	public function test_does_not_look_up_model_capabilities(): void {
		$this->transporter->queue_response(
			$this->make_tags_response(
				array(
					array( 'name' => 'llama3.2' ),
					array( 'name' => 'qwen2.5:3b' ),
					array( 'name' => 'gemma3:latest' ),
				)
			)
		);

		$availability = new OllamaProviderAvailability( $this->directory );

		$this->assertTrue( $availability->isConfigured() );
		$this->assertSame( 1, $this->transporter->get_request_count() );
	}

	/**
	 * Tests that checking availability before listing models costs one request between them.
	 */
	public function test_shares_its_request_with_the_model_listing(): void {
		$this->transporter->queue_response(
			$this->make_tags_response(
				array(
					array(
						'name'         => 'qwen2.5:3b',
						'capabilities' => array( 'completion', 'tools' ),
					),
				)
			)
		);

		$availability = new OllamaProviderAvailability( $this->directory );

		$this->assertTrue( $availability->isConfigured() );
		$this->assertCount( 1, $this->directory->listModelMetadata() );
		$this->assertSame( 1, $this->transporter->get_request_count() );
	}
}

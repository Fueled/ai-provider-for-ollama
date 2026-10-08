<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Tests\Integration\Provider;

use Fueled\AiProviderForOllama\Metadata\OllamaModelDetailsCache;
use Fueled\AiProviderForOllama\Metadata\OllamaModelMetadataDirectory;
use Fueled\AiProviderForOllama\Provider\OllamaProvider;
use Fueled\AiProviderForOllama\Provider\OllamaProviderAvailability;
use Fueled\AiProviderForOllama\Tests\Integration\Mocks\MockHttpTransporter;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
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

	/**
	 * Tests that when a transport exception or network timeout is thrown, the host is reported as not configured.
	 */
	public function test_is_not_configured_when_network_connection_times_out(): void {
		$failing_transporter = new class implements HttpTransporterInterface {
			public function send( Request $request, ?RequestOptions $options = null ): Response {
				throw new \RuntimeException( 'Connection timed out after 3000ms' );
			}
		};

		$this->directory->setHttpTransporter( $failing_transporter );
		$availability = new OllamaProviderAvailability( $this->directory );

		$this->assertFalse( $availability->isConfigured() );
	}

	/**
	 * Tests that when connection to the Ollama daemon is refused, the host is reported as not configured.
	 */
	public function test_is_not_configured_when_connection_is_refused(): void {
		$failing_transporter = new class implements HttpTransporterInterface {
			public function send( Request $request, ?RequestOptions $options = null ): Response {
				throw new \RuntimeException( 'Failed to connect to localhost port 11434: Connection refused' );
			}
		};

		$this->directory->setHttpTransporter( $failing_transporter );
		$availability = new OllamaProviderAvailability( $this->directory );

		$this->assertFalse( $availability->isConfigured() );
	}

	/**
	 * Tests that various HTTP error response statuses report the provider as not configured.
	 *
	 * @dataProvider data_http_error_status_codes
	 *
	 * @param int    $status_code The HTTP status code.
	 * @param string $error_body  The response body.
	 */
	public function test_is_not_configured_on_http_error_status_codes( int $status_code, string $error_body ): void {
		$this->transporter->set_response_to_return(
			new Response( $status_code, array(), $error_body )
		);

		$availability = new OllamaProviderAvailability( $this->directory );

		$this->assertFalse( $availability->isConfigured() );
	}

	/**
	 * Data provider of HTTP error status codes and payloads.
	 *
	 * @return array<string, array{0: int, 1: string}>
	 */
	public function data_http_error_status_codes(): array {
		return array(
			'401 Unauthorized'        => array( 401, '{"error":"Unauthorized"}' ),
			'403 Forbidden'           => array( 403, '{"error":"Forbidden"}' ),
			'404 Not Found'           => array( 404, '{"error":"404 page not found"}' ),
			'502 Bad Gateway HTML'    => array( 502, '<html><body><h1>502 Bad Gateway</h1></body></html>' ),
			'503 Service Unavailable' => array( 503, '{"error":"Ollama service unavailable"}' ),
			'504 Gateway Timeout'     => array( 504, '{"error":"Gateway Timeout"}' ),
		);
	}

	/**
	 * Tests that a 200 response with truncated/malformed JSON reports the provider as not configured.
	 */
	public function test_is_not_configured_when_response_body_is_malformed_json(): void {
		$this->transporter->set_response_to_return(
			new Response( 200, array(), '{"models": [' )
		);

		$availability = new OllamaProviderAvailability( $this->directory );

		$this->assertFalse( $availability->isConfigured() );
	}

	/**
	 * Tests that a 200 response with an empty body reports the provider as not configured.
	 */
	public function test_is_not_configured_when_response_body_is_empty(): void {
		$this->transporter->set_response_to_return(
			new Response( 200, array(), '' )
		);

		$availability = new OllamaProviderAvailability( $this->directory );

		$this->assertFalse( $availability->isConfigured() );
	}

	/**
	 * Tests that non-array values for the models key report the provider as not configured.
	 *
	 * @dataProvider data_invalid_models_values
	 *
	 * @param mixed $invalid_models_value The non-array value for the models key.
	 */
	public function test_is_not_configured_when_models_key_is_not_an_array( $invalid_models_value ): void {
		$this->transporter->set_response_to_return(
			new Response( 200, array(), (string) json_encode( array( 'models' => $invalid_models_value ) ) )
		);

		$availability = new OllamaProviderAvailability( $this->directory );

		$this->assertFalse( $availability->isConfigured() );
	}

	/**
	 * Data provider of non-array models key values.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public function data_invalid_models_values(): array {
		return array(
			'string value'  => array( 'llama3.2' ),
			'integer value' => array( 12345 ),
			'boolean false' => array( false ),
			'null value'    => array( null ),
		);
	}

	/**
	 * Tests that malformed non-array entries inside the models array are safely filtered out.
	 */
	public function test_is_configured_and_filters_out_non_array_entries_in_models(): void {
		$raw_models = array(
			'invalid-string-entry',
			123,
			null,
			false,
			array( 'name' => 'valid-llama' ),
		);

		$this->transporter->queue_response(
			new Response( 200, array(), (string) json_encode( array( 'models' => $raw_models ) ) )
		);

		$availability = new OllamaProviderAvailability( $this->directory );

		$this->assertTrue( $availability->isConfigured() );
		$this->assertSame(
			array( array( 'name' => 'valid-llama' ) ),
			$this->directory->listModelTags()
		);
	}
}

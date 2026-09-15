<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForOllama\Tests\Integration\Mocks;

use WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\DTO\Response;

/**
 * Mock HTTP transporter for testing.
 *
 * Supports both a single configurable response and a FIFO queue of responses
 * for multi-call scenarios (e.g. MetadataDirectory tests that call /api/tags
 * then /api/show per model).
 */
class MockHttpTransporter implements HttpTransporterInterface {

	/**
	 * Every request that was sent, in order.
	 *
	 * @var list<Request>
	 */
	private array $requests = array();

	/**
	 * The fallback response to return when the queue is empty.
	 *
	 * @var Response|null
	 */
	private ?Response $response_to_return = null;

	/**
	 * FIFO queue of responses. Popped before falling back to $response_to_return.
	 *
	 * @var list<Response>
	 */
	private array $responses_queue = array();

	/**
	 * {@inheritDoc}
	 */
	public function send( Request $request, ?RequestOptions $options = null ): Response {
		$this->requests[] = $request;

		if ( ! empty( $this->responses_queue ) ) {
			return array_shift( $this->responses_queue );
		}

		return $this->response_to_return ?? new Response( 200, array(), '{"status":"success"}' );
	}

	/**
	 * Returns the last request that was sent.
	 *
	 * @return Request|null The last request, or null if none was sent.
	 */
	public function get_last_request(): ?Request {
		return empty( $this->requests ) ? null : $this->requests[ count( $this->requests ) - 1 ];
	}

	/**
	 * Returns every request that was sent, in order.
	 *
	 * @return list<Request> The requests.
	 */
	public function get_requests(): array {
		return $this->requests;
	}

	/**
	 * Returns how many requests were sent.
	 *
	 * @return int The request count.
	 */
	public function get_request_count(): int {
		return count( $this->requests );
	}

	/**
	 * Sets the fallback response to return when the queue is empty.
	 *
	 * @param Response $response The response to return.
	 */
	public function set_response_to_return( Response $response ): void {
		$this->response_to_return = $response;
	}

	/**
	 * Adds a response to the end of the FIFO queue.
	 *
	 * @param Response $response The response to enqueue.
	 */
	public function queue_response( Response $response ): void {
		$this->responses_queue[] = $response;
	}
}

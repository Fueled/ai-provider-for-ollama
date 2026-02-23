<?php

declare( strict_types=1 );

namespace WordPress\AiProviderOllama\Tests\Integration\Models;

use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiProviderOllama\Models\OllamaTextGenerationModel;

/**
 * Test double that exposes the protected createRequest() method for path-normalization tests.
 */
class MockOllamaTextGenerationModel extends OllamaTextGenerationModel {

	/**
	 * Publicly exposes the protected createRequest() method.
	 *
	 * @param HttpMethodEnum                   $method  The HTTP method.
	 * @param string                           $path    The API endpoint path.
	 * @param array<string, string|list<string>> $headers The request headers.
	 * @param string|array<string, mixed>|null $data    The request data.
	 * @return Request The constructed request object.
	 */
	public function expose_create_request(
		HttpMethodEnum $method,
		string $path,
		array $headers = array(),
		$data = null
	): Request {
		return $this->createRequest( $method, $path, $headers, $data );
	}
}

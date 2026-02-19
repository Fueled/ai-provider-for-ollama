<?php

declare( strict_types=1 );

namespace WordPress\AiClientProviderOllama\Models;

use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;
use WordPress\AiClientProviderOllama\Provider\OllamaProvider;

/**
 * Class for an Ollama text generation model using the OpenAI-compatible chat completions API.
 *
 * TODO: Could look to use the native API instead of the OpenAI-compatible API.
 *
 * @since 1.0.0
 */
class OllamaTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected function createRequest(
		HttpMethodEnum $method,
		string $path,
		array $headers = array(),
		$data = null
	): Request {
		// Ollama supports OpenAI-compatible endpoints at /v1/.
		$path = ltrim( (string) preg_replace( '#^v1/?#', '', ltrim( $path, '/' ) ), '/' );
		$path = '/v1/' . $path;

		return new Request(
			$method,
			OllamaProvider::url( $path ),
			$headers,
			$data,
			$this->getRequestOptions()
		);
	}
}

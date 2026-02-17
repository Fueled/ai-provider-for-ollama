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
		return new Request(
			$method,
			OllamaProvider::url( $path ),
			$headers,
			$data,
			$this->getRequestOptions()
		);
	}
}

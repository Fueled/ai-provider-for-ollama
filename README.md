# WordPress AI Client Provider for Ollama

Ollama provider for the [PHP AI Client SDK](https://github.com/WordPress/php-ai-client). Works as both a Composer package with the `php-ai-client` package and as a WordPress plugin with the `wp-ai-client` plugin.

[Ollama](https://ollama.com/) lets you run large language models locally. This provider uses the [OpenAI-compatible API](https://ollama.com/blog/openai-compatibility) that Ollama exposes at `/v1/chat/completions`.

## Requirements

- PHP 7.4+
- [php-ai-client](https://github.com/WordPress/php-ai-client) `^0.4`
- Ollama running locally or remotely

## Installation

### As a WordPress Plugin

1. Install and activate the [wp-ai-client](https://github.com/WordPress/wp-ai-client) plugin.
2. Place this plugin in your `wp-content/plugins/` directory.
3. Activate "WordPress AI Client Provider for Ollama" from the Plugins screen.

### As a Composer Package

```bash
composer require wordpress/ai-client-provider-ollama
```

## Configuration

### Ollama Host URL

By default, the provider connects to `http://localhost:11434`. You can change this in two ways:

1. **Environment variable** (takes precedence): Set the `OLLAMA_HOST` environment variable.
2. **WordPress admin**: Go to **Settings > Ollama** and enter your Ollama host URL.

### API Key

For local Ollama instances, no API key is needed. The plugin automatically registers an empty API key as a fallback.

For remote Ollama instances that require authentication (e.g., Ollama Cloud), enter the API key in the wp-ai-client **Settings > AI Credentials** screen.

## Usage

### With WordPress (wp-ai-client)

```php
use WordPress\AI_Client\Prompt_Builder;

$model = get_option( 'wp_ai_client_ollama_settings', array() )['model'] ?? 'llama3.2';

$result = Prompt_Builder::create()
    ->using_provider( 'ollama' )
    ->with_model( $model )
    ->set_system_instruction( 'You are a helpful assistant.' )
    ->add_text_message( 'Hello, how are you?' )
    ->generate_text();
```

### Standalone PHP (php-ai-client)

```php
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClientProviderOllama\Provider\OllamaProvider;

require_once 'vendor/autoload.php';

$registry = AiClient::defaultRegistry();
$registry->registerProvider(OllamaProvider::class);
$registry->setProviderRequestAuthentication('ollama', new ApiKeyRequestAuthentication(''));

$result = AiClient::prompt('Hello!')
    ->usingProvider('ollama')
    ->generateText();
```

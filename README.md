# AI Provider for Ollama

![AI Provider for Ollama](https://github.com/Fueled/ai-provider-for-ollama/blob/develop/.wordpress-org/banner-1544x500.png)

[![Support Level](https://img.shields.io/badge/support-active-green.svg)](#support-level) [![Release Version](https://img.shields.io/github/release/Fueled/ai-provider-for-ollama.svg)](https://github.com/Fueled/ai-provider-for-ollama/releases/latest) ![WordPress Plugin Required PHP Version](https://img.shields.io/wordpress/plugin/required-php/ai-provider-for-ollama) ![WordPress Plugin: Required WP Version](https://img.shields.io/wordpress/plugin/wp-version/ai-provider-for-ollama) ![WordPress Plugin: Tested WP Version](https://img.shields.io/wordpress/plugin/tested/ai-provider-for-ollama) [![GPLv2 License](https://img.shields.io/github/license/Fueled/ai-provider-for-ollama.svg)](https://github.com/Fueled/ai-provider-for-ollama/blob/develop/LICENSE.md)

[![Test](https://github.com/Fueled/ai-provider-for-ollama/actions/workflows/test.yml/badge.svg)](https://github.com/Fueled/ai-provider-for-ollama/actions/workflows/test.yml) [![Plugin Check](https://github.com/Fueled/ai-provider-for-ollama/actions/workflows/plugin-check.yml/badge.svg)](https://github.com/Fueled/ai-provider-for-ollama/actions/workflows/plugin-check.yml) [![Dependency Review](https://github.com/Fueled/ai-provider-for-ollama/actions/workflows/dependency-review.yml/badge.svg)](https://github.com/Fueled/ai-provider-for-ollama/actions/workflows/dependency-review.yml)

> Ollama provider for the PHP and WP AI Client packages.

## Overview

Ollama provider for the [PHP AI Client SDK](https://github.com/WordPress/php-ai-client). Works as both a Composer package with the `php-ai-client` package and as a WordPress plugin with the AI Client that is bundled with WordPress 7.0+.

[Ollama](https://ollama.com/) lets you run large language models locally or remotely. Ollama exposes an [OpenAI-compatible API](https://ollama.com/blog/openai-compatibility), and this provider uses that API to communicate with any model you have pulled into Ollama (Llama, Mistral, Gemma, Phi, and many more) or any available Ollama Cloud model.

## Requirements

- PHP 7.4+
- [php-ai-client](https://github.com/WordPress/php-ai-client) `^1.3` or WordPress 7.0+
- Ollama running locally or remotely (like Ollama Cloud)

## Installation

### As a WordPress Plugin

1. Upload the plugin files to `/wp-content/plugins/ai-provider-for-ollama/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **Settings > Ollama** to configure the host URL and see available models.

### As a Composer Package

```bash
composer require fueled/ai-provider-for-ollama
```

## Configuration

<img src="/.wordpress-org/screenshot-1.png" alt="Settings > Ollama screen showing available AI models and Host URL configuration." width="600">

### Ollama Host URL

By default, the provider connects to `http://localhost:11434`. You can change this in two ways:

1. **Environment variable** (takes precedence): Set the `OLLAMA_HOST` environment variable.
2. **WordPress admin**: Go to **Settings > Ollama** and enter your Ollama host URL.

### API Key

For local Ollama instances, no API key is needed. The plugin automatically registers an empty API key as a fallback.

For remote Ollama instances that require authentication (e.g., Ollama Cloud), enter the API key in the **Settings > Connectors** screen. If using Ollama Cloud, you also need to set your Ollama host URL in the **Settings > Ollama** screen to `https://ollama.com`.

## Usage

### With WordPress (AI Client)

```php
$result = wp_ai_client_prompt( 'Hello, how are you?' )
    ->using_provider( 'ollama' )
    ->using_system_instruction( 'You are a helpful assistant.' )
    ->generate_text();
```

### Standalone PHP (php-ai-client)

```php
use Fueled\AiProviderForOllama\Provider\OllamaProvider;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;

require_once 'vendor/autoload.php';

$registry = AiClient::defaultRegistry();
$registry->registerProvider(OllamaProvider::class);
$registry->setProviderRequestAuthentication('ollama', new ApiKeyRequestAuthentication(''));

$result = AiClient::prompt('Hello!')
    ->usingProvider('ollama')
    ->generateText();
```

## Support Level

**Active:** Fueled is actively working on this, and we expect to continue work for the foreseeable future including keeping tested up to the most recent version of WordPress.  Bug reports, feature requests, questions, and pull requests are welcome.

## Changelog

A complete listing of all notable changes to AI Provider for Ollama are documented in [CHANGELOG.md](https://github.com/Fueled/ai-provider-for-ollama/blob/develop/CHANGELOG.md).

## Contributing

Please read [CODE_OF_CONDUCT.md](https://github.com/Fueled/ai-provider-for-ollama/blob/develop/CODE_OF_CONDUCT.md) for details on our code of conduct, [CONTRIBUTING.md](https://github.com/Fueled/ai-provider-for-ollama/blob/develop/CONTRIBUTING.md) for details on the process for submitting pull requests to us, and [CREDITS.md](https://github.com/Fueled/ai-provider-for-ollama/blob/develop/CREDITS.md) for a listing of maintainers, contributors, and libraries for AI Provider for Ollama.

## Like what you see?

[![Work with the 10up WordPress Practice at Fueled](https://github.com/10up/.github/blob/trunk/profile/10up-github-banner.jpg)](http://10up.com/contact/)

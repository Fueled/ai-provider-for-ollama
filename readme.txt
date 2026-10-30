=== AI Provider for Ollama ===
Contributors:      fueled, 10up, dkotter, jeffpaul
Tags:              ai, ollama, llm, local-ai, connector
Requires at least: 7.0
Tested up to:      7.1
Stable tag:        1.4.0
Requires PHP:      8.2
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Ollama provider for the WordPress AI Client.

== Description ==

This plugin provides [Ollama](https://ollama.com/) integration for the WordPress AI Client. It lets WordPress sites use large language models running locally or on a remote Ollama instance for text, image, embedding, and speech generation and other AI capabilities.

Ollama exposes an [OpenAI-compatible API](https://ollama.com/blog/openai-compatibility), and this provider uses that API to communicate with any model you have pulled into Ollama (Llama, Mistral, Gemma, Phi, and many more).

**Features:**

* Text generation with any Ollama model
* Image generation with supported models
* Embedding generation with any embedding-capable Ollama model
* Text to speech with speech models such as phonon (Ollama 0.40.9+, Apple Silicon only)
* Typed decisions (choice, yes/no probability, and score) with local decision models such as nimble and tev1
* Automatic model discovery from your Ollama instance
* Function calling support
* Structured output (JSON mode) support
* Settings page for host URL and seeing available models
* Works without an API key for local instances

**Requirements:**

* PHP 8.2 or higher
* WordPress 7.0 or higher
* Ollama running locally or on a remote server (like Ollama Cloud)

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/ai-provider-for-ollama/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **Settings > Ollama** to configure the host URL and see available models.

== Frequently Asked Questions ==

= How do I install Ollama? =

Visit [ollama.com](https://ollama.com/) to download and install Ollama for your platform. Once installed, pull a model (example `ollama pull llama3.2`) and the provider will automatically discover it.

= Do I need an API key? =

No. For local Ollama instances, no API key is needed. The plugin automatically handles authentication for local setups.

For remote Ollama instances that require authentication, enter the API key in the **Settings > Connectors** screen. If using Ollama Cloud, you also need to set your Ollama host URL in the **Settings > Ollama** screen to `https://ollama.com`.

= How do I change the Ollama host URL? =

By default, the provider connects to `http://localhost:11434`. You can change this in two ways:

1. Set the `OLLAMA_HOST` environment variable (takes precedence).
2. Go to **Settings > Ollama** in the WordPress admin and enter your host URL.

= Where do I report security bugs found in this plugin? =

Please report security bugs found in the source code of the AI Provider for Ollama plugin through the [Patchstack Vulnerability Disclosure  Program](https://patchstack.com/database/vdp/b715f2ba-e67f-437c-9398-cb523702a7ad). The Patchstack team will assist you with verification, CVE assignment, and notify the developers of this plugin.

== Screenshots ==

1. Settings > Ollama screen showing available AI models and Host URL configuration.

== Changelog ==

= 1.4.0 - 2026-10-30 =

**Added**

- Support for text-to-speech with Ollama speech models such as `phonon`, including voice selection (requires Ollama 0.40.9+) ([#115](https://github.com/Fueled/ai-provider-for-ollama/pull/115)).
- A refresh models button on the settings page ([#102](https://github.com/Fueled/ai-provider-for-ollama/pull/102)).

**Changed**

- Refresh the cached model list when the host or API key changes ([#102](https://github.com/Fueled/ai-provider-for-ollama/pull/102)).

= 1.3.0 - 2026-10-08 =

**Added**

- Support for Jev-style decision models ([#103](https://github.com/Fueled/ai-provider-for-ollama/pull/103)).
- `ai_provider_for_ollama_request_timeout` and `ai_provider_for_ollama_connect_timeout` filters to override Ollama request and connect timeouts ([#89](https://github.com/Fueled/ai-provider-for-ollama/pull/89)).

**Changed**

- Optimized Ollama model discovery and availability checks by caching model details, reducing redundant `/api/show` requests, and adding separate timeouts for discovery requests ([#100](https://github.com/Fueled/ai-provider-for-ollama/pull/100)).

**Fixed**

- Ensure we only declare function calling support on models that advertise support for tools ([#94](https://github.com/Fueled/ai-provider-for-ollama/pull/94)).

= 1.2.0 - 2026-08-18 =

**Added**

- Support for generating embeddings (props [@dkotter](https://github.com/dkotter) via [#69](https://github.com/Fueled/ai-provider-for-ollama/pull/69)).

**Changed**

- Bumped WordPress tested-up-to version 7.1 (props [@jeffpaul](https://github.com/jeffpaul), [@dkotter](https://github.com/dkotter) via [#81](https://github.com/Fueled/ai-provider-for-ollama/pull/81)).

[View historical changelog details here](https://github.com/Fueled/ai-provider-for-ollama/blob/develop/CHANGELOG.md).

== Upgrade Notice ==

= 1.0.0 =

Initial release.

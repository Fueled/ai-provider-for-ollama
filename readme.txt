=== AI Provider for Ollama ===
Contributors:      fueled, 10up
Tags:              ai, ollama, llm, local-ai, connector
Requires at least: 7.0
Tested up to:      7.0
Stable tag:        1.0.3
Requires PHP:      7.4
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Ollama provider for the WordPress AI Client.

== Description ==

This plugin provides [Ollama](https://ollama.com/) integration for the WordPress AI Client. It lets WordPress sites use large language models running locally or on a remote Ollama instance for text generation and other AI capabilities.

Ollama exposes an [OpenAI-compatible API](https://ollama.com/blog/openai-compatibility), and this provider uses that API to communicate with any model you have pulled into Ollama (Llama, Mistral, Gemma, Phi, and many more).

**Features:**

* Text generation with any Ollama model
* Automatic model discovery from your Ollama instance
* Function calling support
* Structured output (JSON mode) support
* Settings page for host URL and seeing available models
* Works without an API key for local instances

**Requirements:**

* PHP 7.4 or higher
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

== Changelog ==

= 1.0.3 - 2026-03-25 =

* **Changed:** Removed AI Client dependency FAQ entry (props [@raftaar1191](https://github.com/raftaar1191) via [#29](https://github.com/Fueled/ai-provider-for-ollama/pull/29)).
* **Fixed:** Ensure the vendor directory ends up in our final release (props [@soderlind](https://github.com/soderlind), [@dkotter](https://github.com/dkotter) via [#31](https://github.com/Fueled/ai-provider-for-ollama/pull/31)).

= 1.0.2 - 2026-03-23 =

* **Changed:** Updated plugin display name and slug per WPORG feedback (props [@dkotter](https://github.com/dkotter), [@jeffpaul](https://github.com/jeffpaul) via [#25](https://github.com/Fueled/ai-provider-for-ollama/pull/25)).

= 1.0.1 - 2026-03-20 =

* **Added:** Support for the provider description and logo path (props [@jeffpaul](https://github.com/jeffpaul), [@dkotter](https://github.com/dkotter) via [#13](https://github.com/Fueled/ai-provider-for-ollama/pull/13)).
* **Changed:** Display name and slug to meet WPORG Plugin team requirements (props [@jeffpaul](https://github.com/jeffpaul), [@dkotter](https://github.com/dkotter) via [#22](https://github.com/Fueled/ai-provider-for-ollama/pull/22)).
* **Changed:** Update menu name from Ollama Settings to Ollama (props [@jeffpaul](https://github.com/jeffpaul), [@dkotter](https://github.com/dkotter) via [#19](https://github.com/Fueled/ai-provider-for-ollama/pull/19)).
* **Fixed:** Ensure we properly check if the provider is connected rather than defaulting to always showing as connected (props [@raftaar1191](https://github.com/raftaar1191), [@dkotter](https://github.com/dkotter) via [#17](https://github.com/Fueled/ai-provider-for-ollama/pull/17)).

= 1.0.0 - 2026-03-05 =

* Initial release
* Text generation with Ollama models via the OpenAI-compatible API
* Automatic model discovery from the Ollama instance
* Settings page for host URL and default model
* Function calling and structured output support

== Upgrade Notice ==

= 1.0.0 =

Initial release.

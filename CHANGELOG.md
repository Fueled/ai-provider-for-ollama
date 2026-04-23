# Changelog

All notable changes to this project will be documented in this file, per [the Keep a Changelog standard](http://keepachangelog.com/), and will adhere to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased] - TBD

## [1.1.0] - 2026-04-23
### Added
- Support for image generation when using compatible models (props [@milindmore22](https://github.com/milindmore22), [@dkotter](https://github.com/dkotter) via [#30](https://github.com/Fueled/ai-provider-for-ollama/pull/30)).
- Integrate with the `wpai_has_ai_credentials` filter to ensure the AI plugin sees Ollama as a valid, connected provider (props [@dkotter](https://github.com/dkotter), [@jeffpaul](https://github.com/jeffpaul) via [#43](https://github.com/Fueled/ai-provider-for-ollama/pull/43)).
- Show the capabilities of each model next to the model name on our settings page (props [@dkotter](https://github.com/dkotter), [@jeffpaul](https://github.com/jeffpaul) via [#51](https://github.com/Fueled/ai-provider-for-ollama/pull/51)).

### Changed
- Increase the standard timeout to be 60 seconds for text generation (props [@dkotter](https://github.com/dkotter), [@jeffpaul](https://github.com/jeffpaul) via [#49](https://github.com/Fueled/ai-provider-for-ollama/pull/49)).

### Fixed
- Properly parse structured outputs (props [@dkotter](https://github.com/dkotter), [@jeffpaul](https://github.com/jeffpaul) via [#49](https://github.com/Fueled/ai-provider-for-ollama/pull/49)).

### Developer
- Update readmes to ensure accuracy (props [@juanmaguitar](https://github.com/juanmaguitar), [@dkotter](https://github.com/dkotter), [@jeffpaul](https://github.com/jeffpaul) via [#37](https://github.com/Fueled/ai-provider-for-ollama/pull/37)).
- Add WP version checker action (props [@jeffpaul](https://github.com/jeffpaul), [@dkotter](https://github.com/dkotter) via [#44](https://github.com/Fueled/ai-provider-for-ollama/pull/44)).
- Added WPORG readme/asset updater action (props [@jeffpaul](https://github.com/jeffpaul), [@dkotter](https://github.com/dkotter) via [#45](https://github.com/Fueled/ai-provider-for-ollama/pull/45)).
- Bump `picomatch` from 2.3.1 to 2.3.2 and from 4.0.3 to 4.0.4 (props [@dependabot[bot]](https://github.com/apps/dependabot), [@dkotter](https://github.com/dkotter) via [#34](https://github.com/Fueled/ai-provider-for-ollama/pull/34)).
- Bump `lodash-es` from 4.17.23 to 4.18.1 (props [@dependabot[bot]](https://github.com/apps/dependabot), [@dkotter](https://github.com/dkotter) via [#38](https://github.com/Fueled/ai-provider-for-ollama/pull/38)).
- Bump `lodash` from 4.17.23 to 4.18.1 (props [@dependabot[bot]](https://github.com/apps/dependabot), [@dkotter](https://github.com/dkotter) via [#39](https://github.com/Fueled/ai-provider-for-ollama/pull/39)).
- Bump `basic-ftp` from 5.2.0 to 5.2.2 (props [@dependabot[bot]](https://github.com/apps/dependabot), [@dkotter](https://github.com/dkotter) via [#40](https://github.com/Fueled/ai-provider-for-ollama/pull/40), [#41](https://github.com/Fueled/ai-provider-for-ollama/pull/41)).
- Bump `axios` from 1.13.5 to 1.15.0 (props [@dependabot[bot]](https://github.com/apps/dependabot), [@dkotter](https://github.com/dkotter) via [#42](https://github.com/Fueled/ai-provider-for-ollama/pull/42)).
- Bump `follow-redirects` from 1.15.11 to 1.16.0 (props [@dependabot[bot]](https://github.com/apps/dependabot), [@dkotter](https://github.com/dkotter) via [#46](https://github.com/Fueled/ai-provider-for-ollama/pull/46)).
- Bump `fast-xml-parser` from 5.5.7 to 5.7.1 (props [@dependabot[bot]](https://github.com/apps/dependabot), [@dkotter](https://github.com/dkotter) via [#52](https://github.com/Fueled/ai-provider-for-ollama/pull/52)).

## [1.0.3] - 2026-03-25
### Changed
- Removed AI Client dependency FAQ entry (props [@raftaar1191](https://github.com/raftaar1191) via [#29](https://github.com/Fueled/ai-provider-for-ollama/pull/29)).

### Fixed
- Ensure the vendor directory ends up in our final release (props [@soderlind](https://github.com/soderlind), [@dkotter](https://github.com/dkotter) via [#31](https://github.com/Fueled/ai-provider-for-ollama/pull/31)).

## [1.0.2] - 2026-03-23
### Changed
- Updated plugin display name and slug per WPORG feedback (props [@dkotter](https://github.com/dkotter), [@jeffpaul](https://github.com/jeffpaul) via [#25](https://github.com/Fueled/ai-provider-for-ollama/pull/25)).

## [1.0.1] - 2026-03-20
### Added
- Support for the provider description and logo path (props [@jeffpaul](https://github.com/jeffpaul), [@dkotter](https://github.com/dkotter) via [#13](https://github.com/Fueled/ai-provider-for-ollama/pull/13)).

### Changed
- Display name and slug to meet WPORG Plugin team requirements (props [@jeffpaul](https://github.com/jeffpaul), [@dkotter](https://github.com/dkotter) via [#22](https://github.com/Fueled/ai-provider-for-ollama/pull/22)).
- Update menu name from Ollama Settings to Ollama (props [@jeffpaul](https://github.com/jeffpaul), [@dkotter](https://github.com/dkotter) via [#19](https://github.com/Fueled/ai-provider-for-ollama/pull/19)).

### Fixed
- Ensure we properly check if the provider is connected rather than defaulting to always showing as connected (props [@raftaar1191](https://github.com/raftaar1191), [@dkotter](https://github.com/dkotter) via [#17](https://github.com/Fueled/ai-provider-for-ollama/pull/17)).

### Developer
- Bump `svgo` from 3.3.2 to 3.3.3 (props [@dependabot[bot]](https://github.com/apps/dependabot), [@dkotter](https://github.com/dkotter) via [#11](https://github.com/Fueled/ai-provider-for-ollama/pull/11)).
- Bump `simple-git` from 3.31.1 to 3.33.0 (props [@dependabot[bot]](https://github.com/apps/dependabot), [@dkotter](https://github.com/dkotter) via [#12](https://github.com/Fueled/ai-provider-for-ollama/pull/12)).
- Bump `fast-xml-parser` from 5.4.2 to 5.5.7 (props [@dependabot[bot]](https://github.com/apps/dependabot), [@dkotter](https://github.com/dkotter) via [#16](https://github.com/Fueled/ai-provider-for-ollama/pull/16), [#20](https://github.com/Fueled/ai-provider-for-ollama/pull/20)).
- Bump `flatted` from 3.3.3 to 3.4.2 (props [@dependabot[bot]](https://github.com/apps/dependabot), [@dkotter](https://github.com/dkotter) via [#21](https://github.com/Fueled/ai-provider-for-ollama/pull/21)).

## [1.0.0] - 2026-03-05
First public release of the AI Provider for Ollama plugin. 🎉

### Added
- Initial release
- Text generation with Ollama models via the OpenAI-compatible API
- Automatic model discovery from the Ollama instance
- Settings page for host URL and default model
- Function calling and structured output support

[Unreleased]: https://github.com/Fueled/ai-provider-for-ollama/compare/main...develop
[1.1.0]: https://github.com/Fueled/ai-provider-for-ollama/compare/1.0.3...1.1.0
[1.0.3]: https://github.com/Fueled/ai-provider-for-ollama/compare/1.0.2...1.0.3
[1.0.2]: https://github.com/Fueled/ai-provider-for-ollama/compare/1.0.1...1.0.2
[1.0.1]: https://github.com/Fueled/ai-provider-for-ollama/compare/1.0.0...1.0.1
[1.0.0]: https://github.com/Fueled/ai-provider-for-ollama/tree/1.0.0

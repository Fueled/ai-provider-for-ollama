# Changelog

All notable changes to this project will be documented in this file, per [the Keep a Changelog standard](http://keepachangelog.com/), and will adhere to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased] - TBD

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
[1.0.2]: https://github.com/Fueled/ai-provider-for-ollama/compare/1.0.1...1.0.2
[1.0.1]: https://github.com/Fueled/ai-provider-for-ollama/compare/1.0.0...1.0.1
[1.0.0]: https://github.com/Fueled/ai-provider-for-ollama/tree/1.0.0

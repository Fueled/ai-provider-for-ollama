# Changelog

All notable changes to this project will be documented in this file, per [the Keep a Changelog standard](http://keepachangelog.com/), and will adhere to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased] - TBD

## [1.3.0] - 2026-10-13
### Added
- Support for Jev-style decision models ([#103](https://github.com/Fueled/ai-provider-for-ollama/pull/103)).
- `ai_provider_for_ollama_request_timeout` and `ai_provider_for_ollama_connect_timeout` filters to override Ollama request and connect timeouts ([#89](https://github.com/Fueled/ai-provider-for-ollama/pull/89)).

### Changed
- Optimized Ollama model discovery and availability checks by caching model details, reducing redundant `/api/show` requests, and adding separate timeouts for discovery requests ([#100](https://github.com/Fueled/ai-provider-for-ollama/pull/100)).

### Fixed
- Ensure we only declare function calling support on models that advertise support for tools ([#94](https://github.com/Fueled/ai-provider-for-ollama/pull/94)).

### Developer
- Add WordPress Playground blueprint file ([#88](https://github.com/Fueled/ai-provider-for-ollama/pull/88)).
- Add Patchstack security reporting item to FAQ ([#107](https://github.com/Fueled/ai-provider-for-ollama/pull/107)).
- Add unit test coverage for `DecisionResult` and `OllamaModelDetailsCache` ([#108](https://github.com/Fueled/ai-provider-for-ollama/pull/108)).
- Bump `adm-zip` from 0.5.16 to 0.6.1 ([#90](https://github.com/Fueled/ai-provider-for-ollama/pull/90), [#99](https://github.com/Fueled/ai-provider-for-ollama/pull/99)).
- Bump `brace-expansion` from 1.1.18 to 1.1.21 ([#106](https://github.com/Fueled/ai-provider-for-ollama/pull/106)).
- Bump `browserslist` from 4.28.1 to 4.28.8 ([#91](https://github.com/Fueled/ai-provider-for-ollama/pull/91)).
- Bump `fast-uri` from 3.1.5 to 3.1.7 ([#92](https://github.com/Fueled/ai-provider-for-ollama/pull/92)).
- Bump `http-cache-semantics` from 4.2.0 to 4.3.0 ([#106](https://github.com/Fueled/ai-provider-for-ollama/pull/106)).
- Bump `http-proxy-middleware` from 2.0.9 to 2.0.10 ([#96](https://github.com/Fueled/ai-provider-for-ollama/pull/96)).
- Bump `js-yaml` from 3.14.2 to 3.15.2 ([#96](https://github.com/Fueled/ai-provider-for-ollama/pull/96)).
- Bump `linkify-it` from 3.0.3 to 5.0.2 ([#104](https://github.com/Fueled/ai-provider-for-ollama/pull/104)).
- Bump `markdown-it` from 12.3.2 to 14.3.2 ([#104](https://github.com/Fueled/ai-provider-for-ollama/pull/104)).
- Bump `minimatch` from 9.0.5 to 9.0.9 ([#104](https://github.com/Fueled/ai-provider-for-ollama/pull/104)).
- Bump `postcss-selector-parser` from 6.1.2 to 6.1.4 and from 7.1.1 to 7.1.5 ([#91](https://github.com/Fueled/ai-provider-for-ollama/pull/91)).
- Bump `svgo` from 3.3.4 to 3.3.5 ([#96](https://github.com/Fueled/ai-provider-for-ollama/pull/96)).
- Removes `extract-zip` ([#97](https://github.com/Fueled/ai-provider-for-ollama/pull/97)).

## [1.2.0] - 2026-08-18
### Added
- Support for generating embeddings (props [@dkotter](https://github.com/dkotter) via [#69](https://github.com/Fueled/ai-provider-for-ollama/pull/69)).

### Changed
- Bumped WordPress tested-up-to version 7.1 (props [@jeffpaul](https://github.com/jeffpaul), [@dkotter](https://github.com/dkotter) via [#81](https://github.com/Fueled/ai-provider-for-ollama/pull/81)).

### Developer
- Bump `tmp` from 0.2.5 to 0.2.7 (props [@dkotter](https://github.com/dkotter) via [#65](https://github.com/Fueled/ai-provider-for-ollama/pull/65)).
- Bump `shell-quote` from 1.8.3 to 1.10.0 (props [@dkotter](https://github.com/dkotter) via [#66](https://github.com/Fueled/ai-provider-for-ollama/pull/66), [#73](https://github.com/Fueled/ai-provider-for-ollama/pull/73)).
- Bump `launch-editor` from 2.12.0 to 2.14.1 (props [@dkotter](https://github.com/dkotter) via [#67](https://github.com/Fueled/ai-provider-for-ollama/pull/67)).
- Bump `form-data` from 4.0.5 to 4.0.6 (props [@dkotter](https://github.com/dkotter) via [#68](https://github.com/Fueled/ai-provider-for-ollama/pull/68)).
- Bump `websocket-driver` from 0.7.4 to 0.7.5 (props [@dkotter](https://github.com/dkotter) via [#70](https://github.com/Fueled/ai-provider-for-ollama/pull/70)).
- Bump `@nodable/entities` from 2.1.0 to 3.0.0 (props [@dkotter](https://github.com/dkotter) via [#71](https://github.com/Fueled/ai-provider-for-ollama/pull/71)).
- Bump `axios` from 1.16.0 to 1.18.1 (props [@dkotter](https://github.com/dkotter) via [#72](https://github.com/Fueled/ai-provider-for-ollama/pull/72)).
- Bump `immutable` from 5.1.5 to 5.1.9 (props [@dkotter](https://github.com/dkotter) via [#74](https://github.com/Fueled/ai-provider-for-ollama/pull/74)).
- Bump `svgo` from 3.3.3 to 3.3.4 (props [@dkotter](https://github.com/dkotter) via [#75](https://github.com/Fueled/ai-provider-for-ollama/pull/75)).
- Bump `fast-uri` from 3.1.3 to 3.1.5 (props [@dkotter](https://github.com/dkotter) via [#76](https://github.com/Fueled/ai-provider-for-ollama/pull/76)).
- Bump `postcss` from 8.5.14 to 8.5.25 (props [@dkotter](https://github.com/dkotter) via [#76](https://github.com/Fueled/ai-provider-for-ollama/pull/76)).
- Bump `brace-expansion` from 1.1.12 to 1.1.18 (props [@dkotter](https://github.com/dkotter) via [#77](https://github.com/Fueled/ai-provider-for-ollama/pull/77)).
- Bump `ip-address` from 10.2.0 to 10.4.0 (props [@dkotter](https://github.com/dkotter) via [#78](https://github.com/Fueled/ai-provider-for-ollama/pull/78)).
- Bump `wp-coding-standards/wpcs` from 3.3.0 to 3.4.1 (props [@dkotter](https://github.com/dkotter) via [#79](https://github.com/Fueled/ai-provider-for-ollama/pull/79)).
- Bump `squizlabs/php_codesniffer` from 3.13.5 to 3.13.6 (props [@dkotter](https://github.com/dkotter) via [#79](https://github.com/Fueled/ai-provider-for-ollama/pull/79)).

## [1.1.1] - 2026-05-14
### Added
- Ensure the AI plugin sees Ollama as a valid, connected provider within the status dashboard widget (props [@dkotter](https://github.com/dkotter), [@jeffpaul](https://github.com/jeffpaul) via [#55](https://github.com/Fueled/ai-provider-for-ollama/pull/55)).

### Fixed
- More robust path inclusion for the logo (props [@dkotter](https://github.com/dkotter), [ABCdatos](https://profiles.wordpress.org/abcdatos/) via [#61](https://github.com/Fueled/ai-provider-for-ollama/pull/61)).

### Developer
- Bump `ip-address` from 10.1.0 to 10.2.0 (props [@dependabot[bot]](https://github.com/apps/dependabot), [@dkotter](https://github.com/dkotter) via [#57](https://github.com/Fueled/ai-provider-for-ollama/pull/57)).
- Bump `axios` from 1.15.0 to 1.16.0 (props [@dependabot[bot]](https://github.com/apps/dependabot), [@dkotter](https://github.com/dkotter) via [#58](https://github.com/Fueled/ai-provider-for-ollama/pull/58)).
- Bump `postcss` from 8.5.6 to 8.5.14 (props [@dependabot[bot]](https://github.com/apps/dependabot), [@dkotter](https://github.com/dkotter) via [#58](https://github.com/Fueled/ai-provider-for-ollama/pull/58)).
- Bump `simple-git` from 3.33.0 to 3.36.0 (props [@dependabot[bot]](https://github.com/apps/dependabot), [@dkotter](https://github.com/dkotter) via [#58](https://github.com/Fueled/ai-provider-for-ollama/pull/58)).
- Bump `fast-xml-builder` from 1.1.5 to 1.2.0 (props [@dependabot[bot]](https://github.com/apps/dependabot), [@dkotter](https://github.com/dkotter) via [#59](https://github.com/Fueled/ai-provider-for-ollama/pull/59)).
- Bump `@babel/plugin-transform-modules-systemjs` from 7.29.0 to 7.29.4 (props [@dependabot[bot]](https://github.com/apps/dependabot), [@dkotter](https://github.com/dkotter) via [#60](https://github.com/Fueled/ai-provider-for-ollama/pull/60)).

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
[1.3.0]: https://github.com/Fueled/ai-provider-for-ollama/compare/1.2.0...1.3.0
[1.2.0]: https://github.com/Fueled/ai-provider-for-ollama/compare/1.1.1...1.2.0
[1.1.1]: https://github.com/Fueled/ai-provider-for-ollama/compare/1.1.0...1.1.1
[1.1.0]: https://github.com/Fueled/ai-provider-for-ollama/compare/1.0.3...1.1.0
[1.0.3]: https://github.com/Fueled/ai-provider-for-ollama/compare/1.0.2...1.0.3
[1.0.2]: https://github.com/Fueled/ai-provider-for-ollama/compare/1.0.1...1.0.2
[1.0.1]: https://github.com/Fueled/ai-provider-for-ollama/compare/1.0.0...1.0.1
[1.0.0]: https://github.com/Fueled/ai-provider-for-ollama/tree/1.0.0

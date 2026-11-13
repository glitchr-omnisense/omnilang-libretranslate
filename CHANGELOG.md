# Changelog

## [Unreleased]

## [1.0.0] - 2026-11-13

The first stable version. It requires `omnisense/contracts` `^1.0`, `omnisense/omnilang` `^1.0` with no stability flag, so `composer require omnilang/libretranslate` installs it in a project with Composer's default minimum-stability; the 1.x series follows semantic versioning. A tag `vX.Y.Z` is released by `.github/workflows/release.yml` once the package's CI passes on it.

- 2026-11-10: the provider written, on omnisense/omnilang and omnisense/contracts: /translate
  (JSON, a list of texts, html or text, auto), /detect (confidence brought to 0-1), /languages
  (targets gathered), /translate_file and the file downloaded from translatedFileUrl. No key by
  default; one by reference in the body when the server asks. policy.json: a self-hosted
  server's - limits the operator's, commercial use allowed, no attribution. Tests on MockHttp over
  answers written from the API spec (1.9.6) and the server's source, each asserting the exact
  request; no live call (no public server allows it); PolicyTest, BareTest; PHPStan at max,
  baseline empty.

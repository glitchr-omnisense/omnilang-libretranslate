---
title: LibreTranslate
order: 0
---

# omnilang/libretranslate

Read on 2026-11-10: the LibreTranslate repository and its docs (docs.libretranslate.com: API usage,
installation and its arguments, API keys, FAQ, mirrors, the translate and translate_file
operations), its API spec (`libretranslate.com/spec`, 1.9.6), its source (`libretranslate/app.py`:
a list of texts answers a list of `detectedLanguage`; argos-translate-files'
`get_supported_formats()`), the portal's pricing and privacy pages.

## Options

| Option | Default | |
|---|---|---|
| `base_uri` | required | the site's server |
| `api_key` | none | by reference; sent in the body (`api_key`) when given |
| `files` | true | false: no documents |
| `timeout` | 60 s | |

## Calls

| Method | Request | Read |
|---|---|---|
| `translate()` | `POST /translate`, JSON: `q[]`, `source` (`auto` when none), `target`, `format` (`text`, `html`), `api_key` | `translatedText[]`, `detectedLanguage[]` (or one object) `.language` |
| `languages()` | `GET /languages` | `code`, `name`, `targets[]` (a language is a target when some source lists it) |
| `detect()` | `POST /detect` per text: `q`, `api_key` | `[]`: `language`, `confidence` (0-100) |
| `translateDocument()` | `POST /translate_file`, multipart: `file`, `source`, `target`, `api_key`; then `GET` its `translatedFileUrl` | the file |

The server's errors: 400, 403 (banned), 429 ("Slow down": honoured), 500, each `{"error": "…"}`.

## Not verified

- A server's `charLimit` and `supportedFilesFormat` (`/frontend/settings`) are its own: a request
  over its character limit is refused by the server, and the file types listed are
  argos-translate-files' default.
- `/download_file` is not in the spec; the address `translatedFileUrl` gives is followed as it is.

# omnilang/libretranslate

[omnisense/omnilang](https://github.com/glitchr-omnisense/omnilang)'s
[LibreTranslate](https://github.com/LibreTranslate/LibreTranslate), under any PHP host: a server the
site runs itself (AGPL-3.0, Argos Translate's models), no key by default.

- **translate** (`/translate`): several texts a request, plain or HTML, `auto` for a source to
  detect; the languages (`/languages`);
- **detect** (`/detect`): a language, its confidence (brought from 0-100 to 0-1), the alternatives;
- **documents** (`/translate_file`, then the address it gives): txt, odt, odp, docx, pptx, epub,
  html, srt, pdf.

No glossary, no formality.

```php
use Omnilang\Libretranslate\LibretranslateFactory;
use Omnilang\Model\TranslationQuery;

$local = (new LibretranslateFactory($httpClient))->create(['base_uri' => 'http://libretranslate:5000']);
echo $local->translate(TranslationQuery::text('Bonjour', 'en'))[0]->text;
```

```yaml
# a server beside the site (docker compose)
libretranslate:
    image: libretranslate/libretranslate
    environment: { LT_LOAD_ONLY: 'en,fr,ja' }
```

Options: `base_uri` (required: the site's server), `api_key` (by reference, for a server started
with `--api-keys`, libretranslate.com's paid plans among them), `files` (false for a server started
with `--disable-files-translation`), `timeout`; `policy` (write the server's `--req-limit` there),
`usage`.

**Policy** ([policy.json](policy.json), read on 2026-11-10): software, not a service: no limit
but the operator's, commercial use allowed, no attribution, the operator keeps or not what it
receives. The public libretranslate.com needs a paid key; no public mirror says it may be called by
a program, so the tests make no live call.

See [docs/index.md](docs/index.md). MIT licence.

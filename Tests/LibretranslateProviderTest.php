<?php

namespace Omnilang\Libretranslate\Tests;

use Omnilang\Exception\InvalidConfigException;
use Omnilang\Exception\NotSupportedException;
use Omnilang\Exception\UsageLimitReachedException;
use Omnilang\Libretranslate\LibretranslateFactory;
use Omnilang\Libretranslate\LibretranslateProvider;
use Omnilang\Model\Document;
use Omnilang\Model\DocumentQuery;
use Omnilang\Model\TextFormat;
use Omnilang\Model\TranslationQuery;
use Omnilang\Testing\MockHttp;
use PHPUnit\Framework\TestCase;

/**
 * LibreTranslate's answers written from its API spec (libretranslate.com/spec,
 * 1.9.6) and its source (app.py: a list of texts gives a list of detected
 * languages), read on 2026-11-10. No live call: no public server says it
 * may be called by a program without a key. Every test checks the exact
 * request.
 */
final class LibretranslateProviderTest extends TestCase
{
    private const BASE = 'http://libretranslate:5000';
    private const FIXTURES = __DIR__.'/Fixtures/';

    public function testTextsTranslatedWithNoKey(): void
    {
        $http = self::http();
        $translations = self::provider($http)->translate(TranslationQuery::text(['Chambre double vue jardin', 'Petit-déjeuner compris'], 'en'));

        self::assertSame(['Double room with garden view', 'Breakfast included'], array_map('strval', $translations));
        self::assertSame(['fr', 'libretranslate', 22], [$translations[1]->detected?->code, $translations[1]->provider, $translations[1]->characters]);
        self::assertSame(['POST '.self::BASE.'/translate'], $http->lines());
        self::assertSame(['q' => ['Chambre double vue jardin', 'Petit-déjeuner compris'], 'source' => 'auto', 'target' => 'en', 'format' => 'text'], $http->json(), 'no key: none sent');
    }

    public function testHtmlFromAGivenLanguageWithAKey(): void
    {
        $http = self::http();
        $translation = self::provider($http, ['api_key' => 'not-a-real-lt-key'])->translate(new TranslationQuery('<p>Merci</p>', 'ja', 'fr', TextFormat::Html))[0];

        self::assertSame(['<p>ありがとう</p>', 'fr', null], [$translation->text, $translation->sourceLanguage()?->code, $translation->detected]);
        self::assertSame(['q' => ['<p>Merci</p>'], 'source' => 'fr', 'target' => 'ja', 'format' => 'html', 'api_key' => 'not-a-real-lt-key'], $http->json());

        $this->expectException(NotSupportedException::class);
        self::provider($http)->translate(new TranslationQuery('Bonjour', 'en', 'fr', glossaries: 'g'));
    }

    public function testLanguagesAndDetection(): void
    {
        $http = self::http();
        $provider = self::provider($http);
        $languages = $provider->languages();

        self::assertSame([['en', true], ['fr', true], ['ja', true], ['eo', false]], array_map(static fn ($l) => [$l->language->code, $l->target], $languages), 'a target is a language some source translates into');
        $detection = $provider->detect(['Guten Morgen'])[0];
        self::assertSame(['de', 0.9, 'nl', 0.1], [$detection->language->code, $detection->confidence, $detection->alternatives[0]->language->code, $detection->alternatives[0]->confidence], 'a confidence out of 100 brought to 0-1');
        self::assertSame(['GET '.self::BASE.'/languages', 'POST '.self::BASE.'/detect'], $http->lines());
        self::assertSame(['q' => 'Guten Morgen'], $http->json());
    }

    public function testAFileTranslatedThenDownloaded(): void
    {
        $http = self::http();
        $job = self::provider($http)->translateDocument(new DocumentQuery(new Document("Bonjour\n", 'note.txt'), 'en', 'fr'));

        self::assertSame([true, "Hello\n", 'note.en.txt'], [$job->isDone(), $job->result?->content, $job->result?->filename]);
        self::assertSame(['POST '.self::BASE.'/translate_file', 'GET '.self::BASE.'/download_file/0d7a.note.txt'], $http->lines());
        $form = $http->form(0);
        self::assertSame(['source', 'target', 'file'], array_keys($form));
        self::assertSame(['fr', 'en', "Bonjour\n", 'note.txt'], [$form['source']['value'], $form['target']['value'], $form['file']['value'], $form['file']['filename']]);
        self::assertFalse(self::provider($http, ['files' => false])->capabilities()->documents);
    }

    public function testTheServersOwnLimitIsHonoured(): void
    {
        $http = MockHttp::routes(['~/translate$~' => [429, '{"error": "Too many request limits violations"}', ['Retry-After' => '60']]]);

        try {
            self::provider($http)->translate(TranslationQuery::text('Bonjour', 'en'));
            self::fail('A 429 is a limit reached.');
        } catch (UsageLimitReachedException $e) {
            self::assertSame(60, $e->retryAfter);
        }

        $this->expectException(InvalidConfigException::class);
        (new LibretranslateFactory($http->client))->create([]);
    }

    private static function http(): MockHttp
    {
        return MockHttp::routes([
            '~^POST http://libretranslate:5000/translate$~' => static fn (string $method, string $url, string $body): array => str_contains($body, '"html"')
                ? [200, '{"translatedText": ["<p>ありがとう</p>"]}']
                : [200, '@'.self::FIXTURES.'translate.json'],
            '~^GET http://libretranslate:5000/languages$~' => [200, '@'.self::FIXTURES.'languages.json'],
            '~^POST http://libretranslate:5000/detect$~' => [200, '[{"confidence": 90.0, "language": "de"}, {"confidence": 10.0, "language": "nl"}]'],
            '~^POST http://libretranslate:5000/translate_file$~' => [200, '{"translatedFileUrl": "http://libretranslate:5000/download_file/0d7a.note.txt"}'],
            '~^GET http://libretranslate:5000/download_file/~' => [200, "Hello\n", ['Content-Type' => 'text/plain']],
        ]);
    }

    /** @param array<string, mixed> $options */
    private static function provider(MockHttp $http, array $options = []): LibretranslateProvider
    {
        $provider = (new LibretranslateFactory($http->client))->create($options + ['base_uri' => self::BASE]);
        self::assertInstanceOf(LibretranslateProvider::class, $provider);

        return $provider;
    }
}

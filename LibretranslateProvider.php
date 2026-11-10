<?php

namespace Omnilang\Libretranslate;

use Omnilang\Capabilities;
use Omnilang\DetectorInterface;
use Omnilang\DocumentsInterface;
use Omnilang\Exception\NotSupportedException;
use Omnilang\Exception\ProviderException;
use Omnilang\Http\Data;
use Omnilang\Http\JsonClient;
use Omnilang\Http\Multipart;
use Omnilang\Model\Detection;
use Omnilang\Model\Document;
use Omnilang\Model\DocumentJob;
use Omnilang\Model\DocumentQuery;
use Omnilang\Model\DocumentStatus;
use Omnilang\Model\Language;
use Omnilang\Model\SupportedLanguage;
use Omnilang\Model\Translation;
use Omnilang\Model\TranslationQuery;
use Omnilang\TranslatorInterface;
use Omnisense\Contracts\Policy\DeclaresUsagePolicyInterface;
use Omnisense\Contracts\Policy\UsagePolicy;

/**
 * A LibreTranslate server (AGPL-3.0, its API spec 1.9.6 and its source,
 * read on 2026-11-10), self-hosted: /translate (JSON; several texts in one
 * request, plain or HTML, "auto" for a source to detect), /detect (one
 * text a request, a confidence out of 100), /languages, /translate_file
 * (multipart; the translated file then downloaded from the address it
 * gives). No glossary, no formality. The api_key, when the server asks
 * for one, goes in the body of every request.
 */
final class LibretranslateProvider implements TranslatorInterface, DetectorInterface, DocumentsInterface, DeclaresUsagePolicyInterface
{
    public const POLICY = __DIR__.'/policy.json';

    /** The extensions argos-translate-files reads (get_supported_formats(): Txt, Odt, Odp, Docx, Pptx, Epub, Html, Srt, Pdf). */
    public const DOCUMENT_TYPES = ['txt', 'odt', 'odp', 'docx', 'pptx', 'epub', 'html', 'srt', 'pdf'];

    public function __construct(
        private readonly JsonClient $json,
        private readonly string $baseUri,
        #[\SensitiveParameter]
        private readonly ?string $apiKey = null,
        private readonly bool $files = true,
    ) {
    }

    public function getName(): string
    {
        return 'libretranslate';
    }

    public function getTitle(): string
    {
        return 'LibreTranslate';
    }

    public function usagePolicy(): UsagePolicy
    {
        return UsagePolicy::fromFile(self::POLICY);
    }

    public function capabilities(): Capabilities
    {
        return new Capabilities(
            $this->usagePolicy(),
            translate: true,
            detect: true,
            documents: $this->files,
            html: true,
            detectsSource: true,
            documentTypes: $this->files ? self::DOCUMENT_TYPES : [],
            selfHosted: true,
        );
    }

    public function translate(TranslationQuery $query): array
    {
        if (null !== $query->glossaryFor($this->getName())) {
            throw NotSupportedException::call('LibreTranslate', 'apply a glossary');
        }
        $data = $this->json->request('POST', $this->baseUri.'/translate', ['json' => $this->withKey([
            'q' => $query->texts,
            'source' => null === $query->source ? 'auto' : $query->source->code,
            'target' => $query->target->code,
            'format' => $query->isHtml() ? 'html' : 'text',
        ])]) ?? throw ProviderException::answered('LibreTranslate', 'POST /translate', 404);
        $texts = $data->get('translatedText');
        $texts = \is_string($texts) ? [$texts] : (\is_array($texts) ? array_values($texts) : []);
        if (\count($texts) !== \count($query->texts)) {
            throw ProviderException::unexpected('LibreTranslate', 'POST /translate', \sprintf('%d translations for %d texts.', \count($texts), \count($query->texts)));
        }
        $detected = $data->get('detectedLanguage');
        $detected = \is_array($detected) && array_is_list($detected) ? $detected : array_fill(0, \count($texts), $detected);

        return array_map(static fn (int $i) => new Translation(
            \is_string($texts[$i]) ? $texts[$i] : '',
            $query->target,
            'libretranslate',
            $query->source,
            null === $query->source ? Language::tryOf(Data::of($detected[$i] ?? null)->string('language')) : null,
            $query->format,
            mb_strlen($query->texts[$i]),
        ), array_keys($texts));
    }

    public function languages(): array
    {
        $all = $this->json->get($this->baseUri.'/languages')?->items() ?? [];
        $targets = [];
        foreach ($all as $one) {
            foreach ($one->strings('targets') as $target) {
                $targets[$target] = true;
            }
        }
        $languages = [];
        foreach ($all as $one) {
            $code = $one->string('code');
            if (null !== $language = Language::tryOf($code)) {
                $languages[] = new SupportedLanguage($language, $one->string('name'), true, isset($targets[(string) $code]));
            }
        }

        return $languages;
    }

    public function detect(array $texts): array
    {
        return array_map(function (string $text): Detection {
            $found = [];
            foreach ($this->json->request('POST', $this->baseUri.'/detect', ['json' => $this->withKey(['q' => $text])])?->items() ?? [] as $one) {
                $language = Language::tryOf($one->string('language'));
                $confidence = $one->float('confidence');
                if (null !== $language) {
                    $found[] = new Detection($language, 'libretranslate', null === $confidence ? null : round($confidence / 100, 4));
                }
            }

            return [] === $found ? throw ProviderException::unexpected('LibreTranslate', 'POST /detect', 'No language.') : new Detection($found[0]->language, 'libretranslate', $found[0]->confidence, \array_slice($found, 1));
        }, $texts);
    }

    public function translateDocument(DocumentQuery $query): DocumentJob
    {
        if (!$this->files) {
            throw NotSupportedException::call('LibreTranslate', 'translate files on a server started with --disable-files-translation');
        }
        if (null !== $query->glossaryFor($this->getName())) {
            throw NotSupportedException::call('LibreTranslate', 'apply a glossary');
        }
        $form = Multipart::encode(array_filter(['source' => null === $query->source ? 'auto' : $query->source->code, 'target' => $query->target->code, 'api_key' => $this->apiKey]), ['file' => $query->document]);
        $data = $this->json->request('POST', $this->baseUri.'/translate_file', ['body' => $form['body']], $form['headers']) ?? throw ProviderException::answered('LibreTranslate', 'POST /translate_file', 404);
        $url = $data->string('translatedFileUrl') ?? throw ProviderException::unexpected('LibreTranslate', 'POST /translate_file', 'No translatedFileUrl.');
        $file = $this->json->binary('GET', $url) ?? throw ProviderException::answered('LibreTranslate', 'GET /download_file', 404);
        $result = new Document($file['body'], $query->translatedName(), $query->document->mimeType);

        return new DocumentJob('libretranslate-'.substr(hash('sha256', $url), 0, 16), 'libretranslate', DocumentStatus::Done, $query->target, $query->source, filename: $result->filename, result: $result);
    }

    public function documentStatus(DocumentJob $job): DocumentJob
    {
        return $job;
    }

    public function documentResult(DocumentJob $job): Document
    {
        return $job->result ?? throw NotSupportedException::call('LibreTranslate', 'keep a document: /translate_file answers it at once');
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    private function withKey(array $fields): array
    {
        return null === $this->apiKey ? $fields : $fields + ['api_key' => $this->apiKey];
    }
}

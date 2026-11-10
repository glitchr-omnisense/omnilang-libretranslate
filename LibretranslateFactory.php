<?php

namespace Omnilang\Libretranslate;

use Omnilang\Config;
use Omnilang\Exception\InvalidConfigException;
use Omnilang\Http\JsonClient;
use Omnilang\ProviderFactory;
use Omnilang\ProviderInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * "libretranslate": a LibreTranslate server. Options base_uri (required:
 * the site's own server, "http://libretranslate:5000"), api_key (by
 * reference, "env:LIBRETRANSLATE_API_KEY": only for a server started with
 * --api-keys, libretranslate.com's paid plans among them; none by
 * default), files (false: a server started with
 * --disable-files-translation), timeout (seconds); policy (a server's
 * --req-limit, which a site writes here to count it), usage.
 */
final class LibretranslateFactory extends ProviderFactory
{
    public function __construct(private readonly ?HttpClientInterface $http = null)
    {
    }

    protected function populate(Config $c): void
    {
        $c->defaults([
            'omnilang.factory_name' => 'libretranslate',
            'omnilang.factory_title' => 'LibreTranslate',
            'omnilang.required_options' => ['base_uri'],
            'api_key' => null,
            'files' => true,
            'timeout' => null,
        ]);
    }

    protected function build(Config $c): ProviderInterface
    {
        $base = rtrim((string) $c->string('base_uri'), '/');
        if (!preg_match('~^https?://[^/\s]+~', $base)) {
            throw new InvalidConfigException(\sprintf('The "libretranslate" provider needs its server\'s base_uri, such as http://libretranslate:5000, not "%s".', $base));
        }
        $key = $c->secret('api_key');
        $http = $this->http ?? HttpClient::create(['timeout' => 60]);
        if (null !== $timeout = $c->string('timeout')) {
            $http = $http->withOptions(['timeout' => (float) $timeout]);
        }

        return new LibretranslateProvider(new JsonClient($http, 'LibreTranslate', [], $c->backoff(), null === $key ? [] : [$key]), $base, $key, $c->bool('files'));
    }
}

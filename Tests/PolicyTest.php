<?php

namespace Omnilang\Libretranslate\Tests;

use Omnilang\Exception\UsageLimitReachedException;
use Omnilang\Http\Data;
use Omnilang\Libretranslate\LibretranslateFactory;
use Omnilang\Model\TranslationQuery;
use Omnilang\Registry;
use Omnilang\Testing\MockHttp;
use Omnisense\Contracts\Policy\Permission;
use Omnisense\Contracts\Policy\UsagePolicy;
use PHPUnit\Framework\TestCase;

/** A self-hosted server's policy: no limit but the one its operator sets, written in the site's policy option. */
final class PolicyTest extends TestCase
{
    public function testTheDeclaredPolicy(): void
    {
        $policy = self::registry(MockHttp::routes([]))->policy('libretranslate');
        $composer = Data::of(json_decode((string) file_get_contents(__DIR__.'/../composer.json'), true, 512, \JSON_THROW_ON_ERROR));

        self::assertSame('policy.json', $composer->string('extra.omnisense.policy'));
        self::assertEquals(UsagePolicy::fromFile(__DIR__.'/../policy.json'), $policy);
        self::assertSame([[], false, Permission::Allowed, false], [$policy->rateLimits, $policy->requirements->key, $policy->rights->commercial, $policy->rights->attribution->required]);
    }

    public function testTheOperatorsReqLimitWrittenInTheSitesPolicy(): void
    {
        $http = MockHttp::routes(['~/translate$~' => [200, '{"translatedText": ["Hello"]}']]);
        $registry = self::registry($http, ['rateLimits' => [['max' => 2, 'window' => 'minute', 'note' => '--req-limit 2']]]);

        $registry->translator('libretranslate')->translate(TranslationQuery::text('Bonjour', 'en', 'fr'));
        $registry->translator('libretranslate')->translate(TranslationQuery::text('Bonjour', 'en', 'fr'));
        $this->expectException(UsageLimitReachedException::class);
        $registry->translator('libretranslate')->translate(TranslationQuery::text('Bonjour', 'en', 'fr'));
    }

    /** @param array<string, mixed> $policy */
    private static function registry(MockHttp $http, array $policy = []): Registry
    {
        return new Registry([new LibretranslateFactory($http->client)], ['libretranslate' => ['factory' => 'libretranslate', 'options' => ['base_uri' => 'http://libretranslate:5000'], 'policy' => $policy]]);
    }
}

<?php

namespace Omnilang\Libretranslate\Tests;

use Omnilang\Libretranslate\LibretranslateProvider;
use Omnilang\Testing\BareTestCase;

/** omnilang/libretranslate's classes load with omnisense/omnilang, omnisense/contracts and Symfony's HTTP client contracts alone. */
final class BareTest extends BareTestCase
{
    protected static function entryClass(): string
    {
        return LibretranslateProvider::class;
    }

    protected static function requiredPackages(): array
    {
        return ['symfony/http-client-contracts'];
    }
}

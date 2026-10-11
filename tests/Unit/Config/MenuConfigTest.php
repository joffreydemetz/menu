<?php

declare(strict_types=1);

namespace JDZ\Menu\Tests\Unit\Config;

use JDZ\Menu\Config\MenuConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MenuConfigTest extends TestCase
{
    public static function blankSettingsProvider(): array
    {
        return [
            'nothing set' => [[]],
            'everything set to null' => [[
                'publicPath' => null,
                'cacheThumbs' => null,
                'language' => null,
                'rootPath' => null,
                'requestUri' => null,
                'baseUrl' => null,
            ]],
        ];
    }

    #[DataProvider('blankSettingsProvider')]
    public function testBlankSettingsFallBackToTypedDefaults(array $settings): void
    {
        $config = new MenuConfig();
        foreach ($settings as $key => $value) {
            $config->set($key, $value);
        }

        $this->assertSame([
            'publicPath' => '',
            'cacheThumbs' => 0,
            'language' => null,
            'rootPath' => '',
            'requestUri' => '',
            'baseUrl' => '',
        ], [
            'publicPath' => $config->getPublicPath(),
            'cacheThumbs' => $config->getCacheThumbs(),
            'language' => $config->getLanguage(),
            'rootPath' => $config->getRootPath(),
            'requestUri' => $config->getRequestUri(),
            'baseUrl' => $config->getBaseUrl(),
        ]);
    }

    public static function cacheThumbsProvider(): array
    {
        return [
            'int' => [30, 30],
            'numeric string' => ['30', 30],
            'leading digits' => ['15min', 15],
            'non-numeric string' => ['never', 0],
        ];
    }

    #[DataProvider('cacheThumbsProvider')]
    public function testCacheThumbsIsReadAsAnInt(mixed $value, int $expected): void
    {
        $this->assertSame($expected, (new MenuConfig())->set('cacheThumbs', $value)->getCacheThumbs());
    }

    public static function languageProvider(): array
    {
        return [
            'language code' => ['fr', 'fr'],
            'empty string' => ['', null],
            // 1.1.0: false read as '' (a string, not "no language")
            'false' => [false, null],
            'an int is a string' => [0, '0'],
        ];
    }

    #[DataProvider('languageProvider')]
    public function testEmptyLanguageReadsAsNull(mixed $value, ?string $expected): void
    {
        $this->assertSame($expected, (new MenuConfig())->set('language', $value)->getLanguage());
    }

    public static function pathValues(): array
    {
        return [
            'string' => ['/var/www', '/var/www'],
            'int' => [123, '123'],
            'float' => [1.5, '1.5'],
            'true' => [true, '1'],
            'array' => [['a'], ''],
        ];
    }

    /** 1.1.0: the path getters cast (an int was a TypeError on the string return type) */
    #[DataProvider('pathValues')]
    public function testPathGettersCastToString(mixed $value, string $expected): void
    {
        $config = (new MenuConfig())
            ->set('publicPath', $value)
            ->set('rootPath', $value)
            ->set('requestUri', $value)
            ->set('baseUrl', $value);

        $this->assertSame(
            [$expected, $expected, $expected, $expected],
            [$config->getPublicPath(), $config->getRootPath(), $config->getRequestUri(), $config->getBaseUrl()]
        );
    }
}

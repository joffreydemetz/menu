<?php

declare(strict_types=1);

namespace JDZ\Menu\Config;

use JDZ\Menu\Contract\MenuConfigInterface;
use JDZ\Utils\Data;

/**
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class MenuConfig extends Data implements MenuConfigInterface
{
    public function getPublicPath(): string
    {
        return $this->string('publicPath');
    }

    public function getCacheThumbs(): int
    {
        return $this->getInt('cacheThumbs', 0);
    }

    public function getLanguage(): ?string
    {
        $lang = $this->string('language');
        return '' !== $lang ? $lang : null;
    }

    public function getRootPath(): string
    {
        return $this->string('rootPath');
    }

    public function getRequestUri(): string
    {
        return $this->string('requestUri');
    }

    public function getBaseUrl(): string
    {
        return $this->string('baseUrl');
    }

    /**
     * A setting as a string: a scalar is cast (an int was a TypeError on the
     * getters' return type, false read as ''), anything else is ''.
     */
    private function string(string $key): string
    {
        $value = $this->get($key);
        return is_scalar($value) ? (string) $value : '';
    }
}

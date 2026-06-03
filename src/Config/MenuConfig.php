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
        return $this->get('publicPath', '');
    }

    public function getCacheThumbs(): int
    {
        return $this->getInt('cacheThumbs', 0);
    }

    public function getLanguage(): ?string
    {
        $lang = $this->get('language');
        return ($lang !== null && $lang !== '') ? (string)$lang : null;
    }

    public function getRootPath(): string
    {
        return $this->get('rootPath', '');
    }

    public function getRequestUri(): string
    {
        return $this->get('requestUri', '');
    }

    public function getBaseUrl(): string
    {
        return $this->get('baseUrl', '');
    }
}

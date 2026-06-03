<?php

declare(strict_types=1);

namespace JDZ\Menu\Contract;

use JDZ\Utils\DataInterface;

/**
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
interface MenuConfigInterface extends DataInterface
{
    public function getPublicPath(): string;
    public function getCacheThumbs(): int;
    public function getLanguage(): ?string;
    public function getRootPath(): string;
    public function getRequestUri(): string;
    public function getBaseUrl(): string;
}

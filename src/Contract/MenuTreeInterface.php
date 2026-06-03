<?php

declare(strict_types=1);

namespace JDZ\Menu\Contract;

/**
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
interface MenuTreeInterface
{
    public function setMenu(): static;
    public function toTemplate(): array;
}

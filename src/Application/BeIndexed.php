<?php

declare(strict_types=1);

namespace JeroenG\Explorer\Application;

interface BeIndexed
{
    public function prepareIndexAction(array $indexAction): array;
}

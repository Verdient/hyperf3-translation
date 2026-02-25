<?php

declare(strict_types=1);

namespace Verdient\Hyperf3\Translation;

use Hyperf\Contract\TranslatorInterface;
use Hyperf\Contract\TranslatorLoaderInterface;
use Hyperf\Translation\FileLoaderFactory;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                TranslatorLoaderInterface::class => FileLoaderFactory::class,
                TranslatorInterface::class => TranslatorFactory::class,
            ]
        ];
    }
}

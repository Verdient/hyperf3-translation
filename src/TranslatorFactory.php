<?php

declare(strict_types=1);

namespace Verdient\Hyperf3\Translation;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\TranslatorLoaderInterface;
use Psr\Container\ContainerInterface;

use function Hyperf\Support\make;

/**
 * 翻译工厂
 *
 * @author Verdient。
 */
class TranslatorFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $config = $container->get(ConfigInterface::class);
        $locale = $config->get('translation.locale', 'zh_CN');

        $loader = $container->get(TranslatorLoaderInterface::class);

        $translator = make(Translator::class, compact('loader', 'locale'));

        return $translator;
    }
}

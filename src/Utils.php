<?php

declare(strict_types=1);

namespace Verdient\Hyperf3\Translation;

use FilesystemIterator;
use Hyperf\Context\RequestContext;
use Hyperf\Contract\TranslatorInterface;
use Verdient\Hyperf3\Di\Container;

/**
 * 工具
 *
 * @author Verdient。
 */
class Utils
{
    /**
     * 获取支持的语言
     *
     * @author Verdient。
     */
    public static function accceptLanguages(): array
    {
        if (!$request = RequestContext::getOrNull()) {
            return [];
        }

        $languages = [];

        if ($request->hasHeader('Accept-Language')) {
            foreach (explode(',', $request->getHeaderLine('Accept-Language')) as $language) {
                $languageUnit = explode(';', $language, 2);
                if (count($languageUnit) === 2) {
                    if (str_starts_with($languageUnit[1], 'q=')) {
                        $q = substr($languageUnit[1], 2);
                        if (is_numeric($q) && $q > 0 && $q <= 1) {
                            $languages[$languageUnit[0]] = (float) $q;
                        }
                    }
                } else {
                    $languages[$languageUnit[0]] = 1;
                }
            }
        }

        if ($specifiedLanguage = $request->getHeaderLine('Lang')) {
            unset($languages[$specifiedLanguage]);
            $languages = [$specifiedLanguage => 1, ...$languages];
        }

        arsort($languages);

        return $languages;
    }

    /**
     * 获取已存储的语言
     *
     * @param string $path 路径
     *
     * @author Verdient。
     */
    public static function storagedLocales(string $path): array
    {
        $locales = [];

        if (is_dir($path)) {
            foreach (new FilesystemIterator($path) as $fileInfo) {
                if ($fileInfo->isDir()) {
                    $locales[] = $fileInfo->getFilename();
                }
            }
        }

        return $locales;
    }

    /**
     * 获取当前的语言
     *
     * @author Verdient。
     */
    public static function locale(): string
    {
        return Container::get(TranslatorInterface::class)->getLocale();
    }
}

<?php

declare(strict_types=1);

namespace Verdient\Hyperf3\Translation;

use Hyperf\Collection\Arr;
use Hyperf\Collection\Collection;
use Hyperf\Context\Context;
use Hyperf\Contract\TranslatorInterface;
use Hyperf\Contract\TranslatorLoaderInterface;
use Hyperf\Stringable\Str;
use Hyperf\Translation\MessageSelector;
use Override;

use function Hyperf\Config\config;

/**
 * 翻译器
 *
 * @author Verdient。
 */
class Translator implements TranslatorInterface
{
    /**
     * 已加载翻译组的数组
     *
     * @author Verdient。
     */
    protected array $loaded = [];

    /**
     * 消息选择器
     *
     * @author Verdient。
     */
    protected ?MessageSelector $selector = null;

    /**
     * 已解析项目的缓存
     *
     * @author Verdient。
     */
    protected array $parsed = [];

    /**
     * @param TranslatorLoaderInterface $loader 加载器
     * @param string $locale 区域/语言
     *
     * @author Verdient。
     */
    public function __construct(
        protected TranslatorLoaderInterface $loader,
        protected string $locale
    ) {}

    /**
     * 获取区域/语言
     *
     * @param ?string $locale 区域/语言
     *
     * @return string[]
     * @author Verdient。
     */
    protected function getLocales(?string $locale): array
    {
        if (empty($locale)) {
            $locale = $this->getLocaleFromContext();
        }

        if ($locale) {
            return [$locale];
        }

        $contextKey = __CLASS__ . '::' . $locale;

        $locales = Context::get($contextKey);

        if (!empty($locales)) {
            return $locales;
        }

        $languages = array_keys(Utils::accceptLanguages());

        if (empty($languages)) {
            $locales = [$this->locale];
        } else {
            $supportedLocales = config('translation.locales');

            if (empty($supportedLocales)) {
                $locales = [];

                foreach ($languages as $language) {
                    $locales[] = $language;
                    if (strlen($language) > 2 && $language[2] === '-') {
                        $locales[] = substr($language, 0, 2);
                    }
                }

                $locales = array_values(array_unique($locales));
            } else {
                $locales = array_intersect($languages, $supportedLocales);

                foreach (array_diff($supportedLocales, $locales) as $locale) {
                    foreach ($languages as $language) {
                        if (substr($locale, 0, 2) === substr($language, 0, 2)) {
                            $locales[] = $locale;
                        }
                    }
                }
            }

            if (empty($locales)) {
                $locales = [$this->locale];
            } else {
                $locales = array_values(array_unique($locales));
            }
        }

        Context::set($contextKey, $locales);

        return $locales;
    }

    /**
     * @author Verdient。
     */
    #[Override]
    public function trans(string $key, array $replace = [], ?string $locale = null): array|string
    {
        [$namespace, $group, $item] = $this->parseKey($key);

        $locales = $this->getLocales($locale);

        foreach ($locales as $locale) {
            if (!is_null($line = $this->getLine(
                $namespace,
                $group,
                $locale,
                $item,
                $replace
            ))) {
                break;
            }
        }

        return $line ?? $key;
    }

    /**
     * @author Verdient。
     */
    #[Override]
    public function transChoice(string $key, $number, array $replace = [], ?string $locale = null): string
    {
        [$namespace, $group, $item] = $this->parseKey($key);

        $locales = $this->getLocales($locale);

        foreach ($locales as $locale) {
            if (!is_null($line = $this->getLine(
                $namespace,
                $group,
                $locale,
                $item,
                $replace
            ))) {
                break;
            }
        }

        if (is_null($line)) {
            return $key;
        }

        if (is_countable($number)) {
            $number = count($number);
        }

        $replace['count'] = $number;

        return $this->makeReplacements(
            $this->getSelector()->choose($line, $number, $locale),
            $replace
        );
    }

    /**
     * 获取上下文区域键名
     *
     * @author Verdient。
     */
    protected function getLocaleContextKey(): string
    {
        return sprintf('%s::%s', TranslatorInterface::class, 'locale');
    }

    /**
     * 从上下文中获取区域/语言
     *
     * @author Verdient。
     */
    public function getLocaleFromContext(): ?string
    {
        return Context::get($this->getLocaleContextKey());
    }

    /**
     * @author Verdient。
     */
    #[Override]
    public function getLocale(): string
    {
        $locales = $this->getLocales(null);

        return reset($locales);
    }

    /**
     * @author Verdient。
     */
    #[Override]
    public function setLocale(string $locale)
    {
        Context::set($this->getLocaleContextKey(), $locale);
    }

    /**
     * 载入语言
     *
     * @param string $namespace 命名空间
     * @param string $group 组
     * @param string $locale 区域/语言
     *
     * @author Verdient。
     */
    protected function load(string $namespace, string $group, string $locale)
    {
        if ($this->isLoaded($namespace, $group, $locale)) {
            return;
        }

        $lines = $this->loader->load($locale, $group, $namespace);

        $this->loaded[$namespace][$group][$locale] = $lines;
    }

    /**
     * 将键解析为命名空间、组和项条目
     *
     * @param string $key 键名
     *
     * @author Verdient。
     */
    protected function parseKey(string $key): array
    {
        if (isset($this->parsed[$key])) {
            return $this->parsed[$key];
        }

        if (!str_contains($key, '::')) {
            $segments = explode('.', $key);

            $parsed = $this->parseBasicSegments($segments);
        } else {
            $parsed = $this->parseNamespacedSegments($key);
        }

        if (is_null($parsed[0])) {
            $parsed[0] = '*';
        }

        return $this->parsed[$key] = $parsed;
    }

    /**
     * 获取消息选择器
     *
     * @author Verdient。
     */
    public function getSelector(): MessageSelector
    {
        if (!isset($this->selector)) {
            $this->selector = new MessageSelector();
        }

        return $this->selector;
    }

    /**
     * 从加载的数组中检索语言行
     *
     * @param string $namespace 命名空间
     * @param string $group 组
     * @param mixed $item 条目
     * @param string $locale 区域/语言
     *
     * @author Verdient。
     */
    protected function getLine(string $namespace, string $group, string $locale, $item, array $replace)
    {
        $this->load($namespace, $group, $locale);

        if (!is_null($item)) {
            $line = Arr::get($this->loaded[$namespace][$group][$locale], $item);
        } else {
            $line = $this->loaded[$namespace][$group][$locale];
        }

        if (is_string($line)) {
            return $this->makeReplacements($line, $replace);
        }

        if (is_array($line) && count($line) > 0) {
            foreach ($line as $key => $value) {
                $line[$key] = $this->makeReplacements($value, $replace);
            }

            return $line;
        }

        return null;
    }

    /**
     * 在一行上进行占位符替换
     *
     * @param array|string $line 行
     * @param array $replace 替换数组
     *
     * @author Verdient。
     */
    protected function makeReplacements(array|string $line, array $replace): array|string
    {
        if (empty($replace)) {
            return $line;
        }

        $replace = $this->sortReplacements($replace);

        foreach ($replace as $key => $value) {
            $key = (string) $key;
            $value = (string) $value;
            $line = str_replace(
                [':' . $key, ':' . Str::upper($key), ':' . Str::ucfirst($key)],
                [$value, Str::upper($value), Str::ucfirst($value)],
                $line
            );
        }

        return $line;
    }

    /**
     * 对替换数组进行排序
     *
     * @param array $replace 替换数组
     *
     * @author Verdient。
     */
    protected function sortReplacements(array $replace): array
    {
        return (new Collection($replace))->sortBy(fn($value, $key) => mb_strlen((string) $key) * -1)->all();
    }

    /**
     * 判断给定的组是否已加载
     *
     * @param string $namespace 命名空间
     * @param string $group 组
     * @param string $locale 语言
     *
     * @author Verdient。
     */
    protected function isLoaded(string $namespace, string $group, string $locale): bool
    {
        return isset($this->loaded[$namespace][$group][$locale]);
    }

    /**
     * 解析基本段数组
     *
     * @param string $key 键名
     *
     * @author Verdient。
     */
    protected function parseBasicSegments(array $segments): array
    {
        $group = $segments[0];

        $item = count($segments) === 1
            ? null
            : implode('.', array_slice($segments, 1));

        return [null, $group, $item];
    }

    /**
     * 解析命名空间段数组
     *
     * @param string $key 键名
     *
     * @author Verdient。
     */
    protected function parseNamespacedSegments(string $key): array
    {
        [$namespace, $item] = explode('::', $key);

        $itemSegments = explode('.', $item);

        $groupAndItem = array_slice(
            $this->parseBasicSegments($itemSegments),
            1
        );

        return array_merge([$namespace], $groupAndItem);
    }
}

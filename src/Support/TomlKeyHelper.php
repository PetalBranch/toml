<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Support;

/**
 * TOML 键名格式化工具类
 *
 * 提供将任意字符串转换为合法 TOML 键名的功能。
 * 根据 TOML 规范，键名可以是裸键（仅包含字母、数字、下划线和连字符），
 * 或者是用双引号包围的引用键。该工具类负责判断输入字符串是否符合
 * 裸键的要求，如果不符合则自动转换为适当的引用键格式。
 */
class TomlKeyHelper
{
    /**
     * 格式化键名为合法的 TOML 键
     *
     * 将输入的键名字符串转换为符合 TOML 规范的格式。
     * 如果键名只包含字母、数字、下划线和连字符，则作为裸键直接返回；
     * 否则将其转换为双引号包围的引用键，并对特殊字符进行转义。
     *
     * @param string $key 待格式化的原始键名字符串
     * @return string 格式化后的合法 TOML 键名
     */
    public static function format(string $key): string
    {
        // 裸键 (Bare keys) 仅允许 A-Z, a-z, 0-9, dash (-), underscore (_)
        if (preg_match('/^[A-Za-z0-9_-]+$/', $key)) {
            return $key;
        }

        // 复杂键名必须作为 Quoted Key 处理，使用极其严格的字符串转义
        return self::escapeString($key);
    }


    /**
     * 转义字符串为 TOML 格式
     *
     * 将普通字符串转换为符合 TOML 规范的引用字符串格式。
     * 首先转义特殊字符（如反斜杠、双引号、控制字符等），
     * 然后使用 Unicode 转义序列处理其他不可见的控制字符。
     * 最终返回用双引号包围的转义后字符串。
     *
     * @param string $value 需要转义的原始字符串
     * @return string 转义后的 TOML 字符串（包含首尾双引号）
     */
    public static function escapeString(string $value): string
    {
        // 1. 常见控制字符和特殊字符转义
        $search = ["\\", "\"", "\x08", "\x0c", "\x0a", "\x0d", "\x09"];
        $replace = ["\\\\", "\\\"", "\\b", "\\f", "\\n", "\\r", "\\t"];
        $escaped = str_replace($search, $replace, $value);

        // 2. 处理 TOML 严禁的其他不可见控制字符 (\uXXXX)
        return '"' . preg_replace_callback('/[\x00-\x07\x0B\x0E-\x1F\x7F]/', function ($matches) {
                return sprintf("\\u%04x", ord($matches[0]));
            }, $escaped) . '"';
    }
}

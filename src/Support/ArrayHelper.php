<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Support;

/**
 * 数组辅助工具类
 *
 * 提供处理 PHP 数组的实用方法。
 * 主要用于判断数组是否为关联数组，这在 TOML 数据转换中至关重要，
 * 因为关联数组和索引数组在 TOML 中有不同的表示方式。
 */
class ArrayHelper
{
    /**
     * 判断是否为关联数组
     *
     * 检查给定的数组是否为关联数组（即键名不是连续的整数）。
     * 在 TOML 转换中，非关联数组映射为 Array [...]，关联数组映射为 Table 或 Inline Table。
     *
     * @param array $array 要检查的数组
     * @return bool 如果是关联数组返回 true，否则返回 false
     */
    public static function isAssoc(array $array): bool
    {
        if (empty($array)) return false;

        // 检查键名是否为连续整数
        return array_keys($array) !== range(0, count($array) - 1);
    }
}

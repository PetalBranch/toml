<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Support;

/**
 * 位置类
 *
 * 表示 TOML 源文件中的位置信息（行号和列号）
 * 用于标记语法元素在源代码中的具体位置
 *
 * @package Petalbranch\Toml\Support
 */
final class Position
{
    /**
     * 构造函数
     *
     * @param int $line 行号
     * @param int $column 列号
     */
    public function __construct(
        public int $line,
        public int $column,
    )
    {
    }

}

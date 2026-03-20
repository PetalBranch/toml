<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Support;

/**
 * 错误上下文类
 *
 * 用于存储解析错误发生时的上下文行信息
 * 包含错误所在行及其前后行的内容
 *
 * @package Petalbranch\Toml\Support
 */
final readonly class ErrorContext
{
    /**
     * 构造函数
     *
     * @param string|null $line 错误所在的源代码行
     * @param string|null $previousLine 错误所在行的前一行
     * @param string|null $nextLine 错误所在行的后一行
     */
    public function __construct(
        public ?string $line,
        public ?string $previousLine,
        public ?string $nextLine,
    )
    {
    }

}

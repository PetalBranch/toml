<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Contract\Exception;

use Petalbranch\Toml\Support\ErrorContext;
use Petalbranch\Toml\Type\ParseErrorType;

/**
 * 解析异常接口
 *
 * 用于表示 TOML 解析过程中发生的错误，提供错误位置信息
 *
 * @package Petalbranch\Toml\Contract\Exception
 */
interface ParseExceptionInterface extends TomlExceptionInterface
{
    /**
     * 获取错误发生的行号
     *
     * @return int 返回错误所在的行号，从 1 开始计数
     */
    public function getLineNumber(): int;

    /**
     * 获取错误发生的列号
     *
     * @return int 返回错误所在的列号，从 1 开始计数
     */
    public function getColumnNumber(): int;


    /**
     * 获取错误上下文的源代码行
     *
     * @return ErrorContext|null 返回包含错误所在行及其前后行的上下文对象，如果无法获取则返回 null
     */
    public function getContext(): ?ErrorContext;

    /**
     * 获取解析错误的类型
     *
     * @return ParseErrorType 返回错误类型枚举值，用于标识具体的错误类别
     */
    public function getErrorType(): ParseErrorType;
}

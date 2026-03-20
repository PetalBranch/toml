<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Exception;

use Petalbranch\Toml\Type\ParseErrorType;
use Petalbranch\Toml\Contract\Exception\ParseExceptionInterface;
use Petalbranch\Toml\Support\ErrorContext;
use RuntimeException;
use Throwable;

/**
 * 解析异常类
 *
 * 用于表示 TOML 解析过程中发生的错误，提供详细的错误位置信息
 *
 * @package Petalbranch\Toml\Exception
 */
class ParseException extends RuntimeException implements ParseExceptionInterface
{
    /**
     * 构造函数
     *
     * @param string $message 异常消息
     * @param ParseErrorType $type
     * @param int $lineNumber 错误发生的行号，默认为 0
     * @param int $columnNumber 错误发生的列号，默认为 0
     * @param ErrorContext|null $context 错误上下文对象，默认为 null
     * @param Throwable|null $previous 前一个异常对象，默认为 null
     */
    public function __construct(
        string                         $message,
        public readonly ParseErrorType $type,
        public readonly int            $lineNumber = 0,
        public readonly int            $columnNumber = 0,
        public readonly ?ErrorContext  $context = null,
        ?Throwable                     $previous = null
    )
    {
        // 自动拼接详细的错误信息，方便直接打印
        $detailedMessage = sprintf('[%s] %s', $this->type->name, $message);
        if ($lineNumber > 0) {
            $detailedMessage .= sprintf(' at line %d', $lineNumber);
            if ($columnNumber > 0) {
                $detailedMessage .= sprintf(', column %d', $columnNumber);
            }
        }
        if ($context !== null) {
            $detailedMessage .= sprintf(' (snippet: "%s")', $context->line);
        }

        parent::__construct($detailedMessage, 0, $previous);
    }

    /**
     * 获取错误发生的行号
     *
     * @return int 返回错误所在的行号
     */
    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    /**
     * 获取错误发生的列号
     *
     * @return int 返回错误所在的列号
     */
    public function getColumnNumber(): int
    {
        return $this->columnNumber;
    }

    /**
     * 获取解析错误的类型
     *
     * @return ParseErrorType 返回错误类型枚举值
     */
    public function getErrorType(): ParseErrorType
    {
        return $this->type;
    }

    /**
     * 获取错误上下文的源代码行
     *
     * @return ErrorContext|null 返回包含错误所在行及其前后行的上下文对象，如果无法获取则返回 null
     */
    public function getContext(): ?ErrorContext
    {
        return $this->context;
    }
}

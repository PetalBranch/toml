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
    // 详细报错控制开关，默认开启
    public static bool $detailedErrorOutput = true;

    /**
     * 构造函数
     *
     * @param string $message 异常消息
     * @param ParseErrorType $type
     * @param int $lineNumber 错误发生的行号，默认为 0
     * @param int $columnNumber 错误发生的列号，默认为 0
     * @param ErrorContext|null $context 错误上下文对象，默认为 null
     * @param int $length 错误字符长度，默认为 1
     * @param Throwable|null $previous 前一个异常对象，默认为 null
     */
    public function __construct(
        string                         $message,
        public readonly ParseErrorType $type,
        public readonly int            $lineNumber = 0,
        public readonly int            $columnNumber = 0,
        public readonly ?ErrorContext  $context = null,
        public readonly int            $length = 1,
        ?Throwable                     $previous = null
    )
    {
        $detailedMessage = sprintf('[%s] %s', $this->type->name, $message);

        if ($lineNumber > 0) {
            $detailedMessage .= sprintf(' (Line: %d, Column: %d)', $lineNumber, $columnNumber);
        }

        if (self::$detailedErrorOutput && $context !== null && $context->line !== null) {
            $detailedMessage .= "\n\n";

            if ($context->previousLine !== null) {
                $detailedMessage .= sprintf("  %4d | %s\n", $lineNumber - 1, $context->previousLine);
            }

            $detailedMessage .= sprintf("> %4d | %s\n", $lineNumber, $context->line);

            if ($columnNumber > 0) {
                $prefixLength = 9;
                // 计算真实的终端视觉宽度
                // 1. 计算前面的 Padding 宽度 (定位游标起点)
                $precedingText = mb_substr($context->line, 0, $columnNumber - 1, 'UTF-8');
                $paddingWidth = mb_strwidth($precedingText, 'UTF-8');
                $padding = str_repeat(' ', $prefixLength + $paddingWidth);

                // 2. 计算错误片段本身的视觉宽度！(决定画几个 ^)
                // 从出错的列开始，截取 $length 个字符
                $errorText = mb_substr($context->line, $columnNumber - 1, $length, 'UTF-8');
                // 获取这段错误代码在终端里的真实视觉宽度 (比如 🤪 是 2，a 是 1)
                $pointerWidth = mb_strwidth($errorText, 'UTF-8');

                // 3. 画出等宽的指针
                $pointers = str_repeat('^', max(1, $pointerWidth));
                $detailedMessage .= $padding . $pointers . "\n";
            }

            if ($context->nextLine !== null) {
                $detailedMessage .= sprintf("  %4d | %s\n", $lineNumber + 1, $context->nextLine);
            }
        }

        parent::__construct($detailedMessage, 0, $previous);
    }

    /**
     * 获取错误发生的行号
     */
    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    /**
     * 获取错误发生的列号
     */
    public function getColumnNumber(): int
    {
        return $this->columnNumber;
    }

    /**
     * 获取解析错误的类型
     */
    public function getErrorType(): ParseErrorType
    {
        return $this->type;
    }

    /**
     * 获取错误上下文的源代码行
     */
    public function getContext(): ?ErrorContext
    {
        return $this->context;
    }
}

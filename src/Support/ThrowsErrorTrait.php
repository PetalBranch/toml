<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Support;

use Petalbranch\Toml\Exception\ParseException;
use Petalbranch\Toml\Type\ParseErrorType;

/**
 * 错误抛出机制特质 (Trait)
 *
 * 为 Lexer 和 Parser 提供统一的、带有上下文片段提取能力的异常抛出机制。
 * 通过混入 (use) 该 Trait，类将自动获得 $rawSource 属性和 throwError 方法。
 */
trait ThrowsErrorTrait
{
    /**
     * @var string 原始 TOML 源代码，用于在报错时提取上下文片段
     */
    private string $rawSource = '';

    /**
     * 统一的异常抛出入口
     *
     * 自动附加包含错误行代码及其上下文的 ErrorContext
     *
     * @param string $message 错误信息
     * @param ParseErrorType $type 错误类型
     * @param int $line 错误行号
     * @param int $column 错误列号
     * @param int $length 错误片段长度（用于绘制波浪线）
     * @return never
     * @throws ParseException
     */
    private function throwError(string $message, ParseErrorType $type, int $line, int $column, int $length = 1): never
    {
        $context = null;

        if ($this->rawSource !== '' && $line > 0) {
            $lines = preg_split('/\r\n|\r|\n/', $this->rawSource);
            $currentIndex = $line - 1;
            $currentLine = $lines[$currentIndex] ?? null;

            if ($currentLine !== null) {
                $previousLine = $lines[$currentIndex - 1] ?? null;
                $nextLine = $lines[$currentIndex + 1] ?? null;
                $context = new ErrorContext($currentLine, $previousLine, $nextLine);
            }
        }

        throw new ParseException($message, $type, $line, $column, $context, $length);
    }
}
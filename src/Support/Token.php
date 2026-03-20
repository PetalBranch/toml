<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Support;

use Petalbranch\Toml\Contract\Lexer\TokenInterface;
use Petalbranch\Toml\Type\TokenType;

/**
 * 词法单元实现类
 *
 * 实现了 TokenInterface 接口，用于存储和管理词法分析器生成的词法单元信息
 *
 * @package Petalbranch\Toml\Support
 */
final readonly class Token implements TokenInterface
{
    /**
     * @param TokenType $type 词法单元类型
     * @param string $lexeme 原始文本
     * @param mixed $value 解析后的值
     * @param int $line 起始行号
     * @param int $column 起始列号
     * @param int $endLine 结束行号
     * @param int $endColumn 结束列号
     */
    public function __construct(
        private TokenType $type,
        private string    $lexeme,
        private mixed     $value,
        private int       $line,
        private int       $column,
        private int       $endLine,
        private int       $endColumn,
    )
    {
    }

    /**
     * 获取词法单元类型
     *
     * @return TokenType 返回词法单元的类型枚举值
     */
    public function getType(): TokenType
    {
        return $this->type;
    }

    /**
     * 获取词法单元的原始文本
     *
     * @return string 返回词法单元在源代码中的原始字符串表示
     */
    public function getLexeme(): string
    {
        return $this->lexeme;
    }

    /**
     * 获取词法单元的值
     *
     * @return mixed 返回词法单元解析后的值，类型取决于具体的词法单元类型
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * 获取词法单元所在的行号
     *
     * @return int 返回词法单元在源代码中的起始行号
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * 获取词法单元所在的起始列号
     *
     * @return int 返回词法单元在源代码中的起始列号
     */
    public function getColumn(): int
    {
        return $this->column;
    }

    /**
     * 获取词法单元所在的结束行号
     *
     * @return int 词法单元在源代码中的结束行号
     */
    public function getEndLine(): int
    {
        return $this->endLine;
    }

    /**
     * 获取词法单元所在的结束列号
     *
     * @return int 返回词法单元在源代码中的结束列号
     */
    public function getEndColumn(): int
    {
        return $this->endColumn;
    }
}

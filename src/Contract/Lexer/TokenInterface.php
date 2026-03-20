<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Contract\Lexer;

use Petalbranch\Toml\Type\TokenType;

/**
 * 词法单元接口
 *
 * 定义 TOML 词法分析器生成的词法单元的基本行为
 *
 * @package Petalbranch\Toml\Contract\Lexer
 */
interface TokenInterface
{
    /**
     * 获取词法单元类型
     *
     * @return TokenType 返回词法单元的类型枚举值
     */
    public function getType(): TokenType;

    /**
     * 获取词法单元的原始文本
     *
     * @return string 返回词法单元在源代码中的原始字符串表示
     */
    public function getLexeme(): string;

    /**
     * 获取词法单元的值
     *
     * @return mixed 返回词法单元解析后的值，类型取决于具体的词法单元类型
     */
    public function getValue(): mixed;

    /**
     * 获取词法单元所在的行号
     *
     * @return int 返回词法单元在源代码中的起始行号
     */
    public function getLine(): int;

    /**
     * 获取词法单元所在的起始列号
     *
     * @return int 返回词法单元在源代码中的起始列号
     */
    public function getColumn(): int;

    /**
     * 获取词法单元所在的结束行号
     *
     * @return int 词法单元在源代码中的结束行号
     */
    public function getEndLine(): int;

    /**
     * 获取词法单元所在的结束列号
     *
     * @return int 返回词法单元在源代码中的结束列号
     */
    public function getEndColumn(): int;

}

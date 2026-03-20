<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Contract\Lexer;

/**
 * 词法分析器接口
 *
 * 定义 TOML 词法分析器的基本行为，负责将源代码字符串转换为词法单元流
 *
 * @package Petalbranch\Toml\Contract\Lexer
 */
interface LexerInterface
{
    /**
     * 将源代码字符串转换为词法单元流
     *
     * 对输入的 TOML 源代码进行词法分析，生成有序的词法单元序列
     *
     * @param string $source 要分析的 TOML 源代码字符串
     * @return TokenStreamInterface 词法单元流
     */
    public function tokenize(string $source): TokenStreamInterface;
}

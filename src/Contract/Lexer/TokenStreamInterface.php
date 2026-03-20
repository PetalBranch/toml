<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Contract\Lexer;

use Petalbranch\Toml\Contract\Exception\ParseExceptionInterface;
use Petalbranch\Toml\Type\TokenType;

/**
 * 词法单元流接口
 *
 * 定义词法单元流的基本操作，用于语法分析器遍历和处理词法单元序列
 *
 * @package Petalbranch\Toml\Contract\Lexer
 */
interface TokenStreamInterface
{
    /**
     * 获取当前词法单元
     *
     * @return TokenInterface 返回当前位置的词法单元
     */
    public function current(): TokenInterface;

    /**
     * 移动到下一个词法单元并返回
     *
     * @return TokenInterface 返回移动后的新位置的词法单元
     */
    public function next(): TokenInterface;

    /**
     * 预览指定偏移量的词法单元而不移动当前位置
     *
     * @param int $offset 预览的偏移量，默认为 1（下一个词法单元）
     * @return TokenInterface 返回指定偏移位置的词法单元，当预览位置超出词法单元流范围，则返回 EOF token。
     */
    public function peek(int $offset = 1): TokenInterface;

    /**
     * 匹配当前词法单元类型并返回
     *
     * 检查当前词法单元是否匹配指定的类型之一，如果匹配则返回该词法单元
     *
     * @param TokenType ...$types 要匹配的词法单元类型列表
     * @return TokenInterface|null 如果匹配成功返回当前词法单元并前进，否则返回 null
     */
    public function match(TokenType ...$types): ?TokenInterface;

    /**
     * 期望当前词法单元是指定类型之一，否则抛出异常
     *
     * 检查当前词法单元是否匹配指定的类型之一，如果不匹配则抛出带有自定义消息的异常
     *
     * @param string $message 自定义错误消息
     * @param TokenType ...$types 期望的词法单元类型列表
     * @return TokenInterface 如果匹配成功则返回当前词法单元并前进
     * @throws ParseExceptionInterface 当当前词法单元类型不匹配任何指定类型时抛出异常
     */
    public function expect(string $message, TokenType ...$types): TokenInterface;

    /**
     * 检查是否已到达词法单元流末尾
     *
     * @return bool 如果已到达文件末尾（EOF）返回 true，否则返回 false
     */
    public function isEOF(): bool;

}

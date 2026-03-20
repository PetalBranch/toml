<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Contract\Parser;

use Petalbranch\Toml\Contract\Exception\ParseExceptionInterface;
use Petalbranch\Toml\Contract\Lexer\TokenStreamInterface;

/**
 * 解析器接口
 *
 * 定义 TOML 解析器的基本行为，支持将 TOML 格式解析为数组或语法树节点
 *
 * @package Petalbranch\Toml\Contract\Parser
 */
interface ParserInterface
{
    /**
     * 解析 TOML 字符串
     *
     * @param string $toml 要解析的 TOML 格式字符串
     * @return array 返回解析后的关联数组
     * @throws ParseExceptionInterface 当 TOML 格式错误时抛出解析异常
     */
    public function parse(string $toml): array;

    /**
     * 解析 TOML 文件
     *
     * @param string $filename 要解析的 TOML 文件路径
     * @return array 返回解析后的关联数组
     * @throws ParseExceptionInterface 当文件不存在或格式错误时抛出解析异常
     */
    public function parseFile(string $filename): array;

    /**
     * 解析 TOML 字符串为语法树节点
     *
     * @param string $toml 要解析的 TOML 格式字符串
     * @return TableNodeInterface 返回解析后的表节点接口实例
     * @throws ParseExceptionInterface 当 TOML 格式错误时抛出解析异常
     */
    public function parseToNode(string $toml): TableNodeInterface;

    /**
     * 解析词法单元流为语法树节点
     *
     * @param TokenStreamInterface $stream 词法单元流对象
     * @return TableNodeInterface 返回解析后的表节点接口实例
     * @throws ParseExceptionInterface 当解析过程中遇到错误时抛出解析异常
     */
    public function parseStream(TokenStreamInterface $stream): TableNodeInterface;
}

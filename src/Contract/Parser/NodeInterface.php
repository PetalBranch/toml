<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Contract\Parser;

use Petalbranch\Toml\Support\Position;
use Petalbranch\Toml\Type\TomlType;

/**
 * TOML 节点接口
 *
 * 定义 TOML 解析树中节点的基本行为
 * 所有具体的值类型都必须实现此接口。
 *
 * @package Petalbranch\Toml\Contract\Parser
 */
interface NodeInterface
{

    /**
     * 获取节点的 TOML 数据类型
     *
     * @return TomlType 返回节点对应的 TOML 数据类型枚举值
     */
    public function getType(): TomlType;

    /**
     * 获取节点的原始字符串表示
     *
     * @return string 返回节点在 TOML 源文件中的原始字符串值
     */
    public function getRaw(): string;

    /**
     * 获取节点的值
     *
     * @return mixed 返回节点存储的实际值，类型取决于具体的数据类型
     */
    public function getValue(): mixed;

    /**
     * 获取节点在源文件中的位置
     *
     * @return Position 返回包含行号和列号的位置对象
     */
    public function getPosition(): Position;

    /**
     * 获取节点的前导注释
     *
     * @return list<string>|null 返回位于节点之前的注释字符串数组
     */
    public function getLeadingComments(): ?array;

    /**
     * 获取节点的尾部注释
     *
     * @return string|null 返回位于节点同一行末尾的注释，如果没有则返回 null
     */
    public function getTrailingComment(): ?string;

}

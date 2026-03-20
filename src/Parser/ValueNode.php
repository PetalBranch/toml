<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Parser;

use Petalbranch\Toml\Support\Position;
use Petalbranch\Toml\Type\TomlType;

/**
 * TOML 值节点类
 *
 * 表示 TOML 中的基础数据类型节点（字符串、整数、浮点数、布尔值、日期时间等）。
 * 它是抽象语法树（AST）的叶子节点，不包含任何子节点。
 */
class ValueNode extends Node
{
    /**
     * @var TomlType TOML 类型枚举值
     */
    protected TomlType $type;

    /**
     * @var mixed 解析后的 PHP 原生值
     */
    protected mixed $value;


    /**
     * 构造函数
     *
     * @param TomlType $type TOML 类型枚举值
     * @param mixed $value 解析后的 PHP 原生值
     * @param Position $position 节点在源码中的位置
     * @param string $raw 原始字符串
     * @param list<string>|null $leadingComments 前导注释
     * @param string|null $trailingComment 尾部注释
     */
    public function __construct(
        TomlType $type,
        mixed    $value,
        Position $position,
        string   $raw,
        ?array   $leadingComments = null,
        ?string  $trailingComment = null
    )
    {
        parent::__construct($position, $raw, $leadingComments, $trailingComment);
        $this->type = $type;
        $this->value = $value;
    }


    /**
     * 获取节点的 TOML 类型
     *
     * @return TomlType 返回标量类型枚举值
     */
    public function getType(): TomlType
    {
        return $this->type;
    }


    /**
     * 获取节点的值
     *
     * @return mixed 返回解析后的 PHP 原生值，类型由具体的 TOML 类型决定
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * 快速检查当前标量是否为指定的 TOML 类型
     *
     * 这是一个实用的语法糖，避免在业务层频繁调用 $node->getType() === TomlType::...
     *
     * @param TomlType $type 要检查的类型
     * @return bool 如果类型匹配返回 true，否则返回 false
     */
    public function is(TomlType $type): bool
    {
        return $this->type === $type;
    }
}

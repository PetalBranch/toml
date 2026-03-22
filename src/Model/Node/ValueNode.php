<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Model\Node;

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
     * 设置节点的值 (用于 AST 树的动态修改与无损回写)
     *
     * 开发者在直接修改 AST 树时调用此方法。
     * 为了确保 Dumper 在重新转储时能正确渲染（例如给字符串加引号），
     * 该方法会自动尝试推断新值的 TOML 类型。
     *
     * @param mixed $value 新的 PHP 原生值
     * @param TomlType|null $type 可选的强制类型，不传则自动推断基础类型
     * @return self 返回当前实例以支持链式调用
     */
    public function setValue(mixed $value, ?TomlType $type = null): self
    {
        $this->value = $value;

        if ($type !== null) {
            $this->type = $type;
            return $this;
        }

        // 类型推断，防止 Dumper 渲染时类型错乱
        $this->type = match (true) {
            is_int($value) => TomlType::INTEGER,
            is_float($value) => TomlType::FLOAT,
            is_bool($value) => TomlType::BOOLEAN,
            is_string($value) => TomlType::STRING,
            // 对于复杂的日期/时间类型，建议开发者显式传入 $type
            // 否则保持原来的类型不变
            default => $this->type,
        };

        return $this;
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

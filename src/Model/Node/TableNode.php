<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Model\Node;

use Petalbranch\Toml\Contract\Parser\NodeInterface;
use Petalbranch\Toml\Contract\Parser\TableNodeInterface;
use Petalbranch\Toml\Exception\ParseException;
use Petalbranch\Toml\Model\Entry;
use Petalbranch\Toml\Model\KeyPath;
use Petalbranch\Toml\Type\ParseErrorType;
use Petalbranch\Toml\Type\TomlType;

/**
 * TOML 表格节点类
 *
 * 表示 TOML 中的标准表格结构，包含多个键值对条目
 */
class TableNode extends Node implements TableNodeInterface
{

    /**
     * 表格条目集合，键名映射到 Entry 对象
     *
     * @var array<string, Entry>
     */
    protected array $entries = [];

    /**
     * 表格是否为隐式定义
     *
     * @var bool
     */
    protected bool $implicit = false;

    /**
     * 获取节点的 TOML 类型
     *
     * @return TomlType 返回表格类型枚举值
     */
    public function getType(): TomlType
    {
        return TomlType::TABLE;
    }


    /**
     * 获取表格的值
     *
     * 递归提取所有子节点的值并组成关联数组
     *
     * @return array 返回包含所有子节点值的关联数组
     */
    public function getValue(): array
    {
        return array_map(function ($entry) {
            return $entry->value->getValue();
        }, $this->entries);
    }

    /**
     * 获取指定键的子节点
     *
     * @param string $key 要获取的键名
     * @return NodeInterface|null 返回对应的节点，如果键不存在则返回 null
     */
    public function get(string $key): ?NodeInterface
    {
        return $this->entries[$key]?->value ?? null;
    }

    /**
     * 根据路径获取子节点
     *
     * 支持多级嵌套路径访问，逐层向下查找直到路径终点
     *
     * @param KeyPath $path 键路径对象，包含路径段数组
     * @return NodeInterface|null 返回路径对应的节点，如果路径不存在或中间某层不是表节点则返回 null
     */
    public function getPath(KeyPath $path): ?NodeInterface
    {
        $current = $this;
        $segments = $path->segments;

        foreach ($segments as $i => $segment) {
            // 如果中间某一层不是表节点，说明路径断了或类型不匹配
            if (!$current instanceof TableNodeInterface) return null;

            $node = $current->get($segment);

            // 如果已经到了路径的最后一段，直接返回找到的节点
            if ($i === count($segments) - 1) {
                return $node;
            }

            // 否则，继续往下钻
            $current = $node;
        }

        return null;
    }


    /**
     * 设置子节点
     *
     * @param string $key 键名
     * @param NodeInterface $value 要设置的节点值
     * @return void
     */
    public function set(string $key, NodeInterface $value): void
    {
        // 同一个 key 不能重复定义，抛出异常
        if (isset($this->entries[$key])) {
            throw new ParseException("Duplicate key: $key", ParseErrorType::DUPLICATE_KEY);
        }
        $this->entries[$key] = new Entry($key, $value);
    }


    /**
     * 检查是否存在指定的键
     *
     * @param string $key 要检查的键名
     * @return bool 如果键存在返回 true，否则返回 false
     */
    public function has(string $key): bool
    {
        return isset($this->entries[$key]);
    }

    /**
     * 移除指定的子节点
     *
     * @param string $key 要移除的键名
     * @return void
     */
    public function remove(string $key): void
    {
        unset($this->entries[$key]);
    }

    /**
     * 检查表是否为内联格式
     *
     * @return bool 标准表返回 false，表示不是内联表
     */
    public function isInline(): bool
    {
        // 默认的标准表不是内联的
        return false;
    }

    /**
     * 获取表的条目集合
     *
     * 返回所有条目的索引数组，重新排序以确保连续的数组索引
     *
     * @return list<Entry> 返回包含表中所有键值对的索引数组
     */
    public function getEntries(): array
    {
        return array_values($this->entries);
    }


    /**
     * 检查表是否为隐式创建的
     *
     * @return bool 如果表是隐式创建的返回 true，否则返回 false
     */
    public function isImplicit(): bool
    {
        return $this->implicit;
    }


    /**
     * 设置表是否为隐式创建的
     *
     * @param bool $implicit 设置为 true 表示表是隐式创建的
     * @return void
     */
    public function setImplicit(bool $implicit): void
    {
        $this->implicit = $implicit;
    }
}

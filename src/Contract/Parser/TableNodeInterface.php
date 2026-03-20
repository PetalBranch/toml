<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Contract\Parser;

use Petalbranch\Toml\Model\Entry;
use Petalbranch\Toml\Model\KeyPath;

/**
 * 表节点接口
 *
 * 继承自 NodeInterface，用于表示 TOML 中的表类型节点
 * 提供对表内子节点的访问和管理方法
 *
 * @package Petalbranch\Toml\Contract\Parser
 */
interface TableNodeInterface extends NodeInterface
{

    /**
     * 获取指定键的子节点
     *
     * @param string $key 要获取的键名
     * @return NodeInterface|null 返回对应的节点，如果键不存在则返回 null
     */
    public function get(string $key): ?NodeInterface;

    /**
     * 根据路径获取子节点
     *
     * @param KeyPath $path 键路径对象，包含路径段数组
     * @return NodeInterface|null 返回路径对应的节点，如果路径不存在则返回 null
     */
    public function getPath(KeyPath $path): ?NodeInterface;

    /**
     * 设置子节点
     *
     * @param string $key 键名
     * @param NodeInterface $value 要设置的节点值
     */
    public function set(string $key, NodeInterface $value): void;

    /**
     * 检查是否存在指定的键
     *
     * @param string $key 要检查的键名
     * @return bool 如果键存在返回 true，否则返回 false
     */
    public function has(string $key): bool;

    /**
     * 移除指定的子节点
     *
     * @param string $key 要移除的键名
     */
    public function remove(string $key): void;

    /**
     * 检查表是否为内联格式
     *
     * @return bool 如果是内联表返回 true，否则返回 false
     */
    public function isInline(): bool;

    /**
     * 获取表的条目集合
     *
     * @return list<Entry> 返回包含表中所有键值对的关联数组
     */
    public function getEntries(): array;
}

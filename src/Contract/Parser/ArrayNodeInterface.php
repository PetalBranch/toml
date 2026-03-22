<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Contract\Parser;

use Countable;
use IteratorAggregate;
use Traversable;

/**
 * 数组节点接口
 *
 * 继承自 NodeInterface，用于表示 TOML 中的数组类型节点
 * 提供对数组元素的添加、访问和计数方法
 *
 * @package Petalbranch\Toml\Contract\Parser
 * @extends IteratorAggregate<int, NodeInterface>
 */
interface ArrayNodeInterface extends NodeInterface, IteratorAggregate, Countable
{
    /**
     * 添加子节点到数组末尾
     *
     * @param NodeInterface $node 要添加的节点对象
     */
    public function add(NodeInterface $node): void;

    /**
     * 获取指定索引位置的子节点
     *
     * @param int $index 要获取的索引位置
     * @return NodeInterface|null 返回对应位置的节点，如果索引不存在则返回 null
     */
    public function get(int $index): ?NodeInterface;


    /**
     * 获取数组的元素集合
     *
     * @return list<NodeInterface> 返回包含所有数组元素的索引数组，每个元素都是 NodeInterface 对象
     */
    public function getElements(): array;

    /**
     * 检查数组是否应作为“表数组 (Array of Tables)”格式化
     *
     * 对应 TOML 中的 [[table]] 语法。
     * 如果返回 false，则表示应格式化为普通的内联数组 [ {a=1}, {b=2} ]。
     *
     * @return bool 如果是表数组语法则返回 true
     */
    public function isTableArray(): bool;

    /**
     * 获取迭代器用于遍历数组节点
     *
     * @return Traversable<int, NodeInterface> 返回可遍历对象，用于迭代访问所有子节点
     */
    public function getIterator(): Traversable;
}

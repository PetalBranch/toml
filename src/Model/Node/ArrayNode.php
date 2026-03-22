<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Model\Node;

use ArrayIterator;
use OutOfBoundsException;
use Petalbranch\Toml\Contract\Parser\ArrayNodeInterface;
use Petalbranch\Toml\Contract\Parser\NodeInterface;
use Petalbranch\Toml\Support\Position;
use Petalbranch\Toml\Type\TomlType;
use Traversable;

/**
 * TOML 数组节点类
 *
 * 表示 TOML 中的数组结构，支持普通数组和表格数组（[[table]]）两种形式
 */
class ArrayNode extends Node implements ArrayNodeInterface
{
    /**
     * @var array<int, NodeInterface> 保存所有子节点的数组
     */
    protected array $elements = [];

    /**
     * @var bool 是否为表格数组
     */
    protected bool $declaredAsTableArray;

    /**
     * 构造函数
     *
     * @param Position|null $position 节点在源码中的位置
     * @param string $raw 原始字符串
     * @param bool $declaredAsTableArray 是否为表格数组，默认为 false
     * @param list<string>|null $leadingComments 前导注释
     * @param string|null $trailingComment 尾部注释
     */
    public function __construct(
        ?Position $position = null,
        string    $raw = "",
        bool      $declaredAsTableArray = false,
        ?array    $leadingComments = null,
        ?string   $trailingComment = null
    )
    {
        parent::__construct($position, $raw, $leadingComments, $trailingComment);
        $this->declaredAsTableArray = $declaredAsTableArray;
    }

    /**
     * 向数组末尾添加元素
     *
     * @param NodeInterface $node 要添加的节点对象
     * @return void
     */
    public function add(NodeInterface $node): void
    {
        $this->elements[] = $node;
    }


    /**
     * 获取指定索引位置的元素
     *
     * @param int $index 要获取的元素索引
     * @return NodeInterface|null 返回对应的节点，如果索引不存在则返回 null
     */
    public function get(int $index): ?NodeInterface
    {
        return $this->elements[$index] ?? null;
    }

    /**
     * 设置指定索引位置的元素
     *
     * 只能修改已存在的索引位置，如果索引不存在将抛出异常
     *
     * @param int $index 要设置的元素索引
     * @param NodeInterface $value 新的节点值
     * @return void
     * @throws OutOfBoundsException 当索引不存在时抛出异常
     */
    public function set(int $index, NodeInterface $value): void
    {
        if (!array_key_exists($index, $this->elements)) {
            throw new OutOfBoundsException(sprintf('Index %d does not exist in ArrayNode.', $index));
        }
        $this->elements[$index] = $value;

    }

    /**
     * 移除指定索引位置的元素
     *
     * 删除元素后会自动重新索引数组以保持连续的整数索引
     *
     * @param int $index 要移除的元素索引
     * @return void
     */
    public function remove(int $index): void
    {
        if (array_key_exists($index, $this->elements)) {
            unset($this->elements[$index]);
            $this->elements = array_values($this->elements);
        }
    }


    /**
     * 获取数组的元素集合
     *
     * @return list<NodeInterface> 返回包含所有数组元素的索引数组
     */
    public function getElements(): array
    {
        return array_values($this->elements);
    }


    /**
     * 检查是否为表格数组
     *
     * @return bool 如果是表格数组（[[table]] 形式）返回 true，否则返回 false
     */
    public function isTableArray(): bool
    {
        return $this->declaredAsTableArray;
    }


    /**
     * 获取数组的迭代器
     *
     * @return Traversable<int, NodeInterface> 返回遍历数组元素的迭代器
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->elements);
    }


    /**
     * 获取数组元素的数量
     *
     * @return int 返回数组中元素的个数
     */
    public function count(): int
    {
        return count($this->elements);
    }


    /**
     * 获取节点的 TOML 类型
     *
     * @return TomlType 返回数组类型枚举值
     */
    public function getType(): TomlType
    {
        return TomlType::ARRAY;
    }

    /**
     * 获取数组的值
     *
     * 递归提取所有元素的值并组成索引数组
     *
     * @return array<int, mixed> 返回包含所有元素值的索引数组
     */
    public function getValue(): array
    {
        $result = [];
        foreach ($this->elements as $element) {
            $result[] = $element->getValue();
        }
        return $result;
    }


    /**
     * 检查数组是否全部由表格节点组成
     *
     * 遍历所有元素，验证每个元素是否都是 TableNode 类型
     *
     * @return bool 如果所有元素都是 TableNode 类型返回 true，否则返回 false
     */
    public function isAllTables(): bool
    {
        foreach ($this->elements as $element) {
            if (!$element instanceof TableNode) return false;
        }
        return true;
    }
}

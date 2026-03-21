<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Model\Node;

use Petalbranch\Toml\Contract\Parser\NodeInterface;
use Petalbranch\Toml\Support\Position;
use Petalbranch\Toml\Type\TomlType;

/**
 * TOML 解析器节点抽象基类
 *
 * 所有 TOML 节点的基类，提供了节点的基本属性和通用方法
 */
abstract class Node implements NodeInterface
{

    /**
     * @param Position|null $position 节点在源码中的位置
     * @param string $raw 原始字符串
     * @param list<string>|null $leadingComments 前导注释
     * @param string|null $trailingComment 尾部注释
     */
    public function __construct(
        protected ?Position $position,
        protected string    $raw = '',
        protected ?array    $leadingComments = null,
        protected ?string   $trailingComment = null,
    )
    {
    }


    /**
     * 获取节点的原始字符串
     *
     * @return string 原始字符串
     */
    public function getRaw(): string
    {
        return $this->raw;
    }


    /**
     * 获取节点在源码中的位置
     *
     * @return Position 位置信息对象
     */
    public function getPosition(): Position
    {
        return $this->position ?? new Position(0, 0);
    }


    /**
     * 获取节点的前导注释列表
     *
     * @return list<string>|null 前导注释数组，如果没有则返回 null
     */
    public function getLeadingComments(): ?array
    {
        return $this->leadingComments;
    }


    /**
     * 获取节点的尾部注释
     *
     * @return string|null 尾部注释字符串，如果没有则返回 null
     */
    public function getTrailingComment(): ?string
    {
        return $this->trailingComment;
    }

    /**
     * 设置节点的前导注释
     *
     * @param list<string>|null $comments 前导注释数组
     * @return void
     */
    public function setLeadingComments(?array $comments): void
    {
        $this->leadingComments = $comments;
    }

    /**
     * 设置节点的尾部注释
     *
     * @param string|null $comment 尾部注释字符串
     * @return void
     */
    public function setTrailingComment(?string $comment): void
    {
        $this->trailingComment = $comment;
    }


    /**
     * 获取节点的 TOML 类型
     *
     * 由具体子类实现以返回对应的类型
     *
     * @return TomlType TOML 类型枚举值
     */
    abstract public function getType(): TomlType;

    /**
     * 获取节点的值
     *
     * 由具体子类实现以返回解析后的值
     *
     * @return mixed 节点的值，类型由具体子类决定
     */
    abstract public function getValue(): mixed;
}

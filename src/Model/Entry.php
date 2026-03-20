<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Model;

use Petalbranch\Toml\Contract\Parser\NodeInterface;

/**
 * 条目类
 *
 * 表示表节点中的键值对条目
 *
 * @package Petalbranch\Toml\Model
 */
final class Entry
{
    /**
     * 构造函数
     *
     * @param string $key 键名
     * @param NodeInterface $value 节点值对象
     */
    public function __construct(
        public string        $key,
        public NodeInterface $value,
    )
    {
    }
}

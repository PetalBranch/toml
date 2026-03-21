<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Contract\Dumper;

use Petalbranch\Toml\Contract\Parser\NodeInterface;

/**
 * TOML 节点转储器接口
 *
 * 定义将语法树节点转换为 TOML 格式字符串的契约。
 * 实现该接口的类负责将解析器生成的节点对象序列化为格式化的 TOML 字符串。
 * 这种分离的设计允许灵活地处理不同类型的节点（如键值对、表、数组等）。
 */
interface NodeDumperInterface
{
    /**
     * 转储节点为 TOML 字符串
     *
     * 将给定的语法树节点转换为相应的 TOML 格式字符串表示。
     *
     * @param NodeInterface $node 要转储的语法树节点
     * @return string 生成的 TOML 格式字符串
     */
    public function dump(NodeInterface $node): string;
}

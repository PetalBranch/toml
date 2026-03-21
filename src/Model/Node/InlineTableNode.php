<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Model\Node;

/**
 * TOML 内联表格节点类
 *
 * 继承自 TableNode，表示 TOML 中的内联表格结构
 * 内联表使用花括号 {} 包裹
 */
class InlineTableNode extends TableNode
{
    /**
     * 检查表是否为内联格式
     *
     * @return bool 返回 true，表示这是内联表
     */
    public function isInline(): bool
    {
        return true;
    }
}

<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Type;

/**
 * TOML 节点类型枚举
 *
 * 定义了 TOML 解析过程中可能遇到的各种节点类型
 */
enum NodeType
{
    /** 表格节点类型 */
    case TABLE;

    /** 键值对节点类型 */
    case KEY_VALUE;

    /** 数组节点类型 */
    case ARRAY;

    /** 内联表格节点类型 */
    case INLINE_TABLE;

    /** 值节点类型 */
    case VALUE;

    /** 注释节点类型 */
    case COMMENT;
}

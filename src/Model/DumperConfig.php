<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Model;

/**
 * TOML 转储器配置类
 *
 * 该类用于存储和管理 TOML 序列化过程中的配置选项。
 * 它是一个不可变的最终类，包含控制输出格式的各种属性，
 * 如缩进大小、换行符类型和内联表使用策略。
 * 这些配置会影响生成的 TOML 文件的可读性和格式。
 */
final class DumperConfig
{
    /**
     * 缩进空格数
     *
     * 控制嵌套结构的缩进级别，每个级别使用的空格数量。
     * 默认值为 4 个空格。
     */
    public int $indent = 4;

    /**
     * 换行符
     *
     * 指定在生成的 TOML 字符串中使用的换行字符序列。
     * 默认值为 Unix 风格的换行符 "\n"。
     */
    public string $newline = "\n";

    /**
     * 内联表标志
     *
     * 控制是否使用内联表语法来表示简单的表结构。
     * 当设置为 true 时，简单的键值对集合将被格式化为内联表。
     * 默认值为 false，表示使用标准的多行表语法。
     */
    public bool $inlineTable = false;

    /**
     * 内联表最大嵌套深度
     *
     * 控制内联表可以嵌套的最大层级深度。
     * 当表的嵌套深度超过此值时，即使满足其他条件也不会使用内联表语法。
     * 默认值为 2 层。
     */
    public int $inlineTableMaxDepth = 2;

    /**
     * 内联表最大项目数
     *
     * 控制内联表可以包含的最大键值对数量。
     * 当表中的项目数超过此值时，即使满足其他条件也不会使用内联表语法。
     * 默认值为 8 个项目。
     */
    public int $inlineTableMaxItems = 8;
}

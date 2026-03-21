<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Dumper;

use Petalbranch\Toml\Contract\Dumper\NodeDumperInterface;
use Petalbranch\Toml\Contract\Parser\NodeInterface;
use Petalbranch\Toml\Exception\DumpException;
use Petalbranch\Toml\Model\DumperConfig;
use Petalbranch\Toml\Model\Node\ArrayNode;
use Petalbranch\Toml\Model\Node\TableNode;
use Petalbranch\Toml\Support\TomlKeyHelper;

/**
 * TOML 节点转储器
 *
 * 负责将抽象语法树（AST）节点序列化为格式化的 TOML 字符串。
 * 实现了 NodeDumperInterface 接口，提供递归遍历节点树并生成
 * 符合规范的 TOML 输出的功能。支持普通表、内联表和表数组的渲染，
 * 根据配置决定输出格式。
 */
class NodeDumper implements NodeDumperInterface
{
    private ValueDumper $valueDumper;

    /**
     * 构造函数
     *
     * 创建一个新的 NodeDumper 实例。
     *
     * @param DumperConfig $config 转储配置对象，控制输出格式
     */
    public function __construct(
        private readonly DumperConfig $config
    )
    {
        $this->valueDumper = new ValueDumper(); // ValueDumper 不再需要 config
    }

    /**
     * 转储节点为 TOML 字符串
     *
     * 将给定的语法树节点转换为相应的 TOML 格式字符串。
     * 仅接受 TableNode 类型的根节点。
     *
     * @param NodeInterface $node 要转储的语法树节点
     * @return string 生成的 TOML 格式字符串
     * @throws DumpException 当根节点不是 TableNode 时抛出异常
     */
    public function dump(NodeInterface $node): string
    {
        if (!$node instanceof TableNode) {
            throw new DumpException("NodeDumper can only dump TableNode at the root.");
        }
        return $this->dumpTable($node, [], false); // 根文档不需要打印表头
    }

    /**
     * 转储表节点为 TOML 字符串
     *
     * 将表节点及其子节点递归转换为格式化的 TOML 字符串。
     * 根据配置和嵌套深度决定是否使用内联表语法。
     * 支持普通键值对、嵌套表和表数组的渲染。
     *
     * @param TableNode $node 要转储的表节点
     * @param array $path 当前节点在文档中的路径（用于生成完整键名）
     * @param bool $emitHeader 是否输出表头（[section]）
     * @return string 生成的 TOML 格式字符串
     */
    public function dumpTable(TableNode $node, array $path = [], bool $emitHeader = true): string
    {
        $output = "";
        $nl = $this->config->newline;
        $entries = $node->getEntries();

        // --- 第一步：渲染表头 ---
        if ($emitHeader && !empty($path)) {
            $fullPath = implode('.', array_map([TomlKeyHelper::class, 'format'], $path));
            $output .= "[$fullPath]" . $nl;
        }

        // --- 第二步：渲染内联表、数组及普通键值对 ---
        foreach ($entries as $entry) {
            $child = $entry->value;
            $isInline = false;

            if ($child instanceof TableNode) {
                $depth = count($path) + 1; // 当前处于哪一层嵌套
                $itemCount = count($child->getEntries());
                // 核心：由 Dumper 决定是否渲染为 Inline
                if ($this->config->inlineTable && $depth <= $this->config->inlineTableMaxDepth && $itemCount <= $this->config->inlineTableMaxItems) {
                    $isInline = true;
                }
            } elseif ($child instanceof ArrayNode) {
                // 表格数组 [[table]] 保持 Block 渲染，除非你强制想把它压缩
                if (!($child->isAllTables() && count($child->getElements()) > 0)) {
                    $isInline = true; // 普通数组 [1,2,3] 必定是 Inline 的
                }
            } else {
                $isInline = true; // ValueNode 必定是 Inline 的
            }

            if ($isInline) {
                $output .= sprintf(
                    "%s = %s%s",
                    TomlKeyHelper::format($entry->key),
                    $this->dumpInlineNode($child), // 调用内置的 Inline 排版器
                    $nl
                );
            }
        }

        // --- 第三步：渲染独立的子表 (Block Tables) ---
        foreach ($entries as $entry) {
            $child = $entry->value;
            if ($child instanceof TableNode) {
                $depth = count($path) + 1;
                $itemCount = count($child->getEntries());
                // 如果刚才已经作为 Inline 渲染过了，这里直接跳过！
                if ($this->config->inlineTable && $depth <= $this->config->inlineTableMaxDepth && $itemCount <= $this->config->inlineTableMaxItems) {
                    continue;
                }
                if ($output !== "" && !str_ends_with($output, $nl . $nl)) {
                    $output .= $nl; // Block 之间空一行，更美观
                }
                $output .= $this->dumpTable($child, array_merge($path, [$entry->key]));
            }
        }

        // --- 第四步：渲染表数组 (Array of Tables) ---
        foreach ($entries as $entry) {
            $child = $entry->value;
            if ($child instanceof ArrayNode && $child->isAllTables() && count($child->getElements()) > 0) {
                foreach ($child->getElements() as $tableItem) {
                    /** @var TableNode $tableItem */
                    if ($output !== "" && !str_ends_with($output, $nl . $nl)) $output .= $nl;

                    $fullPath = implode('.', array_map([TomlKeyHelper::class, 'format'], array_merge($path, [$entry->key])));
                    $output .= "[[$fullPath]]" . $nl;
                    // 表数组内部的键值对，不需要再次打印自身的表头，所以 emitHeader=false
                    $output .= $this->dumpTable($tableItem, array_merge($path, [$entry->key]), false);
                }
            }
        }

        return $output;
    }

    /**
     * 将 AST 节点渲染为单行内联字符串
     *
     * 递归地将节点及其子节点转换为单行的内联格式。
     * 用于生成内联表 { ... } 和内联数组 [ ... ] 的内容。
     *
     * @param NodeInterface $node 要渲染的节点
     * @return string 单行内联格式的字符串表示
     */
    private function dumpInlineNode(NodeInterface $node): string
    {
        if ($node instanceof TableNode) {
            $parts = [];
            foreach ($node->getEntries() as $entry) {
                $parts[] = sprintf('%s = %s', TomlKeyHelper::format($entry->key), $this->dumpInlineNode($entry->value));
            }
            return '{ ' . implode(', ', $parts) . ' }';
        }

        if ($node instanceof ArrayNode) {
            $parts = [];
            foreach ($node->getElements() as $element) {
                $parts[] = $this->dumpInlineNode($element);
            }
            return '[' . implode(', ', $parts) . ']';
        }

        // 最终的叶子节点，交给 ValueDumper 处理
        return $this->valueDumper->dump($node->getValue());
    }
}

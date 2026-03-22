<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Model;

/**
 * 键路径类
 *
 * 表示 TOML 表中的键路径，由多个路径段组成
 * 用于访问嵌套的表结构
 *
 * @package Petalbranch\Toml\Model
 */
final readonly class KeyPath
{
    /**
     * 构造函数
     *
     * @param array<string> $segments 路径段数组，每个元素代表路径中的一个层级
     */
    public function __construct(
        public array $segments
    )
    {
    }


    /**
     * 从字符串创建键路径对象
     *
     * 将点号分隔的路径字符串转换为 KeyPath 对象
     *
     * @param string $path 路径字符串，例如 "table.subtable.key"
     * @param string $separator 路径分隔符，默认为点号
     * @return self 返回创建的键路径对象
     */
    public static function fromString(string $path, string $separator = '.'): self
    {
        assert($separator !== '');
        return new self(explode($separator, $path));
    }


    /**
     * 追加新的路径段
     *
     * 在当前路径末尾添加一个新的路径段，返回新的键路径对象
     *
     * @param string $segment 要添加的路径段
     * @return KeyPath 返回包含新路径段的键路径对象
     */
    public function append(string $segment): KeyPath
    {
        return new self([...$this->segments, $segment]);
    }


    /**
     * 获取完整路径的字符串表示
     *
     * 自动将非裸键的段包裹在双引号中，并转义内部的双引号和反斜杠。
     * 确保输出的字符串是合法的 TOML 路径，可被 fromString() 正确解析。
     */
    public function __toString(): string
    {
        $processedSegments = array_map(function (string $segment): string {
            // 只有完全符合裸键规则 (A-Za-z0-9_-) 才不加引号
            if ($this->isBareKey($segment)) return $segment;

            // 否则加双引号，并转义内部特殊字符
            return '"' . $this->escapeString($segment) . '"';
        }, $this->segments);

        return implode('.', $processedSegments);
    }

    /**
     * 判断是否为合法的 TOML 裸键
     *
     * 规则：仅包含 A-Za-z0-9_-
     * 注意：空字符串不是合法键，必须加引号（虽然构造函数通常已禁止空串）
     */
    private function isBareKey(string $segment): bool
    {
        if ($segment === '') return false;
        return preg_match('/^[A-Za-z0-9_-]+$/', $segment) === 1;
    }

    /**
     * 转义双引号字符串中的特殊字符
     */
    private function escapeString(string $segment): string
    {
        // 必须首先转义反斜杠，否则会导致双重转义错误
        // 然后转义双引号
        return str_replace(
            ['\\', '"'],
            ['\\\\', '\\"'],
            $segment
        );
    }

}

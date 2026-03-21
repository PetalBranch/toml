<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Contract\Dumper;

/**
 * TOML 可序列化接口
 *
 * 定义对象转换为 TOML 格式字符串的契约。
 * 实现该接口的类必须提供将其自身状态转换为 TOML 字符串表示的方法。
 * 这允许自定义对象直接控制其在 TOML 序列化过程中的输出格式。
 */
interface TomlSerializableInterface
{
    /**
     * 转换为 TOML 格式的字符串
     *
     * 将对象的状态转换为符合 TOML 规范的字符串表示形式。
     *
     * @return string 对象的 TOML 字符串表示
     */
    public function toToml(): string;
}

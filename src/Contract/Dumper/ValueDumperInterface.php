<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Contract\Dumper;

/**
 * TOML 值转储器接口
 *
 * 定义将单个值转换为 TOML 格式字符串的契约。
 * 实现该接口的类负责处理各种数据类型（如标量、数组、对象等）
 * 并将其序列化为符合 TOML 规范的字符串表示。
 * 这种单一职责的设计允许灵活扩展和组合不同的转储策略。
 */
interface ValueDumperInterface
{
    /**
     * 转储值为 TOML 字符串
     *
     * 将给定的任意类型的值转换为相应的 TOML 格式字符串。
     *
     * @param mixed $value 要转储的数据值，可以是任何支持的类型
     * @return string 生成的 TOML 格式字符串
     */
    public function dump(mixed $value): string;
}

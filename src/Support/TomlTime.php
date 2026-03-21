<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Support;

use Petalbranch\Toml\Contract\Type\TomlTemporalInterface;
use Stringable;

/**
 * TOML 时间类型实现
 *
 * 该类表示 TOML 规范中的本地时间类型（Local Time），用于处理不包含日期部分的时间值。
 * 它实现了 TomlTemporalInterface 接口，确保可以被正确识别为时间类型，
 * 同时实现了 Stringable 接口以支持字符串转换。
 * 该类是只读的（readonly），一旦创建就不能修改其属性。
 */
readonly final class TomlTime implements TomlTemporalInterface, Stringable
{

    /**
     * 构造函数
     *
     * 创建一个新的 TomlTime 实例。
     *
     * @param int $hour 小时部分，范围应为 0-23
     * @param int $minute 分钟部分，范围应为 0-59
     * @param int $second 秒数部分，范围应为 0-60（支持闰秒）
     * @param int $microsecond 微秒部分，默认为 0，范围应为 0-999999
     */
    public function __construct(
        public int $hour,
        public int $minute,
        public int $second,
        public int $microsecond = 0
    )
    {
    }

    /**
     * 格式化为 TOML 格式的时间字符串
     *
     * 将当前时间格式化为 TOML 规范要求的本地时间格式。
     * 基本格式为 HH:MM:SS，如果微秒部分非零，则附加小数部分，
     * 并去除末尾多余的零。
     *
     * @return string 格式化后的时间字符串，如 "14:30:45" 或 "14:30:45.123"
     */
    public function formatToml(): string
    {
        $base = sprintf('%02d:%02d:%02d', $this->hour, $this->minute, $this->second);

        if ($this->microsecond > 0) {
            $fraction = rtrim(sprintf('%06d', $this->microsecond), '0');
            return $base . '.' . $fraction;
        }

        return $base;
    }

    /**
     * 字符串魔术方法
     *
     * 允许对象在字符串上下文中自动转换为字符串表示。
     * 使用 formatToml() 方法提供格式化的字符串输出。
     *
     * @return string 对象的字符串表示
     */
    public function __toString(): string
    {
        return $this->formatToml();
    }
}

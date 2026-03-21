<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Support;

use DateTimeImmutable;
use Petalbranch\Toml\Contract\Dumper\TomlSerializableInterface;
use Petalbranch\Toml\Contract\Type\TomlTemporalInterface;
use Stringable;

/**
 * TOML 日期类型实现
 *
 * 该类表示 TOML 规范中的本地日期类型（Local Date），用于处理不包含时间部分的日期值。
 * 它实现了 TomlTemporalInterface 接口，确保可以被正确识别为时间类型。
 * 内部使用 DateTimeImmutable 对象存储日期，并强制将时间部分设置为 00:00:00.000，
 * 以确保只表示纯粹的日期概念。
 */
readonly final class TomlDate implements TomlTemporalInterface, Stringable, TomlSerializableInterface
{
    private DateTimeImmutable $date;

    /**
     * 年份值
     *
     * 存储日期的年份部分，例如 2023
     */
    public int $year;

    /**
     * 月份值
     *
     * 存储日期的月份部分，范围为 1-12
     */
    public int $month;

    /**
     * 日期值
     *
     * 存储日期的日部分，范围为 1-31，具体取决于月份和闰年情况
     */
    public int $day;

    /**
     * 构造函数
     *
     * 创建一个新的 TomlDate 实例。
     * 在构造过程中，输入的 DateTimeImmutable 对象的时间部分会被设置为 00:00:00.000，
     * 以确保只保留日期信息。
     *
     * @param DateTimeImmutable $date 用于初始化的日期时间对象
     */
    public function __construct(DateTimeImmutable $date)
    {
        // 将时间移至00:00:00.000
        $this->date = $date->setTime(0, 0);
        $this->year = (int)$this->date->format('Y');
        $this->month = (int)$this->date->format('m');
        $this->day = (int)$this->date->format('d');
    }

    /**
     * 获取内部存储的日期对象
     *
     * 返回一个只包含日期部分（时间固定为 00:00:00.000）的 DateTimeImmutable 对象。
     *
     * @return DateTimeImmutable 当前实例的日期对象
     */
    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    /**
     * 格式化为 TOML 格式的日期字符串
     *
     * 将当前日期格式化为 TOML 规范要求的本地日期格式（YYYY-MM-DD）。
     *
     * @return string 格式化后的日期字符串，如 "2023-12-25"
     */
    public function formatToml(): string
    {
        return $this->date->format('Y-m-d');
    }

    /**
     * 转换为 TOML 格式的字符串
     *
     * 将当前日期对象转换为符合 TOML 规范的字符串表示。
     * 该方法实现了 TomlSerializableInterface 接口，允许对象
     * 在序列化时被正确处理。
     *
     * @return string 格式化后的 TOML 日期字符串
     */
    public function __toString(): string
    {
        return $this->formatToml();
    }

    /**
     * 转换为 TOML 格式的字符串
     *
     * 将当前日期对象转换为符合 TOML 规范的字符串表示。
     * 该方法实现了 TomlSerializableInterface 接口，允许对象
     * 在序列化时被正确处理。
     *
     * @return string 格式化后的 TOML 日期字符串
     */
    public function toToml(): string
    {
        return $this->formatToml();
    }
}

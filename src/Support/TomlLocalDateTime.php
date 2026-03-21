<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Support;

use DateTimeImmutable;
use Petalbranch\Toml\Contract\Dumper\TomlSerializableInterface;
use Petalbranch\Toml\Contract\Type\TomlTemporalInterface;
use Stringable;

/**
 * TOML 本地日期时间类型实现
 *
 * 该类表示 TOML 规范中的本地日期时间类型（Local Date-Time），用于处理不包含时区信息的日期时间值。
 * 它实现了 TomlTemporalInterface 接口，确保可以被正确识别为时间类型，
 * 同时实现了 Stringable 接口以支持字符串转换。
 * 内部使用 DateTimeImmutable 对象存储完整的日期时间信息，并提供属性访问年、月、日、时、分、秒和纳秒。
 */
readonly final class TomlLocalDateTime implements TomlTemporalInterface, Stringable, TomlSerializableInterface
{
    /**
     * 年份值
     *
     * 存储日期时间的年份部分
     */
    public int $year;

    /**
     * 月份值
     *
     * 存储日期时间的月份部分，范围为 1-12
     */
    public int $month;

    /**
     * 日期值
     *
     * 存储日期时间的日部分，范围为 1-31，具体取决于月份和闰年情况
     */
    public int $day;

    /**
     * 小时值
     *
     * 存储日期时间的小时部分，范围为 0-23
     */
    public int $hour;

    /**
     * 分钟值
     *
     * 存储日期时间的分钟部分，范围为 0-59
     */
    public int $minute;

    /**
     * 秒数值
     *
     * 存储日期时间的秒数部分，范围为 0-60（支持闰秒）
     */
    public int $second;

    /**
     * 纳秒值
     *
     * 存储日期时间的小数部分（纳秒），范围为 0-999999
     */
    public int $nanosecond;

    private DateTimeImmutable $dateTime;

    /**
     * 构造函数
     *
     * 创建一个新的 TomlLocalDateTime 实例。
     *
     * @param DateTimeImmutable $dateTime 用于初始化的日期时间对象
     */
    public function __construct(DateTimeImmutable $dateTime)
    {
        $this->dateTime = $dateTime;

        // 初始化公共属性
        $this->year = (int)$this->dateTime->format('Y');
        $this->month = (int)$this->dateTime->format('m');
        $this->day = (int)$this->dateTime->format('d');
        $this->hour = (int)$this->dateTime->format('H');
        $this->minute = (int)$this->dateTime->format('i');
        $this->second = (int)$this->dateTime->format('s');

        // 从微秒计算纳秒（DateTimeImmutable 只提供到微秒）
        $microsecond = (int)$this->dateTime->format('u');
        $this->nanosecond = $microsecond * 1000;
    }

    /**
     * 获取内部存储的日期时间对象
     *
     * 返回用于存储完整日期时间信息的 DateTimeImmutable 对象。
     *
     * @return DateTimeImmutable 当前实例的日期时间对象
     */
    public function getDateTime(): DateTimeImmutable
    {
        return $this->dateTime;
    }

    /**
     * 格式化为 TOML 格式的日期时间字符串
     *
     * 将当前日期时间格式化为 TOML 规范要求的本地日期时间格式（YYYY-MM-DDTHH:MM:SS）。
     * 如果有小数秒，则包含小数部分。
     *
     * @return string 格式化后的日期时间字符串，如 "2023-12-25T14:30:45" 或 "2023-12-25T14:30:45.123"
     */
    public function formatToml(): string
    {
        $u = $this->dateTime->format('u');
        $fraction = '';
        if ((int)$u > 0) {
            $fraction = '.' . rtrim($u, '0');
        }
        return $this->dateTime->format('Y-m-d\\TH:i:s') . $fraction;
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

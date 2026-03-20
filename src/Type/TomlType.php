<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Type;

/**
 * TOML 数据类型枚举
 *
 * 定义 TOML 格式支持的所有数据类型
 *
 * @package Petalbranch\Toml\Type
 */
enum TomlType
{
    /**
     * 字符串
     *
     * @see https://toml.io/en/v1.1.0#string Toml.String
     */
    case STRING;

    /**
     * 整数
     *
     * @see https://toml.io/en/v1.1.0#integer Toml.Integer
     */
    case INTEGER;

    /**
     * 浮点数
     *
     * @see https://toml.io/en/v1.1.0#float Toml.Float
     */
    case FLOAT;

    /**
     * 布尔值
     *
     * @see https://toml.io/en/v1.1.0#boolean Toml.Boolean
     */

    case BOOLEAN;

    /**
     * 带偏移量的日期时间
     *
     * @see https://toml.io/en/v1.1.0#offset-date-time Toml.OffsetDateTime
     */
    case OFFSET_DATETIME;

    /**
     * 本地日期时间
     *
     * @see https://toml.io/en/v1.1.0#local-date-time Toml.LocalDateTime
     */
    case LOCAL_DATETIME;


    /**
     * 本地日期
     *
     * @see https://toml.io/en/v1.1.0#local-date Toml.Date
     */
    case LOCAL_DATE;


    /**
     * 本地时间
     *
     * @see https://toml.io/en/v1.1.0#local-time Toml.Time
     */
    case LOCAL_TIME;


    /**
     * 数组
     *
     * @see https://toml.io/en/v1.1.0#array Toml.Array
     */
    case ARRAY;

    /**
     * 表
     *
     * @see https://toml.io/en/v1.1.0#table Toml.Table
     */
    case TABLE;

    /**
     * 表数组
     *
     * @deprecated 建议使用`ArrayNode&lt;TableNode&gt;` 后期发版时会删除此枚举
     * @see https://toml.io/en/v1.1.0#array-of-tables Toml.ArrayOfTable
     */
    case ARRAY_OF_TABLE;


    /**
     * 判断是否为日期时间类型
     *
     * 检查当前类型是否属于日期时间相关类型（带偏移量的日期时间、本地日期时间、本地日期、本地时间）
     *
     * @return bool 如果是日期时间类型返回 true，否则返回 false
     */
    public function isDateTime(): bool
    {
        return in_array($this, [
            self::OFFSET_DATETIME,
            self::LOCAL_DATETIME,
            self::LOCAL_DATE,
            self::LOCAL_TIME,
        ], true);
    }


    /**
     * 判断是否为标量类型
     *
     * 检查当前类型是否属于标量类型（字符串、整数、浮点数、布尔值）或日期时间类型
     *
     * @return bool 如果是标量类型或日期时间类型返回 true，否则返回 false
     */
    public function isScalar(): bool
    {
        return in_array($this, [
                self::STRING,
                self::INTEGER,
                self::FLOAT,
                self::BOOLEAN,
            ], true) || $this->isDateTime();
    }

}

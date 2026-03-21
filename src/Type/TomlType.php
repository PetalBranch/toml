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

}

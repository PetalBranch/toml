<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Contract\Dumper;

/**
 * TOML 类型格式化器接口
 *
 * 定义处理特定数据类型的格式化契约，扩展了基本的值格式化功能。
 * 除了判断是否可以处理特定值和执行格式化外，还提供了优先级机制，
 * 用于在多个格式化器都能处理同一类型值时决定使用哪个格式化器。
 * 这种设计支持灵活的格式化策略选择和扩展。
 */
interface TypeFormatterInterface
{
    /**
     * 判断是否可以处理指定的值
     *
     * 检查当前访问器是否支持处理给定的数据值。
     *
     * @param mixed $value 待检查的数据值
     * @return bool 如果可以处理返回 true，否则返回 false
     */
    public function canHandle(mixed $value): bool;

    /**
     * 格式化值为 TOML 字符串
     *
     * 将给定的值转换为符合 TOML 规范的字符串表示形式。
     *
     * @param mixed $value 要格式化的数据值
     * @return string 格式化后的 TOML 字符串
     */
    public function format(mixed $value): string;


    /**
     * 获取格式化器的优先级
     *
     * 返回当前格式化器的优先级数值，用于在多个格式化器都能处理
     * 同一类型值时决定使用哪个格式化器。数值越大表示优先级越高。
     *
     * @return int 优先级数值
     */
    public function priority(): int;
}

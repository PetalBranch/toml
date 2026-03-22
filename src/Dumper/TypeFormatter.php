<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Dumper;

use InvalidArgumentException;
use Petalbranch\Toml\Contract\Dumper\TomlSerializableInterface;
use Petalbranch\Toml\Contract\Dumper\TypeFormatterInterface;
use Stringable;

/**
 * 值访问器
 *
 * 该类实现了 TypeVisitorInterface 接口，负责处理基本数据类型的格式化。
 * 它能够处理标量值（字符串、数字、布尔值）、null 值以及实现了特定接口的对象。
 * 对于数组类型，它会返回 false 表示无法处理，以便由专门的数组处理器处理。
 * 提供字符串转义功能，确保输出的 TOML 字符串符合规范。
 */
class TypeFormatter implements TypeFormatterInterface
{

    /**
     * 判断是否可以处理指定的值
     *
     * 检查当前访问器是否支持处理给定的数据值。
     * 本实现仅处理非数组类型的值，因为数组需要特殊的表或数组处理逻辑。
     *
     * @param mixed $value 待检查的数据值
     * @return bool 如果可以处理返回 true（非数组类型），否则返回 false
     */
    public function canHandle(mixed $value): bool
    {
        // 只要不是普通的 array (因为 array 可能是 Table 或 Array，需要专门处理)
        return is_scalar($value) || $value === null;
    }

    /**
     * 格式化值为 TOML 字符串
     *
     * 将给定的值转换为符合 TOML 规范的字符串表示形式。
     * 支持多种数据类型：null、布尔值、整数、浮点数、字符串以及实现了特定接口的对象。
     *
     * @param mixed $value 要格式化的数据值
     * @return string 格式化后的 TOML 字符串
     */
    public function format(mixed $value): string
    {
        if ($value === null) return 'null'; // TOML 1.1.0 提案或视业务而定，通常 TOML 不支持 null
        if (is_bool($value)) return $value ? 'true' : 'false';

        if (is_int($value)) return (string)$value;

        if (is_float($value)) {
            if (is_infinite($value)) return $value > 0 ? 'inf' : '-inf';
            if (is_nan($value)) return 'nan';
            return str_contains((string)$value, '.') ? (string)$value : $value . '.0';
        }

        if ($value instanceof TomlSerializableInterface) {
            return $value->toToml();
        }

        if (is_string($value)) {
            return $this->escapeString($value);
        }

        if ($value instanceof Stringable) {
            return (string)$value;
        }

        throw new InvalidArgumentException("Cannot format value of type " . get_debug_type($value));
    }

    /**
     * 转义字符串值
     *
     * 将普通字符串转换为 TOML 格式的引用字符串，对特殊字符进行转义。
     * 支持基本的转义序列：反斜杠、双引号、退格、换页、换行、回车和制表符。
     *
     * @param string $s 需要转义的原始字符串
     * @return string 转义后的 TOML 字符串（包含首尾双引号）
     */
    private function escapeString(string $s): string
    {
        // 简单的转义逻辑，后续我们需要完善它以支持多行字符串判断
        $search = ["\\", "\"", "\b", "\f", "\n", "\r", "\t"];
        $replace = ["\\\\", "\\\"", "\\b", "\\f", "\\n", "\\r", "\\t"];
        return '"' . str_replace($search, $replace, $s) . '"';
    }

    /**
     * 获取格式化器的优先级
     *
     * 返回当前类型格式化器的优先级数值。
     * 优先级用于在多个格式化器都能处理同一类型值时决定使用哪个格式化器。
     * 数值越大表示优先级越高，当前实现返回固定优先级值10。
     *
     * @return int 格式化器的优先级数值
     */
    public function priority(): int
    {
        return 10;
    }

}

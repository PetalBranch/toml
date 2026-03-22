<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Dumper;

use Petalbranch\Toml\Contract\Dumper\TomlSerializableInterface;
use Petalbranch\Toml\Contract\Dumper\TypeFormatterInterface;
use Petalbranch\Toml\Contract\Dumper\ValueDumperInterface;
use Petalbranch\Toml\Exception\DumpException;
use Petalbranch\Toml\Support\TomlKeyHelper;
use Stringable;

/**
 * TOML 值转储器
 *
 * 负责将单个值转换为 TOML 格式字符串。
 * 支持通过注册多个格式化器来处理不同类型的值，
 * 优先使用高优先级的格式化器。提供对基本类型、
 * 字符串和浮点数的特殊处理，确保输出符合 TOML 规范。
 */
class ValueDumper implements ValueDumperInterface
{
    /** @var TypeFormatterInterface[] 存储已注册的类型格式化器，按优先级排序 */
    private array $formatters = [];

    /**
     * 添加格式化器
     *
     * 将指定的类型格式化器添加到格式化器列表中，并根据优先级保持排序。
     * 优先级高的格式化器会排在前面，在处理值时会被优先选择。
     * 如果相同的格式化器实例已存在，则不会重复添加。
     *
     * @param TypeFormatterInterface $formatter 要添加的类型格式化器
     */
    public function addFormatter(TypeFormatterInterface $formatter): void
    {
        if (in_array($formatter, $this->formatters, true)) return;

        $priority = $formatter->priority();
        $insertIndex = 0;
        foreach ($this->formatters as $index => $existingFormatter) {
            if ($priority > $existingFormatter->priority()) break;
            $insertIndex = $index + 1;
        }
        array_splice($this->formatters, $insertIndex, 0, [$formatter]);
    }

    /**
     * 转储值为 TOML 字符串
     *
     * 将给定的值转换为 TOML 格式的字符串表示。
     * 首先检查是否实现了 TomlSerializableInterface 接口，
     * 然后尝试使用已注册的格式化器进行处理，最后使用默认规则处理基本类型。
     *
     * @param mixed $value 要转储的数据值
     * @return string 生成的 TOML 格式字符串
     */
    public function dump(mixed $value): string
    {
        if ($value instanceof TomlSerializableInterface) {
            return $value->toToml();
        }

        foreach ($this->formatters as $formatter) {
            if ($formatter->canHandle($value)) {
                return $formatter->format($value);
            }
        }

        // 3. 基础标量处理
        return match (true) {
            is_string($value) => $this->dumpString($value),
            is_bool($value) => $value ? 'true' : 'false',
            is_float($value) => $this->dumpFloat($value),
            is_int($value), $value instanceof Stringable => (string)$value,
            $value === null => throw DumpException::unsupportedType('null'),
            default => throw new DumpException('Unsupported type: ' . get_debug_type($value)),
        };
    }

    /**
     * 转储字符串值
     *
     * 将字符串值转换为符合 TOML 规范的引用字符串格式。
     * 使用 TomlKeyHelper 的 escapeString 方法处理特殊字符的转义。
     *
     * @param string $value 要转储的字符串值
     * @return string 转义后的 TOML 字符串
     */
    private function dumpString(string $value): string
    {
        return TomlKeyHelper::escapeString($value);
    }

    /**
     * 转储浮点数值
     *
     * 将浮点数值转换为符合 TOML 规范的字符串表示。
     * 特殊处理无限大和非数字值，使用 json_encode 确保精度不丢失，
     * 并为整数形式的浮点数补上 .0 后缀。
     *
     * @param float $value 要转储的浮点数值
     * @return string 格式化后的浮点数字符串
     */
    private function dumpFloat(float $value): string
    {
        // 1. 处理特殊的无限大和非数字 (必须全小写)
        if (is_infinite($value)) {
            return $value > 0 ? 'inf' : '-inf';
        }
        if (is_nan($value)) {
            return 'nan';
        }

        // 使用 json_encode 获取无损精度的字符串表示
        // 规避了 (string) 强转时默认 14 位精度的截断问题
        $str = (string)json_encode($value);

        // 2. 补齐 .0 逻辑
        // 注意这里将 stripos 的判断改为了更为严谨的 === false
        if (!str_contains($str, '.') && stripos($str, 'e') === false) {
            $str .= '.0';
        }

        return $str;
    }
}

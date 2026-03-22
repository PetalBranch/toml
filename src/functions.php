<?php
declare(strict_types=1);

use Petalbranch\Toml\Contract\Exception\ParseExceptionInterface;
use Petalbranch\Toml\Exception\DumpException;
use Petalbranch\Toml\Toml;
use Petalbranch\Toml\Model\DumperConfig;

if (!function_exists('toml_decode')) {
    /**
     * 将 TOML 格式的字符串解析为 PHP 数组
     *
     * @param string $toml 需要解析的 TOML 格式字符串
     * @return array<string, mixed> 解析后生成的关联数组
     * @throws ParseExceptionInterface 当 TOML 格式不合法时，抛出包含精确定位的异常
     */
    function toml_decode(string $toml): array
    {
        return Toml::parse($toml);
    }
}

if (!function_exists('toml_encode')) {
    /**
     * 将 PHP 数据编码为 TOML 格式的字符串
     *
     * @param mixed $value 需要编码的 PHP 数组或实现序列化接口的对象
     * @param DumperConfig|null $config 可选的转储格式化配置（如控制缩进、等号对齐等）
     * @return string 编码后生成的纯 TOML 字符串
     * @throws DumpException 当遇到无法被序列化为 TOML 的数据类型（如闭包）时抛出异常
     */
    function toml_encode(mixed $value, ?DumperConfig $config = null): string
    {
        return Toml::dump($value, $config);
    }
}

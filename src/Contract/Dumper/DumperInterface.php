<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Contract\Dumper;

use Petalbranch\Toml\Model\DumperConfig;


/**
 * TOML 转储器接口
 *
 * 定义将数据结构转换为 TOML 格式字符串的契约。
 * 实现该接口的类应提供将任意数据序列化为 TOML 字符串的功能，
 * 支持通过配置对象自定义输出格式，并能将结果写入文件。
 */
interface DumperInterface
{

    /**
     * 将数据转储为 TOML 格式的字符串
     *
     * 将输入的数据结构转换为格式化的 TOML 字符串。
     * 可以通过配置对象控制输出格式，如缩进、换行符等。
     *
     * @param mixed $data 要转换的数据，可以是数组、对象或其他支持的类型
     * @param DumperConfig|null $config 可选的转储配置对象，控制输出格式
     * @return string 格式化后的 TOML 字符串
     */
    public function dump(mixed $data, ?DumperConfig $config = null): string;


    /**
     * 将数据转储到文件中
     *
     * 将输入的数据结构转换为 TOML 格式并写入指定文件。
     * 可以通过配置对象控制输出格式。
     *
     * @param string $filename 目标文件路径
     * @param mixed $data 要转换并写入的数据
     * @param DumperConfig|null $config 可选的转储配置对象，控制输出格式
     * @return bool 写入成功返回 true，失败返回 false
     */
    public function dumpFile(string $filename, mixed $data, ?DumperConfig $config = null): bool;
}

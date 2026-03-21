<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Dumper;

use Petalbranch\Toml\Builder\NodeBuilder;
use Petalbranch\Toml\Contract\Dumper\DumperInterface;
use Petalbranch\Toml\Model\DumperConfig;

/**
 * TOML 转储器
 *
 * 这是一个 Facade (门面)，负责协调 Hydrator (NodeBuilder) 和 Serializer (NodeDumper)。
 * 提供将 PHP 数据结构转换为 TOML 格式字符串的主要接口，支持通过配置对象
 * 自定义输出格式，并能将结果写入文件。该类封装了从数据到AST节点树再到
 * TOML字符串的完整转换过程。
 */
class Dumper implements DumperInterface
{
    /**
     * 将数据转储为 TOML 字符串
     *
     * 将输入的数据结构转换为格式化的 TOML 字符串。
     * 可以使用自定义配置覆盖默认配置。
     * 处理过程分为两个阶段：首先使用 NodeBuilder 将数据水合为抽象语法树（AST），
     * 然后使用 NodeDumper 遍历 AST 生成最终的 TOML 字符串。
     *
     * @param mixed $data 要转换的数据
     * @param DumperConfig|null $config 可选的转储配置
     * @return string 生成的 TOML 格式字符串
     */
    public function dump(mixed $data, ?DumperConfig $config = null): string
    {
        $currentConfig = $config ?? new DumperConfig();

        // 1. Hydrator 阶段：将 PHP 数据水合为 AST 节点树
        // 将配置传递给 Builder，让其根据深度和数量决定是否生成内联表
        $builder = new NodeBuilder($currentConfig);
        $rootNode = $builder->build($data);

        // 2. Dumper 阶段：遍历 AST 节点树生成 TOML 字符串
        $nodeDumper = new NodeDumper($currentConfig);
        return $nodeDumper->dump($rootNode);
    }

    /**
     * 将数据转储到文件中
     *
     * 将输入的数据转换为 TOML 格式并写入指定文件。
     * 可以使用自定义配置覆盖默认配置。
     * 内部调用 dump 方法生成 TOML 字符串，然后写入文件。
     *
     * @param string $filename 目标文件路径
     * @param mixed $data 要转换并写入的数据
     * @param DumperConfig|null $config 可选的转储配置
     * @return bool 写入成功返回 true，失败返回 false
     */
    public function dumpFile(string $filename, mixed $data, ?DumperConfig $config = null): bool
    {
        return file_put_contents($filename, $this->dump($data, $config)) !== false;
    }
}

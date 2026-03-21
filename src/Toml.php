<?php

declare(strict_types=1);

namespace Petalbranch\Toml;

use Petalbranch\Toml\Dumper\Dumper;
use Petalbranch\Toml\Exception\ParseException;
use Petalbranch\Toml\Exception\DumpException;
use Petalbranch\Toml\Model\DumperConfig;
use Petalbranch\Toml\Parser\Lexer;
use Petalbranch\Toml\Parser\Parser;
use Petalbranch\Toml\Type\ParseErrorType;


/**
 * TOML 门面类
 *
 * 提供简洁的静态接口来解析和生成 TOML 格式的数据。
 * 封装了底层的解析器、词法分析器和转储器，提供简单易用的方法，
 * 无需直接处理复杂的对象创建和配置。
 */
final class Toml
{

    /**
     * 私有构造函数
     *
     * 防止类被实例化，因为所有方法都是静态的。
     */
    private function __construct()
    {
    }


    /**
     * 解析 TOML 字符串
     *
     * 将输入的 TOML 格式字符串解析为 PHP 关联数组。
     *
     * @param string $toml 要解析的 TOML 格式字符串
     * @return array 解析后的 PHP 关联数组
     */
    public static function parse(string $toml): array
    {
        $parser = new Parser(new Lexer());
        return $parser->parse($toml);
    }


    /**
     * 解析 TOML 文件
     *
     * 读取指定路径的文件内容并将其作为 TOML 文档进行解析。
     *
     * @param string $filename 要解析的文件路径
     * @return array 解析后的 PHP 关联数组
     * @throws ParseException 当文件不存在、不可读或读取失败时抛出异常
     */
    public static function parseFile(string $filename): array
    {
        if (!is_file($filename) || !is_readable($filename)) {
            throw new ParseException(
                sprintf('File "%s" does not exist or is not readable.', $filename),
                ParseErrorType::FILE_NOT_FOUND
            );
        }

        $content = file_get_contents($filename);
        if ($content === false) {
            throw new ParseException(
                sprintf('Failed to read file "%s".', $filename),
                ParseErrorType::FILE_READ_FAILED
            );
        }

        return self::parse($content);
    }


    /**
     * 将数据转储为 TOML 字符串
     *
     * 将给定的数据结构转换为格式化的 TOML 字符串。
     *
     * @param mixed $data 要转换的数据
     * @param DumperConfig|null $config 可选的转储配置
     * @return string 生成的 TOML 格式字符串
     */
    public static function dump(mixed $data, ?DumperConfig $config = null): string
    {
        $dumper = new Dumper();
        return $dumper->dump($data, $config);
    }

    /**
     * 将数据转储到文件中
     *
     * 将给定的数据结构转换为 TOML 格式并写入指定文件。
     *
     * @param string $filename 目标文件路径
     * @param mixed $data 要转换并写入的数据
     * @param DumperConfig|null $config 可选的转储配置
     * @return bool 写入成功返回 true，失败抛出异常
     * @throws DumpException 当文件写入失败时抛出异常
     */
    public static function dumpFile(string $filename, mixed $data, ?DumperConfig $config = null): bool
    {
        $dumper = new Dumper();
        $result = $dumper->dumpFile($filename, $data, $config);

        if ($result === false) {
            throw new DumpException(sprintf('Failed to write TOML data to file "%s".', $filename));
        }

        return true;
    }



    /**
     * 启用详细错误输出
     *
     * 控制是否在解析异常中包含详细的错误信息。
     * 当启用时，ParseException 将包含更详细的上下文信息，
     * 有助于调试和定位问题；禁用时则只提供基本的错误信息。
     *
     * @param bool $enable 是否启用详细错误输出，默认为 true
     */
    public static function enableDetailedErrors(bool $enable = true): void
    {
        ParseException::$detailedErrorOutput = $enable;
    }

}

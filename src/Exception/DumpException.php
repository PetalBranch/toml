<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Exception;

use Petalbranch\Toml\Contract\Exception\DumpExceptionInterface;
use Petalbranch\Toml\Model\KeyPath;
use RuntimeException;

/**
 * 转储异常类
 *
 * 用于表示 TOML 转储（序列化）过程中发生的错误
 *
 * @package Petalbranch\Toml\Exception
 */
class DumpException extends RuntimeException implements DumpExceptionInterface
{

    /**
     * 创建不支持的类型异常
     *
     * 当尝试将不支持的 PHP 类型转换为 TOML 格式时抛出此异常
     *
     * @param string $type 不支持的数据类型名称
     * @param KeyPath $path 错误发生时的键路径
     * @return self 返回创建的异常对象
     */
    public static function unsupportedType(string $type, KeyPath $path = new KeyPath([])): self
    {
        $message = sprintf('Cannot dump unsupported PHP type "%s" to TOML', $type);
        if ($path != '') {
            $message .= sprintf(' at path "%s"', $path);
        }

        return new self($message);
    }
}

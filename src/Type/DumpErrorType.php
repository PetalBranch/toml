<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Type;

/**
 * 转储错误类型枚举
 *
 * 定义 TOML 转储（序列化）过程中可能发生的各种错误类型
 *
 * @package Petalbranch\Toml\Type
 */
enum DumpErrorType
{
    /** 不支持的数据类型错误 */
    case UNSUPPORTED_TYPE;
    /** 无效的键名错误 */
    case INVALID_KEY;
    /** 循环引用错误 */
    case CYCLIC_REFERENCE;
}

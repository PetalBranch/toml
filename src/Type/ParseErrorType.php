<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Type;

/**
 * 解析错误类型枚举
 *
 * 定义 TOML 解析过程中可能发生的各种错误类型
 */
enum ParseErrorType
{
    /** 语法错误 */
    case SYNTAX_ERROR;

    /** 重复的键名错误 */
    case DUPLICATE_KEY;

    /** 无效的数据类型错误 */
    case INVALID_TYPE;

    /** 无效的表定义错误 */
    case INVALID_TABLE;

    /** 无效的数组定义错误 */
    case INVALID_ARRAY;

    /** 无效的字符错误 */
    case INVALID_CHAR;

    /** 意外的文件末尾错误 */
    case UNEXPECTED_EOF;

    /** 内部错误 */
    case INTERNAL_ERROR;

    /** 意外的词法单元错误 */
    case UNEXPECTED_TOKEN;
    /** 冲突的键名错误 */
    case CONFLICTING_KEY;
    /** 无效的表定义错误 */
    case INVALID_TABLE_DEFINITION;
    case UNKNOWN_SCALAR_TYPE;
}

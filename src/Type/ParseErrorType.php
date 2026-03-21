<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Type;

/**
 * 解析错误类型枚举
 *
 * 定义 TOML 解析过程中可能发生的各种错误类型。
 * 每种错误类型对应解析器在处理 TOML 文档时可能遇到的特定问题，
 * 如语法错误、重复键、无效字符等。这些类型可用于精确标识和处理不同的解析异常情况。
 */
enum ParseErrorType
{

    /** 重复的键名错误 - 当在同一作用域中出现重复的键名时 */
    case DUPLICATE_KEY;

    /** 无效的数据类型错误 - 当值的类型与预期不符时 */
    case INVALID_TYPE;

    /** 无效的表定义错误 - 当表的定义格式不正确时 */
    case INVALID_TABLE;

    /** 无效的数组定义错误 - 当数组的定义格式不正确时 */
    case INVALID_ARRAY;

    /** 无效的字符错误 - 当遇到不允许的特殊字符时 */
    case INVALID_CHAR;

    /** 意外的文件末尾错误 - 当解析器在未完成解析前到达文件末尾时 */
    case UNEXPECTED_EOF;

    /** 内部错误 - 当解析器内部发生未预期的错误时 */
    case INTERNAL_ERROR;

    /** 意外的词法单元错误 - 当遇到与当前解析上下文不符的词法单元时 */
    case UNEXPECTED_TOKEN;

    /** 冲突的键名错误 - 当尝试用不同类型的值重新定义已存在的键时 */
    case CONFLICTING_KEY;

    /** 无效的表定义错误 - 当表的命名或嵌套方式不合法时 */
    case INVALID_TABLE_DEFINITION;

    /** 未知的标量类型错误 - 当无法识别标量值的类型时 */
    case UNKNOWN_SCALAR_TYPE;

    /** 文件未找到错误 - 当尝试读取不存在的文件时 */
    case FILE_NOT_FOUND;
    /** 文件读取失败错误 - 当文件存在但无法成功读取内容时 */
    case FILE_READ_FAILED;
}

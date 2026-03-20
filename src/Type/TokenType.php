<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Type;

/**
 * 词法单元类型枚举
 *
 * 定义 TOML 词法分析器识别的所有词法单元类型
 *
 * @package Petalbranch\Toml\Type
 */
enum TokenType
{
    // ===== 基础结构 =====
    /**
     * 文件结束标记
     */
    case EOF;
    /**
     * 换行符
     */
    case NEWLINE;

    // ===== 符号 =====
    /**
     * 等号 (=)
     */
    case EQUAL;
    /**
     * 点号 (.)
     */
    case DOT;
    /**
     * 逗号 (,)
     */
    case COMMA;
    /**
     * 左方括号 ([)
     */
    case LEFT_BRACKET;
    /**
     * 右方括号 (])
     */
    case RIGHT_BRACKET;
    /**
     * 左花括号 ({)
     */
    case LEFT_BRACE;
    /**
     * 右花括号 (})
     */
    case RIGHT_BRACE;

    // ===== 标识符 (用于裸键) =====
    /**
     * 裸键（无引号的键名）
     *
     * @note 标识符（对应 TOML 中的 Bare Key，仅包含 A-Za-z0-9_-）
     * @note 遇到 true/false 等，Lexer 也可以先扫成 IDENTIFIER，然后判断如果是关键字，再转成 BOOLEAN。
     */
    case IDENTIFIER;

    // ===== 字符串 =====
    /**
     * 基本字符串（双引号）
     */
    case STRING_BASIC;
    /**
     * 字面量字符串（单引号）
     */
    case STRING_LITERAL;
    /**
     * 多行基本字符串（三个双引号）
     */
    case STRING_MULTILINE_BASIC;
    /**
     * 多行字面量字符串（三个单引号）
     */
    case STRING_MULTILINE_LITERAL;

    // ===== 数值 =====
    /**
     * 整数
     */
    case INTEGER;
    /**
     * 浮点数
     */
    case FLOAT;

    // ===== 布尔 =====
    /**
     * 布尔值（true 或 false）
     */
    case BOOLEAN;

    // ===== 日期时间 =====
    /**
     * 带偏移量的日期时间
     */
    case OFFSET_DATETIME;
    /**
     * 本地日期时间
     */
    case LOCAL_DATETIME;
    /**
     * 本地日期
     */
    case LOCAL_DATE;
    /**
     * 本地时间
     */
    case LOCAL_TIME;

    // ===== 注释 =====
    /**
     * 注释
     */
    case COMMENT;

    // ===== 特殊 =====
    /**
     * 无效的词法单元
     */
    case INVALID;
}

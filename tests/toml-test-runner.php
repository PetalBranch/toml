#!/usr/bin/env php
<?php

declare(strict_types=1);

// 引入自动加载
use Petalbranch\Toml\Exception\ParseException;
use Petalbranch\Toml\Parser\ArrayNode;
use Petalbranch\Toml\Parser\Lexer;
use Petalbranch\Toml\Parser\Parser;
use Petalbranch\Toml\Parser\TableNode;
use Petalbranch\Toml\Parser\ValueNode;
use Petalbranch\Toml\Type\TomlType;

require_once '../vendor/autoload.php';


// 1. 读取标准输入
$input = file_get_contents('php://stdin');

try {
    $parser = new Parser(new Lexer());

    // 1. 开始解析！
    $ast = $parser->parseToNode($input);

    // 2. 转换为 toml-test 期望的格式
    $output = convertNodeToTomlTestFormat($ast);

    // 3. 打印成 JSON 并正常退出
    echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit(0);

} catch (Throwable $e) {
    if ($e instanceof ParseException) {
        fwrite(STDERR, json_encode([
                        "msg" => $e->getMessage(),
                        "type" => $e->type->name,
                        "line" => $e->lineNumber,
                        "column" => $e->columnNumber
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
        exit(1);
    }
    // 遇到语法错误，打印错误信息到 STDERR 并返回状态码 1 (告知 toml-test 这是一个 invalid 解析)
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}


function convertNodeToTomlTestFormat($node): mixed
{
    if ($node instanceof TableNode) {
        $entries = $node->getEntries();

        // 如果是空表，必须返回 stdClass，以确保 json_encode 输出 {} 而不是 []
        if (empty($entries)) {
            return new stdClass();
        }

        // 如果有数据，使用关联数组，因为 PHP 关联数组允许 "\0" 作为键名，而 stdClass 不允许！
        $result = [];
        foreach ($entries as $entry) {
            $result[$entry->key] = convertNodeToTomlTestFormat($entry->value);
        }

        // 输出判定：打破 PHP 的 JSON 序列化死局
        if (array_is_list($result)) {
            // 如果全是数字键 (如 "0", "1")，PHP 会把它当成普通数组。
            // 必须强转为 object，强制输出 {"0": ...}
            return (object) $result;
        }

        // 如果包含非纯连续数字的键 (如 "\0" 或 "a")，
        // PHP 原生就能把它序列化为完美的 JSON 对象，无需强转，保留 \0 的可见性！
        return $result;
    }

    if ($node instanceof ArrayNode) {
        $result = [];
        foreach ($node->getElements() as $element) {
            $result[] = convertNodeToTomlTestFormat($element);
        }
        return $result;
    }

    if ($node instanceof ValueNode) {
        $type = $node->getType();
        $val = $node->getValue();

        // 映射类型到 toml-test 的标准名称
        $typeStr = match ($type) {
            TomlType::INTEGER => 'integer',
            TomlType::FLOAT => 'float',
            TomlType::BOOLEAN => 'bool',
            TomlType::OFFSET_DATETIME => 'datetime',
            TomlType::LOCAL_DATETIME => 'datetime-local',
            TomlType::LOCAL_DATE => 'date-local',
            TomlType::LOCAL_TIME => 'time-local',
            default => 'string'
        };

        // 格式化值 (toml-test 要求 value 统一是字符串)
        $valStr = (string)$val;

        if ($type === TomlType::BOOLEAN) {
            $valStr = $val ? 'true' : 'false';
        } elseif ($type === TomlType::FLOAT) {
            // PHP 的 NAN 和 INF 需要转为 toml-test 要求的格式
            if (is_nan($val)) {
                $valStr = 'nan';
            } elseif (is_infinite($val)) {
                $valStr = $val > 0 ? 'inf' : '-inf';
            } else {

                // json_encode 底层使用 serialize_precision，能完美保留 16 位以上的精度！
                $valStr = (string) json_encode($val);

                // 如果是一个普通整数形式的浮点数 (如 1.0)，PHP 默认 (string) 会变成 "1"
                // 但 toml-test 期望看到小数点，所以要兜底补上
                if (!str_contains($valStr, '.') && !str_contains(strtolower($valStr), 'e')) {
                    $valStr .= '.0';
                }
            }
        } elseif (in_array($type, [TomlType::OFFSET_DATETIME, TomlType::LOCAL_DATETIME, TomlType::LOCAL_DATE, TomlType::LOCAL_TIME])) {
            // 时间类型直接使用我们在 Lexer 里保存好的原始清洗字符串
            $valStr = $node->getValue();
        }

        return [
                'type' => $typeStr,
                'value' => $valStr,
        ];
    }

    return null;
}
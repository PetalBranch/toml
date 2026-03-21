# Petalbranch TOML

[![PHP Version Require](https://img.shields.io/badge/php-%3E%3D8.3-8892BF.svg)](https://php.net)
[![TOML Version](https://img.shields.io/badge/TOML-v1.1.0-blue.svg)](https://toml.io)
[![License](https://img.shields.io/badge/license-Apache--2.0-green.svg)](LICENSE.txt)

一个纯 PHP 实现的 TOML 解析器与生成器（Parser & Dumper）。

基于词法分析（Lexer）与抽象语法树（AST）架构设计，将解析（Parse）与生成（Dump）彻底解耦，提供高可靠、可维护的 TOML 读写能力。

---

## ✨ 核心特性

* **严格规范实现（Spec-Compliant）**
  完整通过 `toml-test v2.1.0`：

    * Decoder：100%
    * Encoder：99.5%

* **AST 驱动架构（AST-first Design）**
  Parse → AST → Hydration → Serialization 全链路解耦，保证数据一致性与扩展性。
* **UTF-8 字符级解析（Unicode-safe Lexer）**
  基于 `mb_str_split` 按字符而非字节切分输入，确保 Emoji、多语言字符等在词法阶段被正确识别为单一字符单元，避免常见的多字节截断与定位错误。

* **精准错误诊断（Rich Error Reporting）**
  提供结构化错误、源码片段与精确定位（行/列/指针）。

* **智能生成器（Smart Dumper）**
  自动选择 Inline Table / 标准 Table，避免冗长结构。

* **原生时间类型支持**
  完整覆盖 TOML 时间模型（Offset / Local / Date / Time）。

* **极简 API**
  单一 `Toml` 门面类覆盖绝大多数使用场景，无需手动操作 AST。

---

## ❌ 精准错误诊断（Rich Error Reporting）

当 TOML 格式非法时，解析器会提供**结构化错误信息 + 精确定位 + 上下文片段**：

```text
[UNEXPECTED_TOKEN] Unexpected token "." after value.
Key-value pairs must be separated by newlines.
(Line: 1, Column: 11)

>    1 | "oh,🤪" = 7.
                    ^
```
```text
[CONFLICTING_KEY] Conflict: 'fruit' is already defined and is not an array of tables (Line: 6, Column: 3)

     5 | 
>    6 | [[fruit]]  # 解析器必须在发现“fruit”是数组而非表时抛出错误
           ^^^^^
     7 | name = "apple"
```

支持的错误类型（部分）：

* `UNEXPECTED_TOKEN`：非法语法结构
* `INVALID_CHAR`：非法字符
* `INVALID_TYPE`：类型错误
* `CONFLICTING_KEY`：键冲突
* `INVALID_TABLE_DEFINITION`：非法表定义

每个错误均包含：

* 行号 / 列号
* 原始代码片段
* 精确指针（`^`）
* 可读错误描述

👉 不仅是解析器，也可以作为 **TOML Linter / 校验工具** 使用。

此外，详细错误信息是**可配置的**（默认开启）：

```php
use Petalbranch\Toml\Toml;

Toml::enableDetailedErrors(false); // 全局关闭详细错误输出
```

---
## 🧪 测试覆盖（toml-test）

本项目基于官方 `toml-test v2.1.0` 测试套件进行验证：

```text
toml-test v2.1.0
  valid tests:   214 passed, 0 failed
  encoder tests: 213 passed, 1 failed
  invalid tests: 466 passed, 0 failed
```

说明：

* **解析（Decoder）**：100% 通过
* **非法用例（Invalid）**：100% 正确拒绝
* **生成（Encoder）**：仅 1 项边界差异（不影响主流使用场景）

👉 覆盖 TOML 规范的大量边界情况，保证实现的可靠性与一致性。

> ⚠️ 唯一失败用例说明（encoder/key/quoted-unicode）：
>
> 该用例包含 `\u0000`（空字节）作为键名，并通过 JSON 传入编码器。
> 由于 PHP 在字符串/数组键层面对空字节存在底层限制，且 JSON 解析链路无法可靠承载该键，导致输入在进入编码器前即被判定为非法（`Invalid JSON input`）。
>
> 👉 该行为与本库的「已知限制」一致，不影响常规 TOML 使用场景。

---

## 🤔 为什么选择 Petalbranch TOML？

| 特性   | 本库             | 常见实现    |
|------|----------------|---------|
| 规范覆盖 | ✅ 高（toml-test） | ⚠️ 部分实现 |
| 错误提示 | ✅ 精确定位 + 片段    | ❌ 简单异常  |
| 架构   | ✅ AST 分层       | ❌ 直接解析  |
| 生成质量 | ✅ 智能排版         | ❌ 机械输出  |
| 时间类型 | ✅ 强类型对象        | ⚠️ 字符串  |

---

## 🧠 架构简述

```text
TOML Text
   ↓
Lexer（词法分析）
   ↓
Parser（语法分析）
   ↓
AST（抽象语法树）
   ↓
Hydration（转 PHP 数据）
   ↓
Dumper（序列化回 TOML）
```

---

## 📦 安装

通过 Composer 安装（需要 PHP 8.3+）：

```bash
composer require petalbranch/toml
```

---

## 🚀 快速开始

### 1. 解析 TOML (Parse)

```php
use Petalbranch\Toml\Toml;

$config = Toml::parseFile(__DIR__ . '/config.toml');

$tomlString = <<<TOML
title = "Type Examples"

[strings]
basic = "I'm a basic string"
multi = """
The quick brown \
fox jumps over \
the lazy dog."""

[numbers]
integer = 123_456
hex = 0xDEADBEEF
float = 3.1415
special = inf

[booleans]
true = true

[dates]
offset = 1979-05-27T07:32:00-07:00
local = 2023-10-27T00:00:00

[[arrays_of_tables]]
name = "table1"
[[arrays_of_tables]]
name = "table2"
TOML;

$data = Toml::parse($tomlString);

echo $data['numbers']['special'];
echo get_class($data['dates']['offset']);
```

---

### 2. 生成 TOML (Dump)

```php
use Petalbranch\Toml\Toml;
use Petalbranch\Toml\Support\TomlDate;

$data = [
    'project' => [
        'name' => 'My Webman App',
        'created_at' => new TomlDate(new \DateTimeImmutable('2023-10-27')),
    ],
    'servers' => [
        'web' => [
            'ip' => '192.168.1.1',
            'ports' => [80, 443]
        ]
    ]
];

$toml = Toml::dump($data);
Toml::dumpFile(__DIR__ . '/config.generated.toml', $data);
```

---

### 3. 智能内联表（Smart Inline Tables）

```php
use Petalbranch\Toml\Toml;
use Petalbranch\Toml\Model\DumperConfig;

$config = new DumperConfig();
$config->newline = "\n";
$config->inlineTable = true;
$config->inlineTableMaxDepth = 2;
$config->inlineTableMaxItems = 4;

$toml = Toml::dump($data, $config);
```

---

## 🎯 使用场景

* 配置文件解析（替代 JSON / YAML）
* CLI 工具配置系统
* 框架配置层（Webman / Laravel 风格）
* TOML 校验工具（CI / Lint）
* 构建工具 / 包管理器

---

## ⚙️ 性能与生产环境建议

TOML 解析涉及词法分析与语法树构建，建议在生产环境中对结果进行缓存：

```php
use Petalbranch\Toml\Toml;

$cacheFile = __DIR__ . '/runtime/toml_cache.php';

if (is_file($cacheFile) && !constant('DEBUG_MODE')) {
    $config = require $cacheFile;
} else {
    $config = Toml::parseFile(__DIR__ . '/config.toml');
    file_put_contents($cacheFile, '<?php return ' . var_export($config, true) . ';');
}
```

---

## ⚠️ 已知限制（Known Limitations）

由于 PHP 对象模型限制，不支持使用空字节（`\u0000`）作为键名。

---

## 📄 开源协议

本项目基于 Apache License 2.0 协议开源 - 查看 [LICENSE](LICENSE.txt) 文件了解更多细节。

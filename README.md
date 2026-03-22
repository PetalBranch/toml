# Petalbranch TOML

[![PHP Version Require](https://img.shields.io/badge/php-%3E%3D8.3-8892BF.svg)](https://php.net)
[![TOML Version](https://img.shields.io/badge/TOML-v1.1.0-blue.svg)](https://toml.io)
[![PHPStan Level 9](https://img.shields.io/badge/PHPStan-Level%209-brightgreen.svg)](https://phpstan.org/)
[![License](https://img.shields.io/badge/license-Apache--2.0-green.svg)](LICENSE.txt)

一个纯 PHP 实现的工业级 TOML 解析器与生成器（Parser & Dumper）。

基于词法分析（Lexer）与抽象语法树（AST）架构设计，将解析（Parse）与生成（Dump）彻底解耦。不仅提供高可靠的 TOML 读写能力，更具备*
*无损注释回写**、**O(1) 内存极速解析**与**等号对齐排版**等高级特性。

---

## Language

* [中文](README.md)
* [English](README-EN.md)

---

## ✨ 核心特性

* 🛡️ **工业级类型安全 (PHPStan Level 9)**
  全代码满级通过 PHPStan（Level 9）静态分析，消灭一切隐式转换与类型盲区，提供绝对可靠的代码质量。
* 🚀 **O(1) 内存极速解析 (Zero-Copy Lexer)**
  抛弃低效的 `mb_str_split`，采用底层字节游标与动态 UTF-8 探针技术。无论解析 1KB 还是 100MB 的配置文件，内存占用均趋近于 O(
  1)，无惧超大体积文件。
* 🔄 **真正的无损回写 (Lossless Round-Trip)**
  AST 树完整保留开发者编写的前导注释与行尾注释。支持直接修改 AST 节点并重新 Dump，注释与排版原封不动，完美胜任“配置可视化面板”的底层引擎。
* 💅 **极致美学转储 (Alignment Formatting)**
  Dumper 支持智能等号对齐，自动计算键名视觉宽度（完美兼容中文字符与 Emoji），转储出极具秩序美的 TOML 代码。
* 🎯 **严格规范实现 (Spec-Compliant)**
  完整通过 `toml-test v2.1.0` 测试套件：Decoder 100% 通过，Encoder 99.5% 通过。
* 🔍 **精准错误诊断 (Rich Error Reporting)**
  提供结构化错误、源码片段与精确定位（行/列/像素级指针）。
* ⏱️ **原生时间类型支持**
  完整覆盖 TOML 1.1.0 时间模型（Offset / Local / Date / Time）。

---

## ❌ 精准错误诊断（Rich Error Reporting）

当 TOML 格式非法时，解析器会提供**结构化错误信息 + 精确定位 + 上下文片段**。游标指针（`^`
）不仅能精确定位列数，还能根据全角/半角字符（如中文、Emoji）自动计算视觉宽度，实现像素级对齐：

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

## 🔄 无损回写与动态修改 (Lossless Round-Trip)

大多数解析器在转储时会丢失注释。本库允许你直接操作 AST（抽象语法树），修改值后重新 Dump，所有注释和排版将被完美保留：

```php
use Petalbranch\Toml\Parser\Lexer;
use Petalbranch\Toml\Parser\Parser;
use Petalbranch\Toml\Dumper\NodeDumper;
use Petalbranch\Toml\Model\DumperConfig;

$originalToml = <<<TOML
# 数据库核心配置
[database]
server = "192.168.1.1"  # 主库 IP
port = 3306             # 请勿在生产环境修改
TOML;

// 1. 解析为包含完整注释的 AST 树
$parser = new Parser(new Lexer());
$rootNode = $parser->parseToNode($originalToml); 

// 2. 直接修改 AST 节点的值 (自动推断类型)
$rootNode->get('database')->get('port')->setValue(6379);

// 3. 拿着这棵树直接 Dump 出去
$dumper = new NodeDumper(new DumperConfig());
echo $dumper->dump($rootNode);

/* 输出结果：注释被完美保留！
# 数据库核心配置
[database]
server = "192.168.1.1"  # 主库 IP
port = 6379             # 请勿在生产环境修改
*/
```

## 💅 极致美学转储 (Alignment Formatting)

强迫症福音！开启 alignEquals 配置后，Dumper 会自动预扫描同一层级的键名，并使用空格智能对齐所有等号：

```php
use Petalbranch\Toml\Toml;
use Petalbranch\Toml\Model\DumperConfig;

$config = new DumperConfig();
$config->alignEquals = true; // 开启等号对齐

$data = [
    'server' => [
        'ip' => '127.0.0.1',
        'max_connections' => 1000,
        '中文端口号' => 8080,
        'enable' => true
    ]
];

echo Toml::dump($data, $config);

/* 输出极其清爽的排版：
[server]
ip              = "127.0.0.1"
max_connections = 1000
"中文端口号"    = 8080
enable          = true
*/
```

---

## 🧪 测试覆盖（toml-test）

本项目基于官方 `toml-test v2.1.0` 测试套件进行验证：

```text
> cd tests && .\toml-test-v2.1.0-windows-amd64.exe test -toml "1.1" -decoder "php toml-test-decoder.php" -encoder "php toml-test-encoder.php"

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
> 由于 PHP 在字符串/数组键层面对空字节存在底层限制，且 JSON 解析链路无法可靠承载该键，导致输入在进入编码器前即被判定为非法（
`Invalid JSON input`）。
>
> 👉 该行为与本库的「已知限制」一致，不影响常规 TOML 使用场景。

---

## 🤔 为什么选择 Petalbranch TOML？

| 特性             | 本库                  | 常见实现               |
|----------------|---------------------|--------------------|
| 静态分析 (PHPStan) | ✅ Level 9 满级通关      | ❌ 普遍存在 mixed 警告    |
| 内存占用 (Lexer)   | ✅ O(1) 极速字节游标       | ❌ O(N) 数组全量拆分      |
| 注释无损回写         | ✅ 完美支持 (AST 级别)     | ❌ 转储时注释全部丢失        |
| 错误诊断           | ✅ 源码片段 + 像素级游标      | ❌ 仅抛出简单的 Exception |
| 生成质量 (Dumper)  | ✅ 智能内联 + 等号对齐       | ❌ 机械式换行输出          |
| 规范覆盖度          | ✅ 极高 (toml-test 验证) | ⚠️ 部分实现 / 落后规范     |

---

## 🧠 架构简述

```text
TOML Text
   ↓ (O(1) 字节游标扫描)
Lexer（词法分析，生成 Token Stream）
   ↓ 
Parser（语法分析，收集注释与层级）
   ↓
AST（包含所有元数据的抽象语法树） ——> [可直接修改节点进行无损回写]
   ↓
Hydration（转化为纯净的 PHP 数组）
   ↓
Dumper（序列化回 TOML，支持对齐与智能排版）
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

// 解析文件为 PHP 数组
$config = Toml::parseFile(__DIR__ . '/config.toml');

// 直接解析字符串
$tomlString = 'title = "Petalbranch"';
$data = Toml::parse($tomlString);
```

---

### 2. 生成 TOML (Dump)

```php
use Petalbranch\Toml\Toml;
use Petalbranch\Toml\Model\DumperConfig;

$data = [
    'title' => 'TOML Example',
    'owner' => ['name' => 'Tom', 'dob' => new \DateTime('1979-05-27T07:32:00-08:00')]
];

// 使用默认配置生成字符串
$toml = Toml::dump($data);

// 使用自定义配置并直接写入文件
$config = new DumperConfig();
$config->inlineTable = true; // 开启内联表智能压缩
Toml::dumpFile(__DIR__ . '/config.generated.toml', $data, $config);
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

在生产环境中，建议将解析后的 PHP 数组进行缓存以达到极致性能（如使用 OPcache）：

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

由于 PHP 底层哈希表对象模型的限制，本库不支持使用空字节（`\u0000`）作为对象的键名。这在正常工程实践中极度罕见。

---

## 📄 开源协议

本项目基于 Apache License 2.0 协议开源 - 查看 [LICENSE](LICENSE.txt) 文件了解更多细节。

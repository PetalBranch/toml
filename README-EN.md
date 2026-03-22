# Petalbranch TOML

[![PHP Version Require](https://img.shields.io/badge/php-%3E%3D8.3-8892BF.svg)](https://php.net)
[![TOML Version](https://img.shields.io/badge/TOML-v1.1.0-blue.svg)](https://toml.io)
[![PHPStan Level 9](https://img.shields.io/badge/PHPStan-Level%209-brightgreen.svg)](https://phpstan.org/)
[![License](https://img.shields.io/badge/license-Apache--2.0-green.svg)](LICENSE.txt)

An industrial-grade TOML Parser and Dumper implemented in pure PHP.

Designed with a Lexer and Abstract Syntax Tree (AST) architecture, completely decoupling parsing from dumping. It not
only provides highly reliable TOML reading and writing capabilities but also features advanced capabilities like *
*lossless comment preservation (round-trip)**, **O(1) memory zero-copy parsing**, and **aligned equals formatting**.

---

## Language

* [中文](README.md)
* [English](README-EN.md)

---

## ✨ Core Features

* 🛡️ **Industrial-Grade Type Safety (PHPStan Level 9)**
  The entire codebase passes PHPStan Level 9 static analysis. Zero implicit conversions and zero type blind spots,
  ensuring absolute code reliability.
* 🚀 **O(1) Memory Zero-Copy Lexer**
  Abandons the inefficient `mb_str_split` in favor of an underlying byte-cursor and dynamic UTF-8 probes. Whether
  parsing a 1KB or 100MB config file, memory usage remains near O(1).
* 🔄 **True Lossless Round-Trip**
  The AST completely retains developer-written leading and trailing comments. You can directly modify AST nodes and
  re-dump them without losing comments or formatting—perfect as an engine for configuration dashboards.
* 💅 **Alignment Formatting**
  The Dumper supports smart equals sign (`=`) alignment. It automatically calculates the visual width of keys (perfectly
  compatible with CJK characters and Emojis), outputting beautifully ordered TOML code.
* 🎯 **Strict Spec-Compliance**
  Fully passes the official `toml-test v2.1.0` test suite: 100% pass rate for Decoder, 99.5% for Encoder.
* 🔍 **Rich Error Reporting**
  Provides structured errors, source code snippets, and exact pointers (line/column/pixel-level alignment).
* ⏱️ **Native Temporal Types**
  Fully supports the TOML 1.1.0 time model (Offset / Local / Date / Time).

---

## ❌ Rich Error Reporting

When encountering invalid TOML formatting, the parser provides **structured error messages + precise locations + context
snippets**. The cursor pointer (`^`) not only pinpoints the exact column but also calculates the visual width based on
full-width/half-width characters (like Chinese or Emojis) for pixel-perfect alignment:

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
>    6 | [[fruit]]  # The parser throws an error when it detects 'fruit' is an array instead of a table
           ^^^^^
     7 | name = "apple"
```

Supported error types include:

* `UNEXPECTED_TOKEN`: Invalid syntax structure
* `INVALID_CHAR`: Invalid character
* `INVALID_TYPE`: Type mismatch
* `CONFLICTING_KEY`: Key conflict
* `INVALID_TABLE_DEFINITION`: Invalid table definition

Every error includes:

* Line / Column numbers
* Original source code snippet
* Precise pointer (`^`)
* Human-readable error description

👉 Not just a parser, it can also be used as a **TOML Linter / Validator**.

Additionally, detailed error messages are **configurable** (enabled by default):

```php
use Petalbranch\Toml\Toml;

Toml::enableDetailedErrors(false); // Globally disable detailed error output
```

## 🔄 Lossless Round-Trip & Dynamic Modification

Most parsers strip away comments during the dumping process. This library allows you to interact directly with the AST (
Abstract Syntax Tree). Modify values and re-dump, and **all comments and formatting will be perfectly preserved**:

```php
use Petalbranch\Toml\Parser\Lexer;
use Petalbranch\Toml\Parser\Parser;
use Petalbranch\Toml\Dumper\NodeDumper;
use Petalbranch\Toml\Model\DumperConfig;

$originalToml = <<<TOML
# Core Database Configuration
[database]
server = "192.168.1.1"  # Master DB IP
port = 3306             # Do not modify in production
TOML;

// 1. Parse into an AST containing full comments
$parser = new Parser(new Lexer());
$rootNode = $parser->parseToNode($originalToml); 

// 2. Directly modify the AST node value (types are automatically inferred)
$rootNode->get('database')->get('port')->setValue(6379);

// 3. Dump the AST directly back to a string
$dumper = new NodeDumper(new DumperConfig());
echo $dumper->dump($rootNode);

/* Output: Comments are perfectly preserved!
# Core Database Configuration
[database]
server = "192.168.1.1"  # Master DB IP
port = 6379             # Do not modify in production
*/
```

## 💅 Alignment Formatting

A blessing for developers with OCD! By enabling the `alignEquals` configuration, the Dumper automatically pre-scans key
names at the same level and intelligently aligns all equals signs with spaces:

```php
use Petalbranch\Toml\Toml;
use Petalbranch\Toml\Model\DumperConfig;

$config = new DumperConfig();
$config->alignEquals = true; // Enable equals alignment

$data = [
    'server' => [
        'ip' => '127.0.0.1',
        'max_connections' => 1000,
        '中文端口号' => 8080,
        'enable' => true
    ]
];

echo Toml::dump($data, $config);

/* Generates incredibly clean formatting:
[server]
ip              = "127.0.0.1"
max_connections = 1000
"中文端口号"    = 8080
enable          = true
*/
```

---

## 🧪 Test Coverage (toml-test)

This project is rigorously verified against the official `toml-test v2.1.0` test suite:

```text
> cd tests && .\toml-test-v2.1.0-windows-amd64.exe test -toml "1.1" -decoder "php toml-test-decoder.php" -encoder "php toml-test-encoder.php"

toml-test v2.1.0
  valid tests:   214 passed, 0 failed
  encoder tests: 213 passed, 1 failed
  invalid tests: 466 passed, 0 failed
```

Breakdown:

* **Decoder**: 100% Passed
* **Invalid (Rejection)**: 100% correctly rejected
* **Encoder**: Only 1 edge-case difference (does not affect mainstream use cases)

👉 Extensive coverage of TOML specification edge cases ensures unparalleled reliability and consistency.

> ⚠️ Note on the single failed test (`encoder/key/quoted-unicode`):
>
> This test uses a null byte (`\u0000`) as a dictionary key, passed into the encoder via JSON. Due to underlying
> limitations in PHP's array/string key handling of null bytes, and JSON extension constraints, the input is deemed
> invalid (`Invalid JSON input`) before it even reaches the TOML encoder.
>
> 👉 This behavior aligns with this library's "Known Limitations" and has zero impact on regular TOML usage.

---

## 🤔 Why Petalbranch TOML?

| Feature             | This Library                      | Common PHP Implementations     |
|---------------------|-----------------------------------|--------------------------------|
| Static Analysis     | ✅ Level 9 (Max) Passed            | ❌ Widespread `mixed` warnings  |
| Lexer Memory        | ✅ O(1) Fast Byte Cursor           | ❌ O(N) Full array splitting    |
| Round-Trip Comments | ✅ Fully Supported (AST)           | ❌ Comments lost on dump        |
| Error Diagnostics   | ✅ Source Snippet + Pixel Cursor   | ❌ Basic Exceptions only        |
| Dumper Quality      | ✅ Smart Inline + Equals Alignment | ❌ Mechanical line-break output |
| Spec Coverage       | ✅ Extremely High (`toml-test`)    | ⚠️ Partial / Outdated spec     |

---

## 🧠 Architecture Overview

```text
TOML Text
   ↓ (O(1) Byte-cursor scanning)
Lexer (Lexical analysis, generates Token Stream)
   ↓ 
Parser (Syntax analysis, collects comments & hierarchy)
   ↓
AST (Abstract Syntax Tree with all metadata) ——> [Can be directly mutated for Lossless Round-Trip]
   ↓
Hydration (Transforms into pure PHP arrays)
   ↓
Dumper (Serializes back to TOML, supports alignment and smart formatting)
```

---

## 📦 Installation

Install via Composer (Requires PHP 8.3+):

```bash
composer require petalbranch/toml
```

---

## 🚀 Quick Start

### 1. Parse TOML

```php
use Petalbranch\Toml\Toml;

// Parse a file into a PHP array
$config = Toml::parseFile(__DIR__ . '/config.toml');

// Parse a string directly
$tomlString = 'title = "Petalbranch"';
$data = Toml::parse($tomlString);
```

---

### 2. Dump TOML

```php
use Petalbranch\Toml\Toml;
use Petalbranch\Toml\Model\DumperConfig;

$data = [
    'title' => 'TOML Example',
    'owner' => ['name' => 'Tom', 'dob' => new \DateTime('1979-05-27T07:32:00-08:00')]
];

// Dump to string using default config
$toml = Toml::dump($data);

// Dump to file using custom config
$config = new DumperConfig();
$config->inlineTable = true; // Enable smart inline table compression
Toml::dumpFile(__DIR__ . '/config.generated.toml', $data, $config);
```

---

## 🎯 Use Cases

* Configuration file parsing (Alternative to JSON / YAML)
* CLI tool configuration systems
* Framework configuration layers (Webman / Laravel style)
* TOML Validators (CI / Lints)
* Build tools / Package managers

---

## ⚙️ Performance & Production Recommendations

For maximum performance in production environments, it's highly recommended to cache the parsed PHP array (e.g.,
utilizing OPcache):

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

## ⚠️ Known Limitations

Due to the underlying constraints of PHP's hash table object model, this library does not support using null bytes (
`\u0000`) as object/array keys. This scenario is practically non-existent in standard engineering practices.

---

## 📄 License

This project is open-sourced under the Apache License 2.0 - see the [LICENSE](LICENSE.txt) file for details.
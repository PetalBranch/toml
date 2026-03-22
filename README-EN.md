# Petalbranch TOML

[![PHP Version Require](https://img.shields.io/badge/php-%3E%3D8.3-8892BF.svg)](https://php.net)
[![TOML Version](https://img.shields.io/badge/TOML-v1.1.0-blue.svg)](https://toml.io)
[![PHPStan Level 9](https://img.shields.io/badge/PHPStan-Level%209-brightgreen.svg)](https://phpstan.org/)
[![License](https://img.shields.io/badge/license-Apache--2.0-green.svg)](LICENSE.txt)

An industrial-grade TOML parser and dumper implemented purely in PHP.

Based on a Lexer and Abstract Syntax Tree (AST) architecture, it completely decouples parsing and dumping. It not only
provides high-reliability TOML read/write capabilities but also features **Lossless Comment Round-Trip**, **O(1) Memory
Extremely Fast Parsing**, and **Equal Sign Alignment Formatting**.

---

## Language

* [中文](README.md)
* [English](README-EN.md)

---

## ✨ Core Features

* 🧲 **Zero Learning Curve (Native-like DX)**
  Provides out-of-the-box global helper functions `toml_encode()` and `toml_decode()`, perfectly mirroring PHP's native
  JSON functions for muscle memory, offering an extremely smooth experience.
* 🛡️ **Industrial-Grade Type Safety (PHPStan Level 9)**
  The entire codebase passes PHPStan (Level 9) static analysis at the highest level, eliminating all implicit
  conversions and type blind spots, providing absolutely reliable code quality.
* 🚀 **O(1) Memory Extremely Fast Parsing (Zero-Copy Lexer)**
  Abandons inefficient `mb_str_split`, adopting low-level byte cursors and dynamic UTF-8 probing techniques. Whether
  parsing a 1KB or 100MB configuration file, memory usage approaches O(1), fearless of super-large files.
* 🔄 **True Lossless Round-Trip**
  The AST tree fully preserves leading and trailing comments written by the developer. Supports directly modifying AST
  nodes and re-dumping, with comments and formatting intact, perfectly suitable as the backing engine for "Configuration
  Visualization Panels".
* 💅 **Ultimate Aesthetics Dump (Alignment Formatting)**
  The Dumper supports smart equal sign alignment, automatically calculating the visual width of key names (perfectly
  compatible with Chinese characters and Emojis), dumping TOML code with extreme order and beauty.
* 🎯 **Strict Specification Implementation (Spec-Compliant)**
  Fully passes the `toml-test v2.1.0` test suite: Decoder 100% pass, Encoder 99.5% pass.
* 🔍 **Precise Error Diagnostics (Rich Error Reporting)**
  Provides structured errors, source code snippets, and precise location (line/column/pixel-level pointer).
* ⏱️ **Native Time Type Support**
  Fully covers the TOML 1.1.0 time model (Offset / Local / Date / Time).

---

## ❌ Precise Error Diagnostics (Rich Error Reporting)

When TOML format is illegal, the parser provides **structured error information + precise location + context snippets**.
The cursor pointer (`^`) not only precisely locates the column but also automatically calculates visual width based on
full-width/half-width characters (such as Chinese, Emoji), achieving pixel-level alignment:

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
>    6 | [[fruit]]  # The parser must throw an error when it discovers "fruit" is an array and not a table
           ^^^^^
     7 | name = "apple"
```

Supported error types (partial):

* `UNEXPECTED_TOKEN`: Illegal syntax structure
* `INVALID_CHAR`: Illegal character
* `INVALID_TYPE`: Type error
* `CONFLICTING_KEY`: Key conflict
* `INVALID_TABLE_DEFINITION`: Illegal table definition

Each error includes:

* Line number / Column number
* Raw code snippet
* Precise pointer (`^`)
* Readable error description

👉 Not just a parser, but can also be used as a **TOML Linter / Validation Tool**.

Additionally, detailed error information is **configurable** (default: enabled):

```php
use Petalbranch\Toml\Toml;

Toml::enableDetailedErrors(false); // Globally disable detailed error output
```

## 🔄 Lossless Round-Trip & Dynamic Modification (Lossless Round-Trip)

Most parsers lose comments when dumping. This library allows you to directly manipulate the AST (Abstract Syntax Tree),
modify values, and re-dump, with all comments and formatting perfectly preserved:

```php
use Petalbranch\Toml\Parser\Lexer;
use Petalbranch\Toml\Parser\Parser;
use Petalbranch\Toml\Dumper\NodeDumper;
use Petalbranch\Toml\Model\DumperConfig;

$originalToml = <<<TOML
# Database core configuration
[database]
server = "192.168.1.1"  # Main database IP
port = 3306             # Do not modify in production environment
TOML;

// 1. Parse into an AST tree containing full comments
$parser = new Parser(new Lexer());
$rootNode = $parser->parseToNode($originalToml); 

// 2. Directly modify the value of the AST node (type inferred automatically)
$rootNode->get('database')->get('port')->setValue(6379);

// 3. Directly dump the tree
$dumper = new NodeDumper(new DumperConfig());
echo $dumper->dump($rootNode);

/* Output Result: Comments are perfectly preserved!
# Database core configuration
[database]
server = "192.168.1.1"  # Main database IP
port = 6379             # Do not modify in production environment
*/
```

## 💅 Ultimate Aesthetics Dump (Alignment Formatting)

A blessing for perfectionists! After enabling the `alignEquals` configuration, the Dumper will automatically pre-scan
key names at the same level and use spaces to intelligently align all equal signs:

```php
use Petalbranch\Toml\Toml;
use Petalbranch\Toml\Model\DumperConfig;

$config = new DumperConfig();
$config->alignEquals = true; // Enable equal sign alignment

$data = [
    'server' => [
        'ip' => '127.0.0.1',
        'max_connections' => 1000,
        '中文端口号' => 8080,
        'enable' => true
    ]
];

echo Toml::dump($data, $config);

/* Outputs extremely clean formatting:
[server]
ip              = "127.0.0.1"
max_connections = 1000
"中文端口号"    = 8080
enable          = true
*/
```

---

## 🧪 Test Coverage (toml-test)

This project is verified based on the official `toml-test v2.1.0` test suite:

```text
> cd tests && .\toml-test-v2.1.0-windows-amd64.exe test -toml "1.1" -decoder "php toml-test-decoder.php" -encoder "php toml-test-encoder.php"

toml-test v2.1.0
  valid tests:   214 passed, 0 failed
  encoder tests: 213 passed, 1 failed
  invalid tests: 466 passed, 0 failed
```

Explanation:

* **Parser (Decoder)**: 100% pass
* **Invalid Cases (Invalid)**: 100% correctly rejected
* **Generator (Encoder)**: Only 1 boundary difference (does not affect major use cases)

👉 Covers a large number of TOML specification boundary cases, ensuring the reliability and consistency of the
implementation.

> ⚠️ Unique Failed Test Case Explanation (encoder/key/quoted-unicode):
>
> This test case contains `\u0000` (null byte) as a key name, and passes it to the encoder via JSON.
> Due to PHP's underlying limitations on null bytes in string/array keys, and the inability of the JSON parsing pipeline
> to reliably handle this key, the input is deemed illegal before entering the encoder (`Invalid JSON input`).
>
> 👉 This behavior is consistent with the library's "Known Limitations" and does not affect regular TOML use cases.

---

## 🤔 Why Choose Petalbranch TOML?

| Feature                     | This Library                                            | Common Implementations                  |
|-----------------------------|---------------------------------------------------------|-----------------------------------------|
| API Friendliness            | ✅ Provides `toml_encode`/`toml_decode` global functions | ❌ Only supports verbose class calls     |
| Static Analysis (PHPStan)   | ✅ Level 9 full pass                                     | ❌ Generally has `mixed` warnings        |
| Memory Usage (Lexer)        | ✅ O(1) extremely fast byte cursor                       | ❌ O(N) full array split                 |
| Lossless Comment Round-Trip | ✅ Perfect support (AST level)                           | ❌ Comments lost during dumping          |
| Error Diagnostics           | ✅ Source snippet + pixel-level cursor                   | ❌ Only throws simple Exception          |
| Generation Quality (Dumper) | ✅ Smart inline + equal sign alignment                   | ❌ Mechanical line break output          |
| Specification Coverage      | ✅ Very High (toml-test verified)                        | ⚠️ Partial implementation / Behind spec |

---

## 🧠 Architecture Overview

```text
TOML Text
   ↓ (O(1) byte cursor scan)
Lexer (Lexical analysis, generates Token Stream)
   ↓ 
Parser (Syntax analysis, collects comments and hierarchy)
   ↓
AST (Abstract Syntax Tree containing all metadata) ——> [Can directly modify nodes for lossless round-trip]
   ↓
Hydration (Converts to pure PHP array)
   ↓
Dumper (Serializes back to TOML, supports alignment and smart formatting)
```

---

## 📦 Installation

Install via Composer (requires PHP 8.3+):

```bash
composer require petalbranch/toml
```

---

## 🚀 Quick Start

### 0. Smooth Global Helper Functions (Recommended)

If you are familiar with PHP's JSON functions, then you have already mastered 90% of this library's usage:

```php
// 1. Parse TOML string to PHP array
$tomlString = 'title = "Petalbranch"';
$data = toml_decode($tomlString);

// 2. Encode PHP array to TOML string
$config = [
    'server' => [
        'ip' => '127.0.0.1',
        'port' => 8080
    ]
];
echo toml_encode($config);
```

### 1. Parse TOML (Parse)

```php
use Petalbranch\Toml\Toml;

// Parse file to PHP array
$config = Toml::parseFile(__DIR__ . '/config.toml');

// Directly parse string
$tomlString = 'title = "Petalbranch"';
$data = Toml::parse($tomlString);
```

---

### 2. Generate TOML (Dump)

```php
use Petalbranch\Toml\Toml;
use Petalbranch\Toml\Model\DumperConfig;

$data = [
    'title' => 'TOML Example',
    'owner' => ['name' => 'Tom', 'dob' => new \DateTime('1979-05-27T07:32:00-08:00')]
];

// Generate string using default configuration
$toml = Toml::dump($data);

// Use custom configuration and write directly to file
$config = new DumperConfig();
$config->inlineTable = true; // Enable smart compression for inline tables
Toml::dumpFile(__DIR__ . '/config.generated.toml', $data, $config);
```

---

## 🎯 Use Cases

* Configuration file parsing (replacement for JSON / YAML)
* CLI tool configuration systems
* Framework configuration layers (Webman / Laravel style)
* TOML validation tools (CI / Lint)
* Build tools / Package managers

---

## ⚙️ Performance & Production Environment Suggestions

In production environments, it is recommended to cache the parsed PHP array to achieve extreme performance (e.g., using
OPcache):

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

Due to limitations in PHP's underlying hash table object model, this library does not support using null bytes (
`\u0000`) as object key names. This is extremely rare in normal engineering practice.

---

## 📄 License

This project is open-sourced under the Apache License 2.0 - see the [LICENSE](LICENSE.txt) file for more details.
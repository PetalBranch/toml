<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Parser;

use Generator;
use LogicException;
use Petalbranch\Toml\Contract\Lexer\LexerInterface;
use Petalbranch\Toml\Contract\Lexer\TokenStreamInterface;
use Petalbranch\Toml\Exception\ParseException;
use Petalbranch\Toml\Support\LazyTokenStream;
use Petalbranch\Toml\Support\ThrowsErrorTrait;
use Petalbranch\Toml\Support\Token;
use Petalbranch\Toml\Type\ParseErrorType;
use Petalbranch\Toml\Type\TokenType;


/**
 * TOML 词法分析器实现类
 *
 * 实现了 LexerInterface 接口，负责将 TOML 源代码解析为词法单元流。
 * 使用状态机模型和生成器实现惰性扫描，支持所有 TOML 规范的词法结构。
 *
 * @package Petalbranch\Toml\Parser
 */
class Lexer implements LexerInterface
{
    use ThrowsErrorTrait;

    private string $source = '';
    private int $byteLength = 0;

    // 游标状态
    private int $cursor = 0;
    private int $line = 1;
    private int $column = 1;

    // 缓存当前字符，避免重复计算截取
    private ?string $currentChar = null;
    private int $currentCharLen = 0;

    // UTF-8 字节码常量
    private const int UTF8_BYTE_128 = 128; // 10000000
    private const int UTF8_CONTINUATION_THRESHOLD = 192; // 11000000
    private const int UTF8_2BYTE_THRESHOLD = 224; // 11100000
    private const int UTF8_3BYTE_THRESHOLD = 240; // 11110000

    // 正则模式数组，用于匹配不同类型的词法单元
    private const array patterns = [
        TokenType::BOOLEAN->name => '/^(true|false)(?![A-Za-z0-9_-])/',
        TokenType::FLOAT->name . '_SPECIAL' => '/^[+-]?(inf|nan)(?![A-Za-z0-9_-])/',
        TokenType::OFFSET_DATETIME->name => '/^\d{4}-\d{2}-\d{2}[Tt ]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:[Zz]|[+-]\d{2}:\d{2})(?![A-Za-z0-9_-])/',
        TokenType::LOCAL_DATETIME->name => '/^\d{4}-\d{2}-\d{2}[Tt ]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?![A-Za-z0-9_-])/',
        TokenType::LOCAL_TIME->name => '/^\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?![A-Za-z0-9_-])/',
        TokenType::LOCAL_DATE->name => '/^\d{4}-\d{2}-\d{2}(?![A-Za-z0-9_-])/',
        TokenType::FLOAT->name => '/^[+-]?(?:0|[1-9](?:_?\d)*)(?:\.\d(?:_?\d)*(?:[eE][+-]?\d(?:_?\d)*)?|[eE][+-]?\d(?:_?\d)*)(?![A-Za-z0-9_-])/',
        TokenType::INTEGER->name . '_HEX' => '/^0x[0-9a-fA-F](?:_?[0-9a-fA-F])*(?![A-Za-z0-9_-])/',
        TokenType::INTEGER->name . '_OCT' => '/^0o[0-7](?:_?[0-7])*(?![A-Za-z0-9_-])/',
        TokenType::INTEGER->name . '_BIN' => '/^0b[01](?:_?[01])*(?![A-Za-z0-9_-])/',
        TokenType::INTEGER->name => '/^[+-]?(?:0|[1-9](?:_?\d)*)(?![A-Za-z0-9_-])/',
        TokenType::IDENTIFIER->name => '/^[A-Za-z0-9_-]+/',
    ];

    /**
     * 将 TOML 源代码转换为词法单元流
     *
     * 初始化词法分析器的状态，并返回一个惰性加载的词法单元流。
     *
     * @param string $source 要解析的 TOML 源代码字符串
     * @return TokenStreamInterface 返回词法单元流对象
     */
    public function tokenize(string $source): TokenStreamInterface
    {
        $this->rawSource = $source;
        $this->source = $source;

        // 注意：这里取的是字节长度，不是字符长度，速度极快！
        $this->byteLength = strlen($source);

        $this->cursor = 0;
        $this->line = 1;
        $this->column = 1;

        // 初始化第一个字符
        $this->updateCurrentChar();

        return new LazyTokenStream($this->scan());
    }

    /**
     * 核心扫描生成器 (状态机主循环)
     *
     * @return Generator<Token>
     */
    private function scan(): Generator
    {
        while (!$this->isAtEnd()) {
            $char = $this->currentChar();

            // 1. 忽略空白字符 (空格、制表符)
            if ($char === ' ' || $char === "\t") {
                $this->advance();
                continue;
            }

            // 2. 处理换行符 (CRLF 或 LF)
            if ($char === "\n" || $char === "\r") {
                yield $this->scanNewline();
                continue;
            }

            // 3. 处理注释
            if ($char === '#') {
                yield $this->scanComment();
                continue;
            }

            // 4. 处理单字符符号
            if ($this->isSingleCharToken($char)) {
                yield $this->scanSingleCharToken($char);
                continue;
            }

            // 5. 状态分发：字符串
            if ($char === '"' || $char === "'") {
                yield $this->scanString();
                continue;
            }

            // 6. 状态分发：裸键 (Bare Key) 或 关键字 (布尔值)
            if ($this->isAlphaNumericOrDash($char)) {
                yield $this->scanIdentifierOrNumber();
                continue;
            }

            // 如果走到这里，说明遇到了不认识的字符 (包含非法的控制字符等)
            $this->throwError(
                sprintf('Unexpected character "%s"', $char),
                ParseErrorType::INVALID_CHAR,
                $this->line,
                $this->column
            );
        }

        // 最终一定要吐出一个 EOF，告诉 Parser 结束了
        yield new Token(TokenType::EOF, '', null, $this->line, $this->column, $this->line, $this->column);
    }


    // ==========================================
    // 动态 UTF-8 游标引擎
    // ==========================================

    /**
     * 更新当前字符信息
     *
     * 该方法根据当前游标位置从源字符串中读取下一个UTF-8字符，并更新当前字符及其长度属性。
     * 通过检查UTF-8编码的首字节ASCII码值来确定字符的字节长度，支持1-4字节的UTF-8字符。
     *
     * @return void
     */
    private function updateCurrentChar(): void
    {
        if ($this->cursor >= $this->byteLength) {
            $this->currentChar = null;
            $this->currentCharLen = 0;
            return;
        }

        // 通过读取首个字节的 ASCII 码，瞬间判断该 UTF-8 字符的长度！
        $byte = ord($this->source[$this->cursor]);

        if ($byte < self::UTF8_BYTE_128) { // 0xxxxxxx: 单字节 (ASCII 字符)
            $this->currentCharLen = 1;
        } elseif ($byte < self::UTF8_2BYTE_THRESHOLD) { // 110xxxxx: 双字节 (如部分拉丁文、希腊字母)
            $this->currentCharLen = 2;
        } elseif ($byte < self::UTF8_3BYTE_THRESHOLD) { // 1110xxxx: 三字节 (如大部分中文字符)
            $this->currentCharLen = 3;
        } else { // 11110xxx: 四字节 (如 Emoji 💩)
            $this->currentCharLen = 4;
        }

        $this->currentChar = substr($this->source, $this->cursor, $this->currentCharLen);
    }

    /**
     * 判断是否已到达源字符串末尾
     *
     * 检查当前游标位置是否已经到达或超过源字符串的字节长度，
     * 用于确定词法分析是否已完成。
     *
     * @return bool 如果游标已到达或超过源字符串末尾返回true，否则返回false
     * @phpstan-impure
     */
    private function isAtEnd(): bool
    {
        return $this->cursor >= $this->byteLength;
    }

    /**
     * 获取当前字符
     *
     * 返回当前解析位置的字符。如果当前没有字符（如已到达源字符串末尾），
     * 则返回空字符串。
     *
     * @return string 当前字符，若无则返回空字符串
     */
    private function currentChar(): string
    {
        return $this->currentChar ?? '';
    }


    /**
     * 向前查看指定偏移量的字符
     *
     * 该方法模拟游标向前移动指定数量的字符，然后返回目标位置的UTF-8字符，
     * 而不会实际改变当前游标位置。用于语法分析时的前瞻判断。
     *
     * @param int $charOffset 要向前查看的字符数量，默认为1
     * @return string|null 返回目标位置的字符，如果超出源字符串长度则返回null
     */
    private function peekChar(int $charOffset = 1): ?string
    {
        $tempCursor = $this->cursor;

        // 模拟游标往前跳指定的字符数
        for ($i = 0; $i < $charOffset; $i++) {
            if ($tempCursor >= $this->byteLength) return null;
            $len = $this->getCharLength($tempCursor);
            if ($len === 0) return null;
            $tempCursor += $len;
        }

        // 提取最终到达位置的字符
        if ($tempCursor >= $this->byteLength) return null;
        $len = $this->getCharLength($tempCursor);

        return substr($this->source, $tempCursor, $len);
    }

    /**
     * 向前移动一个字符位置
     *
     * 将词法分析器的游标向前移动当前字符的字节长度，同时更新列号，
     * 并预加载下一个字符的信息。此方法用于处理完当前字符后，
     * 移动到下一个字符进行分析。
     *
     * @return string 返回移动前的当前字符
     */
    private function advance(): string
    {
        $char = $this->currentChar();
        // 游标直接加上该字符的【字节长度】
        $this->cursor += $this->currentCharLen;
        $this->column++;

        // 更新下一个字符的缓存
        $this->updateCurrentChar();

        return $char;
    }

    // ==========================================
    // 状态机具体实现：基础片段
    // ==========================================


    /**
     * 扫描换行符
     *
     * 处理 TOML 中的换行符，支持 Unix (\n) 和 Windows (\r\n) 格式的换行。
     * 扫描换行符后会自动更新行号和列号计数器。
     *
     * @return Token 返回换行符词法单元对象，类型为 TokenType::NEWLINE
     */
    private function scanNewline(): Token
    {
        $startLine = $this->line;
        $startCol = $this->column;
        $char = $this->advance(); // 可能是 \n 或 \r

        $lexeme = $char;

        // 处理 Windows 的 \r\n
        if ($char === "\r") {
            if (!$this->isAtEnd() && $this->currentChar() === "\n") {
                $lexeme .= $this->advance(); // 合法的 CRLF
            } else {
                // 拦截非法的裸 \r (Bare CR)
                $this->throwError(
                    "Bare CR (carriage return) is not allowed",
                    ParseErrorType::INVALID_CHAR,
                    $this->line,
                    $this->column
                );
            }
        }

        // 换行后，行号+1，列号归 1
        $this->line++;
        $this->column = 1;

        return new Token(
            TokenType::NEWLINE,
            $lexeme,
            $lexeme,
            $startLine,
            $startCol,
            $startLine, // 换行符自身的结束行还是当前行
            $startCol + mb_strlen($lexeme) - 1
        );
    }


    /**
     * 扫描注释内容
     *
     * 从 # 字符开始扫描注释，直到遇到换行符或文件末尾。
     * 注释内容会保留 # 符号作为词法单元的原始文本，解析后的值会去掉 # 符号并去除首尾空白。
     *
     * @return Token 返回注释词法单元对象，类型为 TokenType::COMMENT
     */
    private function scanComment(): Token
    {
        $startLine = $this->line;
        $startCol = $this->column;
        $comment = '#';
        $this->advance(); // 先把 '#' 吃掉

        while (!$this->isAtEnd() && $this->currentChar() !== "\n" && $this->currentChar() !== "\r") {
            $char = $this->currentChar();

            // 拦截非法的控制字符，参阅：https://toml.io/en/v1.1.0#comment；
            if ($this->isControlChar($char) && $char !== "\t") {
                $this->throwError(
                    "Invalid control character in comment",
                    ParseErrorType::INVALID_CHAR,
                    $this->line,
                    $this->column
                );
            }

            $comment .= $this->advance();
        }
        // 注意：不要在这里吃掉换行符，留给下一轮循环的 scanNewline 处理，
        // 这样 Parser 就能准确知道这里发生了一次换行。
        return new Token(TokenType::COMMENT, $comment, trim(ltrim($comment, '#')), $startLine, $startCol, $this->line, $this->column);
    }

    /**
     * 检查字符是否为单字符词法单元
     *
     * @param string $char 要检查的字符
     * @return bool 如果字符是单字符词法单元（=、.、,、[、]、{、}）则返回 true，否则返回 false
     */
    private function isSingleCharToken(string $char): bool
    {
        return in_array($char, ['=', '.', ',', '[', ']', '{', '}'], true);
    }

    /**
     * 扫描单个字符词法单元
     *
     * 处理 TOML 中的单字符符号，如等号、点号、逗号、方括号和花括号等
     *
     * @param string $char 要扫描的单个字符
     * @return Token 返回对应的词法单元对象
     */
    private function scanSingleCharToken(string $char): Token
    {
        $startCol = $this->column;
        $this->advance();

        $type = match ($char) {
            '=' => TokenType::EQUAL,
            '.' => TokenType::DOT,
            ',' => TokenType::COMMA,
            '[' => TokenType::LEFT_BRACKET,
            ']' => TokenType::RIGHT_BRACKET,
            '{' => TokenType::LEFT_BRACE,
            '}' => TokenType::RIGHT_BRACE,
            default => throw new LogicException("Unreachable")
        };

        return new Token($type, $char, $char, $this->line, $startCol, $this->line, $startCol);
    }

    /**
     * 检查字符是否为字母、数字、下划线或中划线
     *
     * @param string $char 要检查的单个字符
     * @return bool 如果字符是字母、数字、下划线或中划线则返回 true，否则返回 false
     */
    private function isAlphaNumericOrDash(string $char): bool
    {
        // 允许字母、数字、下划线、中划线 (用于裸键和数字)
        return preg_match('/^[a-zA-Z0-9_\-+]$/', $char) === 1;
    }

    /**
     * 扫描字符串
     *
     * 根据引号类型和数量判断字符串的种类，并调用相应的扫描方法。
     * 支持四种 TOML 字符串类型：基本字符串、字面量字符串、多行基本字符串和多行字面量字符串。
     *
     * @return Token 返回扫描后的字符串词法单元对象
     */
    private function scanString(): Token
    {
        $quote = $this->currentChar();
        // 向前看两个字符，判断是否是多行字符串 (""" 或 ''')
        $isMultiline = $this->peekChar() === $quote && $this->peekChar(2) === $quote;
        if ($quote === "'") {
            return $isMultiline ? $this->scanMultilineLiteralString() : $this->scanLiteralString();
        } else {
            return $isMultiline ? $this->scanMultilineBasicString() : $this->scanBasicString();
        }
    }

    /**
     * 扫描字面量字符串
     *
     * 处理 TOML 中的字面量字符串（由单引号包围），不支持转义序列。
     * 字面量字符串不能包含换行符和未转义的控制字符（Tab 除外）。
     *
     * @return Token 返回字面量字符串词法单元对象，类型为 TokenType::STRING_LITERAL
     * @throws ParseException 当字符串格式错误、包含非法字符或未正确终止时抛出异常
     */
    private function scanLiteralString(): Token
    {
        $startLine = $this->line;
        $startCol = $this->column;

        $this->advance(); // 消耗开头的 '
        $lexeme = "'";
        $value = "";

        while (!$this->isAtEnd() && $this->currentChar() !== "'") {
            $char = $this->currentChar();

            // 单行字符串不允许真正的换行符
            if ($char === "\n" || $char === "\r") {
                $this->throwError("Literal strings cannot contain newlines", ParseErrorType::INVALID_CHAR, $this->line, $this->column);
            }

            // 检查非法控制字符 (TOML 规范要求除 Tab 外的控制字符必须被转义，但字面量无法转义，所以直接报错)
            // 参考：https://toml.io/en/v1.1.0#string
            if ($this->isControlChar($char) && $char !== "\t") {
                $this->throwError("Invalid control character in string", ParseErrorType::INVALID_CHAR, $this->line, $this->column);
            }

            $lexeme .= $char;
            $value .= $char;
            $this->advance();
        }

        if ($this->isAtEnd()) {
            $this->throwError("Unterminated literal string", ParseErrorType::UNEXPECTED_EOF, $this->line, $this->column);
        }

        $lexeme .= $this->currentChar(); // 加上结尾的 '
        $this->advance(); // 消耗结尾的 '

        return new Token(TokenType::STRING_LITERAL, $lexeme, $value, $startLine, $startCol, $this->line, $this->column - 1);
    }


    /**
     * 扫描基本字符串
     *
     * 处理 TOML 中的基本字符串（由双引号包围），支持转义序列和控制字符检查。
     * 基本字符串不能包含未转义的控制字符和换行符。
     *
     * @return Token 返回基本字符串词法单元对象，类型为 TokenType::STRING_BASIC
     * @throws ParseException 当字符串格式错误、包含非法字符或未正确终止时抛出异常
     */
    private function scanBasicString(): Token
    {
        $startLine = $this->line;
        $startCol = $this->column;

        $this->advance(); // 消耗开头的 "
        $lexeme = '"';
        $value = "";

        while (!$this->isAtEnd() && $this->currentChar() !== '"') {
            $char = $this->currentChar();

            if ($char === "\n" || $char === "\r") {
                $this->throwError("Basic strings cannot contain newlines", ParseErrorType::INVALID_CHAR, $this->line, $this->column);
            }

            if ($this->isControlChar($char) && $char !== "\t") {
                $this->throwError("Invalid control character in string", ParseErrorType::INVALID_CHAR, $this->line, $this->column);
            }

            // 处理转义字符
            $lexeme .= $char;
            if ($char === '\\') {
                $this->advance(); // 消耗反斜杠

                if ($this->isAtEnd()) {
                    $this->throwError("Unterminated escape sequence", ParseErrorType::UNEXPECTED_EOF, $this->line, $this->column);
                }

                $escapeResult = $this->scanEscapeSequence();
                $lexeme .= $escapeResult['lexeme'];
                $value .= $escapeResult['value'];
            } else {
                $value .= $char;
                $this->advance();
            }
        }

        if ($this->isAtEnd()) {
            $this->throwError("Unterminated basic string", ParseErrorType::UNEXPECTED_EOF, $this->line, $this->column);
        }

        $lexeme .= $this->currentChar();
        $this->advance(); // 消耗结尾的 "

        return new Token(TokenType::STRING_BASIC, $lexeme, $value, $startLine, $startCol, $this->line, $this->column - 1);
    }

    /**
     * 扫描转义序列
     *
     * 处理 TOML 字符串中的转义序列，包括普通转义字符（\b, \t, \n, \f, \r, \e, \", \\）
     * 和特殊转义序列（\xHH, \uXXXX, \UXXXXXXXX）。
     * 支持 Toml v1.1.0 新增的 \e (ESC 字符) 和 \xHH (基本十六进制转义) 功能
     *
     * @return array{lexeme: string, value: string} 返回包含词法单元原始文本和解析后值的关联数组
     *               - lexeme: 转义序列的原始字符串表示（包括反斜杠和后续字符）
     *               - value: 转义序列解析后的实际字符
     * @throws ParseException 当遇到无效的转义序列时抛出异常
     */
    private function scanEscapeSequence(): array
    {
        $char = $this->advance();

        $value = match ($char) {
            'b' => "\x08",
            't' => "\t",
            'n' => "\n",
            'f' => "\f",
            'r' => "\r",
            'e' => "\e", // Toml v1.1.0 新增：\e 转换为 ESC 字符 (U+001B)
            '"' => '"',
            '\\' => '\\',
            'x' => $this->scanHexEscape(), // Toml v1.1.0 新增：\xHH 基本十六进制转义
            'u' => $this->scanUnicodeEscape(4),
            'U' => $this->scanUnicodeEscape(8),
            default => $this->throwError(sprintf('Invalid escape sequence: \\%s', $char), ParseErrorType::INVALID_CHAR, $this->line, $this->column - 1)
        };

        // 如果是十六进制或 Unicode 转义，lexeme 需要把后面的 Hex 字符也算上
        $lexeme = $char;
        if ($char === 'x' || $char === 'u' || $char === 'U') {
            $len = $char === 'x' ? 2 : ($char === 'u' ? 4 : 8);
            // 因为十六进制字符全是 ASCII（单字节），所以直接截取光标之前的 $len 个字节
            $lexeme .= substr($this->source, $this->cursor - $len, $len);
        }

        return ['lexeme' => $lexeme, 'value' => $value];
    }

    /**
     * 扫描 Unicode 转义序列
     *
     * 从输入中读取指定长度的十六进制字符，将其转换为对应的 Unicode 字符。
     * 该方法会验证转义序列的格式和 Unicode 码点的有效性。
     *
     * @param int $length 要读取的十六进制字符数量（4 表示基本多文种平面，6 表示补充平面）
     * @return string 返回解码后的 Unicode 字符
     * @throws ParseException 当转义序列格式错误或 Unicode 码点无效时抛出异常
     */
    private function scanUnicodeEscape(int $length): string
    {
        $startCol = $this->column;
        $hex = '';
        for ($i = 0; $i < $length; $i++) {
            if ($this->isAtEnd()) {
                $this->throwError("Unterminated Unicode escape", ParseErrorType::UNEXPECTED_EOF, $this->line, $this->column);
            }
            $char = $this->advance();
            if (preg_match('/^[0-9a-fA-F]$/', $char) !== 1) {
                $this->throwError(sprintf('Invalid Unicode escape character: %s', $char), ParseErrorType::INVALID_CHAR, $this->line, $startCol + 1 + $i);
            }
            $hex .= $char;
        }

        $codePoint = hexdec($hex);

        // TOML 规范要求：Unicode 标量值必须在有效范围内
        if (($codePoint >= 0xD800 && $codePoint <= 0xDFFF) || $codePoint > 0x10FFFF) {
            $this->throwError(sprintf('Invalid Unicode scalar value: %X', $codePoint), ParseErrorType::INVALID_CHAR, $this->line, $startCol);
        }

        return mb_chr((int)$codePoint, 'UTF-8');
    }

    /**
     * 扫描多行字面量字符串
     *
     * 处理 TOML 中的多行字面量字符串（由三个单引号包围），不支持转义序列。
     * 支持跨行内容，自动处理开头的换行符，并检查控制字符的合法性。
     *
     * @return Token 返回多行字面量字符串词法单元对象，类型为 TokenType::STRING_MULTILINE_LITERAL
     * @throws ParseException 当字符串格式错误、包含非法字符或未正确终止时抛出异常
     */
    private function scanMultilineLiteralString(): Token
    {
        $startLine = $this->line;
        $startCol = $this->column;

        // 消耗开头的 '''
        $this->advanceN(3);
        $lexeme = "'''";
        $value = "";

        // TOML 规则：如果多行字符串紧跟一个换行符，必须忽略该换行符
        $char = $this->currentChar();
        if ($char === "\n" || $char === "\r") {
            $lexeme .= $this->consumeStringNewline();
        }

        while (!$this->isAtEnd()) {
            // 分离字符串尾部的单引号和结束符
            if ($this->currentChar() === "'") {
                $quotes = 1;
                while ($this->peekChar($quotes) === "'") {
                    $quotes++;
                }

                if ($quotes >= 3) {
                    $contentQuotes = $quotes - 3;
                    if ($contentQuotes > 2) {
                        $this->throwError("Too many quotes in multiline literal string", ParseErrorType::INVALID_CHAR, $this->line, $this->column);
                    }

                    for ($i = 0; $i < $contentQuotes; $i++) {
                        $lexeme .= $this->advance();
                        $value .= "'";
                    }
                    break;
                }
            }

            $char = $this->currentChar();

            // 维护行号
            if ($char === "\n" || $char === "\r") {
                $nl = $this->consumeStringNewline();
                $lexeme .= $nl;
                $value .= $nl;
                continue;
            }

            // 检查非法控制字符
            if ($this->isControlChar($char) && $char !== "\t") {
                $this->throwError("Invalid control character in multiline literal string", ParseErrorType::INVALID_CHAR, $this->line, $this->column);
            }

            $lexeme .= $char;
            $value .= $char;
            $this->advance();
        }

        if ($this->isAtEnd()) {
            $this->throwError("Unterminated multiline literal string", ParseErrorType::UNEXPECTED_EOF, $startLine, $startCol);
        }

        // 消耗结尾的 '''
        $this->advanceN(3);
        $lexeme .= "'''";

        return new Token(TokenType::STRING_MULTILINE_LITERAL, $lexeme, $value, $startLine, $startCol, $this->line, $this->column - 1);
    }


    /**
     * 扫描多行基本字符串
     *
     * 处理 TOML 中的多行基本字符串（由三个双引号包围），支持转义序列和行尾反斜杠折叠。
     * 多行基本字符串可以跨行，自动处理开头的换行符，并支持行尾反斜杠折叠功能
     * （即反斜杠后跟空白符和换行符时，这些字符会被忽略）。
     *
     * @return Token 返回多行基本字符串词法单元对象，类型为 TokenType::STRING_MULTILINE_BASIC
     * @throws ParseException 当字符串格式错误、包含非法字符或未正确终止时抛出异常
     */
    private function scanMultilineBasicString(): Token
    {
        $startLine = $this->line;
        $startCol = $this->column;

        $this->advanceN(3);
        $lexeme = '"""';
        $value = "";

        $char = $this->currentChar();
        if ($char === "\n" || $char === "\r") {
            $lexeme .= $this->consumeStringNewline();
        }

        while (!$this->isAtEnd()) {
            // 分离字符串尾部的双引号和结束符
            if ($this->currentChar() === '"') {
                $quotes = 1;
                while ($this->peekChar($quotes) === '"') {
                    $quotes++;
                }

                if ($quotes >= 3) {
                    $contentQuotes = $quotes - 3;
                    // TOML 允许紧邻结束符的 1 到 2 个双引号，如果多于 2 个则是非法的连续引号
                    if ($contentQuotes > 2) {
                        $this->throwError("Too many quotes in multiline basic string", ParseErrorType::INVALID_CHAR, $this->line, $this->column);
                    }

                    // 把属于内容的引号提前吃掉
                    for ($i = 0; $i < $contentQuotes; $i++) {
                        $lexeme .= $this->advance();
                        $value .= '"';
                    }
                    break; // 完美抽身，把剩下的 3 个留给外面的收尾逻辑
                }
            }

            $char = $this->currentChar();

            // TOML 规则：行尾反斜杠折叠 (Line continuation)
            // 如果遇到 \，且后面跟着的是空白符+换行符，则无视之后所有的空白和换行
            if ($char === '\\' && ($this->peekChar() === "\n" || $this->peekChar() === "\r" || $this->peekChar() === ' ' || $this->peekChar() === "\t")) {
                $lookahead = 1;
                $isContinuation = false;

                // 向前探测：这到底是一个转义的空格，还是真正的行尾折叠？
                while (($peek = $this->peekChar($lookahead)) !== null) {
                    if ($peek === ' ' || $peek === "\t") {
                        $lookahead++;
                    } elseif ($peek === "\n" || $peek === "\r") {
                        $isContinuation = true;
                        break;
                    } else {
                        break;
                    }
                }

                if ($isContinuation) {
                    $lexeme .= $this->advance(); // 吃掉 \
                    // 疯狂吃掉后面所有的空格和换行符，直到遇到正常字符，且不将其加入 $value
                    while (!$this->isAtEnd()) {
                        $c = $this->currentChar();
                        if ($c === ' ' || $c === "\t") {
                            $lexeme .= $this->advance();
                        } elseif ($c === "\n" || $c === "\r") {
                            $lexeme .= $this->consumeStringNewline();
                        } else {
                            break;
                        }
                    }
                    continue; // 折叠处理完毕，直接进入下一轮
                }
            }

            // 正常的转义序列处理
            if ($char === '\\') {
                $lexeme .= $char;
                $this->advance();
                if ($this->isAtEnd()) $this->throwError("Unterminated escape sequence", ParseErrorType::UNEXPECTED_EOF, $this->line, $this->column);
                $escapeResult = $this->scanEscapeSequence(); // 复用之前写的单行字符串转义方法
                $lexeme .= $escapeResult['lexeme'];
                $value .= $escapeResult['value'];
                continue;
            }

            if ($char === "\n" || $char === "\r") {
                $nl = $this->consumeStringNewline();
                $lexeme .= $nl;
                $value .= $nl;
                continue;
            }

            if ($this->isControlChar($char) && $char !== "\t") {
                $this->throwError("Invalid control character in multiline basic string", ParseErrorType::INVALID_CHAR, $this->line, $this->column);
            }

            $lexeme .= $char;
            $value .= $char;
            $this->advance();
        }

        if ($this->isAtEnd()) {
            $this->throwError("Unterminated multiline basic string", ParseErrorType::UNEXPECTED_EOF, $startLine, $startCol);
        }

        $this->advanceN(3);
        $lexeme .= '"""';

        return new Token(TokenType::STRING_MULTILINE_BASIC, $lexeme, $value, $startLine, $startCol, $this->line, $this->column - 1);
    }


    /**
     * 判断字符是否为控制字符
     *
     * 检查给定的字符是否属于ASCII或Unicode控制字符范围。
     * 控制字符包括：
     * - ASCII NUL 到 BS (0x00-0x08)
     * - ASCII HT 到 US (0x0A-0x1F)
     * - ASCII DEL (0x7F)
     *
     * 该方法支持单字节字符和UTF-8多字节字符，根据输入字符的长度选择
     * 使用ord()或mb_ord()函数来获取字符的码点值。
     *
     * @param string $char 要检查的字符
     * @return bool 如果是控制字符返回true，否则返回false
     */
    private function isControlChar(string $char): bool
    {
        $len = strlen($char);
        if ($len === 1) {
            $ord = ord($char);
        } else {
            $ord = mb_ord($char, 'UTF-8');
        }
        return ($ord >= 0x00 && $ord <= 0x08) ||
            ($ord >= 0x0A && $ord <= 0x1F) ||
            ($ord === 0x7F);
    }


    /**
     * 扫描并识别标识符或数值字面量
     *
     * 该方法从当前位置开始扫描源代码，识别出完整的标识符或数值（包括整数、浮点数、
     * 布尔值、日期时间等）。使用预扫描机制来确定token边界，并通过正则表达式模式匹配
     * 来确定具体的token类型。
     *
     * 扫描过程分为两个阶段：
     * 1. 预扫描：使用字节级探测快速收集可能的字符序列，直到遇到分隔符
     * 2. 模式匹配：按优先级顺序应用正则表达式模式，确定最匹配的token类型
     *
     * 支持的token类型包括：
     * - 布尔值 (true/false)
     * - 浮点数 (包括inf/nan特殊值)
     * - 各种格式的日期时间
     * - 十进制/十六进制/八进制/二进制整数
     * - 普通标识符
     *
     * @return Token 返回识别出的token对象，包含类型、原始值、归一化值和位置信息
     */
    private function scanIdentifierOrNumber(): Token
    {
        $startLine = $this->line;
        $startCol = $this->column;

        $subject = '';
        $lookaheadCursor = $this->cursor; // 字节游标
        $charCount = 0;
        $maxLength = 100;

        // 使用底层字节探测，只要不碰到边界符号就一直收割
        while ($lookaheadCursor < $this->byteLength && $charCount < $maxLength) {
            $byteStr = $this->source[$lookaheadCursor];
            // ASCII 分隔符都在单字节范围内，直接用底层字符串查，速度爆表！
            if (in_array($byteStr, ["\n", "\r", '=', ',', '[', ']', '{', '}', '#'], true)) {
                break;
            }

            $len = $this->getCharLength($lookaheadCursor);

            $subject .= substr($this->source, $lookaheadCursor, $len);
            $lookaheadCursor += $len;
            $charCount++;
        }

        $matchedText = null;
        $matchedType = null;

        // 使用预编译的正则
        foreach (self::patterns as $typeName => $regex) {
            if (preg_match($regex, $subject, $matches)) {
                $matchedText = $matches[0];
                $matchedType = $typeName;
                break;
            }
        }

        if ($matchedText === null) {
            $this->throwError(sprintf('Unexpected syntax near "%s"', substr($subject, 0, 10)), ParseErrorType::INVALID_CHAR, $this->line, $this->column);
        }

        $realType = match (true) {
            str_starts_with($matchedType, 'FLOAT') => TokenType::FLOAT,
            str_starts_with($matchedType, 'INTEGER') => TokenType::INTEGER,
            default => constant(TokenType::class . '::' . $matchedType),
        };

        // 将主游标向前推进
        $byteLen = strlen($matchedText); // 因为 matchedText 来自 ASCII 边界内的子串
        $this->cursor += $byteLen;
        $this->column += mb_strlen($matchedText, 'UTF-8');
        $this->updateCurrentChar();

        $normalizedValue = $matchedText;
        if (str_starts_with($matchedType, 'INTEGER') || str_starts_with($matchedType, 'FLOAT')) {
            $normalizedValue = str_replace('_', '', $matchedText);
        }

        return new Token($realType, $matchedText, $normalizedValue, $startLine, $startCol, $this->line, $this->column - 1);
    }

    /**
     * 扫描并处理十六进制转义序列
     *
     * 该方法用于解析形如\xFF的十六进制转义序列，读取两个十六进制字符，
     * 验证其有效性，并将其转换为对应的UTF-8字符。
     * 如果遇到无效的十六进制字符或未终止的转义序列，将抛出相应的解析错误。
     *
     * @return string 返回由十六进制值解码得到的UTF-8字符
     */
    private function scanHexEscape(): string
    {
        $hex = '';
        for ($i = 0; $i < 2; $i++) {
            if ($this->isAtEnd()) {
                $this->throwError("Unterminated hex escape", ParseErrorType::UNEXPECTED_EOF, $this->line, $this->column);
            }
            $char = $this->advance();
            if (preg_match('/^[0-9a-fA-F]$/', $char) !== 1) {
                $this->throwError(sprintf('Invalid hex escape character: %s', $char), ParseErrorType::INVALID_CHAR, $this->line, $this->column - 1);
            }
            $hex .= $char;
        }

        $codePoint = hexdec($hex);
        return mb_chr((int)$codePoint, 'UTF-8');
    }


    /**
     * 消耗字符串内部的换行符 (LF 或 CRLF)，并维护行列号
     * 如果遇到非法的单独 CR (Bare CR)，则抛出异常
     *
     * @return string 返回被消耗的换行符序列
     */
    private function consumeStringNewline(): string
    {
        $char = $this->advance(); // 先吃掉当前的 \n 或 \r
        $newline = $char;

        if ($char === "\r") {
            if (!$this->isAtEnd() && $this->currentChar() === "\n") {
                $newline .= $this->advance(); // 再吃掉 \n，完美形成 \r\n
            } else {
                // 字符串内严禁裸 \r
                $this->throwError(
                    "Bare CR is not allowed in strings",
                    ParseErrorType::INVALID_CHAR,
                    $this->line,
                    $this->column
                );
            }
        }

        // 维护行列号
        $this->line++;
        $this->column = 1;

        return $newline;
    }


    /**
     * 获取指定位置UTF-8字符的字节长度
     *
     * 根据UTF-8编码规则，通过检查首字节的ASCII码值来确定该字符占用的字节数。
     * UTF-8编码规则：
     * - 单字节：0xxxxxxx (ASCII字符)
     * - 双字节：110xxxxx (如部分拉丁文、希腊字母)
     * - 三字节：1110xxxx (如大部分中文字符)
     * - 四字节：11110xxx (如Emoji表情)
     *
     * @param int $position 源字符串中的字节位置
     * @return int 返回字符的字节长度(1-4)，对于ASCII字符返回1
     */
    private function getCharLength(int $position): int
    {
        // 边界检查，避免越界访问
        if ($position < 0 || $position >= $this->byteLength) return 0;
        $byte = ord($this->source[$position]);

        if ($byte >= self::UTF8_CONTINUATION_THRESHOLD) {
            if ($byte < self::UTF8_2BYTE_THRESHOLD) return 2;
            elseif ($byte < self::UTF8_3BYTE_THRESHOLD) return 3;
            else return 4;
        }

        return 1;
    }


    /**
     * 向前移动指定数量的字符
     *
     * 将词法分析器的位置向前移动指定数量的字符，每次移动都会更新
     * 当前字符缓存和位置信息。这是advance()方法的批量版本。
     *
     * @param int $count 要移动的字符数量，默认为1
     * @return void
     */
    private function advanceN(int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->advance();
        }
    }
}
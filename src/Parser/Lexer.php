<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Parser;

use Generator;
use Petalbranch\Toml\Contract\Lexer\LexerInterface;
use Petalbranch\Toml\Contract\Lexer\TokenStreamInterface;
use Petalbranch\Toml\Exception\ParseException;
use Petalbranch\Toml\Support\LazyTokenStream;
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
    private array $chars = [];
    private int $length = 0;

    // 游标状态
    private int $cursor = 0;
    private int $line = 1;
    private int $column = 1;

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
        // 将原文本强行按 UTF-8 切成字符数组。哪怕是 多字节的 Emoji、语言字符，在这里也只是数组里的 1 个元素。
        if ($source !== '') {
            $this->chars = mb_str_split($source, 1, 'UTF-8');
            $this->length = count($this->chars);
        } else {
            $this->chars = [];
            $this->length = 0;
        }

        // 重置状态
        $this->cursor = 0;
        $this->line = 1;
        $this->column = 1;

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
            throw new ParseException(
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
    // 状态机辅助方法：游标控制
    // ==========================================


    /**
     * 检查是否已到达输入末尾
     *
     * @return bool 如果游标位置大于或等于输入长度则返回 true，否则返回 false
     */
    private function isAtEnd(): bool
    {
        return $this->cursor >= $this->length;
    }

    /**
     * 获取当前字符
     *
     * @return string 返回当前游标位置的字符
     */
    private function currentChar(): string
    {
        return $this->chars[$this->cursor];
    }

    /**
     * 获取当前字符的指定偏移量后的字符
     *
     * @param int $offset 偏移量，默认为 1
     * @return string|null 返回指定偏移量后的字符，如果超出输入范围则返回 null
     */
    private function peekChar(int $offset = 1): ?string
    {
        $target = $this->cursor + $offset;
        if ($target >= $this->length) return null;
        return $this->chars[$target];
    }

    /**
     * 消耗当前字符，游标向前移动一步，并维护行列号
     */
    private function advance(): string
    {
        $char = $this->chars[$this->cursor];
        $this->cursor++;

        // 注意：换行逻辑通常在具体的 scanNewline 中处理行号，
        // 但如果有些地方无脑跳过，这里也可以做一层兜底保障。
        $this->column++;

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
        if ($char === "\r" && !$this->isAtEnd() && $this->currentChar() === "\n") {
            $lexeme .= $this->advance();
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
        while (!$this->isAtEnd() && $this->currentChar() !== "\n" && $this->currentChar() !== "\r") {
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
        return preg_match('/^[a-zA-Z0-9_-]$/', $char) === 1;
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
                throw new ParseException("Literal strings cannot contain newlines", ParseErrorType::INVALID_CHAR, $this->line, $this->column);
            }

            // 检查非法控制字符 (TOML 规范要求除 Tab 外的控制字符必须被转义，但字面量无法转义，所以直接报错)
            // 参考：https://toml.io/en/v1.1.0#string
            if ($this->isControlChar($char) && $char !== "\t") {
                throw new ParseException("Invalid control character in string", ParseErrorType::INVALID_CHAR, $this->line, $this->column);
            }

            $lexeme .= $char;
            $value .= $char;
            $this->advance();
        }

        if ($this->isAtEnd()) {
            throw new ParseException("Unterminated literal string", ParseErrorType::UNEXPECTED_EOF, $this->line, $this->column);
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
                throw new ParseException("Basic strings cannot contain newlines", ParseErrorType::INVALID_CHAR, $this->line, $this->column);
            }

            if ($this->isControlChar($char) && $char !== "\t") {
                throw new ParseException("Invalid control character in string", ParseErrorType::INVALID_CHAR, $this->line, $this->column);
            }

            // 处理转义字符
            $lexeme .= $char;
            if ($char === '\\') {
                $this->advance(); // 消耗反斜杠

                if ($this->isAtEnd()) {
                    throw new ParseException("Unterminated escape sequence", ParseErrorType::UNEXPECTED_EOF, $this->line, $this->column);
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
            throw new ParseException("Unterminated basic string", ParseErrorType::UNEXPECTED_EOF, $this->line, $this->column);
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
            'b' => "\b",
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
            default => throw new ParseException(sprintf('Invalid escape sequence: \\%s', $char), ParseErrorType::INVALID_CHAR, $this->line, $this->column - 1)
        };

        // 如果是十六进制或 Unicode 转义，lexeme 需要把后面的 Hex 字符也算上
        $lexeme = $char;
        if ($char === 'x' || $char === 'u' || $char === 'U') {
            $len = $char === 'x' ? 2 : ($char === 'u' ? 4 : 8);
            for ($i = 1; $i <= $len; $i++) {
                $lexeme .= $this->chars[$this->cursor - $len - 1 + $i];
            }
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
        $hex = '';
        for ($i = 0; $i < $length; $i++) {
            if ($this->isAtEnd()) {
                throw new ParseException("Unterminated Unicode escape", ParseErrorType::UNEXPECTED_EOF, $this->line, $this->column);
            }
            $char = $this->advance();
            if (preg_match('/^[0-9a-fA-F]$/', $char) !== 1) {
                throw new ParseException(sprintf('Invalid Unicode escape character: %s', $char), ParseErrorType::INVALID_CHAR, $this->line, $this->column - 1);
            }
            $hex .= $char;
        }

        $codePoint = hexdec($hex);

        // TOML 规范要求：Unicode 标量值必须在有效范围内
        if (($codePoint >= 0xD800 && $codePoint <= 0xDFFF) || $codePoint > 0x10FFFF) {
            throw new ParseException(sprintf('Invalid Unicode scalar value: %X', $codePoint), ParseErrorType::INVALID_CHAR, $this->line, $this->column - $length - 2);
        }

        return mb_chr($codePoint, 'UTF-8');
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
        $this->advance();
        $this->advance();
        $this->advance();
        $lexeme = "'''";
        $value = "";

        // TOML 规则：如果多行字符串紧跟一个换行符，必须忽略该换行符
        if ($this->currentChar() === "\n") {
            $lexeme .= $this->advance();
            $this->line++;
            $this->column = 1;
        } elseif ($this->currentChar() === "\r" && $this->peekChar() === "\n") {
            $lexeme .= $this->advance();
            $lexeme .= $this->advance();
            $this->line++;
            $this->column = 1;
        }

        while (!$this->isAtEnd()) {
            // 检测结束标志 ''' (注意：TOML 允许字符串内部包含 ''，所以必须严格匹配 3 个)
            if ($this->currentChar() === "'" && $this->peekChar() === "'" && $this->peekChar(2) === "'") {
                break;
            }

            $char = $this->currentChar();

            // 维护行号
            if ($char === "\n" || $char === "\r") {
                $lexeme .= $char;
                $value .= $char;
                if ($char === "\r" && $this->peekChar() === "\n") {
                    $char2 = $this->advance();
                    $lexeme .= $char2;
                    $value .= $char2;
                } else {
                    $this->advance();
                }
                $this->line++;
                $this->column = 1;
                continue;
            }

            // 检查非法控制字符
            if ($this->isControlChar($char) && $char !== "\t") {
                throw new ParseException("Invalid control character in multiline literal string", ParseErrorType::INVALID_CHAR, $this->line, $this->column);
            }

            $lexeme .= $char;
            $value .= $char;
            $this->advance();
        }

        if ($this->isAtEnd()) {
            throw new ParseException("Unterminated multiline literal string", ParseErrorType::UNEXPECTED_EOF, $startLine, $startCol);
        }

        // 消耗结尾的 '''
        $this->advance();
        $this->advance();
        $this->advance();
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

        $this->advance();
        $this->advance();
        $this->advance();
        $lexeme = '"""';
        $value = "";

        if ($this->currentChar() === "\n") {
            $lexeme .= $this->advance();
            $this->line++;
            $this->column = 1;
        } elseif ($this->currentChar() === "\r" && $this->peekChar() === "\n") {
            $lexeme .= $this->advance();
            $lexeme .= $this->advance();
            $this->line++;
            $this->column = 1;
        }

        while (!$this->isAtEnd()) {
            if ($this->currentChar() === '"' && $this->peekChar() === '"' && $this->peekChar(2) === '"') {
                break;
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
                            $lexeme .= $c;
                            if ($c === "\r" && $this->peekChar() === "\n") {
                                $lexeme .= $this->advance();
                            } else {
                                $this->advance();
                            }
                            $this->line++;
                            $this->column = 1;
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
                if ($this->isAtEnd()) throw new ParseException("Unterminated escape sequence", ParseErrorType::UNEXPECTED_EOF, $this->line, $this->column);
                $escapeResult = $this->scanEscapeSequence(); // 复用之前写的单行字符串转义方法
                $lexeme .= $escapeResult['lexeme'];
                $value .= $escapeResult['value'];
                continue;
            }

            if ($char === "\n" || $char === "\r") {
                $lexeme .= $char;
                $value .= $char;
                if ($char === "\r" && $this->peekChar() === "\n") {
                    $char2 = $this->advance();
                    $lexeme .= $char2;
                    $value .= $char2;
                } else {
                    $this->advance();
                }
                $this->line++;
                $this->column = 1;
                continue;
            }

            if ($this->isControlChar($char) && $char !== "\t") {
                throw new ParseException("Invalid control character in multiline basic string", ParseErrorType::INVALID_CHAR, $this->line, $this->column);
            }

            $lexeme .= $char;
            $value .= $char;
            $this->advance();
        }

        if ($this->isAtEnd()) {
            throw new ParseException("Unterminated multiline basic string", ParseErrorType::UNEXPECTED_EOF, $startLine, $startCol);
        }

        $this->advance();
        $this->advance();
        $this->advance();
        $lexeme .= '"""';

        return new Token(TokenType::STRING_MULTILINE_BASIC, $lexeme, $value, $startLine, $startCol, $this->line, $this->column - 1);
    }

    /**
     * 检查字符是否为控制字符
     *
     * 检测字符是否属于 TOML 规范中定义的控制字符范围，
     * 包括 U+0000 到 U+0008、U+000A 到 U+001F 以及 U+007F
     *
     * @param string $char 要检查的 UTF-8 字符
     * @return bool 如果字符是控制字符则返回 true，否则返回 false
     */
    private function isControlChar(string $char): bool
    {
        $ord = mb_ord($char, 'UTF-8');
        return ($ord >= 0x00 && $ord <= 0x08) ||
            ($ord >= 0x0A && $ord <= 0x1F) ||
            ($ord === 0x7F);
    }


    /**
     * 扫描标识符或数字
     *
     * 处理 TOML 中的标识符（裸键）、布尔值、整数、浮点数、日期时间等类型。
     * 通过前瞻性读取和正则匹配来准确识别不同的词法单元类型，并对数值进行规范化处理。
     *
     * @return Token 返回扫描后的词法单元对象，类型可能是标识符、布尔值、整数、浮点数或日期时间
     * @throws ParseException 当遇到无法识别的语法时抛出异常
     */
    private function scanIdentifierOrNumber(): Token
    {
        $startLine = $this->line;
        $startCol = $this->column;

        // 1. 圈定候选区域：一直读取，直到遇到 TOML 的结构分隔符
        $subject = '';
        $lookahead = $this->cursor;
        while ($lookahead < $this->length) {
            $c = $this->chars[$lookahead];
            // 注意：绝不能在这里把点号 (.) 加上，因为浮点数和日期都有点号！
            if (in_array($c, [' ', "\t", "\n", "\r", '=', ',', '[', ']', '{', '}', '#'], true)) {
                break;
            }
            $subject .= $c;
            $lookahead++;
        }

        // 2. 严格的正则制导测试 (注意断言 (?![A-Za-z0-9_-]) 防止提前截断裸键)
        $patterns = [
            TokenType::BOOLEAN->name => '/^(true|false)(?![A-Za-z0-9_-])/',
            TokenType::FLOAT->name . '_SPECIAL' => '/^[+-]?(inf|nan)(?![A-Za-z0-9_-])/',

            // Toml v1.1.0 支持时间秒数省略
            TokenType::OFFSET_DATETIME->name => '/^\d{4}-\d{2}-\d{2}[Tt ]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:[Zz]|[+-]\d{2}:\d{2})(?![A-Za-z0-9_-])/',
            TokenType::LOCAL_DATETIME->name => '/^\d{4}-\d{2}-\d{2}[Tt ]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?![A-Za-z0-9_-])/',
            TokenType::LOCAL_TIME->name => '/^\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?![A-Za-z0-9_-])/',
            TokenType::LOCAL_DATE->name => '/^\d{4}-\d{2}-\d{2}(?![A-Za-z0-9_-])/',

            // 浮点数：必须包含小数点或指数 e/E
            TokenType::FLOAT->name => '/^[+-]?(?:0|[1-9](?:_?\d)*)(?:\.\d(?:_?\d)*(?:[eE][+-]?\d(?:_?\d)*)?|[eE][+-]?\d(?:_?\d)*)(?![A-Za-z0-9_-])/',
            TokenType::INTEGER->name . '_HEX' => '/^0x[0-9a-fA-F](?:_?[0-9a-fA-F])*(?![A-Za-z0-9_-])/',
            TokenType::INTEGER->name . '_OCT' => '/^0o[0-7](?:_?[0-7])*(?![A-Za-z0-9_-])/',
            TokenType::INTEGER->name . '_BIN' => '/^0b[01](?:_?[01])*(?![A-Za-z0-9_-])/',
            TokenType::INTEGER->name => '/^[+-]?(?:0|[1-9](?:_?\d)*)(?![A-Za-z0-9_-])/',
            // 兜底：裸键 (Identifier)
            TokenType::IDENTIFIER->name => '/^[A-Za-z0-9_-]+/',
        ];

        $matchedText = null;
        $matchedType = null;

        foreach ($patterns as $typeName => $regex) {
            if (preg_match($regex, $subject, $matches)) {
                $matchedText = $matches[0];
                $matchedType = $typeName;
                break;
            }
        }

        if ($matchedText === null) {
            throw new ParseException(sprintf('Unexpected syntax near "%s"', substr($subject, 0, 10)), ParseErrorType::INVALID_CHAR, $this->line, $this->column);
        }

        // 将正则键名映射回真正的 TokenType 枚举
        $realType = match (true) {
            str_starts_with($matchedType, 'FLOAT') => TokenType::FLOAT,
            str_starts_with($matchedType, 'INTEGER') => TokenType::INTEGER,
            default => constant(TokenType::class . '::' . $matchedType),
        };

        // 3. 将游标向前推进匹配到的字符数
        $len = mb_strlen($matchedText, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $this->advance();
        }

        // 4. Normalization
        $normalizedValue = $matchedText;

        if (str_starts_with($matchedType, 'INTEGER') || str_starts_with($matchedType, 'FLOAT')) {
            // 数值规范化：去除 TOML 允许的视觉分隔符下划线
            $normalizedValue = str_replace('_', '', $matchedText);
            // 这里绝对不做 (int) 或 (float) 的转换，保持为 string
        }

        return new Token(
            $realType,
            $matchedText,
            $normalizedValue,
            $startLine,
            $startCol,
            $this->line,
            $this->column - 1
        );
    }

    /**
     * 扫描十六进制转义序列
     *
     * 读取两个十六进制数字并转换为对应的 Unicode 字符
     * 根据 TOML 规范，码点范围必须在 U+0000 到 U+00FF 之间（小于 256）
     *
     * @return string 返回转义序列解码后的字符
     * @throws ParseException 当遇到未终止的转义序列或无效的十六进制字符时抛出异常
     */
    private function scanHexEscape(): string
    {
        $hex = '';
        for ($i = 0; $i < 2; $i++) {
            if ($this->isAtEnd()) {
                throw new ParseException("Unterminated hex escape", ParseErrorType::UNEXPECTED_EOF, $this->line, $this->column);
            }
            $char = $this->advance();
            if (preg_match('/^[0-9a-fA-F]$/', $char) !== 1) {
                throw new ParseException(sprintf('Invalid hex escape character: %s', $char), ParseErrorType::INVALID_CHAR, $this->line, $this->column - 1);
            }
            $hex .= $char;
        }

        // 规范要求：码点必须 < 256 (U+0000 to U+00FF)
        $codePoint = hexdec($hex);
        return mb_chr($codePoint, 'UTF-8');
    }

}
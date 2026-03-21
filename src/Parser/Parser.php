<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Parser;

use DateTimeImmutable;
use Exception;
use Petalbranch\Toml\Contract\Lexer\LexerInterface;
use Petalbranch\Toml\Contract\Lexer\TokenInterface;
use Petalbranch\Toml\Contract\Lexer\TokenStreamInterface;
use Petalbranch\Toml\Contract\Parser\NodeInterface;
use Petalbranch\Toml\Contract\Parser\ParserInterface;
use Petalbranch\Toml\Contract\Parser\TableNodeInterface;
use Petalbranch\Toml\Exception\ParseException;
use Petalbranch\Toml\Model\KeyPath;
use Petalbranch\Toml\Model\Node\ArrayNode;
use Petalbranch\Toml\Model\Node\InlineTableNode;
use Petalbranch\Toml\Model\Node\TableNode;
use Petalbranch\Toml\Model\Node\ValueNode;
use Petalbranch\Toml\Support\ErrorContext;
use Petalbranch\Toml\Support\Position;
use Petalbranch\Toml\Support\ThrowsErrorTrait;
use Petalbranch\Toml\Support\TomlDate;
use Petalbranch\Toml\Support\TomlLocalDateTime;
use Petalbranch\Toml\Support\TomlOffsetDateTime;
use Petalbranch\Toml\Support\TomlTime;
use Petalbranch\Toml\Type\ParseErrorType;
use Petalbranch\Toml\Type\TokenType;
use Petalbranch\Toml\Type\TomlType;
use RuntimeException;
use SplObjectStorage;

/**
 * TOML 解析器
 *
 * 负责将 TOML 格式的字符串或文件解析为 PHP 数据结构。
 * 实现了状态机模式，通过词法分析器获取 token 流，
 * 然后根据语法规范构建抽象语法树（AST）。支持标准表、
 * 表数组、内联表、各种数据类型和复杂键路径的解析。
 */
class Parser implements ParserInterface
{
    use ThrowsErrorTrait;

    private TokenStreamInterface $stream;

    private TableNode $root;

    private TableNode $currentTable;

    private ?SplObjectStorage $dottedKeyTables = null;

    /**
     * 构造函数
     *
     * 创建一个新的 Parser 实例。
     *
     * @param LexerInterface $lexer 词法分析器实例，用于将源码分解为 token
     */
    public function __construct(
        private readonly LexerInterface $lexer
    )
    {
    }

    // ==========================================
    // 门面方法 (Facade)
    // ==========================================

    /**
     * 解析 TOML 字符串
     *
     * 将输入的 TOML 字符串解析为 PHP 数组。
     * 这是最常用的入口方法。
     *
     * @param string $toml 要解析的 TOML 格式字符串
     * @return array 解析后的 PHP 关联数组
     */
    public function parse(string $toml): array
    {
        return $this->parseToNode($toml)->getValue();
    }

    /**
     * 解析 TOML 文件
     *
     * 读取指定的文件内容并将其作为 TOML 文档进行解析。
     *
     * @param string $filename 要解析的文件路径
     * @return array 解析后的 PHP 关联数组
     * @throws RuntimeException 当文件不存在或不可读时抛出异常
     */
    public function parseFile(string $filename): array
    {
        if (!is_file($filename) || !is_readable($filename)) {
            throw new RuntimeException(sprintf('File "%s" does not exist or is not readable.', $filename));
        }
        $content = file_get_contents($filename);
        return $this->parse($content);
    }

    /**
     * 解析为节点树
     *
     * 将 TOML 字符串解析为抽象语法树（AST）的根节点。
     * 返回的是 TableNode 对象，而不是纯数组。
     *
     * @param string $toml 要解析的 TOML 格式字符串
     * @return TableNodeInterface 解析得到的 AST 根节点
     */
    public function parseToNode(string $toml): TableNodeInterface
    {
        $this->rawSource = $toml;
        $stream = $this->lexer->tokenize($toml);
        return $this->parseStream($stream);
    }

    // ==========================================
    // 核心逻辑 - 状态机与文档主循环
    // ==========================================

    /**
     * 解析 token 流
     *
     * 从 token 流中解析整个 TOML 文档，构建 AST 树。
     * 这是解析器的核心方法，实现了文档主循环。
     *
     * @param TokenStreamInterface $stream 词法分析器生成的 token 流
     * @return TableNodeInterface 解析完成的 AST 根节点
     */
    public function parseStream(TokenStreamInterface $stream): TableNodeInterface
    {
        $this->stream = $stream;

        // 初始化根节点和上下文指针
        $this->root = new TableNode(new Position(1, 1), '');
        $this->root->setImplicit(false); // 根节点本身是显式的容器
        $this->currentTable = $this->root;

        // 文档主循环 (Document Loop)
        while (!$this->stream->isEOF()) {

            // 1. 吸收掉无意义的空行，并收集即将到来的节点的“前导注释”
            $leadingComments = $this->collectLeadingComments();

            if ($this->stream->isEOF()) {
                break; // 如果吸完空行和注释就到底了，直接结束
            }

            $currentToken = $this->stream->current();

            // 2. 状态分发：判断当前行是什么结构
            if ($currentToken->getType() === TokenType::LEFT_BRACKET) {
                // 遇到 `[`，说明是表定义 (Table) 或 表数组定义 ([[...]])
                // 该方法内部会自动更新 $this->currentTable 指针
                $this->parseTableDefinition($leadingComments);

            } elseif ($this->isKeyToken($currentToken)) {
                // 遇到键名，说明是键值对 (Key-Value Entry)
                $this->parseEntry($leadingComments);
            } else {
                // 既不是表头也不是键名，语法错误！
                $this->throwError(
                    sprintf('Unexpected token "%s" at document root', $currentToken->getLexeme()),
                    ParseErrorType::UNEXPECTED_TOKEN,
                    $currentToken->getLine(),
                    $currentToken->getColumn(),
                    mb_strlen($currentToken->getLexeme())
                );
            }
        }

        return $this->root;
    }

    /**
     * 解析键值对条目
     *
     * 处理 TOML 中的键值对定义，包括解析键路径、等号和值
     * 自动处理点号键的隐式建表逻辑，并将注释绑定到对应的节点上
     *
     * @param list<string> $leadingComments 键值对前的前导注释列表
     * @return void
     * @throws ParseException 当键值对语法错误或键名冲突时抛出异常
     */
    private function parseEntry(array $leadingComments): void
    {

        // 1. 提取 KeyPath (例如 a.b.c 将返回包含 3 个段的 KeyPath 对象) 和 元数据
        list($keyPath, $metas) = $this->parseKeyPath();

        // 2. 期待等号
        $this->expectToken("Expected '=' after key", TokenType::EQUAL);

        // 3. 解析具体的值 (可能是标量、数组或内联表)
        $valueNode = $this->parseValue();

        // 4. 收集行尾注释
        // 因为值解析完毕后，游标要么停在换行符上，要么停在行尾注释上
        $trailingComment = $this->collectTrailingComment();

        // 确保解析完值和注释后，这一行必须干净地结束！（换行或 EOF）
        if (!$this->stream->isEOF() && $this->stream->current()->getType() !== TokenType::NEWLINE) {
            $invalid = $this->stream->current();
            $this->throwError(
                sprintf('Unexpected token "%s" after value. Key-value pairs must be separated by newlines.', $invalid->getLexeme()),
                ParseErrorType::UNEXPECTED_TOKEN,
                $invalid->getLine(),
                $invalid->getColumn(),
                mb_strwidth($invalid->getLexeme(), 'UTF-8')
            );
        }

        // 将注释绑定到节点
        $valueNode->setLeadingComments($leadingComments);
        $valueNode->setTrailingComment($trailingComment);

        // 5. 将键值对安全地插入到当前的上下文中 (处理点号键的隐式建表)
        $this->insertDottedKey($this->currentTable, $keyPath, $valueNode, $metas);
    }

    /**
     * 解析 TOML 值
     *
     * 根据当前词法单元的类型解析对应的值节点，支持数组、内联表和各种标量类型
     * 标量类型包括：字符串（基本/字面量/多行）、整数、浮点数、布尔值、日期时间等
     *
     * @return NodeInterface 返回解析后的值节点对象
     *         - 对于数组返回 ArrayNode
     *         - 对于内联表返回 InlineTableNode
     *         - 对于标量值返回 ValueNode
     * @throws ParseException 当遇到无法识别的值类型或语法错误时抛出异常
     */
    private function parseValue(): NodeInterface
    {
        $token = $this->stream->current();
        $type = $token->getType();

        // 分支 1：数组 [ ... ]
        if ($type === TokenType::LEFT_BRACKET) {
            return $this->parseArray();
        }

        // 分支 2：内联表 { ... }
        if ($type === TokenType::LEFT_BRACE) {
            return $this->parseInlineTable();
        }

        // 分支 3：标量值
        $token = $this->expectToken(
            "Expected a value",
            TokenType::STRING_BASIC, TokenType::STRING_LITERAL,
            TokenType::STRING_MULTILINE_BASIC, TokenType::STRING_MULTILINE_LITERAL,
            TokenType::INTEGER, TokenType::FLOAT, TokenType::BOOLEAN,
            TokenType::OFFSET_DATETIME, TokenType::LOCAL_DATETIME,
            TokenType::LOCAL_DATE, TokenType::LOCAL_TIME
        );

        // 映射 Lexer 的 TokenType 到 AST 的 TomlType
        $tomlType = match ($token->getType()) {
            TokenType::STRING_BASIC, TokenType::STRING_LITERAL,
            TokenType::STRING_MULTILINE_BASIC, TokenType::STRING_MULTILINE_LITERAL => TomlType::STRING,
            TokenType::INTEGER => TomlType::INTEGER,
            TokenType::FLOAT => TomlType::FLOAT,
            TokenType::BOOLEAN => TomlType::BOOLEAN,
            TokenType::OFFSET_DATETIME => TomlType::OFFSET_DATETIME,
            TokenType::LOCAL_DATETIME => TomlType::LOCAL_DATETIME,
            TokenType::LOCAL_DATE => TomlType::LOCAL_DATE,
            TokenType::LOCAL_TIME => TomlType::LOCAL_TIME,
            default => $this->throwError(
                "Unknown scalar type",
                ParseErrorType::UNKNOWN_SCALAR_TYPE,
                $token->getLine(),
                $token->getColumn()
            )
        };

        // 类型转换
        $rawValue = (string)$token->getValue();
        $actualValue = match ($tomlType) {
            TomlType::INTEGER => $this->parseInteger($rawValue),
            TomlType::FLOAT => $this->parseFloat($rawValue),
            TomlType::BOOLEAN => $rawValue === 'true',
            // 把所有时间类型都交给专门的验证器
            TomlType::OFFSET_DATETIME,
            TomlType::LOCAL_DATETIME,
            TomlType::LOCAL_DATE,
            TomlType::LOCAL_TIME => $this->parseDatetime($rawValue, $tomlType, $token),
            default => $rawValue, // 字符串保留原样
        };

        return new ValueNode(
            $tomlType,
            $actualValue,
            new Position($token->getLine(), $token->getColumn()),
            $token->getLexeme()
        );
    }

    /**
     * 解析数组
     *
     * 处理 TOML 中的数组定义，包括普通数组和嵌套数组。
     * 支持数组中的任意类型的元素，包括其他数组和表。
     *
     * @return ArrayNode 解析得到的数组节点
     * @throws ParseException 当数组语法错误（如缺少逗号或括号不匹配）时抛出异常
     */
    private function parseArray(): ArrayNode
    {
        $startToken = $this->expectToken("Expected '['", TokenType::LEFT_BRACKET);
        $arrayNode = new ArrayNode(new Position($startToken->getLine(), $startToken->getColumn()), $startToken->getLexeme());

        while (!$this->stream->isEOF()) {
            $this->skipNewlinesAndComments(); // 数组内部允许随意换行和注释

            if ($this->stream->match(TokenType::RIGHT_BRACKET)) {
                break; // 遇到 ] 结束
            }

            $valueNode = $this->parseValue();
            $trailingComment = $this->collectTrailingComment();
            $valueNode->setTrailingComment($trailingComment);

            $arrayNode->add($valueNode);

            $this->skipNewlinesAndComments();

            if ($this->stream->match(TokenType::COMMA)) {
                continue; // 遇到逗号，准备解析下一个
            } elseif ($this->stream->current()->getType() === TokenType::RIGHT_BRACKET) {
                continue; // 尾部逗号是可选的，如果直接跟着 ]，下一轮会 break
            } else {
                $this->throwError(
                    "Expected ',' or ']' in array",
                    ParseErrorType::UNEXPECTED_TOKEN,
                    $this->stream->current()->getLine(),
                    $this->stream->current()->getColumn(),
                    mb_strwidth($this->stream->current()->getLexeme(), 'UTF-8')
                );
            }
        }

        return $arrayNode;
    }

    /**
     * 解析内联表
     *
     * 处理 TOML 中的大括号内联表语法 { key = value }。
     * 内联表必须位于单行内，不能包含换行符。
     *
     * @return InlineTableNode 解析得到的内联表节点
     * @throws ParseException 当内联表语法错误（如缺少逗号或括号不匹配）时抛出异常
     */
    private function parseInlineTable(): InlineTableNode
    {

        $startToken = $this->expectToken("Expected '{'", TokenType::LEFT_BRACE);
        $inlineTable = new InlineTableNode(new Position($startToken->getLine(), $startToken->getColumn()), $startToken->getLexeme());

        while (!$this->stream->isEOF()) {
            $this->skipNewlinesAndComments(); // TOML 1.1.0 允许内联表换行

            if ($this->stream->match(TokenType::RIGHT_BRACE)) break;


            // 内联表里面就是普通的键值对
            list($keyPath, $metas) = $this->parseKeyPath();
            $this->expectToken("Expected '=' in inline table", TokenType::EQUAL);
            $valueNode = $this->parseValue();
            $trailingComment = $this->collectTrailingComment();
            $valueNode->setTrailingComment($trailingComment);

            // 将键值对直接塞进内联表 (内联表不支持复杂的点号隐式建表，只支持单层覆盖)
            if (count($keyPath->segments) > 1) {
                // TOML 1.1.0 允许内联表使用点号键: { a.b = 1 } 相当于 { a = { b = 1 } }
                $this->insertDottedKey($inlineTable, $keyPath, $valueNode, $metas);
            } else {
                $meta = $metas[0];
                if ($inlineTable->has($keyPath->segments[0])) {
                    $this->throwError(
                        "Duplicate key in inline table",
                        ParseErrorType::DUPLICATE_KEY,
                        $meta['line'],
                        $meta['col'],
                        mb_strwidth($keyPath->segments[0], 'UTF-8')
                    );
                }
                $inlineTable->set($keyPath->segments[0], $valueNode);
            }

            $this->skipNewlinesAndComments();

            if ($this->stream->match(TokenType::COMMA)) {
                continue;
            } elseif ($this->stream->current()->getType() === TokenType::RIGHT_BRACE) {
                continue;
            } else {
                $this->throwError(
                    "Expected ',' or '}' in inline table",
                    ParseErrorType::UNEXPECTED_TOKEN,
                    $this->stream->current()->getLine(),
                    $this->stream->current()->getColumn(),
                    mb_strwidth($this->stream->current()->getLexeme(), 'UTF-8')
                );
            }
        }

        return $inlineTable;
    }

    /**
     * 解析表定义
     *
     * 处理 TOML 中的标准表 [table] 和表数组 [[table]] 定义
     * 解析表头语法、验证格式合法性，并在 AST 中创建或定位对应的表节点
     *
     * @param list<string> $leadingComments 表定义前的前导注释列表
     * @return void
     * @throws ParseException 当表定义语法错误、包含意外字符或键名冲突时抛出异常
     */
    private function parseTableDefinition(array $leadingComments): void
    {
        // 1. 判断是标准表 [ 还是表数组 [[
        $firstBracket = $this->expectToken("Expected '[' for table definition", TokenType::LEFT_BRACKET);

        $isTableArray = false;

        if ($this->stream->current()->getType() === TokenType::LEFT_BRACKET) {
            $secondBracket = $this->stream->current();

            // 下一个[的列号 必须比前一个 [ 的列号大 1，否则表示有空格
            if ($secondBracket->getColumn() !== $firstBracket->getColumn() + 1) {
                $this->throwError(
                    "Whitespace is not allowed between '[' and '['",
                    ParseErrorType::INVALID_CHAR,
                    $secondBracket->getLine(),
                    $secondBracket->getColumn(),
                    mb_strwidth($secondBracket->getLexeme(), 'UTF-8')
                );
            }

            $isTableArray = true;
            $this->stream->next(); // 消耗第二个 [
        }



        // 2. 解析键路径 (KeyPath)
        list($keyPath, $metas) = $this->parseKeyPath();

        // 3. 匹配闭合括号 ] 或 ]]
        $firstRightBracket = $this->expectToken("Expected ']'", TokenType::RIGHT_BRACKET);

        if ($isTableArray) {
            $secondRightBracket = $this->expectToken("Expected second ']' for array of tables", TokenType::RIGHT_BRACKET);

            // 下一个]的列号 必须比第一个 ] 的列号大 1，否则表示有空格
            if ($secondRightBracket->getColumn() !== $firstRightBracket->getColumn() + 1) {
                $this->throwError(
                    "Whitespace is not allowed between ']' and ']'",
                    ParseErrorType::INVALID_CHAR,
                    $secondRightBracket->getLine(),
                    $secondRightBracket->getColumn(),
                    mb_strwidth($secondRightBracket->getLexeme(), 'UTF-8')
                );
            }
        }

        // 4. 收集同一行的尾部注释 (如果有的话)
        $trailingComment = $this->collectTrailingComment();

        // 5. 确保表头定义后，该行没有其他垃圾字符 (必须是换行或 EOF)
        if (!$this->stream->isEOF() && $this->stream->current()->getType() !== TokenType::NEWLINE) {
            $invalid = $this->stream->current();
            $this->throwError(
                sprintf('Unexpected token "%s" after table definition', $invalid->getLexeme()),
                ParseErrorType::UNEXPECTED_TOKEN,
                $invalid->getLine(),
                $invalid->getColumn(),
                mb_strwidth($invalid->getLexeme(), 'UTF-8')
            );
        }

        // 6. 在 AST 树中寻址，并转移上下文指针！
        $this->currentTable = $this->resolveTableContext($keyPath, $isTableArray, $leadingComments, $trailingComment, $metas);
    }

    /**
     * 解析键路径
     *
     * 解析由点号分隔的键名序列，支持裸键和字符串键两种形式
     * 例如：a.b.c 或 "key name".sub."another key"
     *
     * @return array{0: KeyPath, 1: array} 返回 KeyPath 对象和各段的元数据(快照)数组
     */
    private function parseKeyPath(): array
    {
        $segments = [];
        $metas = [];

        while (true) {
            // 期待一个键名 (裸键 或 字符串)
            $token = $this->expectToken(
                "Expected key name",
                TokenType::IDENTIFIER,
                TokenType::STRING_BASIC,
                TokenType::STRING_LITERAL,
                TokenType::BOOLEAN,
                TokenType::INTEGER,
                TokenType::FLOAT,
                TokenType::LOCAL_DATE
            );

            // 精准提取键名
            $type = $token->getType();
            if ($type === TokenType::STRING_BASIC || $type === TokenType::STRING_LITERAL) {
                // 字符串必须用 getValue()，因为要去掉引号和处理转义符
                $val = (string)$token->getValue();
            } else {
                // 裸键及其变体必须用 getLexeme()，保持源码中的原样（保留下划线！）
                $val = $token->getLexeme();
            }

            $lexeme = $token->getLexeme();

            // 如果 Lexer 激进地把 1.23 组合成了一个 FLOAT Token。
            // 但在键路径中，裸键是不允许有点号的，TOML 视其为 1 和 23 两个键。
            if ($token->getType() === TokenType::FLOAT && str_contains($val, '.')) {
                $parts = explode('.', $val);
                $colOffset = 0;
                foreach ($parts as $part) {
                    $segments[] = $part;
                    $metas[] = [
                        'lex' => $part,
                        'line' => $token->getLine(),
                        'col' => $token->getColumn() + $colOffset
                    ];
                    $colOffset += strlen($part) + 1;
                }
            } else {
                $segments[] = $val;
                $metas[] = [
                    'lex' => $lexeme,
                    'line' => $token->getLine(),
                    'col' => $token->getColumn()
                ];
            }

            // 如果紧跟着一个点号 (.)，说明路径还没完，继续循环
            if ($this->stream->match(TokenType::DOT)) continue;

            // 没有点号了，路径结束
            break;
        }

        return [new KeyPath($segments), $metas];
    }

    /**
     * 将字符串解析为 PHP 原生整型
     *
     * 解析 TOML 格式的整数字符串，支持十进制、十六进制(0x)、
     * 八进制(0o)和二进制(0b)表示法。
     *
     * @param string $value 要解析的整数字符串
     * @return int 解析后的整数值
     */
    private function parseInteger(string $value): int
    {
        // 处理十六进制、八进制、二进制
        if (str_starts_with($value, '0x')) return hexdec($value);
        if (str_starts_with($value, '0o')) return octdec(str_replace('0o', '0', $value));
        if (str_starts_with($value, '0b')) return bindec($value);

        // 普通十进制 (Lexer 已经去掉了下划线，直接强转即可)
        return (int)$value;
    }

    /**
     * 将字符串解析为 PHP 原生浮点型
     *
     * 解析 TOML 格式的浮点数字符串，支持普通小数和特殊值
     * (inf, -inf, nan)。
     *
     * @param string $value 要解析的浮点数字符串
     * @return float 解析后的浮点数值
     */
    private function parseFloat(string $value): float
    {
        $lower = strtolower($value);

        // 处理特殊的无限大和非数字
        if (in_array($lower, ['inf', '+inf'], true)) return INF;
        if ($lower === '-inf') return -INF;
        if (in_array($lower, ['nan', '+nan', '-nan'], true)) return NAN;

        // 普通浮点数
        return (float)$value;
    }

    /**
     * 解析并验证 TOML 日期时间值
     *
     * 该方法负责验证传入的日期时间字符串是否符合指定的 TOML 日期时间类型格式。
     * 验证包括日期部分（YYYY-MM-DD）的有效性检查，以及时间部分（HH:MM:SS）的正确性验证。
     * 对于日期验证，使用 checkdate 函数来处理大小月、闰年等复杂情况。
     * 对于时间验证，确保小时在 0-23 范围内，分钟在 0-59 范围内，秒数在 0-60 范围内（支持闰秒）。
     *
     * @param string $value 待解析的日期时间字符串
     * @param TomlType $type 日期时间的 TOML 类型（偏移日期时间、本地日期时间或本地日期/时间）
     * @param TokenInterface $token 当前 token，用于错误报告时提供位置信息
     * @return string 验证通过后原样返回输入的日期时间字符串
     * @throws ParseException 当日期时间格式无效时抛出异常
     */
    private function parseDatetime(string $value, TomlType $type, TokenInterface $token): string
    {
        $normalized = strtoupper($value);
        // 1. 严格位校验：禁止 DateTimeImmutable 的自动进位行为
        // 检查日期 YYYY-MM-DD
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $normalized, $matches)) {
            $y = (int)$matches[1];
            $m = (int)$matches[2];
            $d = (int)$matches[3];
            // 检查年月日
            if ($m < 1 || $m > 12 || $d < 1 || !checkdate($m, $d, $y)) {
                $this->throwError("Invalid date: day or month out of range", ParseErrorType::INVALID_CHAR, $token->getLine(), $token->getColumn());
            }
        }

        // 检查时间 HH:MM:SS
        // 注意：如果是 LOCAL_TIME，时间在开头；如果是 DATETIME，时间在 T 后面
        $timePart = ($type === TomlType::LOCAL_TIME) ? $normalized : substr($normalized, 11);
        if (preg_match('/^(\d{2}):(\d{2}):(\d{2})/', $timePart, $matches)) {
            $hh = (int)$matches[1];
            $mm = (int)$matches[2];
            $ss = (int)$matches[3];
            // 24:00:00 是非法的，60 秒仅在闰秒合法（TOML 允许 60）
            if ($hh > 23 || $mm > 59 || $ss > 60) {
                $this->throwError("Invalid time: hour or minute out of range", ParseErrorType::INVALID_CHAR, $token->getLine(), $token->getColumn());
            }
        }

        // 2. 规范化格式 (补齐秒数，空格转 T)
        $normalized = $this->normalizeDatetimeString($normalized, $type);

        try {
            // 3. 构造 Support 对象
            return match ($type) {
                TomlType::LOCAL_DATE => (new TomlDate(new DateTimeImmutable($normalized)))->formatToml(),
                TomlType::LOCAL_TIME => ($this->createTomlTime($normalized))->formatToml(),
                TomlType::LOCAL_DATETIME => (new TomlLocalDateTime(new DateTimeImmutable($normalized)))->formatToml(),
                TomlType::OFFSET_DATETIME => (new TomlOffsetDateTime(new DateTimeImmutable($normalized)))->formatToml(),
                default => throw new Exception("Unreachable")
            };
        } catch (Exception) {
            $this->throwError("Invalid datetime format", ParseErrorType::INVALID_CHAR, $token->getLine(), $token->getColumn());
        }
    }

    // ==========================================
    // 辅助/分支方法 (待实现)
    // ==========================================

    /**
     * 判断当前 Token 是否可以作为键名 (裸键或字符串)
     *
     * 检查给定的 token 是否可以作为键值对的键名使用。
     * 支持裸键、字符串、布尔值、数字和日期等类型。
     *
     * @param TokenInterface $token 要检查的 token
     * @return bool 如果可以作为键名返回 true，否则返回 false
     */
    private function isKeyToken(TokenInterface $token): bool
    {
        return in_array($token->getType(), [
            TokenType::IDENTIFIER,
            TokenType::STRING_BASIC,
            TokenType::STRING_LITERAL,
            // 以下是 Lexer 会“过度识别”但其实也是合法裸键的类型
            TokenType::BOOLEAN,    // true, false
            TokenType::INTEGER,    // 123
            TokenType::FLOAT,      // inf, nan, 1.23
            TokenType::LOCAL_DATE  // 2026-03-21 (只包含数字和中划线，合法)
            // 注意：不包含 TIME，因为带有冒号 : 是不合法的裸键
        ], true);
    }

    /**
     * 收集前导注释并吃掉换行符
     *
     * 在解析新语句之前，收集所有连续的注释和空行。
     * 这些注释会被附加到下一个解析的节点上。
     *
     * @return list<string> 收集到的注释内容列表
     */
    private function collectLeadingComments(): array
    {
        $comments = [];
        while (!$this->stream->isEOF()) {
            $token = $this->stream->current();
            if ($token->getType() === TokenType::COMMENT) {
                $comments[] = $token->getValue(); // 拿到去了 # 的纯净注释
                $this->stream->next();
            } elseif ($token->getType() === TokenType::NEWLINE) {
                $this->stream->next(); // 直接吃掉换行符
            } else {
                break; // 遇到真正的代码，停止收集
            }
        }
        return $comments;
    }

    /**
     * 收集行尾注释
     *
     * 检查当前词法单元是否为行尾注释，如果是则消耗该词法单元并返回注释内容
     *
     * @return string|null 如果存在行尾注释则返回注释内容（不含 # 符号），否则返回 null
     */
    private function collectTrailingComment(): ?string
    {
        $current = $this->stream->current();

        if ($current->getType() === TokenType::COMMENT) {
            $comment = $current->getValue();
            $this->stream->next(); // 消耗掉这个注释
            return $comment;
        }

        return null;
    }

    /**
     * 解析表上下文并返回目标表节点
     *
     * 根据给定的键路径解析或创建对应的表结构，处理普通表和表数组两种情况
     * 对于中间路径中不存在的表会自动隐式创建，并正确处理表的显式/隐式定义冲突
     *
     * @param KeyPath $path 表的路径对象，包含路径段数组
     * @param bool $isTableArray 是否为表数组（[[table]] 形式），true 表示表数组，false 表示普通表
     * @param list<string> $leadingComments 前导注释列表，将附加到新创建的表节点上
     * @param string|null $trailingComment 尾部注释字符串，将附加到新创建的表节点上
     * @return TableNode 返回解析得到的表节点对象
     * @throws ParseException 当遇到键名冲突、类型不匹配或重复定义等错误时抛出异常
     */
    private function resolveTableContext(
        KeyPath $path,
        bool    $isTableArray,
        array   $leadingComments,
        ?string $trailingComment,
        array $metas
    ): TableNode
    {
        $current = $this->root;
        $segments = $path->segments;
        $lastIndex = count($segments) - 1;

        // 1. 遍历除了最后一个节点之外的所有中间路径 (隐式建表)
        for ($i = 0; $i < $lastIndex; $i++) {
            $segment = $segments[$i];

            if (!$current->has($segment)) {
                // 如果中间节点不存在，隐式创建一个新的 TableNode
                $newTable = new TableNode(new Position(0, 0), '');
                $newTable->setImplicit(true); // 标记为隐式创建！
                $current->set($segment, $newTable);
                $current = $newTable;
            } else {
                $node = $current->get($segment);
                if ($node instanceof ArrayNode && $node->isTableArray()) {
                    // 如果中间节点是一个表数组，我们需要进入它的最后一个元素！
                    $elements = $node->getElements();
                    if (empty($elements)) {
                        $this->throwError(
                            "Internal Error: Table array '$segment' is empty",
                            ParseErrorType::INTERNAL_ERROR,
                            0,
                            0
                        );
                    }
                    $current = end($elements);
                } elseif ($node instanceof TableNode) {
                    // 拦截试图穿透内联表的行为
                    if ($node->isInline()) {
                        $this->throwError(
                            sprintf("Cannot add keys to inline table '%s'", $segment),
                            ParseErrorType::INVALID_TABLE_DEFINITION,
                            $metas[$i]['line'],
                            $metas[$i]['col'],
                            mb_strwidth($metas[$i]['lex'], 'UTF-8')
                        );
                    }

                    $current = $node;
                } else {
                    $this->throwError(
                        sprintf("Cannot redefine existing key '%s' as a table", $segment),
                        ParseErrorType::CONFLICTING_KEY,
                        $metas[$i]['line'],
                        $metas[$i]['col'],
                        mb_strwidth($metas[$i]['lex'], 'UTF-8')
                    );
                }
            }
        }

        // 2. 处理最后一个节点 (真正的表或表数组定义)
        $finalSegment = $segments[$lastIndex];
        $finalMeta = $metas[$lastIndex];

        if ($isTableArray) {
            // 处理 [[table_array]]
            if (!$current->has($finalSegment)) {
                $arrayNode = new ArrayNode(new Position(0, 0), '', true);
                $current->set($finalSegment, $arrayNode);
            }

            $arrayNode = $current->get($finalSegment);
            if (!$arrayNode instanceof ArrayNode || !$arrayNode->isTableArray()) {
                $this->throwError(
                    sprintf("Conflict: '%s' is already defined and is not an array of tables", $finalSegment),
                    ParseErrorType::CONFLICTING_KEY,
                    $finalMeta['line'],
                    $finalMeta['col'],
                    mb_strwidth($finalMeta['lex'], 'UTF-8')
                );
            }

            // 创建一个新的表，塞进这个数组里 (表数组里的表都是显式定义的)
            $newTable = new TableNode(new Position(0, 0), '', $leadingComments, $trailingComment);
            $newTable->setImplicit(false);
            $arrayNode->add($newTable);

        } else {
            // 处理 [table]
            if ($current->has($finalSegment)) {
                $node = $current->get($finalSegment);

                if ($node instanceof TableNode) {
                    // 显式/隐式表逻辑
                    if (!$node->isImplicit()) {
                        $this->throwError(
                            sprintf("Table '[%s]' is already defined (cannot redefine via table header)", $path),
                            ParseErrorType::CONFLICTING_KEY,
                            $finalMeta['line'],
                            $finalMeta['col'],
                            mb_strwidth($finalMeta['lex'], 'UTF-8') + 2
                        );
                    }

                    // 如果它之前是隐式的，现在被显式定义了，我们把它转正！
                    $node->setImplicit(false);
                    $node->setLeadingComments($leadingComments);
                    $node->setTrailingComment($trailingComment);

                    // 这里原本就已经有了 early return
                    return $node;
                }

                $this->throwError(
                    sprintf("Conflict: Key '%s' is already defined as a different type", $finalSegment),
                    ParseErrorType::CONFLICTING_KEY,
                    $finalMeta['line'],
                    $finalMeta['col'],
                    mb_strwidth($finalMeta['lex'], 'UTF-8')
                );
            }

            // 完全新建一个表
            $newTable = new TableNode(new Position(0, 0), '', $leadingComments, $trailingComment);
            $newTable->setImplicit(false); // 显式创建
            $current->set($finalSegment, $newTable);

        }
        return $newTable;
    }

    /**
     * 插入点号键值对到指定的表上下文中
     *
     * 处理 TOML 中的点号键语法（如 a.b.c = value），自动为中间路径隐式创建表节点
     * 支持向标准表和表数组的最后一个元素追加内容，但不允许向内联表添加点号键
     * 执行严格的冲突检测，确保不会覆盖已存在的键
     *
     * @param TableNode $context 当前所在的表上下文节点
     * @param KeyPath $path 键路径对象，包含由点号分隔的路径段数组
     * @param NodeInterface $valueNode 要插入的值节点对象
     * @return void
     * @throws ParseException 当尝试向内联表添加点号键、键名冲突或类型不匹配时抛出异常
     */
    private function insertDottedKey(TableNode $context, KeyPath $path, NodeInterface $valueNode, array $metas): void
    {
        // 创建一个 SplObjectStorage 对象，用于存储点号键的表节点
        if ($this->dottedKeyTables === null) $this->dottedKeyTables = new SplObjectStorage();

        $current = $context;
        $segments = $path->segments;
        $lastIndex = count($segments) - 1;

        // 遍历前面的路径段，隐式创建表
        for ($i = 0; $i < $lastIndex; $i++) {
            $segment = $segments[$i];
            $meta = $metas[$i];

            if (!$current->has($segment)) {
                $newTable = new TableNode($valueNode->getPosition(), '');
                $newTable->setImplicit(false); // 点号键的中间表不能再被 [table] 表头重新定义
                $this->dottedKeyTables->attach($newTable); // 记录由点号键专属创建的表
                $current->set($segment, $newTable);
                $current = $newTable;
            } else {
                $node = $current->get($segment);
                if ($node instanceof TableNode) {
                    // TOML 规范：不能向内联表中添加点号键！
                    if ($node->isInline()) {
                        $this->throwError(
                            "Cannot add dotted keys to an inline table",
                            ParseErrorType::INVALID_TABLE_DEFINITION,
                            $meta['line'], $meta['col'],
                            mb_strwidth($meta['lex'], 'UTF-8')
                        );
                    }

                    // 冲突检测：点号键的表不能被 [table] 表头重新定义
                    // 如果这个表不是隐式的，并且也不在我们的 dottedKeyTables 集合里，
                    // 说明它一定是某个 [table] 表头显式创建的！绝对不允许使用点号键穿透！
                    if (!$node->isImplicit() && !$this->dottedKeyTables->contains($node)) {
                        $this->throwError(
                            sprintf("Cannot use dotted keys to add to explicitly defined table '%s'", $segment),
                            ParseErrorType::INVALID_TABLE_DEFINITION,
                            $meta['line'], $meta['col'], mb_strwidth($meta['lex'], 'UTF-8')
                        );
                    }

                    $current = $node;
                } else {
                    $this->throwError(
                        sprintf("Cannot redefine existing key '%s' as a table", $segment),
                        ParseErrorType::INVALID_TABLE_DEFINITION,
                        $meta['line'], $meta['col'], mb_strwidth($meta['lex'], 'UTF-8')
                    );
                }
            }
        }

        $finalSegment = $segments[$lastIndex];
        $finalMeta = $metas[$lastIndex];

        // 冲突检测：如果最终的键已经存在，报错
        if ($current->has($finalSegment)) {
            $this->throwError(
                sprintf("Key '%s' is already defined", $finalSegment),
                ParseErrorType::INVALID_TABLE_DEFINITION,
                $finalMeta['line'], $finalMeta['col'], mb_strwidth($finalMeta['lex'], 'UTF-8')
            );
        }

        $current->set($finalSegment, $valueNode);
    }

    /**
     * 跳过换行符和注释
     *
     * 持续向前移动词法单元流，忽略所有的换行符和注释
     * 用于在解析完一个完整语句后，跳过空白行和注释，定位到下一个有效语句
     *
     * @return void
     */
    private function skipNewlinesAndComments(): void
    {
        while (!$this->stream->isEOF()) {
            $type = $this->stream->current()->getType();
            if ($type === TokenType::NEWLINE || $type === TokenType::COMMENT) {
                $this->stream->next();
            } else {
                break;
            }
        }
    }

    /**
     * 创建 TomlTime 对象
     *
     * 根据标准化的时间字符串创建一个 TomlTime 实例。
     * 支持两种格式的时间字符串：HH:MM:SS 和 HH:MM:SS.micros（包含微秒部分）。
     * 微秒部分如果不足6位会自动补零，超过6位则截断。
     *
     * @param string $normalized 标准化的时间字符串，格式为 HH:MM:SS 或 HH:MM:SS.micros
     * @return TomlTime 新创建的 TomlTime 实例
     */
    private function createTomlTime(string $normalized): TomlTime
    {
        // 格式可能是 HH:MM:SS 或 HH:MM:SS.micros
        $parts = explode('.', $normalized);
        $timeParts = explode(':', $parts[0]);

        $hour = (int)$timeParts[0];
        $minute = (int)$timeParts[1];
        $second = (int)$timeParts[2];
        $micro = 0;

        if (isset($parts[1])) {
            // 补齐 6 位微秒
            $microStr = str_pad($parts[1], 6, '0');
            $micro = (int)substr($microStr, 0, 6);
        }

        return new TomlTime($hour, $minute, $second, $micro);
    }

    /**
     * 标准化日期时间字符串
     *
     * 将输入的日期时间字符串转换为标准格式，以便后续解析。
     * 对于不同类型的日期时间值进行相应的标准化处理：
     * - 本地时间：确保包含秒数部分（HH:MM:SS）
     * - 日期时间：统一使用'T'分隔日期和时间，确保包含秒数部分
     * 同时将所有字符转换为大写形式。
     *
     * @param string $value 待标准化的日期时间字符串
     * @param TomlType $type 日期时间的TOML类型（LOCAL_TIME, OFFSET_DATETIME, LOCAL_DATETIME等）
     * @return string 标准化后的日期时间字符串
     */
    private function normalizeDatetimeString(string $value, TomlType $type): string
    {
        $normalized = strtoupper($value);
        if ($type === TomlType::LOCAL_TIME) {
            return (strlen($normalized) === 5) ? $normalized . ':00' : $normalized;
        }

        if (in_array($type, [TomlType::OFFSET_DATETIME, TomlType::LOCAL_DATETIME], true)) {
            if ($normalized[10] === ' ') $normalized[10] = 'T';
            if (strlen($normalized) === 16) {
                $normalized .= ':00';
            } elseif (strlen($normalized) > 16) {
                $char = $normalized[16];
                if (in_array($char, ['Z', '+', '-'])) {
                    $normalized = substr($normalized, 0, 16) . ':00' . substr($normalized, 16);
                }
            }
        }
        return $normalized;
    }

    // ==========================================
    // 错误抛出
    // ==========================================
    /**
     * 接管 TokenStream 的 expect 断言逻辑
     * 确保所有的语法预期错误都能触发可视化报错
     */
    private function expectToken(string $message, TokenType ...$types): TokenInterface
    {
        $current = $this->stream->current();

        if (!in_array($current->getType(), $types, true)) {
            $lexeme = $current->getLexeme();
            // 计算波浪线的宽度 (EOF 时 lexeme 为空，至少给 1 个宽度)
            $len = max(1, mb_strwidth($lexeme, 'UTF-8'));

            $this->throwError(
                sprintf('%s (Unexpected token "%s")', $message, $current->getType()->name),
                ParseErrorType::INVALID_TYPE, // 或者使用 UNEXPECTED_TOKEN
                $current->getLine(),
                $current->getColumn(),
                $len
            );
        }

        // 断言成功，游标前进，并返回当前 token
        $this->stream->next();
        return $current;
    }
}
<?php

namespace Petalbranch\Toml\Parser;

use Petalbranch\Toml\Contract\Lexer\LexerInterface;
use Petalbranch\Toml\Contract\Lexer\TokenInterface;
use Petalbranch\Toml\Contract\Lexer\TokenStreamInterface;
use Petalbranch\Toml\Contract\Parser\NodeInterface;
use Petalbranch\Toml\Contract\Parser\ParserInterface;
use Petalbranch\Toml\Contract\Parser\TableNodeInterface;
use Petalbranch\Toml\Exception\ParseException;
use Petalbranch\Toml\Model\KeyPath;
use Petalbranch\Toml\Support\Position;
use Petalbranch\Toml\Type\ParseErrorType;
use Petalbranch\Toml\Type\TokenType;
use Petalbranch\Toml\Type\TomlType;
use RuntimeException;

class Parser implements ParserInterface
{
    private TokenStreamInterface $stream;

    private TableNode $root;

    private TableNode $currentTable;

    public function __construct(
        private readonly LexerInterface $lexer
    )
    {
    }

    // ==========================================
    // 门面方法 (Facade)
    // ==========================================

    public function parse(string $toml): array
    {
        return $this->parseToNode($toml)->getValue();
    }


    public function parseFile(string $filename): array
    {
        if (!is_file($filename) || !is_readable($filename)) {
            throw new RuntimeException(sprintf('File "%s" does not exist or is not readable.', $filename));
        }
        $content = file_get_contents($filename);
        return $this->parse($content);
    }

    public function parseToNode(string $toml): TableNodeInterface
    {
        $stream = $this->lexer->tokenize($toml);
        return $this->parseStream($stream);
    }

    // ==========================================
    // 核心逻辑 - 状态机与文档主循环
    // ==========================================


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
                throw new ParseException(
                    sprintf('Unexpected token "%s" at document root', $currentToken->getLexeme()),
                    ParseErrorType::UNEXPECTED_TOKEN,
                    $currentToken->getLine(),
                    $currentToken->getColumn()
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
        // 1. 提取 KeyPath (例如 a.b.c 将返回包含 3 个段的 KeyPath 对象)
        $keyPath = $this->parseKeyPath();

        // 2. 期待等号
        $this->stream->expect("Expected '=' after key", TokenType::EQUAL);

        // 3. 解析具体的值 (可能是标量、数组或内联表)
        $valueNode = $this->parseValue();

        // 4. 收集行尾注释
        // 因为值解析完毕后，游标要么停在换行符上，要么停在行尾注释上
        $trailingComment = $this->collectTrailingComment($valueNode->getPosition()->line);

        // 将注释绑定到节点
        $valueNode->setLeadingComments($leadingComments);
        $valueNode->setTrailingComment($trailingComment);

        // 5. 将键值对安全地插入到当前的上下文中 (处理点号键的隐式建表)
        $this->insertDottedKey($this->currentTable, $keyPath, $valueNode);
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
        $token = $this->stream->expect(
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
            default => throw new ParseException(
                "Unknown scalar type",
                ParseErrorType::UNKNOWN_SCALAR_TYPE,
                $token->getLine(),
                $token->getColumn()
            )
        };

        return new ValueNode(
            $tomlType,
            $token->getValue(),
            new Position($token->getLine(), $token->getColumn()),
            $token->getLexeme()
        );
    }


    private function parseArray(): ArrayNode
    {
        $startToken = $this->stream->expect("Expected '['", TokenType::LEFT_BRACKET);
        $arrayNode = new ArrayNode(new Position($startToken->getLine(), $startToken->getColumn()), $startToken->getLexeme());

        while (!$this->stream->isEOF()) {
            $this->skipNewlinesAndComments(); // 数组内部允许随意换行和注释

            if ($this->stream->match(TokenType::RIGHT_BRACKET)) {
                break; // 遇到 ] 结束
            }

            $valueNode = $this->parseValue();
            $trailingComment = $this->collectTrailingComment($valueNode->getPosition()->line);
            $valueNode->setTrailingComment($trailingComment);

            $arrayNode->add($valueNode);

            $this->skipNewlinesAndComments();

            if ($this->stream->match(TokenType::COMMA)) {
                continue; // 遇到逗号，准备解析下一个
            } elseif ($this->stream->peek()->getType() === TokenType::RIGHT_BRACKET) {
                continue; // 尾部逗号是可选的，如果直接跟着 ]，下一轮会 break
            } else {
                throw new ParseException("Expected ',' or ']' in array", ParseErrorType::UNEXPECTED_TOKEN, $this->stream->current()->getLine(), $this->stream->current()->getColumn());
            }
        }

        return $arrayNode;
    }

    private function parseInlineTable(): InlineTableNode
    {
        $startToken = $this->stream->expect("Expected '{'", TokenType::LEFT_BRACE);
        $inlineTable = new InlineTableNode(new Position($startToken->getLine(), $startToken->getColumn()), $startToken->getLexeme());

        while (!$this->stream->isEOF()) {
            $this->skipNewlinesAndComments(); // TOML 1.1.0 允许内联表换行

            if ($this->stream->match(TokenType::RIGHT_BRACE)) {
                break;
            }

            // 内联表里面就是普通的键值对
            $keyPath = $this->parseKeyPath();
            $this->stream->expect("Expected '=' in inline table", TokenType::EQUAL);
            $valueNode = $this->parseValue();
            $trailingComment = $this->collectTrailingComment($valueNode->getPosition()->line);
            $valueNode->setTrailingComment($trailingComment);

            // 将键值对直接塞进内联表 (内联表不支持复杂的点号隐式建表，只支持单层覆盖)
            if (count($keyPath->segments) > 1) {
                // TOML 1.1.0 允许内联表使用点号键: { a.b = 1 } 相当于 { a = { b = 1 } }
                $this->insertDottedKey($inlineTable, $keyPath, $valueNode);
            } else {
                if ($inlineTable->has($keyPath->segments[0])) {
                    throw new ParseException(
                        "Duplicate key in inline table",
                        ParseErrorType::DUPLICATE_KEY,
                        $this->stream->current()->getLine(),
                        $this->stream->current()->getColumn()
                    );
                }
                $inlineTable->set($keyPath->segments[0], $valueNode);
            }

            $this->skipNewlinesAndComments();

            if ($this->stream->match(TokenType::COMMA)) {
                continue;
            } elseif ($this->stream->peek()->getType() === TokenType::RIGHT_BRACE) {
                continue;
            } else {
                throw new ParseException(
                    "Expected ',' or '}' in inline table",
                    ParseErrorType::UNEXPECTED_TOKEN,
                    $this->stream->current()->getLine(),
                    $this->stream->current()->getColumn()
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
        $this->stream->expect("Expected '[' for table definition", TokenType::LEFT_BRACKET);
        $isTableArray = $this->stream->match(TokenType::LEFT_BRACKET) !== null;

        // 2. 解析键路径 (KeyPath)
        $keyPath = $this->parseKeyPath();

        // 3. 匹配闭合括号 ] 或 ]]
        $this->stream->expect("Expected ']'", TokenType::RIGHT_BRACKET);
        $closingToken = $this->stream->current(); // 记录当前 token 用来拿行号
        if ($isTableArray) {
            $closingToken = $this->stream->expect("Expected second ']' for array of tables", TokenType::RIGHT_BRACKET);
        }

        // 4. 收集同一行的尾部注释 (如果有的话)
        $trailingComment = $this->collectTrailingComment($closingToken->getEndLine());

        // 5. 确保表头定义后，该行没有其他垃圾字符 (必须是换行或 EOF)
        if (!$this->stream->isEOF() && $this->stream->current()->getType() !== TokenType::NEWLINE) {
            $invalid = $this->stream->current();
            throw new ParseException(
                sprintf('Unexpected token "%s" after table definition', $invalid->getLexeme()),
                ParseErrorType::UNEXPECTED_TOKEN,
                $invalid->getLine(),
                $invalid->getColumn()
            );
        }

        // 6. 在 AST 树中寻址，并转移上下文指针！
        $this->currentTable = $this->resolveTableContext($keyPath, $isTableArray, $leadingComments, $trailingComment);
    }


    /**
     * 解析键路径
     *
     * 解析由点号分隔的键名序列，支持裸键和字符串键两种形式
     * 例如：a.b.c 或 "key name".sub."another key"
     *
     * @return KeyPath 返回包含所有路径段的键路径对象
     */
    private function parseKeyPath(): KeyPath
    {
        $segments = [];

        while (true) {
            // 期待一个键名 (裸键 或 字符串)
            $token = $this->stream->expect(
                "Expected key name",
                TokenType::IDENTIFIER,
                TokenType::STRING_BASIC,
                TokenType::STRING_LITERAL
            );

            // 注意：这里用 getValue()，因为如果是 "a b"，我们要的是纯净的 a b
            $segments[] = (string)$token->getValue();

            // 如果紧跟着一个点号 (.)，说明路径还没完，继续循环
            if ($this->stream->match(TokenType::DOT)) continue;

            // 没有点号了，路径结束
            break;
        }

        return new KeyPath($segments);
    }



    // ==========================================
    // 辅助/分支方法 (待实现)
    // ==========================================

    /**
     * 判断当前 Token 是否可以作为键名 (裸键或字符串)
     */
    private function isKeyToken(TokenInterface $token): bool
    {
        return in_array($token->getType(), [
            TokenType::IDENTIFIER,
            TokenType::STRING_BASIC,
            TokenType::STRING_LITERAL
        ], true);
    }

    /**
     * 收集前导注释并吃掉换行符
     * * @return list<string>
     */
    private function collectLeadingComments(): array
    {
        $comments = [];
        while (!$this->stream->isEOF()) {
            $token = $this->stream->current();
            if ($token->getType() === TokenType::COMMENT) {
                $comments[] = $token->getValue(); // 拿到去除了 # 的纯净注释
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
     * @param int $currentLine 当前解析的行号，用于判断注释是否与刚解析的值在同一行
     * @return string|null 如果存在行尾注释则返回注释内容（不含 # 符号），否则返回 null
     */
    private function collectTrailingComment(int $currentLine): ?string
    {
        $current = $this->stream->current();

        // 如果当前 Token 是注释，且行号和刚才解析的值的行号相同！
        if ($current->getType() === TokenType::COMMENT && $current->getLine() === $currentLine) {
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
        ?string $trailingComment
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
                        throw new ParseException(
                            "Internal Error: Table array '$segment' is empty",
                            ParseErrorType::INTERNAL_ERROR,
                            0,
                            0
                        );
                    }
                    $current = end($elements);
                } elseif ($node instanceof TableNode) {
                    $current = $node;
                } else {
                    throw new ParseException(
                        sprintf("Cannot redefine existing key '%s' as a table", $segment),
                        ParseErrorType::CONFLICTING_KEY,
                        $node->getPosition()->line,
                        $node->getPosition()->column
                    );
                }
            }
        }

        // 2. 处理最后一个节点 (真正的表或表数组定义)
        $finalSegment = $segments[$lastIndex];

        if ($isTableArray) {
            // 处理 [[table_array]]
            if (!$current->has($finalSegment)) {
                $arrayNode = new ArrayNode(new Position(0, 0), '', true);
                $current->set($finalSegment, $arrayNode);
            }

            $arrayNode = $current->get($finalSegment);
            if (!$arrayNode instanceof ArrayNode || !$arrayNode->isTableArray()) {
                throw new ParseException(
                    sprintf("Conflict: '%s' is already defined and is not an array of tables", $finalSegment),
                    ParseErrorType::CONFLICTING_KEY,
                    $arrayNode->getPosition()->line,
                    $arrayNode->getPosition()->column
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
                        throw new ParseException(
                            sprintf("Table '[%s]' is already explicitly defined", $path),
                            ParseErrorType::CONFLICTING_KEY,
                            $node->getPosition()->line,
                            $node->getPosition()->column
                        );
                    }

                    // 如果它之前是隐式的，现在被显式定义了，我们把它转正！
                    $node->setImplicit(false);
                    $node->setLeadingComments($leadingComments);
                    $node->setTrailingComment($trailingComment);

                    // 这里原本就已经有了 early return
                    return $node;
                }

                throw new ParseException(
                    sprintf("Conflict: Key '%s' is already defined as a different type", $finalSegment),
                    ParseErrorType::CONFLICTING_KEY,
                    $node->getPosition()->line,
                    $node->getPosition()->column
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
    private function insertDottedKey(TableNode $context, KeyPath $path, NodeInterface $valueNode): void
    {
        $current = $context;
        $segments = $path->segments;
        $lastIndex = count($segments) - 1;

        // 遍历前面的路径段，隐式创建表
        for ($i = 0; $i < $lastIndex; $i++) {
            $segment = $segments[$i];

            if (!$current->has($segment)) {
                $newTable = new TableNode(new Position(0, 0), '');
                $newTable->setImplicit(true); // 点号键创建的表都是隐式的
                $current->set($segment, $newTable);
                $current = $newTable;
            } else {
                $node = $current->get($segment);
                if ($node instanceof TableNode) {
                    // TOML 规范：不能向内联表中添加点号键！
                    if ($node->isInline()) {
                        throw new ParseException(
                            "Cannot add dotted keys to an inline table",
                            ParseErrorType::INVALID_TABLE_DEFINITION,
                            $node->getPosition()->line,
                            $node->getPosition()->column
                        );
                    }
                    $current = $node;
                } elseif ($node instanceof ArrayNode && $node->isTableArray()) {
                    // 允许向表数组的最后一个元素追加内容
                    $elements = $node->getElements();
                    $current = end($elements);
                } else {
                    throw new ParseException(
                        sprintf("Cannot redefine existing key '%s' as a table", $segment),
                        ParseErrorType::INVALID_TABLE_DEFINITION,
                        $node->getPosition()->line,
                        $node->getPosition()->column
                    );
                }
            }
        }

        $finalSegment = $segments[$lastIndex];

        // 冲突检测：如果最终的键已经存在，报错
        if ($current->has($finalSegment)) {
            throw new ParseException(
                sprintf("Key '%s' is already defined", $finalSegment),
                ParseErrorType::INVALID_TABLE_DEFINITION,
                $valueNode->getPosition()->line,
                $valueNode->getPosition()->column
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
}
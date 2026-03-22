<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Support;

use Generator;
use Petalbranch\Toml\Contract\Lexer\TokenInterface;
use Petalbranch\Toml\Contract\Lexer\TokenStreamInterface;
use Petalbranch\Toml\Exception\ParseException;
use Petalbranch\Toml\Type\ParseErrorType;
use Petalbranch\Toml\Type\TokenType;
use RuntimeException;

/**
 * 惰性词法单元流实现类
 *
 * 实现了 TokenStreamInterface 接口，采用惰性加载策略管理词法单元流。
 * 通过缓冲区机制和生成器配合，实现按需解析词法单元，提高内存效率。
 *
 * @package Petalbranch\Toml\Support
 */
final class LazyTokenStream implements TokenStreamInterface
{

    /**
     * 已解析的 Token 缓冲区
     * @var list<TokenInterface>
     */
    private array $buffer = [];

    /**
     * 当前游标位置
     * @var int
     */
    private int $cursor = 0;

    /**
     * 生成器
     * @var Generator<TokenInterface>
     */
    private Generator $generator;

    /**
     * 构造函数
     *
     * @param Generator $generator 词法单元生成器对象
     */
    public function __construct(Generator $generator)
    {
        $this->generator = $generator;
        $this->ensureBufferHas($this->cursor);
    }

    /**
     * 获取当前词法单元
     *
     * @return TokenInterface 返回当前游标位置的词法单元对象
     */
    public function current(): TokenInterface
    {
        return $this->buffer[$this->cursor];
    }

    /**
     * 移动到下一个词法单元并返回当前单元
     *
     * @return TokenInterface 返回移动前的当前词法单元对象
     */
    public function next(): TokenInterface
    {
        $token = $this->current();

        // 只有当当前不是 EOF 时，才移动游标，防止越界
        if ($token->getType() !== TokenType::EOF) {
            $this->cursor++;
            $this->ensureBufferHas($this->cursor);
        }

        return $token;
    }


    /**
     * 预览指定偏移量位置的词法单元
     *
     * @param int $offset 预览的偏移量，默认为 1（下一个位置）
     * @return TokenInterface 返回指定位置的词法单元对象，如果超出文件末尾则返回 EOF
     */
    public function peek(int $offset = 1): TokenInterface
    {
        $targetIndex = $this->cursor + $offset;
        $this->ensureBufferHas($targetIndex);

        $lastIndex = array_key_last($this->buffer);
        if ($lastIndex === null) {
            throw new RuntimeException("Token buffer is empty.");
        }

        return $this->buffer[$targetIndex] ?? $this->buffer[$lastIndex];

    }


    /**
     * 匹配当前词法单元类型
     *
     * 检查当前词法单元的类型是否在指定的类型列表中，如果匹配则消耗该词法单元并返回
     *
     * @param TokenType ...$types 要匹配的词法单元类型列表
     * @return TokenInterface|null 匹配成功时返回当前的词法单元对象，失败时返回 null
     */
    public function match(TokenType ...$types): ?TokenInterface
    {
        $currentType = $this->current()->getType();
        if (in_array($currentType, $types, true)) {
            return $this->next(); // 匹配成功，消耗掉这个 Token 并返回
        }
        return null;
    }

    /**
     * 期望匹配指定的词法单元类型
     *
     * 检查当前词法单元的类型是否在指定的类型列表中，如果匹配则消耗该词法单元并返回，
     * 否则抛出解析异常
     *
     * @param string $message 匹配失败时的错误消息
     * @param TokenType ...$types 要匹配的词法单元类型列表
     * @return TokenInterface 匹配成功时返回当前的词法单元对象
     * @throws ParseException 当匹配失败时抛出解析异常
     */
    public function expect(string $message, TokenType ...$types): TokenInterface
    {
        $token = $this->match(...$types);
        if ($token === null) {
            $current = $this->current();

            throw new ParseException(
                sprintf('%s (Unexpected token "%s")', $message, $current->getType()->name),
                ParseErrorType::INVALID_TYPE,
                $current->getLine(),
                $current->getColumn()
            );
        }
        return $token;
    }


    /**
     * 检查是否已到达词法单元流末尾
     *
     * @return bool 如果当前词法单元类型为 EOF 则返回 true，否则返回 false
     * @phpstan-impure
     */
    public function isEOF(): bool
    {
        return $this->current()->getType() === TokenType::EOF;
    }

    /**
     * 确保缓冲区中存在指定索引的 Token
     *
     * 通过驱动生成器来填充缓冲区，直到缓冲区包含指定索引位置的词法单元。
     * 这是实现惰性加载的核心方法，只在需要时才解析新的词法单元。
     * 如果生成器已耗尽且目标索引不存在，会抛出异常。
     *
     * @param int $targetIndex 需要确保存在的目标索引位置
     * @return void 无返回值
     * @throws ParseException 当词法单元流过早结束且未包含 EOF 标记时抛出异常
     */
    private function ensureBufferHas(int $targetIndex): void
    {
        while (!isset($this->buffer[$targetIndex]) && $this->generator->valid()) {
            $this->buffer[] = $this->generator->current();
            $this->generator->next();
        }

        // 如果循环结束了，但目标索引依然不存在（说明生成器已经 invalid 了）
        if (!isset($this->buffer[$targetIndex])) {
            // 检查缓冲区是否为空，或者最后一个元素不是 EOF
            $lastToken = empty($this->buffer) ? null : end($this->buffer);

            if ($lastToken === null || $lastToken->getType() !== TokenType::EOF) {
                throw new ParseException(
                    "Token stream ended prematurely without an EOF token. This indicates a lexer bug or unexpected termination.",
                    ParseErrorType::INTERNAL_ERROR,
                    $lastToken?->getLine() ?? 0,
                    $lastToken?->getColumn() ?? 0
                );
            }
        }
    }
}
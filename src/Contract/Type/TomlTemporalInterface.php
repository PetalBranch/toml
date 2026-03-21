<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Contract\Type;

/**
 * TOML 时间类型接口
 *
 * 该接口用于标记表示 TOML 规范中时间相关类型的类。
 * 实现此接口的类应表示日期、时间或日期时间等时间值类型。
 * 这是一个标记接口，主要用于类型识别和约束，
 * 确保只有合法的时间类型可以被正确处理。
 */
interface TomlTemporalInterface
{
}

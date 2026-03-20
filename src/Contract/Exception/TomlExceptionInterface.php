<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Contract\Exception;

/**
 * TOML 基础异常接口
 *
 * 作为本库所有异常的顶级标记接口（Marker Interface）。
 * 业务侧可以通过 catch (TomlExceptionInterface $e) 捕获所有与 TOML 处理相关的错误。
 *
 * @package Petalbranch\Toml\Contract\Exception
 */
interface TomlExceptionInterface
{

}

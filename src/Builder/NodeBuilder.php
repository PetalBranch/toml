<?php

declare(strict_types=1);

namespace Petalbranch\Toml\Builder;

use Petalbranch\Toml\Contract\Parser\NodeInterface;
use Petalbranch\Toml\Exception\DumpException;
use Petalbranch\Toml\Model\DumperConfig;
use Petalbranch\Toml\Model\Node\ArrayNode;
use Petalbranch\Toml\Model\Node\InlineTableNode;
use Petalbranch\Toml\Model\Node\TableNode;
use Petalbranch\Toml\Model\Node\ValueNode;
use Petalbranch\Toml\Support\ArrayHelper;
use Petalbranch\Toml\Support\Position;
use Petalbranch\Toml\Support\TomlDate;
use Petalbranch\Toml\Support\TomlLocalDateTime;
use Petalbranch\Toml\Support\TomlOffsetDateTime;
use Petalbranch\Toml\Support\TomlTime;
use Petalbranch\Toml\Type\TomlType;
use stdClass;

/**
 * TOML 节点构建器
 *
 * 负责将 PHP 数据结构转换为 TOML 抽象语法树（AST）节点。
 * 支持数组、对象和基本类型的转换，并根据配置决定是否使用内联表。
 * 确保根节点始终是关联数组或对象，维护正确的嵌套深度和格式。
 */
readonly class NodeBuilder
{
    /**
     * 构造函数
     *
     * 创建一个新的 NodeBuilder 实例。
     *
     * @param DumperConfig $config 转储配置对象，控制输出格式和行为
     */
    public function __construct(
        private DumperConfig $config = new DumperConfig()
    )
    {
    }

    /**
     * 构建 TOML 文档树
     *
     * 将输入的数据构建为 TOML 抽象语法树的根节点。
     * 输入数据必须是关联数组或对象，因为 TOML 文档的根必须是表。
     *
     * @param mixed $data 要转换的原始数据，必须是关联数组或对象
     * @return TableNode 构建完成的文档根节点
     * @throws DumpException 当输入数据不是有效的关联结构时抛出异常
     */
    public function build(mixed $data): TableNode
    {
        // 支持 stdClass 作为根文档
        if (!is_array($data) && !($data instanceof stdClass)) {
            throw new DumpException("The root of a TOML document must be an associative array or object.");
        }

        // 如果传了原生数组，但也得是关联数组才行 (防止传 [1,2,3])
        if (is_array($data) && !ArrayHelper::isAssoc($data) && !empty($data)) {
            throw new DumpException("The root of a TOML document cannot be a sequential array.");
        }

        $node = $this->transformToNode($data, 0);

        if (!$node instanceof TableNode) {
            throw new DumpException("Root node could not be built correctly.");
        }

        return $node;
    }

    /**
     * 转换数据为节点
     *
     * 递归地将 PHP 数据转换为相应的 TOML 节点对象。
     * 根据数据类型和嵌套深度决定创建普通表节点还是内联表节点。
     * 关联数组/对象转换为 TableNode 或 InlineTableNode，索引数组转换为 ArrayNode，
     * 其他类型转换为 ValueNode。
     *
     * @param mixed $data 要转换的数据
     * @param int $depth 当前嵌套深度，用于决定是否使用内联表
     * @return NodeInterface 转换后的节点对象
     */
    private function transformToNode(mixed $data, int $depth): NodeInterface
    {
        // 如果是 stdClass 或者是 关联数组，都视为 Table
        if ($data instanceof stdClass || (is_array($data) && ArrayHelper::isAssoc($data))) {
            $arrayData = $data instanceof stdClass ? get_object_vars($data) : $data;
            $itemCount = count($arrayData);

            $shouldBeInline = $this->config->inlineTable
                && $depth > 0
                && $depth <= $this->config->inlineTableMaxDepth
                && $itemCount <= $this->config->inlineTableMaxItems;

            $table = $shouldBeInline ? new InlineTableNode(null) : new TableNode(null);

            foreach ($arrayData as $key => $value) {
                $table->set((string)$key, $this->transformToNode($value, $depth + 1));
            }
            return $table;
        }

        // 只有纯净的序列数组才会走到这里
        if (is_array($data)) {
            $arrayNode = new ArrayNode(null);
            foreach ($data as $value) {
                $arrayNode->add($this->transformToNode($value, $depth + 1));
            }
            return $arrayNode;
        }

        $type = $this->detectTomlType($data);
        return new ValueNode($type, $data, new Position(0, 0), '');
    }

    /**
     * 检测值的 TOML 类型
     *
     * 根据值的内容和类型确定其对应的 TOML 类型。
     * 使用 match 表达式进行类型匹配，优先检查自定义类型，
     * 然后是基本类型。
     *
     * @param mixed $value 要检测类型的值
     * @return TomlType 对应的 TOML 类型
     * @throws DumpException 当值类型不被支持时抛出异常
     */
    private function detectTomlType(mixed $value): TomlType
    {
        return match (true) {
            is_int($value) => TomlType::INTEGER,
            is_float($value) => TomlType::FLOAT,
            is_bool($value) => TomlType::BOOLEAN,
            is_string($value) => TomlType::STRING,
            $value instanceof TomlOffsetDateTime => TomlType::OFFSET_DATETIME,
            $value instanceof TomlLocalDateTime => TomlType::LOCAL_DATETIME,
            $value instanceof TomlDate => TomlType::LOCAL_DATE,
            $value instanceof TomlTime => TomlType::LOCAL_TIME,
            $value === null => throw DumpException::unsupportedType('null'),
            default => throw DumpException::unsupportedType(gettype($value)),
        };
    }
}

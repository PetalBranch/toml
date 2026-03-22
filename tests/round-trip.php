<?php

declare(strict_types=1);

require_once '../vendor/autoload.php';

use Petalbranch\Toml\Parser\Lexer;
use Petalbranch\Toml\Parser\Parser;
use Petalbranch\Toml\Dumper\NodeDumper;
use Petalbranch\Toml\Model\DumperConfig;
use Petalbranch\Toml\Model\Node\TableNode;
use Petalbranch\Toml\Model\Node\ValueNode;

$originalToml = <<<TOML
# 数据库核心配置
# 请勿在生产环境修改端口！
[database]
server = "192.168.1.1"  # 主库 IP
port = 3306

  # 认证信息
  user = "root"
  password = "password123"  # 定期更换
TOML;

echo "【1. 解析为包含完整注释的 AST 树】\n";
$parser = new Parser(new Lexer());

/** @var TableNode $rootNode */
$rootNode = $parser->parseToNode($originalToml);

// 模拟用户在后台直接修改 AST 节点的值
/** @var TableNode $dbNode */
$dbNode = $rootNode->get('database');

/** @var ValueNode $portNode */
$portNode = $dbNode->get('port');
$portNode->setValue(6379); // 直接修改节点的值

/** @var ValueNode $pwdNode */
$pwdNode = $dbNode->get('password');
$pwdNode->setValue("new_secret_pwd"); // 直接修改节点的值

echo "\n【2. 拿着这棵树直接 Dump 出去】\n";
$dumper = new NodeDumper(new DumperConfig());
// 直接转储带有注释的 AST 树
echo $dumper->dump($rootNode);
<?php
require_once '../vendor/autoload.php';

use Petalbranch\Toml\Parser\Lexer;
use Petalbranch\Toml\Parser\Parser;
use Petalbranch\Toml\Dumper\NodeDumper;
use Petalbranch\Toml\Model\DumperConfig;

$toml = <<<TOML
[server]
ip = "127.0.0.1"
max_connections = 1000
"中文端口号" = 8080
enable = true
TOML;

$parser = new Parser(new Lexer());
$rootNode = $parser->parseToNode($toml);

$config = new DumperConfig();
$config->alignEquals = true;

echo "【开启等号对齐】\n\n";
$dumper = new NodeDumper($config);
echo $dumper->dump($rootNode);
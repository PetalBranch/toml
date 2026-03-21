<?php

declare(strict_types=1);

use Petalbranch\Toml\Toml;

require_once '../vendor/autoload.php';

$toml[] = <<<TOML
"oh,🤪" = 7. 
TOML;

$toml[] = <<<TOML
🤪"oh,🤪" = 7. 
TOML;

$toml[] = <<<TOML
key = # 非法
TOML;

$toml[] = <<<TOML
first = "Tom" last = "Preston-Werner" # 非法
TOML;

$toml[] = <<<TOML
= "no key name"  # 非法
"" = "blank"     # 合法但不鼓励
'' = 'blank'     # 合法但不鼓励
TOML;

$toml[] = <<<TOML
# 这是不可行的
spelling = "favorite"
"spelling" = "favourite"
TOML;

$toml[] = <<<TOML
# 以下是非法的

# 这将 fruit.apple 的值定义为一个整数。
fruit.apple = 1

# 但接下来这将 fruit.apple 像表一样对待了。
# 整数不能变成表。
fruit.apple.smooth = true
TOML;

$toml[] = <<<TOML
str4 = """这有两个引号：""。够简单。"""
str5 = """这有三个引号："""。"""  # 非法
str5 = """这有三个引号：""\"。"""
str6 = """这有十五个引号：""\"""\"""\"""\"""\"。"""

# "这，"她说，"只是个无意义的条款。"
str7 = """"这，"她说，"只是个无意义的条款。""""
TOML;

$toml[] = <<<TOML
quot15 = '''这有十五个引号："""""""""""""""'''

apos15 = '''这有十五个撇号：''''''''''''''''''  # 非法
apos15 = "这有十五个撇号：'''''''''''''''"

# '那，'她说，'仍然没有意义。'
str = ''''那，'她说，'仍然没有意义。''''
TOML;

$toml[] = <<<TOML
# 非法的浮点数
invalid_float_1 = .7
invalid_float_2 = 7.
invalid_float_3 = 3.e+20
TOML;

$toml[] = <<<TOML
[fruit]
apple.color = "红"
apple.taste.sweet = true

[fruit.apple]  # 非法
[fruit.apple.taste]  # 非法

[fruit.apple.texture]  # 你可以添加子表
smooth = true
TOML;

$toml[] = <<<TOML
[product]
type = { name = "Nail" }
type.edible = false  # 非法
TOML;

$toml[] = <<<TOML
# 非法的 TOML 文档
[fruit.physical]  # 子表，但它应该隶属于哪个父元素？
color = "red"
shape = "round"

[[fruit]]  # 解析器必须在发现“fruit”是数组而非表时抛出错误
name = "apple"
TOML;

$toml[] = <<<TOML
# 非法的 TOML 文档
fruits = []

[[fruits]] # 不允许
TOML;

$toml[] = <<<TOML
# 非法的 TOML 文档
[[fruits]]
name = "apple"

[[fruits.varieties]]
name = "red delicious"

# 非法：该表与之前的表数组相冲突
[fruits.varieties]
name = "granny smith"

[fruits.physical]
color = "red"
shape = "round"

# 非法：该表数组与之前的表相冲突
[[fruits.physical]]
color = "green"
TOML;


$i = 0;
$ok_i = 0;
$loss_i = 0;
foreach ($toml as $tomlString) {
    $i++;
    try {
        Toml::parse($tomlString);
        $ok_i++;
        echo "警告：示例 $i 本该失败但通过了。\n";
    } catch (Exception $e) {
        $loss_i++;
        echo "错误示例：$i\n";
        echo $e->getMessage();
        echo "\n";
    }
}
echo "\n";
echo "【测试完成】\n";
echo "成功：$ok_i\n";
echo "失败：$loss_i\n";
echo "应该失败数量：$i\n";
exit;

<?php

use Petalbranch\Toml\Exception\ParseException;
use Petalbranch\Toml\Model\DumperConfig;
use Petalbranch\Toml\Toml;
use PHPUnit\Framework\TestCase;

/**
 * TOML 解析器和转储器功能测试类
 *
 * 该测试类验证 Toml 类的主要功能，包括：
 * - 字符串和文件内容的解析
 * - 数据结构到 TOML 字符串的序列化
 * - 数据到文件的写入
 * 测试覆盖了正常情况、异常处理和配置选项的使用。
 */
class TomlTest extends TestCase
{
    private string $tempDir;

    /**
     * 测试前置设置
     *
     * 在每个测试方法执行前运行，创建临时测试目录用于存放测试文件。
     */
    protected function setUp(): void
    {
        // 创建一个临时的测试目录
        $this->tempDir = __DIR__ . '/temp_test_files';
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

    /**
     * 测试后置清理
     *
     * 在每个测试方法执行后运行，清理创建的临时文件和目录。
     */
    protected function tearDown(): void
    {
        // 测试结束后清理临时文件和目录
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->tempDir);
        }
    }


    /**
     * 测试 TOML 字符串解析功能
     *
     * 验证 parse 方法能够正确解析包含基本键值对和嵌套表的 TOML 字符串。
     * 检查解析结果的数据结构和值是否符合预期。
     */
    public function testParse(): void
    {
        $toml = <<<TOML
            title = "TOML Example"
            [owner]
            name = "Petalbranch"
            dates = [1979-05-27, 2023-10-27]
        TOML;

        $expected = [
            'title' => 'TOML Example',
            'owner' => [
                'name' => 'Petalbranch',
                // 注意：日期类型解析后应为自定义的时间对象，
                // 这里为了门面测试的简洁性，只检查最外层结构
            ]
        ];

        $result = Toml::parse($toml);

        $this->assertIsArray($result);
        $this->assertEquals($expected['title'], $result['title']);
        $this->assertEquals($expected['owner']['name'], $result['owner']['name']);
        $this->assertArrayHasKey('dates', $result['owner']);
        $this->assertCount(2, $result['owner']['dates']);
    }


    /**
     * 测试 TOML 文件解析功能
     *
     * 验证 parseFile 方法能够正确读取并解析 TOML 文件内容。
     * 先创建测试文件，然后调用解析方法，最后验证结果。
     */
    public function testParseFile(): void
    {
        $filename = $this->tempDir . '/test_read.toml';
        $tomlContent = 'foo = "bar"' . "\n" . '[baz]' . "\n" . 'qux = 123';
        file_put_contents($filename, $tomlContent);

        $result = Toml::parseFile($filename);

        $expected = [
            'foo' => 'bar',
            'baz' => [
                'qux' => 123
            ]
        ];

        $this->assertEquals($expected, $result);
    }


    /**
     * 测试解析不存在的文件时抛出异常
     *
     * 验证当尝试解析不存在的文件时，parseFile 方法会抛出 ParseException 异常，
     * 并且异常消息中包含文件路径信息。
     */
    public function testParseFileNotFoundThrowsException(): void
    {
        $filename = $this->tempDir . '/non_existent_file.toml';

        $this->expectException(ParseException::class);
        $this->expectExceptionMessageMatches('/File ".*" does not exist or is not readable./');

        Toml::parseFile($filename);
    }


    /**
     * 测试数据转储为 TOML 字符串功能
     *
     * 验证 dump 方法能够将 PHP 数组正确转换为格式化的 TOML 字符串。
     * 使用正则表达式检查输出字符串中是否包含预期的结构和值。
     */
    public function testDump(): void
    {
        $data = [
            'database' => [
                'server' => '192.168.1.1',
                'ports' => [8001, 8002],
                'enabled' => true
            ]
        ];

        $result = Toml::dump($data);

        // 使用正则检查生成的 TOML 结构，允许缩进和换行差异
        $this->assertMatchesRegularExpression('/\[database]/', $result);
        $this->assertMatchesRegularExpression('/server = "192.168.1.1"/', $result);
        $this->assertMatchesRegularExpression('/ports = \[8001, 8002]/', $result);
        $this->assertMatchesRegularExpression('/enabled = true/', $result);
    }

    /**
     * 测试生成 TOML 时传入自定义配置（如内联表）
     *
     * 验证 dump 方法能够根据提供的 DumperConfig 配置生成相应的输出格式。
     * 特别测试内联表功能是否按配置正确工作。
     */
    public function testDumpWithConfig(): void
    {
        // 我们将索引数组改为关联数组，测试纯粹的 Table 内联
        $data = [
            'points' => [
                'p1' => ['x' => 1, 'y' => 2],
                'p2' => ['x' => 3, 'y' => 4]
            ]
        ];

        // 配置：开启内联表
        $config = new DumperConfig();
        $config->inlineTable = true;
        // 根节点 depth=0, points depth=1, p1/p2 depth=2
        // 所以允许内联的最大深度必须至少为 2
        $config->inlineTableMaxDepth = 2;
        $config->inlineTableMaxItems = 3;

        $result = Toml::dump($data, $config);

        // 预期输出应包含单行的内联表语法 { ... }
        $this->assertStringContainsString('p1 = { x = 1, y = 2 }', $result);
        $this->assertStringContainsString('p2 = { x = 3, y = 4 }', $result);
    }

    /**
     * 测试将数据生成并成功写入文件
     *
     * 验证 dumpFile 方法能够将数据序列化为 TOML 格式并写入指定文件。
     * 检查返回值、文件是否存在以及文件内容是否正确。
     */
    public function testDumpFile(): void
    {
        $filename = $this->tempDir . '/test_write.toml';
        $data = ['app' => ['name' => 'FacadeTest']];

        $result = Toml::dumpFile($filename, $data);

        $this->assertTrue($result);
        $this->assertFileExists($filename);

        // 验证文件内容
        $content = file_get_contents($filename);
        $this->assertStringContainsString('[app]', $content);
        $this->assertStringContainsString('name = "FacadeTest"', $content);
    }
}

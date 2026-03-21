<?php
declare(strict_types=1);

require '../vendor/autoload.php';

use Petalbranch\Toml\Dumper\Dumper;
use Petalbranch\Toml\Support\TomlDate;
use Petalbranch\Toml\Support\TomlTime;
use Petalbranch\Toml\Support\TomlLocalDateTime;
use Petalbranch\Toml\Support\TomlOffsetDateTime;

try {
    $jsonInput = stream_get_contents(STDIN);
    if (empty($jsonInput)) {
        exit(1);
    }

    $taggedData = json_decode($jsonInput, false);
    if ($taggedData === null) {
        throw new Exception("Invalid JSON input");
    }

    $phpData = translateTaggedJson($taggedData);

    $dumper = new Dumper();
    echo $dumper->dump($phpData);
    exit(0);
} catch (Throwable $e) {
    file_put_contents('php://stderr', $e->getMessage() . "\n");
    exit(1);
}

/**
 * 递归翻译 Tagged JSON
 */
function translateTaggedJson(mixed $data): mixed
{
    if ($data instanceof stdClass) {
        if (isset($data->type) && isset($data->value)) {
            $type = $data->type;
            $val = $data->value;

            return match ($type) {
                'string' => (string)$val,
                'integer' => (int)$val,
                'float' => match (strtolower((string)$val)) {
                    'inf', '+inf' => INF,
                    '-inf' => -INF,
                    'nan', '+nan', '-nan' => NAN,
                    default => (float)$val
                },
                'bool' => $val === 'true' || $val === true,
                'datetime' => new TomlOffsetDateTime(new DateTimeImmutable($val)),
                'datetime-local' => new TomlLocalDateTime(new DateTimeImmutable($val)),
                'date-local' => new TomlDate(new DateTimeImmutable($val)),
                'time-local' => parseLocalTime($val),
                default => throw new Exception("Unknown type: $type")
            };
        }

        // 先转数组，操作完再转回对象
        $arr = (array)$data;
        foreach ($arr as $key => $value) {
            $arr[$key] = translateTaggedJson($value);
        }
        return (object)$arr;
    }

    if (is_array($data)) {
        $translated = [];
        foreach ($data as $value) {
            $translated[] = translateTaggedJson($value);
        }
        return $translated;
    }

    return $data;
}


function parseLocalTime(string $val): TomlTime
{
    $parts = explode('.', $val);
    $timeParts = explode(':', $parts[0]);
    $micro = 0;
    if (isset($parts[1])) {
        $micro = (int)str_pad($parts[1], 6, '0', STR_PAD_RIGHT);
    }
    return new TomlTime((int)$timeParts[0], (int)$timeParts[1], (int)$timeParts[2], $micro);
}
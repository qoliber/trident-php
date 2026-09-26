<?php

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Delivery;

use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Delivery\Instance;
use Qoliber\Trident\Delivery\Instances;

final class InstancesTest extends TestCase
{
    public function testDefaultUrlIsOneDefaultInstance(): void
    {
        [$instances, $errors] = Instances::parse(null, 'http://127.0.0.1:9301/', 'secret');
        self::assertSame([], $errors);
        self::assertCount(1, $instances);
        self::assertSame('default', $instances[0]->name);
        self::assertSame('http://127.0.0.1:9301', $instances[0]->apiUrl);
        self::assertSame('secret', $instances[0]->apiToken);
    }

    public function testNothingConfiguredMeansNoInstance(): void
    {
        self::assertSame([[], []], Instances::parse(null, '', ''));
        self::assertSame([[], []], Instances::parse([], '  ', 'x'));
    }

    public function testListWinsOverDefaultUrlAndInheritsTheToken(): void
    {
        [$instances, $errors] = Instances::parse(
            [
                'edge-1' => ['api_url' => 'http://10.0.0.1:9301'],
                'edge-2' => ['api_url' => 'https://10.0.0.2:9301', 'api_token' => 'own'],
            ],
            'http://ignored:9301',
            'admin-token'
        );
        self::assertSame([], $errors);
        self::assertSame(['edge-1', 'edge-2'], array_map(static fn (Instance $i): string => $i->name, $instances));
        self::assertSame('admin-token', $instances[0]->apiToken);
        self::assertSame('own', $instances[1]->apiToken);
    }

    public function testInvalidEntriesAreReportedNotSilentlyUsed(): void
    {
        [$instances, $errors] = Instances::parse(
            [
                'ok' => ['api_url' => 'http://a:1'],
                'no-url' => [],
                'not-an-array' => 'http://b:1',
                'bad scheme' => ['api_url' => 'ftp://c:1'],
                'bad-scheme' => ['api_url' => 'ftp://c:1'],
                'x' => ['api_url' => 'not a url'],
                str_repeat('n', 65) => ['api_url' => 'http://d:1'],
            ],
            '',
            't'
        );
        self::assertSame(['ok'], array_map(static fn (Instance $i): string => $i->name, $instances));
        self::assertCount(6, $errors);
        self::assertStringContainsString('no-url: no api_url', implode('|', $errors));
    }

    public function testListWithoutKeysGetsPositionalNames(): void
    {
        [$instances] = Instances::parse([['api_url' => 'http://a:1'], ['api_url' => 'http://b:1']], '', 't');
        self::assertSame(['instance-1', 'instance-2'], array_map(static fn (Instance $i): string => $i->name, $instances));
    }

    public function testNamesAreCaseSensitive(): void
    {
        [$instances, $errors] = Instances::parse(
            ['Edge-1' => ['api_url' => 'http://a:1'], 'edge-1' => ['api_url' => 'http://b:1']],
            '',
            't'
        );
        self::assertSame([], $errors);
        self::assertCount(2, $instances);
    }

    public function testInvalidDefaultUrlIsAnError(): void
    {
        [$instances, $errors] = Instances::parse(null, 'trident:9301', 't');
        self::assertSame([], $instances);
        self::assertCount(1, $errors);
    }
}

<?php

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Config\SettingsResolver;
use Qoliber\Trident\Delivery\Acknowledgement;
use Qoliber\Trident\Delivery\Backoff;
use Qoliber\Trident\Delivery\Instances;
use Qoliber\Trident\Delivery\OutboxEntry;
use Qoliber\Trident\Delivery\Packer;
use Qoliber\Trident\Http\NetworkGuard;
use Qoliber\Trident\Tags\TagPolicy;
use Qoliber\Trident\Tags\TagSet;

/**
 * The shared contract vectors (integrations/contract/trident-contract.json):
 * this library is the reference, and the Python and JavaScript libraries run
 * the same file. A clone of the published mirror has no vectors; the tests
 * are skipped there.
 */
final class ContractVectorsTest extends TestCase
{
    private static function vectors(string $section): array
    {
        $file = dirname(__DIR__, 3) . '/contract/trident-contract.json';
        if (!is_file($file)) {
            return ['no vectors (a mirror clone)' => [null]];
        }
        $out = [];
        foreach (json_decode((string) file_get_contents($file), true, 512, \JSON_THROW_ON_ERROR)[$section] as $i => $case) {
            $out[$section . ' #' . $i] = [$case];
        }

        return $out;
    }

    private static function need(?array $case): array
    {
        if ($case === null) {
            self::markTestSkipped('integrations/contract/trident-contract.json is not in this checkout');
        }

        return $case;
    }

    public static function acknowledgement(): array { return self::vectors('acknowledgement'); }
    public static function normalise(): array { return self::vectors('normalise'); }
    public static function header(): array { return self::vectors('header'); }
    public static function purgeTags(): array { return self::vectors('purge_tags'); }
    public static function instances(): array { return self::vectors('instances'); }
    public static function settings(): array { return self::vectors('settings'); }
    public static function networkGuard(): array { return self::vectors('network_guard'); }
    public static function backoff(): array { return self::vectors('backoff'); }
    public static function pack(): array { return self::vectors('pack'); }

    #[DataProvider('acknowledgement')]
    public function testAcknowledgement(?array $case): void
    {
        $c = self::need($case);
        self::assertSame($c['failure'], Acknowledgement::purgeFailure($c['status'], $c['body']));
    }

    #[DataProvider('normalise')]
    public function testNormalise(?array $case): void
    {
        $c = self::need($case);
        self::assertSame($c['out'], TagSet::normalise($c['prefix'], $c['tag']));
    }

    #[DataProvider('header')]
    public function testHeader(?array $case): void
    {
        $c = self::need($case);
        $tags = isset($c['generate'])
            ? array_map(static fn (int $i): string => str_replace('{i}', (string) $i, $c['generate']['pattern']), range(0, $c['generate']['count'] - 1))
            : $c['tags'];
        $value = (new TagPolicy($c['prefix'], $c['families'], $c['identity_prefixes']))->headerValue($tags);
        if (isset($c['out'])) {
            self::assertSame($c['out'], $value);
        } else {
            $parts = explode(',', $value);
            self::assertCount($c['expect_count'], $parts);
            self::assertSame($c['expect_last'], end($parts));
        }
    }

    #[DataProvider('purgeTags')]
    public function testPurgeTags(?array $case): void
    {
        $c = self::need($case);
        self::assertSame($c['out'], (new TagPolicy($c['prefix'], $c['families']))->purgeTags($c['tags']));
    }

    #[DataProvider('instances')]
    public function testInstances(?array $case): void
    {
        $c = self::need($case);
        [$instances, $errors] = Instances::parse($c['configured'], $c['default_url'], $c['default_token']);
        self::assertSame($c['names'], array_map(static fn ($i) => $i->name, $instances));
        self::assertSame($c['urls'], array_map(static fn ($i) => $i->apiUrl, $instances));
        self::assertSame($c['tokens'], array_map(static fn ($i) => $i->apiToken, $instances));
        self::assertCount($c['errors'], $errors, implode(' | ', $errors));
    }

    #[DataProvider('settings')]
    public function testSettings(?array $case): void
    {
        $c = self::need($case);
        $s = SettingsResolver::resolve($c['env'], []);
        self::assertSame($c['source'], $s->source);
        self::assertSame($c['names'], $s->instanceNames());
        self::assertCount($c['errors'], $s->errors, implode(' | ', $s->errors));
        self::assertSame($c['mode'], $s->mode);
        self::assertSame($c['prefix'], $s->tagPrefix);
        self::assertSame($c['debug'], $s->debugHeaders);
    }

    #[DataProvider('networkGuard')]
    public function testNetworkGuard(?array $case): void
    {
        $c = self::need($case);
        $guard = new NetworkGuard(static fn (string $host): array => $c['resolves'] ?? []);
        try {
            $address = $guard->address($c['host']);
        } catch (\RuntimeException $e) {
            self::assertTrue($c['refused'] ?? false, $c['host'] . ' refused: ' . $e->getMessage());

            return;
        }
        self::assertFalse($c['refused'] ?? false, $c['host'] . ' was allowed');
        self::assertSame($c['address'], $address);
    }

    #[DataProvider('backoff')]
    public function testBackoff(?array $case): void
    {
        [$failures, $delay] = self::need($case);
        self::assertSame($delay, Backoff::delay($failures));
    }

    #[DataProvider('pack')]
    public function testPack(?array $case): void
    {
        $c = self::need($case);
        $entries = [];
        foreach ($c['entry_tag_counts'] as $i => $n) {
            $entries[] = new OutboxEntry($i + 1, 'e', array_map(static fn (int $j): string => "t{$i}_{$j}", range(0, $n - 1)), 0);
        }
        self::assertSame($c['request_tag_counts'], array_map(static fn (array $r): int => count($r['tags']), Packer::pack($entries)));
    }
}

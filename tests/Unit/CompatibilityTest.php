<?php

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Compatibility;

final class CompatibilityTest extends TestCase
{
    public function testTheSameReleaseLineIsNoWarning(): void
    {
        self::assertNull(Compatibility::warning('1.8.0', '1.8'));
        self::assertNull(Compatibility::warning('1.8.7', '1.8'));
        self::assertNull(Compatibility::warning('v1.8.2-rc.1', '1.8'));
    }

    public function testAnOlderEngineAsksToUpgradeTrident(): void
    {
        $w = Compatibility::warning('1.7.3', '1.8');
        self::assertNotNull($w);
        self::assertStringContainsString('Trident 1.7.3 is connected', $w);
        self::assertStringContainsString('built for Trident 1.8.x', $w);
        self::assertStringContainsString('Upgrade Trident', $w);
    }

    public function testANewerEngineAsksToUpgradeTheIntegration(): void
    {
        $w = Compatibility::warning('1.9.0', '1.8');
        self::assertNotNull($w);
        self::assertStringContainsString('Upgrade this integration', $w);
        self::assertNotNull(Compatibility::warning('2.0.0', '1.8'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unreadable(): array
    {
        return ['empty' => [''], 'word' => ['unknown'], 'html' => ['<b>1.9</b>'], 'major only' => ['2']];
    }

    #[DataProvider('unreadable')]
    public function testAnUnreadableVersionIsNotAMismatch(string $version): void
    {
        self::assertNull(Compatibility::warning($version, '1.8'));
    }

    /**
     * Lockstep: the library's target line must be the engine's. In the
     * monorepo the workspace Cargo.toml is two levels up; the published mirror
     * has no engine, so there the check is skipped.
     */
    public function testTheTargetIsTheEnginesReleaseLine(): void
    {
        $cargo = dirname(__DIR__, 4) . '/Cargo.toml';
        if (!is_file($cargo)) {
            self::markTestSkipped('No engine workspace here (published mirror).');
        }
        $toml = (string) file_get_contents($cargo);
        self::assertSame(1, preg_match('/^\[workspace\.package\].*?^version\s*=\s*"([^"]+)"/ms', $toml, $m), 'workspace version not found');
        self::assertSame(
            Compatibility::line($m[1]),
            Compatibility::TRIDENT,
            'Compatibility::TRIDENT lags the engine: bump it with the engine minor (lockstep versioning).'
        );
    }
}

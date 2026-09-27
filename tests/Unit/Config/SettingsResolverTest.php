<?php

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Config\Settings;
use Qoliber\Trident\Config\SettingsResolver;

/**
 * The env-first settings every platform resolves the same way (1.8.0).
 */
final class SettingsResolverTest extends TestCase
{
    public function testTheEnvironmentWinsAndItsTokenGoesOnlyToItsUrls(): void
    {
        $s = SettingsResolver::resolve(
            ['TRIDENT_INSTANCES' => '{"a":{"api_url":"http://t1:9301"},"b":{"api_url":"http://t2:9301","api_token":"own"}}', 'TRIDENT_API_TOKEN' => 'env-token'],
            ['api_url' => 'http://admin-set:9301', 'api_token' => 'admin-token'],
        );
        self::assertSame(['a', 'b'], $s->instanceNames());
        self::assertSame('env-token', $s->instances[0]->apiToken);
        self::assertSame('own', $s->instances[1]->apiToken);
        self::assertSame('environment (TRIDENT_INSTANCES)', $s->source);
    }

    public function testASingleEnvironmentUrl(): void
    {
        $s = SettingsResolver::resolve(['TRIDENT_API_URL' => 'http://t:9301', 'TRIDENT_API_TOKEN' => 'x'], []);
        self::assertSame('environment (TRIDENT_API_URL)', $s->source);
        self::assertSame('x', $s->instances[0]->apiToken);
    }

    public function testTheAdminUrlNeverGetsTheEnvironmentToken(): void
    {
        $s = SettingsResolver::resolve(['TRIDENT_API_TOKEN' => 'env-token'], ['api_url' => 'http://admin-set:9301', 'api_token' => null]);
        self::assertSame('http://admin-set:9301', $s->instances[0]->apiUrl);
        self::assertSame('', $s->instances[0]->apiToken);
    }

    public function testMalformedInstancesJsonFallsBackToTheAdminSettingWithAnError(): void
    {
        $s = SettingsResolver::resolve(['TRIDENT_INSTANCES' => '{not json'], ['api_url' => 'http://admin:9301', 'api_token' => 't']);
        self::assertSame('admin', $s->source);
        self::assertStringContainsString('TRIDENT_INSTANCES', $s->errors[0]);
    }

    public function testAFailedSettingsReadIsReportedNotSilent(): void
    {
        $s = SettingsResolver::resolve([], ['read_failed' => true]);
        self::assertTrue($s->readFailed);
        self::assertFalse($s->enabled());
        self::assertContains(Settings::READ_FAILED, $s->errors);
    }

    public function testTheAllowlistAndUrlShapeDropInstancesWithAReason(): void
    {
        $s = SettingsResolver::resolve([
            'TRIDENT_INSTANCES' => '{"ok":{"api_url":"http://trident:9301"},"off":{"api_url":"http://elsewhere:9301"},"query":{"api_url":"http://trident:9301/?x="}}',
            'TRIDENT_ALLOWED_API_HOSTS' => 'trident',
        ], []);
        self::assertSame(['ok'], $s->instanceNames());
        self::assertCount(2, $s->errors);
    }

    public function testModePrefixAndDebugHeaders(): void
    {
        self::assertSame('soft', SettingsResolver::resolve([], [])->mode);
        self::assertSame('hard', SettingsResolver::resolve(['TRIDENT_PURGE_MODE' => 'hard'], [])->mode);
        self::assertSame('hard', SettingsResolver::resolve([], ['purge_mode' => 'hard'])->mode);
        self::assertSame('soft', SettingsResolver::resolve(['TRIDENT_PURGE_MODE' => 'nonsense'], [])->mode);
        self::assertSame('none', SettingsResolver::resolve([], [])->source);
        self::assertSame('s1_', SettingsResolver::resolve(['TRIDENT_TAG_PREFIX' => 's1_'], [])->tagPrefix);
        foreach (['1', 'true', 'on', 'YES'] as $v) {
            self::assertTrue(SettingsResolver::resolve(['TRIDENT_DEBUG_HEADERS' => $v], [])->debugHeaders, $v);
        }
        self::assertFalse(SettingsResolver::resolve(['TRIDENT_DEBUG_HEADERS' => '0'], [])->debugHeaders);
    }
}

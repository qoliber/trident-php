<?php

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Security\TokenVault;

final class TokenVaultTest extends TestCase
{
    public function testATokenOpensOnlyForTheUrlAndKeyItWasSealedWith(): void
    {
        $v = new TokenVault('app-secret');
        $sealed = $v->seal('s3cret', 'http://trident:9301');

        self::assertTrue(TokenVault::isSealed($sealed));
        self::assertStringNotContainsString('s3cret', $sealed);
        self::assertSame('s3cret', $v->open($sealed, 'http://trident:9301'));
        self::assertSame('s3cret', $v->open($sealed, 'HTTP://Trident:9301/'), 'case and a trailing slash do not count');
        self::assertNull($v->open($sealed, 'http://attacker:9301'), 'another host');
        self::assertNull($v->open($sealed, 'http://trident:9302'), 'another port');
        self::assertNull($v->open($sealed, 'https://trident:9301'), 'another scheme');
        self::assertNull($v->open($sealed, 'http://trident:9301/other'), 'another path');
        self::assertNull((new TokenVault('rotated'))->open($sealed, 'http://trident:9301'), 'another key');
    }

    public function testTheInfoLabelSeparatesIntegrations(): void
    {
        $sealed = (new TokenVault('app-secret', 'platform-a'))->seal('s3cret', 'http://trident:9301');

        self::assertNull((new TokenVault('app-secret', 'platform-b'))->open($sealed, 'http://trident:9301'));
        self::assertSame('s3cret', (new TokenVault('app-secret', 'platform-a'))->open($sealed, 'http://trident:9301'));
    }

    public function testGarbageNeverOpensAndNeverThrows(): void
    {
        $v = new TokenVault('app-secret');
        foreach (['', 'plain', 'tc1:', 'tc1:%%%', 'tc1:' . base64_encode('short')] as $value) {
            self::assertNull($v->open($value, 'http://trident:9301'), var_export($value, true));
        }
    }

    public function testAnEmptySecretIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TokenVault('');
    }

    /**
     * A token sealed by the Shopware plugin's own TokenVault (before it moved
     * to the library) must still open: stored tokens survive the upgrade.
     */
    public function testATokenSealedByTheOldShopwareCodeOpens(): void
    {
        $kat = json_decode((string) file_get_contents(__DIR__ . '/../../Fixtures/token-vault-kat.json'), true);
        $v = new TokenVault($kat['secret'], $kat['info']);
        self::assertSame($kat['token'], $v->open($kat['sealed'], $kat['url']));
        self::assertNull((new TokenVault($kat['secret']))->open($kat['sealed'], $kat['url']), 'the default label is another key');
    }

    public function testANonceWithATooShortCiphertextIsNull(): void
    {
        $v = new TokenVault('app-secret');
        self::assertNull($v->open('tc1:' . base64_encode(str_repeat('n', 24) . str_repeat('c', 15)), 'http://trident:9301'));
        self::assertNull($v->open('tc1:' . base64_encode(str_repeat('n', 24) . str_repeat('c', 16)), 'http://trident:9301'));
    }

    public function testTheKeyIsNeverDumped(): void
    {
        $v = new TokenVault('app-secret');
        self::assertStringNotContainsString(hash_hkdf('sha256', 'app-secret', 32, TokenVault::DEFAULT_INFO), print_r($v, true));
        self::assertStringContainsString('redacted', print_r($v, true));
        $this->expectException(\LogicException::class);
        serialize($v);
    }

    public function testSecretsAreSensitiveParameters(): void
    {
        foreach ([['__construct', 0], ['seal', 0], ['open', 0]] as [$method, $i]) {
            $p = (new \ReflectionMethod(TokenVault::class, $method))->getParameters()[$i];
            self::assertNotEmpty($p->getAttributes(\SensitiveParameter::class), $method);
        }
    }
}

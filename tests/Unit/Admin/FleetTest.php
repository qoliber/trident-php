<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Admin\Fleet;
use Qoliber\Trident\Client\TridentClient;
use Qoliber\Trident\Delivery\Instance;
use Qoliber\Trident\Testing\FakeTransport;

final class FleetTest extends TestCase
{
    private function fleet(FakeTransport $t): Fleet
    {
        return new Fleet(
            [
                new Instance('edge-1', 'http://edge-1:9301', 't1'),
                new Instance('edge-2', 'http://edge-2:9301', 't2'),
                new Instance('edge-3', 'http://edge-3:9301', 't3'),
            ],
            $t
        );
    }

    public function testEveryInstanceAnswersInConfigurationOrder(): void
    {
        $t = (new FakeTransport())
            ->answer('edge-1', 200, '{"entries":1,"hits":3,"misses":1}')
            ->answer('edge-2', 200, '{"entries":2}')
            ->answer('edge-3', 200, '{"entries":3}');
        $results = $this->fleet($t)->each(static fn (TridentClient $c) => $c->stats()->entries);

        self::assertSame(['edge-1', 'edge-2', 'edge-3'], array_map(static fn ($r) => $r->name(), $results));
        self::assertSame([1, 2, 3], array_map(static fn ($r) => $r->value, $results));
        self::assertTrue($results[0]->isOk());
    }

    public function testEachInstanceUsesItsOwnToken(): void
    {
        $t = new FakeTransport();
        $this->fleet($t)->each(static fn (TridentClient $c) => $c->health());
        self::assertSame(
            ['Bearer t1', 'Bearer t2', 'Bearer t3'],
            array_map(static fn ($r) => $r['headers']['Authorization'], $t->requests)
        );
    }

    public function testADeadInstanceIsReportedAndTheOthersStillAnswer(): void
    {
        $t = (new FakeTransport())
            ->answer('edge-1', 200, '{"entries":1}')
            ->down('edge-2')
            ->answer('edge-3', 404, '{"error":"Reflect mode is not enabled","code":"REFLECT_DISABLED"}');
        $results = $this->fleet($t)->each(static fn (TridentClient $c) => $c->stats()->entries);

        self::assertTrue($results[0]->isOk());
        self::assertFalse($results[1]->isOk());
        self::assertTrue($results[1]->isUnreachable());
        self::assertStringContainsString('Failed to connect', $results[1]->reason());
        self::assertTrue($results[2]->isFeatureDisabled());
        self::assertFalse($results[2]->isUnreachable());
    }

    public function testAnyErrorInOneInstanceStaysInItsResult(): void
    {
        $t = new FakeTransport();
        $results = $this->fleet($t)->each(static function (TridentClient $c) {
            if ($c->instance()->name === 'edge-2') {
                throw new \TypeError('unexpected shape');
            }
            return 'ok';
        });
        self::assertSame(['ok', null, 'ok'], array_map(static fn ($r) => $r->value, $results));
        self::assertStringContainsString('unexpected shape', $results[1]->reason());
    }

    public function testAttemptReturnsNullOnFailure(): void
    {
        self::assertSame(3, Fleet::attempt(static fn () => 3));
        self::assertNull(Fleet::attempt(static function () {
            throw new \RuntimeException('x');
        }));
    }

    public function testOnRunsOnlyTheNamedInstances(): void
    {
        $t = new FakeTransport();
        $results = $this->fleet($t)->on(['edge-3', 'nope'], static fn (TridentClient $c) => $c->warmerRun());
        self::assertCount(1, $results);
        self::assertSame('edge-3', $results[0]->name());
        self::assertSame('http://edge-3:9301/admin/warmer/run', $t->requests[0]['url']);
    }

    public function testOnWithNoNamesMeansAll(): void
    {
        $t = new FakeTransport();
        self::assertCount(3, $this->fleet($t)->on([], static fn (TridentClient $c) => $c->health()));
    }

    public function testClientByName(): void
    {
        $fleet = $this->fleet(new FakeTransport());
        self::assertSame('edge-2', $fleet->client('edge-2')?->instance()->name);
        self::assertNull($fleet->client('missing'));
        self::assertTrue($fleet->has('edge-1'));
        self::assertFalse($fleet->has('missing'));
    }
}

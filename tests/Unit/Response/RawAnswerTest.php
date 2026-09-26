<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Response;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Response\CacheStatsResponse;
use Qoliber\Trident\Response\ClearResponse;
use Qoliber\Trident\Response\PurgeResponse;

/**
 * Every typed response keeps the engine's full answer (1.5.0), so a screen
 * never needs the lower-level Admin\Api for a field no property exists for.
 */
final class RawAnswerTest extends TestCase
{
    /**
     * Every class in src/Response with a static fromArray().
     *
     * @return array<string, array{class-string}>
     */
    public static function responseClasses(): array
    {
        $out = [];
        foreach (glob(dirname(__DIR__, 3) . '/src/Response/*.php') ?: [] as $file) {
            $source = (string) file_get_contents($file);
            if (preg_match_all('/^(?:final |abstract )?class (\w+)/m', $source, $m) === 0) {
                continue;
            }
            foreach ($m[1] as $short) {
                $class = 'Qoliber\\Trident\\Response\\' . $short;
                if (class_exists($class) && method_exists($class, 'fromArray')) {
                    $out[$short] = [$class];
                }
            }
        }
        return $out;
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('responseClasses')]
    public function testFromArrayKeepsTheWholeAnswer(string $class): void
    {
        // Fields no class knows about, next to the ones some classes do.
        $answer = ['engine_field_from_the_future' => ['nested' => 7], 'status' => 'ok', 'purged' => 1, 'mode' => 'hard'];
        /** @var object $response */
        $response = $class::fromArray($answer);

        self::assertTrue(method_exists($response, 'raw'), $class . ' has raw()');
        self::assertSame($answer, $response->raw());
        self::assertSame(7, $response->payload()->int('engine_field_from_the_future.nested'));
    }

    public function testAConstructedResponseHasAnEmptyAnswer(): void
    {
        $r = new PurgeResponse(true, 3);
        self::assertSame([], $r->raw());
        self::assertSame(0, $r->payload()->int('purged'));
    }

    public function testTheTwoArgumentFactoriesKeepTheAnswerToo(): void
    {
        $purge = ['purged' => 4, 'mode' => 'soft', 'state' => 'applied'];
        self::assertSame($purge, PurgeResponse::fromArray($purge, 200)->raw());

        $clear = ['cleared' => true, 'entries_removed' => 9, 'bytes_freed' => 1024];
        self::assertSame($clear, ClearResponse::fromArray($clear, 200)->raw());
    }

    public function testToArrayIsUnchangedByTheRawAnswer(): void
    {
        // toArray() keeps its 1.4 shape; the complete answer is raw().
        $stats = CacheStatsResponse::fromArray(['entries' => 1, 'hits' => 5, 'hit_ratio' => 50.0]);
        self::assertArrayNotHasKey('hits', $stats->toArray());
        self::assertSame(5, $stats->raw()['hits']);
        self::assertSame(5, $stats->hits);
    }
}

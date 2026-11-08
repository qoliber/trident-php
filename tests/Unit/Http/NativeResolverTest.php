<?php

declare(strict_types=1);

namespace Qoliber\Trident\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Qoliber\Trident\Http\NativeResolver;

final class NativeResolverTest extends TestCase
{
    /**
     * Run the actual production resolver/worker in a private process. Controlled
     * native lookup functions replace only the OS wait/answers inside its child.
     * @return array{result: array, worker_pids: list<int>, lookup_calls: string, argv: string, termination_signals: list<int>}
     */
    private function control(string $body, string $lookup = '', string $worker = '', array $settings = []): array
    {
        $directory = sys_get_temp_dir() . '/trident-native-dns-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory, 0700));
        $http = dirname(__DIR__, 3) . '/src/Http/';
        foreach (['NativeResolver.php', 'NativeResolutionException.php', 'NetworkGuard.php'] as $file) {
            self::assertTrue(copy($http . $file, $directory . '/' . $file));
        }
        $originalWorker = file_get_contents($http . 'native-resolver-worker.php');
        if ($settings['first_mismatch'] ?? false) {
            $originalWorker = str_replace("namespace Qoliber\\Trident\\Http;", "namespace Qoliber\\Trident\\Http;\nif(!is_file(__DIR__.'/mismatch')){file_put_contents(__DIR__.'/mismatch','1');exit(66);}", $originalWorker);
        }
        if ($lookup !== '') {
            $originalWorker = str_replace("require __DIR__ . '/NativeResolver.php';", $lookup . "\nrequire __DIR__ . '/NativeResolver.php';", $originalWorker);
        }
        file_put_contents($directory . '/native-resolver-worker.php', $worker !== '' ? $worker : $originalWorker);
        $prefix = $settings['prefix'] ?? '';
        if ($settings['record_termination'] ?? false) {
            $prefix .= 'function proc_terminate($process,int $signal=15):bool {' .
                'file_put_contents(__DIR__."/signals",$signal."\\n",FILE_APPEND);' .
                (($settings['withhold_term'] ?? false) ? 'if($signal===15){return true;}' : '') .
                'return \\proc_terminate($process,$signal);}';
        }
        if ($settings['fixed_cli_fixture'] ?? false) {
            self::assertTrue(mkdir($directory . '/cli', 0700));
            foreach (['php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION, 'php'] as $name) {
                self::assertTrue(symlink(PHP_BINARY, $directory . '/cli/' . $name));
            }
            $prefix = 'const PHP_SAPI="fpm-fcgi";const PHP_BINDIR=' . var_export($directory . '/cli', true) . ';' . $prefix;
        }
        file_put_contents($directory . '/control.php', '<?php declare(strict_types=1); namespace Qoliber\\Trident\\Http; ' . $prefix . '
require __DIR__ . "/NativeResolutionException.php";
require __DIR__ . "/NativeResolver.php";
require __DIR__ . "/NetworkGuard.php";
$result = [];
try { ' . $body . ' } catch (\\Throwable $e) {
    $result = ["class" => get_class($e), "message" => $e->getMessage()];
}
echo json_encode($result, JSON_THROW_ON_ERROR), "\\n";
');
        $argv = [PHP_BINARY];
        if (isset($settings['disable'])) {
            array_push($argv, '-d', 'disable_functions=' . $settings['disable']);
        }
        $argv[] = $directory . '/control.php';
        $process = proc_open($argv, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $stdout = '';
        $stderr = '';
        $status = ['running' => true];
        $output = null;
        try {
            $deadline = microtime(true) + 8;
            do {
                $stdout .= stream_get_contents($pipes[1]);
                $stderr .= stream_get_contents($pipes[2]);
                $status = proc_get_status($process);
                if (!$status['running']) {
                    $stdout .= stream_get_contents($pipes[1]);
                    $stderr .= stream_get_contents($pipes[2]);
                    break;
                }
                usleep(1000);
            } while (microtime(true) < $deadline);
            self::assertFalse($status['running'], 'The contained native control exceeded eight seconds');
            self::assertSame(0, $status['exitcode'], $stderr);
            self::assertSame('', $stderr);
            $workerPids = is_file($directory . '/pids') ? array_map('intval', file($directory . '/pids', FILE_IGNORE_NEW_LINES)) : [];
            foreach ($workerPids as $pid) {
                self::assertGreaterThan(1, $pid);
                self::assertDirectoryDoesNotExist('/proc/' . $pid, 'Native lookup child remained after completion');
            }
            $output = ['result' => json_decode($stdout, true, 16, JSON_THROW_ON_ERROR), 'worker_pids' => $workerPids,
                'lookup_calls' => is_file($directory . '/lookups') ? file_get_contents($directory . '/lookups') : '',
                'argv' => is_file($directory . '/argv') ? file_get_contents($directory . '/argv') : '',
                'termination_signals' => is_file($directory . '/signals') ? array_map('intval', file($directory . '/signals', FILE_IGNORE_NEW_LINES)) : []];
        } finally {
            if ($status['running']) {
                proc_terminate($process, 9);
            }
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            // The bounded control owns this fresh private flat directory only.
            if (is_dir($directory . '/cli')) {
                foreach (glob($directory . '/cli/*') as $link) {
                    self::assertSame(PHP_BINARY, readlink($link));
                    self::assertTrue(unlink($link));
                }
                self::assertTrue(rmdir($directory . '/cli'));
            }
            foreach (glob($directory . '/*') as $file) {
                self::assertFalse(is_link($file));
                self::assertTrue(unlink($file));
            }
            self::assertTrue(rmdir($directory));
        }

        return $output;
    }

    private function lookup(string $statement): string
    {
        return 'file_put_contents(__DIR__."/pids",getmypid()."\\n",FILE_APPEND);' .
            'file_put_contents(__DIR__."/argv",file_get_contents("/proc/self/cmdline"));' .
            'function gethostbynamel(string $host): array {' .
            'file_put_contents(__DIR__."/lookups","A\\n",FILE_APPEND);' . $statement . '}' .
            'function dns_get_record(string $host, int $type): array {' .
            'file_put_contents(__DIR__."/lookups","AAAA\\n",FILE_APPEND);return []; }';
    }

    public function testNativeHostResolutionRetainsSystemAnswersAndCanRepeat(): void
    {
        $resolver = new NativeResolver();
        foreach ([1, 2] as $attempt) {
            self::assertSame(NativeResolver::lookup('localhost'), $resolver->resolve('localhost', hrtime(true) / 1e9 + 2));
        }
    }

    public function testHungNativeLookupIsKilledAndRetryStartsFresh(): void
    {
        $out = $this->control('$r = new NativeResolver(); $start=hrtime(true)/1e9;
try {$r->resolve("private-dns-control.test",$start+0.2);} catch(NativeResolutionException $e){$result["first"]=$e->getMessage();}
$result["seconds"]=hrtime(true)/1e9-$start;
$result["second"]=$r->resolve("private-dns-control.test",hrtime(true)/1e9+1);',
            $this->lookup('if(!is_file(__DIR__."/first")){file_put_contents(__DIR__."/first","1");usleep(2000000);}return ["127.0.0.1"];'));
        self::assertStringContainsString('deadline', $out['result']['first']);
        self::assertLessThan(0.3, $out['result']['seconds']);
        self::assertSame(['127.0.0.1'], $out['result']['second']);
        self::assertCount(2, $out['worker_pids']);
        self::assertStringNotContainsString('private-dns-control.test', $out['argv'], 'Hostname belongs on bounded stdin, not process argv');
    }

    public function testFailedTermEscalatesToRealKillAndReapsLookup(): void
    {
        // Model ineffective TERM at the process boundary; SIGKILL and child
        // retirement remain real, without requiring the optional pcntl module.
        $out = $this->control('$start=hrtime(true)/1e9;try{(new NativeResolver())->resolve("control.test",$start+0.2);}catch(NativeResolutionException $e){$result=["error"=>$e->getMessage(),"seconds"=>hrtime(true)/1e9-$start];}',
            $this->lookup('usleep(2000000);return [];'), '', ['withhold_term' => true, 'record_termination' => true]);
        self::assertStringContainsString('deadline', $out['result']['error']);
        self::assertLessThan(0.3, $out['result']['seconds']);
        self::assertCount(1, $out['worker_pids']);
        self::assertSame([15, 9], $out['termination_signals'], 'An ineffective TERM control must escalate to actual native SIGKILL');
    }

    public function testCallerCancellationPreservesExceptionAndRetiresLookup(): void
    {
        $out = $this->control('(new NativeResolver())->resolve("control.test",hrtime(true)/1e9+3);',
            $this->lookup('usleep(4000000);return [];'), '', [
                'record_termination' => true,
                'prefix' => 'function stream_select(?array &$read,?array &$write,?array &$except,?int $seconds,?int $microseconds=null):int|false {' .
                    'if(is_file(__DIR__."/pids")){throw new \\LogicException("caller-cancelled");}' .
                    'return \\stream_select($read,$write,$except,$seconds,$microseconds);}',
            ]);
        self::assertSame('LogicException', $out['result']['class']);
        self::assertSame('caller-cancelled', $out['result']['message']);
        self::assertCount(1, $out['worker_pids']);
        self::assertContains(15, $out['termination_signals'], 'Caller exception must execute real native worker retirement');
    }

    public function testMalformedOversizedAndFailedWorkersNeverYieldAddresses(): void
    {
        foreach (['<?php echo "invalid";', '<?php echo str_repeat("x",9000);', '<?php exit(65);',
            '<?php echo json_encode(["protocol"=>1,"php"=>PHP_VERSION_ID,"os"=>PHP_OS_FAMILY,"addresses"=>["not-an-ip"]]);'] as $worker) {
            $out = $this->control('(new NativeResolver())->resolve("control.test",hrtime(true)/1e9+1);', '', $worker);
            self::assertSame('Qoliber\\Trident\\Http\\NativeResolutionException', $out['result']['class']);
        }
    }

    public function testMissingProcessCapabilityFailsFastButNumericAndUnlimitedStillWork(): void
    {
        $out = $this->control('$guard = new NetworkGuard();$result["numeric"]=$guard->address("127.0.0.1",hrtime(true)/1e9+1);
$result["unlimited"]=$guard->address("localhost");try{$guard->address("localhost",hrtime(true)/1e9+1);}catch(NativeResolutionException $e){$result["error"]=$e->getMessage();}', '', '', ['disable' => 'proc_open']);
        self::assertSame('127.0.0.1', $out['result']['numeric']);
        self::assertContains($out['result']['unlimited'], ['127.0.0.1', '::1']);
        self::assertStringContainsString('enabled proc_open', $out['result']['error']);
        self::assertSame([], $out['worker_pids']);
    }

    public function testUnavailableFpmCliAndWrongMatchingVersionRefuseBeforeLookup(): void
    {
        $out = $this->control('(new NativeResolver())->resolve("control.test",hrtime(true)/1e9+1);', '', '',
            ['prefix' => 'const PHP_SAPI="fpm-fcgi";const PHP_BINDIR="/nonexistent-trident-cli-control";']);
        self::assertStringContainsString('trusted matching PHP CLI', $out['result']['message']);
        $out = $this->control('(new NativeResolver())->resolve("control.test",hrtime(true)/1e9+1);', $this->lookup('return ["127.0.0.1"];'), '',
            ['prefix' => 'const PHP_VERSION_ID=1;']);
        self::assertStringContainsString('trusted matching PHP CLI', $out['result']['message']);
        self::assertSame('', $out['lookup_calls']);
    }

    public function testFpmPrefersItsVersionedTrustedCliWithoutDependingOnGenericPhp(): void
    {
        $out = $this->control('$result["addresses"]=(new NativeResolver())->resolve("control.test",hrtime(true)/1e9+1);',
            $this->lookup('return ["127.0.0.1"];'), '', ['prefix' => 'const PHP_SAPI="fpm-fcgi";']);
        self::assertSame(['127.0.0.1'], $out['result']['addresses']);
        self::assertCount(1, $out['worker_pids']);
    }

    public function testExitedMismatchBeforeStdinFallsBackWithoutRepeatingDns(): void
    {
        $out = $this->control('$result["addresses"]=(new NativeResolver())->resolve("control.test",hrtime(true)/1e9+1);',
            $this->lookup('return ["127.0.0.1"];'), '', [
                'fixed_cli_fixture' => true, 'first_mismatch' => true,
                'prefix' => 'function fwrite($stream,$data){static $first=true;if($first){$first=false;usleep(50000);}return \\fwrite($stream,$data);}',
            ]);
        self::assertArrayHasKey('addresses', $out['result'], json_encode($out['result']));
        self::assertSame(['127.0.0.1'], $out['result']['addresses']);
        self::assertCount(1, $out['worker_pids'], 'Only the matching worker may enter DNS');
        self::assertSame("A\nAAAA\n", $out['lookup_calls']);
    }

    public function testAnyForbiddenNativeAnswerStillRefusesWholeHost(): void
    {
        $out = $this->control('(new NetworkGuard())->address("control.test",hrtime(true)/1e9+1);',
            $this->lookup('return ["127.0.0.1","169.254.169.254"];'));
        self::assertSame('RuntimeException', $out['result']['class']);
        self::assertStringContainsString('refused', $out['result']['message']);
        self::assertSame("A\nAAAA\n", $out['lookup_calls']);
    }
}

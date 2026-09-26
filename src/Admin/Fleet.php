<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Admin;

use Psr\Log\LoggerInterface;
use Qoliber\Trident\Client\TridentClient;
use Qoliber\Trident\Delivery\Instance;
use Qoliber\Trident\Delivery\Transport;
use Qoliber\Trident\Exception\TridentException;

/**
 * Every configured Trident instance of a site, for an admin screen.
 *
 * A read or an action runs on each instance and comes back as one
 * {@see InstanceResult} per instance, in configuration order. An instance
 * that is down, refuses the token or does not have the feature enabled is
 * reported in its result; it never stops the others and never throws — a
 * dashboard with one dead node still shows the live ones.
 */
final class Fleet
{
    /** @var array<string, TridentClient> */
    private array $clients = [];

    /**
     * @param list<Instance> $instances From {@see \Qoliber\Trident\Delivery\Instances::parse()}.
     */
    public function __construct(
        private readonly array $instances,
        private readonly Transport $transport,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * @return list<Instance>
     */
    public function instances(): array
    {
        return $this->instances;
    }

    public function has(string $name): bool
    {
        return $this->find($name) !== null;
    }

    /**
     * Run a secondary read: its value, or null when it fails — so one failing
     * endpoint blanks one figure on a screen, not the instance's whole section.
     *
     * @template T
     * @param callable(): T $read
     * @return T|null
     */
    public static function attempt(callable $read): mixed
    {
        try {
            return $read();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * The client for one instance by name, or null when none has that name.
     */
    public function client(string $name): ?TridentClient
    {
        $instance = $this->find($name);
        if ($instance === null) {
            return null;
        }
        return $this->clients[$name] ??= TridentClient::forInstance($instance, $this->transport, $this->logger);
    }

    /**
     * Run `$call` on every instance.
     *
     * @template T
     * @param callable(TridentClient, Instance): T $call
     * @return list<InstanceResult<T|null>>
     */
    public function each(callable $call): array
    {
        $results = [];
        foreach ($this->instances as $instance) {
            $results[] = $this->run($instance, $call);
        }
        return $results;
    }

    /**
     * Run `$call` on the named instances only (all when `$names` is empty);
     * unknown names are ignored.
     *
     * @template T
     * @param list<string>                           $names
     * @param callable(TridentClient, Instance): T $call
     * @return list<InstanceResult<T|null>>
     */
    public function on(array $names, callable $call): array
    {
        if ($names === []) {
            return $this->each($call);
        }
        $results = [];
        foreach ($this->instances as $instance) {
            if (in_array($instance->name, $names, true)) {
                $results[] = $this->run($instance, $call);
            }
        }
        return $results;
    }

    /**
     * @template T
     * @param callable(TridentClient, Instance): T $call
     * @return InstanceResult<T|null>
     */
    private function run(Instance $instance, callable $call): InstanceResult
    {
        $client = $this->clients[$instance->name] ??= TridentClient::forInstance($instance, $this->transport, $this->logger);
        try {
            return InstanceResult::ok($instance, $call($client, $instance));
        } catch (TridentException $e) {
            return InstanceResult::failed($instance, $e);
        } catch (\Throwable $e) {
            // An answer this client cannot read (another engine version's
            // shape), or a bad argument: that instance's problem, never the
            // page's — the other instances still show.
            return InstanceResult::failed($instance, new TridentException('Unexpected: ' . $e->getMessage(), 0, $e));
        }
    }

    private function find(string $name): ?Instance
    {
        foreach ($this->instances as $instance) {
            if ($instance->name === $name) {
                return $instance;
            }
        }
        return null;
    }
}

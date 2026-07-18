<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests;

use AndyDefer\LaravelChronos\Providers\LaravelChronosServiceProvider;
use AndyDefer\Repository\RepositoryServiceProvider;
use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class IntegrationTestCase extends Orchestra
{
    protected string $databasePath;

    protected function stripAnsi(string $text): string
    {
        return preg_replace('/\033\[[0-9;]+m/', '', $text);
    }

    protected function getPackageProviders($app): array
    {
        return [
            RepositoryServiceProvider::class,
            LaravelChronosServiceProvider::class,
        ];
    }

    /**
     * Get detailed observers for a model class.
     *
     * @param  class-string  $modelClass
     * @return array<string, array<int, mixed>>
     */
    public function getDetailedObservers(string $modelClass): array
    {
        $events = [
            'creating', 'created', 'updating', 'updated',
            'deleting', 'deleted', 'saving', 'saved',
            'restoring', 'restored',
        ];

        $observers = [];

        foreach ($events as $event) {
            $eventName = 'eloquent.'.$event.': '.$modelClass;
            $listeners = Event::getListeners($eventName);

            if (! empty($listeners)) {
                $observers[$event] = $listeners;
            }
        }

        return $observers;
    }

    /**
     * Assert that a specific observer is registered for a model.
     *
     * @param  class-string  $modelClass
     * @param  class-string  $observerClass
     */
    public function assertHasObserver(
        string $modelClass,
        string $observerClass,
        ?string $message = null
    ): void {
        $observers = $this->getDetailedObservers($modelClass);
        $found = false;

        foreach ($observers as $event => $listeners) {
            foreach ($listeners as $listener) {
                // Extract the listener class from the closure
                $listenerClass = $this->extractListenerClass($listener);

                if ($listenerClass === $observerClass) {
                    $found = true;
                    break 2;
                }
            }
        }

        $message = $message ?? sprintf(
            'Observer [%s] is not registered for model [%s]',
            class_basename($observerClass),
            class_basename($modelClass)
        );

        $this->assertTrue($found, $message);
    }

    /**
     * Assert that a specific observer is NOT registered for a model.
     *
     * @param  class-string  $modelClass
     * @param  class-string  $observerClass
     */
    public function assertNotHasObserver(
        string $modelClass,
        string $observerClass,
        ?string $message = null
    ): void {
        $observers = $this->getDetailedObservers($modelClass);
        $found = false;

        foreach ($observers as $event => $listeners) {
            foreach ($listeners as $listener) {
                $listenerClass = $this->extractListenerClass($listener);

                if ($listenerClass === $observerClass) {
                    $found = true;
                    break 2;
                }
            }
        }

        $message = $message ?? sprintf(
            'Observer [%s] is registered for model [%s] but should not be',
            class_basename($observerClass),
            class_basename($modelClass)
        );

        $this->assertFalse($found, $message);
    }

    /**
     * Assert that a specific observer is registered for a specific event on a model.
     *
     * @param  class-string  $modelClass
     * @param  class-string  $observerClass
     */
    public function assertObserverForEvent(
        string $modelClass,
        string $observerClass,
        string $event,
        ?string $message = null
    ): void {
        $observers = $this->getDetailedObservers($modelClass);
        $found = false;

        if (isset($observers[$event])) {
            foreach ($observers[$event] as $listener) {
                $listenerClass = $this->extractListenerClass($listener);

                if ($listenerClass === $observerClass) {
                    $found = true;
                    break;
                }
            }
        }

        $message = $message ?? sprintf(
            'Observer [%s] is not registered for event [%s] on model [%s]',
            class_basename($observerClass),
            $event,
            class_basename($modelClass)
        );

        $this->assertTrue($found, $message);
    }

    /**
     * Extract the listener class from a closure or array listener.
     *
     * @param  mixed  $listener
     */
    private function extractListenerClass($listener): ?string
    {
        if (is_array($listener) && isset($listener[0])) {
            if (is_object($listener[0])) {
                return get_class($listener[0]);
            }

            return $listener[0];
        }

        if ($listener instanceof \Closure) {
            // Try to extract from the closure's use variables
            $reflection = new \ReflectionFunction($listener);
            $variables = $reflection->getStaticVariables();

            if (isset($variables['listener']) && is_string($variables['listener'])) {
                // Format: "ObserverClass@method"
                return explode('@', $variables['listener'])[0];
            }
        }

        return null;
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // Enable Laravel Context for tests
        $app['config']->set('context.default', 'array');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \Mockery::close();
    }

    protected function runMigrations(): void
    {
        // 1. Charger les migrations des fixtures (modèles de test)
        $fixtureMigrations = __DIR__.'/Fixtures/migrations';
        if (is_dir($fixtureMigrations)) {
            $this->loadMigrationsFrom($fixtureMigrations);
        }

        // 2. Charger les migrations du package laravel-chronos
        $chronosMigrations = __DIR__.'/../database/migrations';
        if (is_dir($chronosMigrations)) {
            $this->loadMigrationsFrom($chronosMigrations);
        }
    }
}

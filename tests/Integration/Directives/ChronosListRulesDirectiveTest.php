<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\LaravelChronos\Directives\ChronosListRulesDirective;
use AndyDefer\LaravelChronos\Tests\IntegrationTestCase;

final class ChronosListRulesDirectiveTest extends IntegrationTestCase
{
    private DirectiveTestingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DirectiveTestingService(
            $this->app,
        );
    }

    protected function tearDown(): void
    {
        $this->service->destroy();
        parent::tearDown();
    }

    // ==================== TESTS: Signature ====================

    public function test_get_signature_returns_correct_string(): void
    {
        $directive = $this->app->make(ChronosListRulesDirective::class);
        $signature = $directive->getSignature();

        $this->assertStringContainsString('chronos:list-rules', $signature);
        $this->assertStringContainsString('::entity->[availability,schedule,impediment]=?', $signature);
        $this->assertStringContainsString('--verbose', $signature);
        $this->assertStringContainsString('--json', $signature);
    }

    public function test_get_description_returns_string(): void
    {
        $directive = $this->app->make(ChronosListRulesDirective::class);
        $description = $directive->getDescription();

        $this->assertIsString($description);
        $this->assertNotEmpty($description);
        $this->assertStringContainsString('validation rules', $description);
        $this->assertStringContainsString('--json', $description);
    }

    public function test_get_aliases_returns_aliases(): void
    {
        $directive = $this->app->make(ChronosListRulesDirective::class);
        $aliases = $directive->getAliases();

        $this->assertTrue($aliases->contains('rules'));
        $this->assertTrue($aliases->contains('lr'));
        $this->assertSame(2, $aliases->count());
    }

    // ==================== TESTS: Basic Execution ====================

    public function test_execute_returns_success(): void
    {
        $response = $this->service->runDirective(ChronosListRulesDirective::class, []);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📋 Laravel Chronos - Validation Rules', $response->output);
        $this->assertStringContainsString('Availability Rules', $response->output);
        $this->assertStringContainsString('Schedule Rules', $response->output);
        $this->assertStringContainsString('Impediment Rules', $response->output);
        $this->assertStringContainsString('Total:', $response->output);
        $this->assertStringContainsString('Generated:', $response->output);
    }

    // ==================== TESTS: Entity Filter ====================

    public function test_execute_with_entity_filter_availability(): void
    {
        $response = $this->service->runDirective(
            ChronosListRulesDirective::class,
            ['availability']
        );

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Availability Rules', $response->output);
        $this->assertStringNotContainsString('Schedule Rules', $response->output);
        $this->assertStringNotContainsString('Impediment Rules', $response->output);
    }

    public function test_execute_with_entity_filter_schedule(): void
    {
        $response = $this->service->runDirective(
            ChronosListRulesDirective::class,
            ['schedule']
        );

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Schedule Rules', $response->output);
        $this->assertStringNotContainsString('Availability Rules', $response->output);
        $this->assertStringNotContainsString('Impediment Rules', $response->output);
    }

    public function test_execute_with_entity_filter_impediment(): void
    {
        $response = $this->service->runDirective(
            ChronosListRulesDirective::class,
            ['impediment']
        );

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Impediment Rules', $response->output);
        $this->assertStringNotContainsString('Availability Rules', $response->output);
        $this->assertStringNotContainsString('Schedule Rules', $response->output);
    }

    public function test_execute_with_invalid_entity_filter_ignores_filter(): void
    {
        $response = $this->service->runDirective(
            ChronosListRulesDirective::class,
            ['invalid']
        );

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Availability Rules', $response->output);
        $this->assertStringContainsString('Schedule Rules', $response->output);
        $this->assertStringContainsString('Impediment Rules', $response->output);
    }

    // ==================== TESTS: Verbose Mode ====================

    public function test_execute_with_verbose_shows_detailed_info(): void
    {
        $response = $this->service->runDirective(
            ChronosListRulesDirective::class,
            ['--verbose']
        );

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Class:', $response->output);
        $this->assertStringContainsString('Methods:', $response->output);
    }

    public function test_execute_with_verbose_and_entity_filter(): void
    {
        $response = $this->service->runDirective(
            ChronosListRulesDirective::class,
            ['availability', '--verbose']
        );

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Availability Rules', $response->output);
        $this->assertStringContainsString('Class:', $response->output);
        $this->assertStringContainsString('Methods:', $response->output);
        $this->assertStringNotContainsString('Schedule Rules', $response->output);
        $this->assertStringNotContainsString('Impediment Rules', $response->output);
    }

    // ==================== TESTS: JSON Output ====================

    public function test_execute_with_json_output(): void
    {
        $response = $this->service->runDirective(
            ChronosListRulesDirective::class,
            ['--json']
        );

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $decoded = json_decode($this->stripAnsi($response->output), true);
        $this->assertIsArray($decoded);
        $this->assertNotEmpty($decoded);
    }

    public function test_execute_with_json_and_entity_filter(): void
    {
        $response = $this->service->runDirective(
            ChronosListRulesDirective::class,
            ['availability', '--json']
        );

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $decoded = json_decode($this->stripAnsi($response->output), true);
        $this->assertIsArray($decoded);
        $this->assertNotEmpty($decoded);
    }

    public function test_execute_with_json_and_verbose(): void
    {
        $response = $this->service->runDirective(
            ChronosListRulesDirective::class,
            ['--verbose', '--json']
        );

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $decoded = json_decode($this->stripAnsi($response->output), true);
        $this->assertIsArray($decoded);
        $this->assertNotEmpty($decoded);
    }

    // ==================== TESTS: List ====================

    public function test_list_option_shows_signature(): void
    {
        $response = $this->service->runSignature('--list');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('chronos:list-rules', $this->stripAnsi($response->output));
    }
}

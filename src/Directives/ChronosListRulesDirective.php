<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Directives;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\MapCollection;
use AndyDefer\LaravelChronos\Contracts\Validation\ValidatorInterface;
use AndyDefer\LaravelChronos\Enums\EntityType;

final class ChronosListRulesDirective extends AbstractDirective
{
    private Console $console;

    public function getSignature(): string
    {
        return 'chronos:list-rules 
                    ::entity->[availability,schedule,impediment]=?#"Filter by entity type" 
                    {--verbose}#"Show detailed information including context data" 
                    {--json}#"Output as JSON"';
    }

    public function getDescription(): string
    {
        return 'List all registered validation rules grouped by entity type. Use --json for machine-readable output.';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['rules', 'lr']);
    }

    protected function execute(): ExitCode
    {
        $app = $this->getApplication();

        if ($app === null) {
            $this->error('Laravel container is not available');

            return ExitCode::RUNTIME_ERROR;
        }

        $this->console = $app->make(Console::class);

        $isJson = $this->isFlagActive('json');
        $isVerbose = $this->isFlagActive('verbose');

        /** @var ValidatorInterface $validator */
        $validator = $app->make(ValidatorInterface::class);

        $entityFilter = $this->getEntityFilter();

        $rulesData = $this->buildRulesData($validator, $entityFilter, $isVerbose);

        if ($isJson) {
            return $this->renderJson($rulesData);
        }

        return $this->renderText($rulesData, $isVerbose);
    }

    private function getEntityFilter(): ?EntityType
    {
        $entityRaw = $this->getArgument('entity');

        if ($entityRaw === null) {
            return null;
        }

        return EntityType::tryFrom($entityRaw);
    }

    private function buildRulesData(ValidatorInterface $validator, ?EntityType $entityFilter, bool $isVerbose): MapCollection
    {
        $data = [];

        $entities = $entityFilter !== null
            ? [$entityFilter]
            : EntityType::cases();

        foreach ($entities as $entityType) {
            $rules = $validator->getRulesForEntity($entityType);

            if (empty($rules)) {
                continue;
            }

            $data[$entityType->value] = $this->buildEntityRulesData($rules, $entityType, $isVerbose);
        }

        $totalRules = 0;
        foreach ($data as $entityData) {
            if ($entityData instanceof MapCollection && $entityData->hasKey('total')) {
                $totalRules += $entityData->get('total');
            }
        }

        $data['_meta'] = MapCollection::from([
            'total_entities' => count($data),
            'total_rules' => $totalRules,
            'generated_at' => date('Y-m-d H:i:s'),
        ]);

        return MapCollection::from($data);
    }

    private function buildEntityRulesData(array $rules, EntityType $entityType, bool $isVerbose): MapCollection
    {
        $rulesList = [];
        $index = 1;

        foreach ($rules as $rule) {
            $ruleClass = get_class($rule);
            $shortName = $this->extractShortName($ruleClass);

            $ruleData = [
                'index' => $index,
                'name' => $shortName,
                'class' => $ruleClass,
                'description' => $rule->getDescription(),
            ];

            if ($isVerbose) {
                $ruleData['methods'] = $this->getRuleMethods($rule);
            }

            $rulesList[] = MapCollection::from($ruleData);
            $index++;
        }

        return MapCollection::from([
            'label' => $entityType->getLabel(),
            'icon' => $this->getEntityIcon($entityType),
            'total' => count($rules),
            'rules' => MapCollection::from($rulesList),
        ]);
    }

    private function getRuleMethods(object $rule): array
    {
        $methods = get_class_methods($rule);
        $ownMethods = array_filter($methods, function ($method) use ($rule) {
            $reflection = new \ReflectionMethod($rule, $method);

            return $reflection->getDeclaringClass()->getName() === get_class($rule);
        });

        return array_values($ownMethods);
    }

    private function extractShortName(string $className): string
    {
        $parts = explode('\\', $className);
        $last = end($parts);

        return preg_replace('/Rule$/', '', $last) ?: $last;
    }

    private function getEntityIcon(EntityType $entityType): string
    {
        return match ($entityType) {
            EntityType::AVAILABILITY => '📅',
            EntityType::SCHEDULE => '📋',
            EntityType::IMPEDIMENT => '🚫',
        };
    }

    // ============================================================
    // RENDERERS
    // ============================================================

    private function renderText(MapCollection $rulesData, bool $isVerbose): ExitCode
    {
        $this->console->title('📋 Laravel Chronos - Validation Rules');

        $meta = $rulesData->get('_meta');
        if ($meta instanceof MapCollection) {
            $totalEntities = $meta->get('total_entities');
            $totalRules = $meta->get('total_rules');
            $generatedAt = $meta->get('generated_at');

            $this->console->line(sprintf(
                '  📊 Total: %d rules across %d entity types',
                $totalRules ?? 0,
                $totalEntities ?? 0
            ));
            $this->console->line(sprintf('  🕐 Generated: %s', $generatedAt ?? ''));
            $this->console->line();
        }

        foreach ($rulesData as $key => $entityData) {
            if ($key === '_meta') {
                continue;
            }

            if (! $entityData instanceof MapCollection) {
                continue;
            }

            $this->renderEntityRules($entityData, $isVerbose);
        }

        $this->console->line();
        $this->console->info('💡 Tip: Use --verbose for more details, --json for machine-readable output');
        $this->console->line();

        return ExitCode::SUCCESS;
    }

    private function renderEntityRules(MapCollection $entityData, bool $isVerbose): void
    {
        $icon = $entityData->get('icon') ?? '📦';
        $label = $entityData->get('label') ?? 'Unknown';
        $total = $entityData->get('total') ?? 0;
        $rules = $entityData->get('rules');

        $this->console->line(sprintf('%s %s Rules (%d)', $icon, $label, $total));
        $this->console->line();

        if (! $rules instanceof MapCollection) {
            $this->console->line('  No rules registered');
            $this->console->line();

            return;
        }

        foreach ($rules as $ruleData) {
            if (! $ruleData instanceof MapCollection) {
                continue;
            }

            $this->renderSingleRule($ruleData, $isVerbose);
        }

        $this->console->line();
    }

    private function renderSingleRule(MapCollection $ruleData, bool $isVerbose): void
    {
        $index = $ruleData->get('index') ?? '?';
        $name = $ruleData->get('name') ?? 'Unnamed';
        $description = $ruleData->get('description') ?? 'No description';

        $this->console->line(sprintf('  %2d. %s', $index, $name));

        if ($isVerbose) {
            $class = $ruleData->get('class') ?? 'Unknown';
            $this->console->line(sprintf('      Class: %s', $class));

            $methods = $ruleData->get('methods');
            if (! empty($methods)) {
                $this->console->line(sprintf('      Methods: %s', implode(', ', $methods)));
            }
        }

        $this->console->line(sprintf('      📝 %s', $description));
        $this->console->line();
    }

    private function renderJson(MapCollection $rulesData): ExitCode
    {
        $data = $this->mapCollectionToArray($rulesData);
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            $this->console->error('Failed to encode JSON');

            return ExitCode::RUNTIME_ERROR;
        }

        $this->console->raw($json);
        $this->console->newLine();

        return ExitCode::SUCCESS;
    }

    private function mapCollectionToArray(mixed $data): mixed
    {
        if ($data instanceof MapCollection) {
            $result = [];
            foreach ($data as $key => $value) {
                $result[$key] = $this->mapCollectionToArray($value);
            }

            return $result;
        }

        if (is_array($data)) {
            return array_map([$this, 'mapCollectionToArray'], $data);
        }

        return $data;
    }
}

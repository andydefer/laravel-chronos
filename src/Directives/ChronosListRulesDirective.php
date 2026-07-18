<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Directives;

use AndyDefer\ConsoleWriter\Console\Components\KeyValue;
use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\MapCollection;
use AndyDefer\LaravelChronos\Contracts\Validation\ValidatorInterface;
use AndyDefer\LaravelChronos\Enums\EntityType;
use ReflectionMethod;

/**
 * Console command to display all registered validation rules grouped by entity type.
 *
 * This directive provides visibility into the validation rules configured for
 * different entity types (Availability, Schedule, Impediment). It supports
 * multiple output formats including human-readable tables, JSON, and raw JSON.
 *
 * @example
 * // Display all rules in a formatted table
 * $ php artisan chronos:list-rules
 *
 * // Show detailed information including class methods
 * $ php artisan chronos:list-rules --verbose
 *
 * // Filter rules for a specific entity type
 * $ php artisan chronos:list-rules availability
 *
 * // Get machine-readable output
 * $ php artisan chronos:list-rules --json
 */
final class ChronosListRulesDirective extends AbstractDirective
{
    private Console $console;

    /**
     * Returns the command signature for the console.
     *
     * Defines the command name, arguments, and available options.
     *
     * @return string The command signature string
     */
    public function getSignature(): string
    {
        return 'chronos:list-rules 
                    ::entity->[availability,schedule,impediment]=?#"Filter by entity type" 
                    {--verbose}#"Show detailed information including context data" 
                    {--json}#"Output as JSON"
                    {--raw}#"Output as raw JSON"';
    }

    /**
     * Returns the command description.
     *
     * @return string The command description
     */
    public function getDescription(): string
    {
        return 'List all registered validation rules grouped by entity type. Use --json for machine-readable output.';
    }

    /**
     * Returns the command aliases.
     *
     * @return StringTypedCollection Collection of alias names
     */
    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['rules', 'lr']);
    }

    /**
     * Executes the command logic.
     *
     * Retrieves validation rules from the validator service and displays them
     * in the requested format (text, JSON, or raw JSON).
     *
     * @return ExitCode The exit code indicating success or failure
     */
    protected function execute(): ExitCode
    {
        $application = $this->getApplication();

        if ($application === null) {
            $this->error('Laravel container is not available');

            return ExitCode::RUNTIME_ERROR;
        }

        $this->console = $application->make(Console::class);
        $isJsonOutput = $this->isFlagActive('json');
        $isVerbose = $this->isFlagActive('verbose');

        /** @var ValidatorInterface $validator */
        $validator = $application->make(ValidatorInterface::class);

        $entityFilter = $this->resolveEntityFilter();
        $rulesData = $this->buildRulesData($validator, $entityFilter, $isVerbose);

        if ($isJsonOutput) {
            return $this->renderJsonOutput($rulesData);
        }

        return $this->renderTextOutput($rulesData, $isVerbose);
    }

    /**
     * Resolves the entity filter from the command argument.
     *
     * @return EntityType|null The filtered entity type or null if no filter applied
     */
    private function resolveEntityFilter(): ?EntityType
    {
        $entityRaw = $this->getArgument('entity');

        if ($entityRaw === null) {
            return null;
        }

        return EntityType::tryFrom($entityRaw);
    }

    /**
     * Builds the complete rules data structure.
     *
     * @param  ValidatorInterface  $validator  The validator service
     * @param  EntityType|null  $entityFilter  Optional entity type filter
     * @param  bool  $isVerbose  Whether to include detailed information
     * @return MapCollection The structured rules data
     */
    private function buildRulesData(
        ValidatorInterface $validator,
        ?EntityType $entityFilter,
        bool $isVerbose
    ): MapCollection {
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

        $totalRules = $this->calculateTotalRules($data);

        $data['_meta'] = MapCollection::from([
            'total_entities' => count($data),
            'total_rules' => $totalRules,
            'generated_at' => date('Y-m-d H:i:s'),
        ]);

        return MapCollection::from($data);
    }

    /**
     * Calculates the total number of rules across all entities.
     *
     * @param  array  $data  The collected rules data
     * @return int Total number of rules
     */
    private function calculateTotalRules(array $data): int
    {
        $total = 0;

        foreach ($data as $entityData) {
            if ($entityData instanceof MapCollection && $entityData->hasKey('total')) {
                $total += $entityData->get('total');
            }
        }

        return $total;
    }

    /**
     * Builds the data structure for a single entity type.
     *
     * @param  array  $rules  The validation rules for the entity
     * @param  EntityType  $entityType  The entity type
     * @param  bool  $isVerbose  Whether to include detailed information
     * @return MapCollection The structured entity rules data
     */
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
                $ruleData['methods'] = $this->extractOwnMethods($rule);
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

    /**
     * Extracts methods defined only in the rule class itself (not inherited).
     *
     * @param  object  $rule  The rule instance
     * @return array List of method names
     */
    private function extractOwnMethods(object $rule): array
    {
        $methods = get_class_methods($rule);

        $ownMethods = array_filter($methods, function ($method) use ($rule) {
            $reflection = new ReflectionMethod($rule, $method);

            return $reflection->getDeclaringClass()->getName() === get_class($rule);
        });

        return array_values($ownMethods);
    }

    /**
     * Extracts the short name from a fully qualified class name.
     *
     * Removes the "Rule" suffix from the class name for cleaner display.
     *
     * @param  string  $className  The fully qualified class name
     * @return string The short name without "Rule" suffix
     */
    private function extractShortName(string $className): string
    {
        $parts = explode('\\', $className);
        $shortName = end($parts);

        return preg_replace('/Rule$/', '', $shortName) ?: $shortName;
    }

    /**
     * Returns the appropriate icon for an entity type.
     *
     * @param  EntityType  $entityType  The entity type
     * @return string The emoji icon
     */
    private function getEntityIcon(EntityType $entityType): string
    {
        return match ($entityType) {
            EntityType::AVAILABILITY => '📅',
            EntityType::SCHEDULE => '📋',
            EntityType::IMPEDIMENT => '🚫',
        };
    }

    /**
     * Renders the rules data as formatted text output.
     *
     * @param  MapCollection  $rulesData  The rules data
     * @param  bool  $isVerbose  Whether to show detailed information
     * @return ExitCode The exit code
     */
    private function renderTextOutput(MapCollection $rulesData, bool $isVerbose): ExitCode
    {
        $this->console->title('📋 Laravel Chronos - Validation Rules');

        $this->renderMetadata($rulesData);
        $this->renderEntitySections($rulesData, $isVerbose);

        $this->console->line();
        $this->console->info('💡 Tip: Use --verbose for more details, --json for machine-readable output');
        $this->console->line();

        return ExitCode::SUCCESS;
    }

    /**
     * Renders the metadata section of the output.
     *
     * @param  MapCollection  $rulesData  The rules data containing metadata
     */
    private function renderMetadata(MapCollection $rulesData): void
    {
        $meta = $rulesData->get('_meta');

        if (! $meta instanceof MapCollection) {
            return;
        }

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

    /**
     * Renders the entity sections of the output.
     *
     * @param  MapCollection  $rulesData  The rules data
     * @param  bool  $isVerbose  Whether to show detailed information
     */
    private function renderEntitySections(MapCollection $rulesData, bool $isVerbose): void
    {
        foreach ($rulesData as $key => $entityData) {
            if ($key === '_meta' || ! $entityData instanceof MapCollection) {
                continue;
            }

            $this->renderEntitySection($entityData, $isVerbose);
        }
    }

    /**
     * Renders a single entity section with its rules.
     *
     * @param  MapCollection  $entityData  The entity data
     * @param  bool  $isVerbose  Whether to show detailed information
     */
    private function renderEntitySection(MapCollection $entityData, bool $isVerbose): void
    {
        $icon = $entityData->get('icon') ?? '📦';
        $label = $entityData->get('label') ?? 'Unknown';
        $total = $entityData->get('total') ?? 0;
        $rules = $entityData->get('rules');

        $this->console->line(sprintf('%s %s Rules (%d)', $icon, $label, $total));
        $this->console->line();

        if (! $rules instanceof MapCollection || $rules->isEmpty()) {
            $this->console->line('  No rules registered');
            $this->console->line();

            return;
        }

        $keyValueData = $this->buildKeyValueData($rules, $isVerbose);

        if (! $keyValueData->isEmpty()) {
            $this->console->raw(KeyValue::renderWithValueColor($keyValueData, 'green'));
        }

        $this->console->line();
    }

    /**
     * Builds the key-value data structure for rendering rules.
     *
     * @param  MapCollection  $rules  The collection of rules
     * @param  bool  $isVerbose  Whether to include detailed information
     * @return MapCollection The key-value data
     */
    private function buildKeyValueData(MapCollection $rules, bool $isVerbose): MapCollection
    {
        $keyValueData = MapCollection::from([]);

        foreach ($rules as $ruleData) {
            if (! $ruleData instanceof MapCollection) {
                continue;
            }

            $index = $ruleData->get('index') ?? '?';
            $name = $ruleData->get('name') ?? 'Unnamed';
            $description = $ruleData->get('description') ?? 'No description';

            $label = sprintf('%2d. %s', $index, $name);
            $value = $this->buildRuleValue($ruleData, $description, $isVerbose);

            $keyValueData = $keyValueData->put($label, $value);
        }

        return $keyValueData;
    }

    /**
     * Builds the value string for a single rule.
     *
     * @param  MapCollection  $ruleData  The rule data
     * @param  string  $description  The rule description
     * @param  bool  $isVerbose  Whether to include detailed information
     * @return string The formatted value string
     */
    private function buildRuleValue(MapCollection $ruleData, string $description, bool $isVerbose): string
    {
        if (! $isVerbose) {
            return $description;
        }

        $class = $ruleData->get('class') ?? 'Unknown';
        $methods = $ruleData->get('methods');

        $methodsText = ! empty($methods)
            ? ' | Methods: '.implode(', ', $methods)
            : '';

        return sprintf('%s | Class: %s%s', $description, $class, $methodsText);
    }

    /**
     * Renders the rules data as JSON output.
     *
     * @param  MapCollection  $rulesData  The rules data
     * @return ExitCode The exit code
     */
    private function renderJsonOutput(MapCollection $rulesData): ExitCode
    {
        $data = $this->convertMapCollectionToArray($rulesData);
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            $this->console->error('Failed to encode JSON');

            return ExitCode::RUNTIME_ERROR;
        }

        $isRaw = $this->getArgument('raw');

        if ($isRaw) {
            $this->console->jsonRaw($json);
        } else {
            $this->console->json($json);
        }

        $this->console->newLine();

        return ExitCode::SUCCESS;
    }

    /**
     * Recursively converts a MapCollection to a native PHP array.
     *
     * @param  mixed  $data  The data to convert
     * @return mixed The converted data
     */
    private function convertMapCollectionToArray(mixed $data): mixed
    {
        if ($data instanceof MapCollection) {
            $result = [];

            foreach ($data as $key => $value) {
                $result[$key] = $this->convertMapCollectionToArray($value);
            }

            return $result;
        }

        if (is_array($data)) {
            return array_map([$this, 'convertMapCollectionToArray'], $data);
        }

        return $data;
    }
}

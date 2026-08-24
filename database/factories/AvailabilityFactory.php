<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Database\Factories;

use AndyDefer\LaravelChronos\Models\Availability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for creating Availability model instances.
 *
 * @extends Factory<Availability>
 *
 * @author Andy Defer
 * @license MIT
 */
final class AvailabilityFactory extends Factory
{
    /**
     * The model class associated with this factory.
     *
     * @var class-string<Availability>
     */
    protected $model = Availability::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'type' => 'default',
            'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'daily_start' => '09:00:00',
            'daily_end' => '17:00:00',
            'validity_start' => '2024-01-01T00:00:00Z',
            'validity_end' => '2024-12-31T23:59:59Z',
            'schedulable_type' => null,
            'schedulable_id' => null,
        ];
    }

    /**
     * Set the name of the availability.
     */
    public function withName(string $name): self
    {
        return $this->state(['name' => $name]);
    }

    /**
     * Set the type of the availability.
     */
    public function withType(string $type): self
    {
        return $this->state(['type' => $type]);
    }

    /**
     * Set the days of the week.
     *
     * @param  array<string>  $days
     */
    public function withDays(array $days): self
    {
        return $this->state(['days' => $days]);
    }

    /**
     * Set the daily start time.
     */
    public function withDailyStart(string $dailyStart): self
    {
        return $this->state(['daily_start' => $dailyStart]);
    }

    /**
     * Set the daily end time.
     */
    public function withDailyEnd(string $dailyEnd): self
    {
        return $this->state(['daily_end' => $dailyEnd]);
    }

    /**
     * Set the validity start date.
     */
    public function withValidityStart(string $validityStart): self
    {
        return $this->state(['validity_start' => $validityStart]);
    }

    /**
     * Set the validity end date.
     */
    public function withValidityEnd(string $validityEnd): self
    {
        return $this->state(['validity_end' => $validityEnd]);
    }

    /**
     * Set the schedulable entity.
     *
     * @param  object|string  $schedulable  The entity or its class name
     * @param  int|null  $id  The entity ID
     */
    public function withSchedulable(object|string $schedulable, ?int $id = null): self
    {
        if (is_object($schedulable)) {
            return $this->state([
                'schedulable_type' => get_class($schedulable),
                'schedulable_id' => $schedulable->id ?? $id,
            ]);
        }

        return $this->state([
            'schedulable_type' => $schedulable,
            'schedulable_id' => $id,
        ]);
    }

    /**
     * Create a cross-day availability (start > end).
     */
    public function crossDay(): self
    {
        return $this->state([
            'daily_start' => '22:00:00',
            'daily_end' => '02:00:00',
        ]);
    }

    /**
     * Create an availability that starts today.
     */
    public function startsToday(): self
    {
        return $this->state([
            'validity_start' => now()->startOfDay()->toIso8601ZuluString(),
        ]);
    }

    /**
     * Create an availability that ends today.
     */
    public function endsToday(): self
    {
        return $this->state([
            'validity_end' => now()->endOfDay()->toIso8601ZuluString(),
        ]);
    }

    /**
     * Create an availability that is only on weekends.
     */
    public function weekends(): self
    {
        return $this->state([
            'days' => ['saturday', 'sunday'],
        ]);
    }

    /**
     * Create an availability that is only on weekdays.
     */
    public function weekdays(): self
    {
        return $this->state([
            'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        ]);
    }

    /**
     * Create a morning availability.
     */
    public function morning(): self
    {
        return $this->state([
            'name' => 'Consultations matin',
            'daily_start' => '09:00:00',
            'daily_end' => '12:00:00',
        ]);
    }

    /**
     * Create an afternoon availability.
     */
    public function afternoon(): self
    {
        return $this->state([
            'name' => 'Consultations après-midi',
            'daily_start' => '14:00:00',
            'daily_end' => '18:00:00',
        ]);
    }

    /**
     * Create an evening availability.
     */
    public function evening(): self
    {
        return $this->state([
            'name' => 'Consultations soir',
            'daily_start' => '19:00:00',
            'daily_end' => '21:00:00',
        ]);
    }
}

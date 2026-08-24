<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Database\Factories;

use AndyDefer\LaravelChronos\Enums\ScheduleStatus;
use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for creating Schedule model instances.
 *
 * @extends Factory<Schedule>
 *
 * @author Andy Defer
 * @license MIT
 */
final class ScheduleFactory extends Factory
{
    /**
     * The model class associated with this factory.
     *
     * @var class-string<Schedule>
     */
    protected $model = Schedule::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('now', '+1 week');
        $end = (clone $start)->modify('+30 minutes');

        return [
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'start_datetime' => $start->format('Y-m-d H:i:s'),
            'end_datetime' => $end->format('Y-m-d H:i:s'),
            'status' => ScheduleStatus::BOOKED,
            'metadata' => [],
            'availability_id' => null,
            'schedulable_type' => null,
            'schedulable_id' => null,
        ];
    }

    /**
     * Set the title of the schedule.
     */
    public function withTitle(string $title): self
    {
        return $this->state(['title' => $title]);
    }

    /**
     * Set the description of the schedule.
     */
    public function withDescription(string $description): self
    {
        return $this->state(['description' => $description]);
    }

    /**
     * Set the start datetime.
     */
    public function withStartDatetime(string $startDatetime): self
    {
        return $this->state(['start_datetime' => $startDatetime]);
    }

    /**
     * Set the end datetime.
     */
    public function withEndDatetime(string $endDatetime): self
    {
        return $this->state(['end_datetime' => $endDatetime]);
    }

    /**
     * Set the status of the schedule.
     */
    public function withStatus(ScheduleStatus $status): self
    {
        return $this->state(['status' => $status]);
    }

    /**
     * Set the availability for this schedule.
     */
    public function withAvailability(Availability $availability): self
    {
        return $this->state(['availability_id' => $availability->id]);
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
     * Set the metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return $this->state(['metadata' => $metadata]);
    }

    /**
     * Create a booked schedule.
     */
    public function booked(): self
    {
        return $this->state(['status' => ScheduleStatus::BOOKED]);
    }

    /**
     * Create a cancelled schedule.
     */
    public function cancelled(): self
    {
        return $this->state(['status' => ScheduleStatus::CANCELLED]);
    }

    /**
     * Create a completed schedule.
     */
    public function completed(): self
    {
        return $this->state(['status' => ScheduleStatus::COMPLETED]);
    }

    /**
     * Create an available schedule.
     */
    public function available(): self
    {
        return $this->state(['status' => ScheduleStatus::AVAILABLE]);
    }

    /**
     * Create a schedule that starts today.
     */
    public function startsToday(): self
    {
        $start = now()->addHours(1)->startOfHour();

        return $this->state([
            'start_datetime' => $start->format('Y-m-d H:i:s'),
            'end_datetime' => $start->addMinutes(30)->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Create a schedule that is in the past.
     */
    public function past(): self
    {
        $start = $this->faker->dateTimeBetween('-1 month', '-1 day');

        return $this->state([
            'start_datetime' => $start->format('Y-m-d H:i:s'),
            'end_datetime' => (clone $start)->modify('+30 minutes')->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Create a schedule that is in the future.
     */
    public function future(): self
    {
        $start = $this->faker->dateTimeBetween('+1 day', '+1 month');

        return $this->state([
            'start_datetime' => $start->format('Y-m-d H:i:s'),
            'end_datetime' => (clone $start)->modify('+30 minutes')->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Create a cross-day schedule.
     */
    public function crossDay(): self
    {
        $start = $this->faker->dateTimeBetween('now', '+1 week');
        $end = (clone $start)->modify('+12 hours');

        return $this->state([
            'start_datetime' => $start->format('Y-m-d H:i:s'),
            'end_datetime' => $end->format('Y-m-d H:i:s'),
        ]);
    }
}

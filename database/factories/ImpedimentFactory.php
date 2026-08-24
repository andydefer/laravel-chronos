<?php

declare(strict_types=1);

namespace AndyDefer\LaravelChronos\Database\Factories;

use AndyDefer\LaravelChronos\Models\Availability;
use AndyDefer\LaravelChronos\Models\Impediment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for creating Impediment model instances.
 *
 * @extends Factory<Impediment>
 *
 * @author Andy Defer
 * @license MIT
 */
final class ImpedimentFactory extends Factory
{
    /**
     * The model class associated with this factory.
     *
     * @var class-string<Impediment>
     */
    protected $model = Impediment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('now', '+1 week');
        $end = (clone $start)->modify('+2 hours');

        return [
            'reason' => $this->faker->sentence(3),
            'start_datetime' => $start->format('Y-m-d H:i:s'),
            'end_datetime' => $end->format('Y-m-d H:i:s'),
            'metadata' => [],
            'availability_id' => null,
        ];
    }

    /**
     * Set the reason for the impediment.
     */
    public function withReason(string $reason): self
    {
        return $this->state(['reason' => $reason]);
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
     * Set the availability for this impediment.
     */
    public function withAvailability(Availability $availability): self
    {
        return $this->state(['availability_id' => $availability->id]);
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
     * Create an impediment that is currently active.
     */
    public function active(): self
    {
        $start = now()->subMinutes(30);
        $end = now()->addMinutes(30);

        return $this->state([
            'start_datetime' => $start->format('Y-m-d H:i:s'),
            'end_datetime' => $end->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Create an impediment that is in the past.
     */
    public function past(): self
    {
        $start = $this->faker->dateTimeBetween('-1 month', '-1 day');
        $end = (clone $start)->modify('+2 hours');

        return $this->state([
            'start_datetime' => $start->format('Y-m-d H:i:s'),
            'end_datetime' => $end->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Create an impediment that is in the future.
     */
    public function future(): self
    {
        $start = $this->faker->dateTimeBetween('+1 day', '+1 month');
        $end = (clone $start)->modify('+2 hours');

        return $this->state([
            'start_datetime' => $start->format('Y-m-d H:i:s'),
            'end_datetime' => $end->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Create an impediment that is a training.
     */
    public function training(): self
    {
        return $this->state([
            'reason' => 'Formation médicale obligatoire',
        ]);
    }

    /**
     * Create an impediment that is a holiday.
     */
    public function holiday(): self
    {
        return $this->state([
            'reason' => 'Congés payés',
        ]);
    }

    /**
     * Create an impediment that is a sick leave.
     */
    public function sickLeave(): self
    {
        return $this->state([
            'reason' => 'Arrêt maladie',
        ]);
    }

    /**
     * Create a cross-day impediment.
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

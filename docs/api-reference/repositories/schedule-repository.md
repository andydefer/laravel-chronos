# ScheduleRepository - Référence Technique

## Description

Repository pour la gestion des entités `Schedule` (plannings) dans la base de données. Fournit des méthodes spécialisées pour rechercher des plannings selon différents critères : statut, plages horaires, chevauchements, et analyse des violations de règles métier (buffer time, durée excessive, etc.).

## Hiérarchie

```
AbstractChronosRepository
    └── ScheduleRepository
        └── ScheduleRepositoryInterface
```

## Rôle principal

Gérer l'accès aux données des plannings qui représentent les événements ou rendez-vous programmés. Le repository fournit des méthodes pour la recherche avancée, la détection de conflits, l'analyse des plages horaires, et l'identification des violations de règles métier.

---

## API

### `findByAvailability(int $availabilityId): Collection`

Retourne tous les plannings associés à une disponibilité.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$availabilityId` | `int` | ID de la disponibilité |

**Retourne :** `Collection<int, Schedule>` - Collection de plannings

**Exemple :**
```php
$schedules = $repository->findByAvailability(42);
```

---

### `findOverlapping(int $availabilityId, DateTimeZuluVO $start, DateTimeZuluVO $end, ?int $excludeId = null): Collection`

Trouve les plannings qui chevauchent une plage horaire.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$availabilityId` | `int` | ID de la disponibilité |
| `$start` | `DateTimeZuluVO` | Début de la plage |
| `$end` | `DateTimeZuluVO` | Fin de la plage |
| `$excludeId` | `int|null` | ID à exclure |

**Retourne :** `Collection<int, Schedule>` - Plannings en conflit

**Exemple :**
```php
$overlapping = $repository->findOverlapping(
    42,
    DateTimeZuluVO::from('2024-01-15T10:00:00Z'),
    DateTimeZuluVO::from('2024-01-15T12:00:00Z')
);
```

---

### `findByStatus(ScheduleStatus $status, ?int $availabilityId = null): Collection`

Trouve les plannings par statut.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$status` | `ScheduleStatus` | Statut (BOOKED, AVAILABLE, CANCELLED, COMPLETED) |
| `$availabilityId` | `int|null` | Filtre par disponibilité |

**Retourne :** `Collection<int, Schedule>` - Plannings avec le statut

**Exemple :**
```php
$booked = $repository->findByStatus(ScheduleStatus::BOOKED);
$cancelled = $repository->findByStatus(ScheduleStatus::CANCELLED, 42);
```

---

### `searchByTitle(string $search, ?int $availabilityId = null): Collection`

Recherche des plannings par titre.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$search` | `string` | Terme de recherche |
| `$availabilityId` | `int|null` | Filtre par disponibilité |

**Retourne :** `Collection<int, Schedule>` - Plannings correspondants

**Exemple :**
```php
$results = $repository->searchByTitle('Réunion');
```

---

### `findByDate(DateTimeZuluVO $date, ?int $availabilityId = null): Collection`

Trouve les plannings pour une date spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$date` | `DateTimeZuluVO` | Date à rechercher |
| `$availabilityId` | `int|null` | Filtre par disponibilité |

**Retourne :** `Collection<int, Schedule>` - Plannings pour la date

---

### `findInDateRange(DateTimeZuluVO $start, DateTimeZuluVO $end, ?int $availabilityId = null): Collection`

Trouve les plannings dans une plage de dates.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$start` | `DateTimeZuluVO` | Début de la plage |
| `$end` | `DateTimeZuluVO` | Fin de la plage |
| `$availabilityId` | `int|null` | Filtre par disponibilité |

**Retourne :** `Collection<int, Schedule>` - Plannings dans la plage

---

### `findByDayOfWeek(int $dayOfWeek, ?int $availabilityId = null): Collection`

Trouve les plannings par jour de la semaine.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$dayOfWeek` | `int` | Jour de la semaine (0=dimanche, 1=lundi, etc.) |
| `$availabilityId` | `int|null` | Filtre par disponibilité |

**Retourne :** `Collection<int, Schedule>` - Plannings pour ce jour

**Exemple :**
```php
$mondaySchedules = $repository->findByDayOfWeek(1);
// 1 = Monday (en SQLite)
```

---

### `findBySchedulable(Model $schedulable): Collection`

Trouve les plannings pour une entité planifiable.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$schedulable` | `Model` | Entité planifiable (ex: `User::find(42)`) |

**Retourne :** `Collection<int, Schedule>` - Plannings pour l'entité

**Exemple :**
```php
$user = User::find(42);
$schedules = $repository->findBySchedulable($user);
```

---

### `findWithInvalidChronology(): Collection`

Trouve les plannings avec une chronologie invalide (start >= end).

**Retourne :** `Collection<int, Schedule>` - Plannings invalides

---

### `findWithExceedingDuration(int $availabilityId, int $maxDurationMinutes): Collection`

Trouve les plannings qui dépassent une durée maximale.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$availabilityId` | `int` | ID de la disponibilité |
| `$maxDurationMinutes` | `int` | Durée maximale en minutes |

**Retourne :** `Collection<int, Schedule>` - Plannings trop longs

---

### `findViolatingBufferTime(int $availabilityId, int $bufferMinutes): Collection`

Trouve les plannings qui ne respectent pas le temps de buffer.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$availabilityId` | `int` | ID de la disponibilité |
| `$bufferMinutes` | `int` | Buffer minimum en minutes |

**Retourne :** `Collection<int, Schedule>` - Plannings violant le buffer

**Exemple :**
```php
$violations = $repository->findViolatingBufferTime(42, 15);
// Plannings avec moins de 15 minutes entre la fin d'un et le début du suivant
```

---

### `findConflicting(int $availabilityId, DateTimeZuluVO $start, DateTimeZuluVO $end, ?int $excludeId = null): Collection`

Alias de `findOverlapping()` pour la détection de conflits.

**Retourne :** `Collection<int, Schedule>` - Plannings en conflit

---

### `hasCrossDaySchedule(int $availabilityId): bool`

Vérifie si une disponibilité a des plannings cross-day (sur plusieurs jours).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$availabilityId` | `int` | ID de la disponibilité |

**Retourne :** `bool` - True si des plannings cross-day existent

**Exemple :**
```php
if ($repository->hasCrossDaySchedule(42)) {
    echo "Cette disponibilité a des plannings sur plusieurs jours";
}
```

---

## Cas d'utilisation

### Cas 1 : Détection de conflits pour un nouveau planning

```php
$user = User::find(42);
$start = DateTimeZuluVO::from('2024-01-15T10:00:00Z');
$end = DateTimeZuluVO::from('2024-01-15T12:00:00Z');

$conflicts = $repository->findConflicting(42, $start, $end);

if ($conflicts->isEmpty()) {
    // Créer le planning
    $repository->create($record);
} else {
    echo "Conflit avec le planning #{$conflicts->first()->id}";
}
```

### Cas 2 : Gestion des statuts

```php
$booked = $repository->findByStatus(ScheduleStatus::BOOKED);

foreach ($booked as $schedule) {
    if ($schedule->end_datetime < now()) {
        $repository->update($schedule->id, ScheduleRecord::from([
            'status' => ScheduleStatus::COMPLETED
        ]));
    }
}
```

### Cas 3 : Nettoyage des données invalides

```php
$invalid = $repository->findWithInvalidChronology();

foreach ($invalid as $schedule) {
    $repository->delete($schedule->id);
}
```

### Cas 4 : Analyse des violations de buffer

```php
$violations = $repository->findViolatingBufferTime(42, 15);

if ($violations->isNotEmpty()) {
    echo "Buffer violations trouvées: " . $violations->count();
}
```

### Cas 5 : Recherche par entité planifiable

```php
$user = User::find(42);
$userSchedules = $repository->findBySchedulable($user);
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Planning inexistant | `ModelNotFoundException` | `No query results for model [Schedule]` |
| Date invalide | `InvalidArgumentException` | Variable selon le contexte |
| Conflit non géré | `Throwable` | Variable selon le contexte |

---

## Performance

| Aspect | Considération |
|--------|---------------|
| **Index recommandés** | `availability_id`, `start_datetime`, `end_datetime`, `status` |
| **Buffer time** | Requête complexe avec jointure - à optimiser pour gros volumes |
| **Cross-day** | Vérification rapide avec `DATE()` |
| **Cache** | Non utilisé - données en temps réel |

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| Laravel 9.x | ✅ Complet |
| Laravel 10.x | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelChronos\Repositories\ScheduleRepository;
use AndyDefer\LaravelChronos\Enums\ScheduleStatus;
use AndyDefer\LaravelChronos\Records\ScheduleRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;

$repository = new ScheduleRepository();
$user = User::find(42);

// Créer un planning
$record = ScheduleRecord::from([
    'availability_id' => 42,
    'schedulable_type' => get_class($user),
    'schedulable_id' => $user->id,
    'title' => 'Réunion d\'équipe',
    'status' => ScheduleStatus::BOOKED,
    'start_datetime' => '2024-01-15T10:00:00Z',
    'end_datetime' => '2024-01-15T11:00:00Z',
]);

$schedule = $repository->create($record);

// Vérifier les conflits
$conflicts = $repository->findConflicting(
    42,
    DateTimeZuluVO::from('2024-01-15T10:30:00Z'),
    DateTimeZuluVO::from('2024-01-15T11:30:00Z')
);

if ($conflicts->isNotEmpty()) {
    echo "Conflit avec le planning #{$conflicts->first()->id}";
}

// Trouver les plannings du jour
$today = DateTimeZuluVO::now();
$daySchedules = $repository->findByDate($today);

// Récupérer les plannings d'un utilisateur
$userSchedules = $repository->findBySchedulable($user);

// Vérifier le buffer
$bufferViolations = $repository->findViolatingBufferTime(42, 15);

// Vérifier les plannings cross-day
if ($repository->hasCrossDaySchedule(42)) {
    echo "Disponibilité #42 a des plannings cross-day";
}
```

---

## Voir aussi

- `AbstractChronosRepository` - Classe parente
- `ScheduleRepositoryInterface` - Interface implémentée
- `ScheduleRecord` - Record de données
- `Schedule` - Modèle Eloquent
- `ScheduleStatus` - Énumération des statuts
- `DateTimeZuluVO` - Value Object pour les dates
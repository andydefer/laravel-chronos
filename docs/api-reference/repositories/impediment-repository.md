# ImpedimentRepository - Référence Technique

## Description

Repository pour la gestion des entités `Impediment` (empêchements) dans la base de données. Fournit des méthodes spécialisées pour rechercher des empêchements selon différents critères : disponibilité associée, plages horaires, chevauchements, et analyse des impacts sur les plannings.

## Hiérarchie

```
AbstractChronosRepository
    └── ImpedimentRepository
        └── ImpedimentRepositoryInterface
```

## Rôle principal

Gérer l'accès aux données des empêchements qui bloquent ou restreignent les disponibilités. Le repository fournit des méthodes pour détecter les conflits, analyser les chevauchements avec les plannings, et identifier les violations de règles métier (durée excessive, buffer time, etc.).

---

## API

### `findByAvailability(int $availabilityId): Collection`

Retourne tous les empêchements associés à une disponibilité.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$availabilityId` | `int` | ID de la disponibilité |

**Retourne :** `Collection<int, Impediment>` - Collection d'empêchements

**Exemple :**
```php
$impediments = $repository->findByAvailability(42);
```

---

### `findInDateRange(DateTimeZuluVO $start, DateTimeZuluVO $end, ?int $availabilityId = null): Collection`

Trouve les empêchements dans une plage de dates.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$start` | `DateTimeZuluVO` | Début de la plage |
| `$end` | `DateTimeZuluVO` | Fin de la plage |
| `$availabilityId` | `int|null` | Filtre par disponibilité |

**Retourne :** `Collection<int, Impediment>` - Empêchements dans la plage

---

### `findOverlapping(int $availabilityId, DateTimeZuluVO $start, DateTimeZuluVO $end, ?int $excludeId = null): Collection`

Trouve les empêchements qui chevauchent une plage horaire.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$availabilityId` | `int` | ID de la disponibilité |
| `$start` | `DateTimeZuluVO` | Début de la plage |
| `$end` | `DateTimeZuluVO` | Fin de la plage |
| `$excludeId` | `int|null` | ID à exclure |

**Retourne :** `Collection<int, Impediment>` - Empêchements en conflit

**Exemple :**
```php
$overlapping = $repository->findOverlapping(
    42,
    DateTimeZuluVO::from('2024-01-15T10:00:00Z'),
    DateTimeZuluVO::from('2024-01-15T12:00:00Z')
);
```

---

### `findBySchedulable(Model $schedulable): Collection`

Trouve les empêchements via la relation avec la disponibilité.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$schedulable` | `Model` | Entité planifiable (ex: `User::find(42)`) |

**Retourne :** `Collection<int, Impediment>` - Empêchements pour l'entité

**Exemple :**
```php
$user = User::find(42);
$impediments = $repository->findBySchedulable($user);
```

---

### `searchByReason(string $search, ?int $availabilityId = null): Collection`

Recherche des empêchements par motif.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$search` | `string` | Terme de recherche |
| `$availabilityId` | `int|null` | Filtre par disponibilité |

**Retourne :** `Collection<int, Impediment>` - Empêchements correspondants

**Exemple :**
```php
$results = $repository->searchByReason('formation');
```

---

### `findByDate(DateTimeZuluVO $date, ?int $availabilityId = null): Collection`

Trouve les empêchements pour une date spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$date` | `DateTimeZuluVO` | Date à rechercher |
| `$availabilityId` | `int|null` | Filtre par disponibilité |

**Retourne :** `Collection<int, Impediment>` - Empêchements pour la date

---

### `findActive(?int $availabilityId = null): Collection`

Trouve les empêchements actifs (en cours).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$availabilityId` | `int|null` | Filtre par disponibilité |

**Retourne :** `Collection<int, Impediment>` - Empêchements actifs

**Exemple :**
```php
$activeImpediments = $repository->findActive();
// Empêchements où start <= now <= end
```

---

### `findConflicting(int $availabilityId, DateTimeZuluVO $start, DateTimeZuluVO $end, ?int $excludeId = null): Collection`

Alias de `findOverlapping()` pour la détection de conflits.

**Retourne :** `Collection<int, Impediment>` - Empêchements en conflit

---

### `findWithInvalidChronology(): Collection`

Trouve les empêchements avec une chronologie invalide (start >= end).

**Retourne :** `Collection<int, Impediment>` - Empêchements invalides

---

### `findWithExceedingDuration(int $availabilityId, int $maxDurationMinutes): Collection`

Trouve les empêchements qui dépassent une durée maximale.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$availabilityId` | `int` | ID de la disponibilité |
| `$maxDurationMinutes` | `int` | Durée maximale en minutes |

**Retourne :** `Collection<int, Impediment>` - Empêchements trop longs

---

### `findViolatingBufferTime(int $availabilityId, int $bufferMinutes): Collection`

Trouve les empêchements qui ne respectent pas le temps de buffer.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$availabilityId` | `int` | ID de la disponibilité |
| `$bufferMinutes` | `int` | Buffer minimum en minutes |

**Retourne :** `Collection<int, Impediment>` - Empêchements violant le buffer

---

### `getBlockedSchedules(Impediment $impediment): Collection`

Retourne tous les plannings bloqués par un empêchement.

**Retourne :** `Collection<int, Schedule>` - Plannings bloqués (partiellement ou totalement)

---

### `getFullyBlockedSchedules(Impediment $impediment): Collection`

Retourne les plannings totalement bloqués par un empêchement.

**Retourne :** `Collection<int, Schedule>` - Plannings entièrement dans l'empêchement

---

### `getPartiallyBlockedSchedules(Impediment $impediment): Collection`

Retourne les plannings partiellement bloqués par un empêchement.

**Retourne :** `Collection<int, Schedule>` - Plannings chevauchant partiellement

---

## Cas d'utilisation

### Cas 1 : Détection de conflits

```php
$user = User::find(42);
$start = DateTimeZuluVO::from('2024-01-15T10:00:00Z');
$end = DateTimeZuluVO::from('2024-01-15T12:00:00Z');

// Récupérer les empêchements via l'utilisateur
$impediments = $repository->findBySchedulable($user);

// Vérifier les conflits sur une disponibilité spécifique
$conflicts = $repository->findOverlapping(42, $start, $end);

if ($conflicts->isEmpty()) {
    // Créer l'empêchement
    $repository->create($record);
} else {
    echo "Conflit avec l'empêchement #{$conflicts->first()->id}";
}
```

### Cas 2 : Analyse d'impact sur les plannings

```php
$impediment = $repository->find(1);

$fullyBlocked = $repository->getFullyBlockedSchedules($impediment);
$partiallyBlocked = $repository->getPartiallyBlockedSchedules($impediment);

echo "Plannings totalement bloqués: " . $fullyBlocked->count();
echo "Plannings partiellement bloqués: " . $partiallyBlocked->count();
```

### Cas 3 : Nettoyage des données invalides

```php
$invalid = $repository->findWithInvalidChronology();

foreach ($invalid as $impediment) {
    $repository->delete($impediment->id);
}
```

### Cas 4 : Surveillance des empêchements actifs

```php
$active = $repository->findActive();

foreach ($active as $impediment) {
    $blockedSchedules = $repository->getBlockedSchedules($impediment);
}
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Empêchement inexistant | `ModelNotFoundException` | `No query results for model [Impediment]` |
| Date invalide | `InvalidArgumentException` | Variable selon le contexte |
| Conflit non géré | `Throwable` | Variable selon le contexte |

---

## Performance

| Aspect | Considération |
|--------|---------------|
| **Index recommandés** | `availability_id`, `start_datetime`, `end_datetime` |
| **Requêtes complexes** | Jointures avec `schedules` pour les analyses d'impact |
| **Buffer time** | Requête avec jointure sur elle-même - à optimiser pour gros volumes |
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

use AndyDefer\LaravelChronos\Repositories\ImpedimentRepository;
use AndyDefer\LaravelChronos\Records\ImpedimentRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;

$repository = new ImpedimentRepository();
$user = User::find(42);

// Créer un empêchement
$record = ImpedimentRecord::from([
    'availability_id' => 42,
    'reason' => 'Maintenance technique',
    'start_datetime' => '2024-01-15T10:00:00Z',
    'end_datetime' => '2024-01-15T12:00:00Z',
]);

$impediment = $repository->create($record);

// Trouver les empêchements pour un utilisateur
$userImpediments = $repository->findBySchedulable($user);

// Vérifier les conflits
$conflicts = $repository->findConflicting(
    42,
    DateTimeZuluVO::from('2024-01-15T11:00:00Z'),
    DateTimeZuluVO::from('2024-01-15T13:00:00Z')
);

if ($conflicts->isNotEmpty()) {
    echo "Conflit avec l'empêchement #{$conflicts->first()->id}";
}

// Analyser l'impact
$blocked = $repository->getBlockedSchedules($impediment);
$fullyBlocked = $repository->getFullyBlockedSchedules($impediment);

// Vérifier la durée
$tooLong = $repository->findWithExceedingDuration(42, 60);

// Vérifier le buffer
$bufferViolations = $repository->findViolatingBufferTime(42, 15);
```

---

## Voir aussi

- `AbstractChronosRepository` - Classe parente
- `ImpedimentRepositoryInterface` - Interface implémentée
- `ImpedimentRecord` - Record de données
- `Impediment` - Modèle Eloquent
- `Schedule` - Modèle des plannings
- `DateTimeZuluVO` - Value Object pour les dates
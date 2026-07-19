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

### `findByAvailability(int $availabilityId, ?int $limit = null): Collection`

Retourne tous les empêchements associés à une disponibilité.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$availabilityId` | `int` | ID de la disponibilité |
| `$limit` | `int|null` | Nombre maximum de résultats à retourner |

**Retourne :** `Collection<int, Impediment>` - Collection d'empêchements

**Exemple :**
```php
$impediments = $repository->findByAvailability(42, 10);
```

---

### `findInDateRange(DateTimeZuluVO $start, DateTimeZuluVO $end, ?int $availabilityId = null, ?int $limit = null): Collection`

Trouve les empêchements dans une plage de dates.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$start` | `DateTimeZuluVO` | Début de la plage |
| `$end` | `DateTimeZuluVO` | Fin de la plage |
| `$availabilityId` | `int|null` | Filtre par disponibilité |
| `$limit` | `int|null` | Nombre maximum de résultats à retourner |

**Retourne :** `Collection<int, Impediment>` - Empêchements dans la plage

---

### `findOverlapping(int $availabilityId, DateTimeZuluVO $start, DateTimeZuluVO $end, ?int $excludeId = null, ?int $limit = null): Collection`

Trouve les empêchements qui chevauchent une plage horaire.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$availabilityId` | `int` | ID de la disponibilité |
| `$start` | `DateTimeZuluVO` | Début de la plage |
| `$end` | `DateTimeZuluVO` | Fin de la plage |
| `$excludeId` | `int|null` | ID à exclure |
| `$limit` | `int|null` | Nombre maximum de résultats à retourner |

**Retourne :** `Collection<int, Impediment>` - Empêchements en conflit

**Exemple :**
```php
$overlapping = $repository->findOverlapping(
    42,
    DateTimeZuluVO::from('2024-01-15T10:00:00Z'),
    DateTimeZuluVO::from('2024-01-15T12:00:00Z'),
    null,
    5
);
```

---

### `findBySchedulable(Model $schedulable, ?int $limit = null): Collection`

Trouve les empêchements via la relation avec la disponibilité.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$schedulable` | `Model` | Entité planifiable (ex: `User::find(42)`) |
| `$limit` | `int|null` | Nombre maximum de résultats à retourner |

**Retourne :** `Collection<int, Impediment>` - Empêchements pour l'entité

**Exemple :**
```php
$user = User::find(42);
$impediments = $repository->findBySchedulable($user, 10);
```

---

### `searchByReason(string $search, ?int $availabilityId = null, ?int $limit = null): Collection`

Recherche des empêchements par motif.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$search` | `string` | Terme de recherche |
| `$availabilityId` | `int|null` | Filtre par disponibilité |
| `$limit` | `int|null` | Nombre maximum de résultats à retourner |

**Retourne :** `Collection<int, Impediment>` - Empêchements correspondants

**Exemple :**
```php
$results = $repository->searchByReason('formation', null, 5);
```

---

### `findByDate(DateTimeZuluVO $date, ?int $availabilityId = null, ?int $limit = null): Collection`

Trouve les empêchements pour une date spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$date` | `DateTimeZuluVO` | Date à rechercher |
| `$availabilityId` | `int|null` | Filtre par disponibilité |
| `$limit` | `int|null` | Nombre maximum de résultats à retourner |

**Retourne :** `Collection<int, Impediment>` - Empêchements pour la date

---

### `findActive(?int $availabilityId = null, ?int $limit = null): Collection`

Trouve les empêchements actifs (en cours).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$availabilityId` | `int|null` | Filtre par disponibilité |
| `$limit` | `int|null` | Nombre maximum de résultats à retourner |

**Retourne :** `Collection<int, Impediment>` - Empêchements actifs

**Exemple :**
```php
$activeImpediments = $repository->findActive(null, 10);
// Empêchements où start <= now <= end
```

---

### `findConflicting(int $availabilityId, DateTimeZuluVO $start, DateTimeZuluVO $end, ?int $excludeId = null, ?int $limit = null): Collection`

Alias de `findOverlapping()` pour la détection de conflits.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$availabilityId` | `int` | ID de la disponibilité |
| `$start` | `DateTimeZuluVO` | Début de la plage |
| `$end` | `DateTimeZuluVO` | Fin de la plage |
| `$excludeId` | `int|null` | ID à exclure |
| `$limit` | `int|null` | Nombre maximum de résultats à retourner |

**Retourne :** `Collection<int, Impediment>` - Empêchements en conflit

---

### `findWithInvalidChronology(?int $limit = null): Collection`

Trouve les empêchements avec une chronologie invalide (start >= end).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$limit` | `int|null` | Nombre maximum de résultats à retourner |

**Retourne :** `Collection<int, Impediment>` - Empêchements invalides

---

### `findWithExceedingDuration(int $availabilityId, int $maxDurationMinutes, ?int $limit = null): Collection`

Trouve les empêchements qui dépassent une durée maximale.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$availabilityId` | `int` | ID de la disponibilité |
| `$maxDurationMinutes` | `int` | Durée maximale en minutes |
| `$limit` | `int|null` | Nombre maximum de résultats à retourner |

**Retourne :** `Collection<int, Impediment>` - Empêchements trop longs

---

### `findViolatingBufferTime(int $availabilityId, int $bufferMinutes, ?int $limit = null): Collection`

Trouve les empêchements qui ne respectent pas le temps de buffer.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$availabilityId` | `int` | ID de la disponibilité |
| `$bufferMinutes` | `int` | Buffer minimum en minutes |
| `$limit` | `int|null` | Nombre maximum de résultats à retourner |

**Retourne :** `Collection<int, Impediment>` - Empêchements violant le buffer

---

### `getBlockedSchedules(Impediment $impediment, ?int $limit = null): Collection`

Retourne tous les plannings bloqués par un empêchement.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$impediment` | `Impediment` | L'empêchement à analyser |
| `$limit` | `int|null` | Nombre maximum de résultats à retourner |

**Retourne :** `Collection<int, Schedule>` - Plannings bloqués (partiellement ou totalement)

---

### `getFullyBlockedSchedules(Impediment $impediment, ?int $limit = null): Collection`

Retourne les plannings totalement bloqués par un empêchement.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$impediment` | `Impediment` | L'empêchement à analyser |
| `$limit` | `int|null` | Nombre maximum de résultats à retourner |

**Retourne :** `Collection<int, Schedule>` - Plannings entièrement dans l'empêchement

---

### `getPartiallyBlockedSchedules(Impediment $impediment, ?int $limit = null): Collection`

Retourne les plannings partiellement bloqués par un empêchement.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$impediment` | `Impediment` | L'empêchement à analyser |
| `$limit` | `int|null` | Nombre maximum de résultats à retourner |

**Retourne :** `Collection<int, Schedule>` - Plannings chevauchant partiellement

---

## Cas d'utilisation avec limit

### Cas 1 : Détection de conflits avec limite

```php
$user = User::find(42);
$start = DateTimeZuluVO::from('2024-01-15T10:00:00Z');
$end = DateTimeZuluVO::from('2024-01-15T12:00:00Z');

// Récupérer les empêchements via l'utilisateur
$impediments = $repository->findBySchedulable($user, 20);

// Vérifier les conflits sur une disponibilité spécifique (limité à 5)
$conflicts = $repository->findOverlapping(42, $start, $end, null, 5);

if ($conflicts->isEmpty()) {
    // Créer l'empêchement
    $repository->create($record);
} else {
    echo "Conflit avec l'empêchement #{$conflicts->first()->id}";
}
```

### Cas 2 : Analyse d'impact sur les plannings avec limite

```php
$impediment = $repository->find(1);

$fullyBlocked = $repository->getFullyBlockedSchedules($impediment, 10);
$partiallyBlocked = $repository->getPartiallyBlockedSchedules($impediment, 10);

echo "Plannings totalement bloqués: " . $fullyBlocked->count();
echo "Plannings partiellement bloqués: " . $partiallyBlocked->count();
```

### Cas 3 : Nettoyage des données invalides avec limite

```php
$invalid = $repository->findWithInvalidChronology(50);

foreach ($invalid as $impediment) {
    $repository->delete($impediment->id);
}
```

### Cas 4 : Surveillance des empêchements actifs avec limite

```php
$active = $repository->findActive(null, 10);

foreach ($active as $impediment) {
    $blockedSchedules = $repository->getBlockedSchedules($impediment, 5);
}
```

### Cas 5 : Recherche paginée

```php
$page = 1;
$perPage = 15;

$impediments = $repository->findByAvailability(42, $perPage);
// Utiliser la collection pour la pagination manuelle
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
| **Buffer time** | Requête avec jointure sur elle-même - utiliser `$limit` pour réduire la charge |
| **Cache** | Non utilisé - données en temps réel |
| **Limite** | Utiliser `$limit` pour réduire la charge sur les grandes bases de données |

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| Laravel 9.x | ✅ Complet |
| Laravel 10.x | ✅ Complet |

---

## Exemple complet avec limit

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

// Trouver les empêchements pour un utilisateur (limité à 10)
$userImpediments = $repository->findBySchedulable($user, 10);

// Vérifier les conflits (limité à 5)
$conflicts = $repository->findConflicting(
    42,
    DateTimeZuluVO::from('2024-01-15T11:00:00Z'),
    DateTimeZuluVO::from('2024-01-15T13:00:00Z'),
    null,
    5
);

if ($conflicts->isNotEmpty()) {
    echo "Conflit avec l'empêchement #{$conflicts->first()->id}";
}

// Analyser l'impact (limité à 10)
$blocked = $repository->getBlockedSchedules($impediment, 10);
$fullyBlocked = $repository->getFullyBlockedSchedules($impediment, 10);

// Vérifier la durée (limité à 10)
$tooLong = $repository->findWithExceedingDuration(42, 60, 10);

// Vérifier le buffer (limité à 10)
$bufferViolations = $repository->findViolatingBufferTime(42, 15, 10);

// Récupérer les empêchements actifs (limité à 20)
$active = $repository->findActive(null, 20);
```

---

## Voir aussi

- `AbstractChronosRepository` - Classe parente
- `ImpedimentRepositoryInterface` - Interface implémentée
- `ImpedimentRecord` - Record de données
- `Impediment` - Modèle Eloquent
- `Schedule` - Modèle des plannings
- `DateTimeZuluVO` - Value Object pour les dates
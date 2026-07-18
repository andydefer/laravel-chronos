# AvailabilityService - Référence Technique

## Description

Service métier pour la gestion des disponibilités (Availability). Encapsule la logique métier, la validation et le tracking des mutations pour les opérations CRUD sur les disponibilités.

## Hiérarchie

```
AvailabilityService
    └── AvailabilityServiceInterface
```

## Rôle principal

Orchestrer les opérations sur les disponibilités avec :
- Validation des règles métier via `ValidatorInterface`
- Tracking des mutations via `ChronosMutationContext`
- Journalisation des opérations via `ServiceContext`
- Gestion centralisée des exceptions

---

## API

### `create(AvailabilityRecord $record): Availability`

Crée une nouvelle disponibilité.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `AvailabilityRecord` | Données de la disponibilité |

**Retourne :** `Availability` - La disponibilité créée

**Exceptions :**
- `ValidationException` - Si la validation échoue
- `Throwable` - Si l'opération échoue

**Exemple :**
```php
$user = User::find(42);
$record = AvailabilityRecord::from([
    'name' => 'Working Hours',
    'type' => 'standard',
    'days' => ['monday', 'tuesday', 'wednesday'],
    'daily_start' => '09:00:00',
    'daily_end' => '17:00:00',
    'validity_start' => '2024-01-01T00:00:00Z',
    'validity_end' => '2024-12-31T23:59:59Z',
    'schedulable_type' => get_class($user),
    'schedulable_id' => $user->id,
]);

$availability = $service->create($record);
```

---

### `update(int $id, AvailabilityRecord $record): Availability`

Met à jour une disponibilité existante.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `int` | ID de la disponibilité |
| `$record` | `AvailabilityRecord` | Nouvelles données |

**Retourne :** `Availability` - La disponibilité mise à jour

**Exceptions :**
- `ModelNotFoundException` - Si la disponibilité n'existe pas
- `ValidationException` - Si la validation échoue
- `Throwable` - Si l'opération échoue

**Exemple :**
```php
$record = AvailabilityRecord::from([
    'name' => 'Updated Working Hours',
    'daily_end' => '18:00:00',
]);

$availability = $service->update(42, $record);
```

---

### `delete(int $id, bool $force = false): bool`

Supprime une disponibilité.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `int` | ID de la disponibilité |
| `$force` | `bool` | Suppression forcée (sans validation) |

**Retourne :** `bool` - True si supprimé

**Exceptions :**
- `ModelNotFoundException` - Si la disponibilité n'existe pas
- `ValidationException` - Si la validation échoue (sauf si force=true)
- `Throwable` - Si l'opération échoue

**Exemple :**
```php
// Suppression soft (avec validation)
$service->delete(42);

// Suppression forcée (sans validation)
$service->delete(42, true);
```

---

### `find(int $id): ?Availability`

Trouve une disponibilité par son ID.

**Retourne :** `Availability|null` - La disponibilité ou null

**Exemple :**
```php
$availability = $service->find(42);
if ($availability) {
    echo $availability->name;
}
```

---

### `findBySchedulable(Model $schedulable): Collection`

Trouve toutes les disponibilités pour une entité planifiable.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$schedulable` | `Model` | Entité planifiable (ex: `User::find(42)`) |

**Retourne :** `Collection<int, Availability>` - Disponibilités de l'entité

**Exemple :**
```php
$user = User::find(42);
$availabilities = $service->findBySchedulable($user);
```

---

### `findByType(string $type): Collection`

Trouve les disponibilités par type.

**Retourne :** `Collection<int, Availability>` - Disponibilités du type

---

### `findActiveAtDate(Model $schedulable, DateTimeZuluVO $date): Collection`

Trouve les disponibilités actives à une date donnée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$schedulable` | `Model` | Entité planifiable |
| `$date` | `DateTimeZuluVO` | Date à vérifier |

**Retourne :** `Collection<int, Availability>` - Disponibilités actives

**Exemple :**
```php
$user = User::find(42);
$today = DateTimeZuluVO::now();
$active = $service->findActiveAtDate($user, $today);
```

---

### `findActiveInDateRange(Model $schedulable, DateTimeZuluVO $start, DateTimeZuluVO $end): Collection`

Trouve les disponibilités actives dans une plage de dates.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$schedulable` | `Model` | Entité planifiable |
| `$start` | `DateTimeZuluVO` | Début de la plage |
| `$end` | `DateTimeZuluVO` | Fin de la plage |

**Retourne :** `Collection<int, Availability>` - Disponibilités dans la plage

---

### `schedulableExists(Model $schedulable): bool`

Vérifie si une entité planifiable existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$schedulable` | `Model` | Entité planifiable |

**Retourne :** `bool` - True si l'entité existe

**Exemple :**
```php
$user = User::find(42);
if ($service->schedulableExists($user)) {
    echo "L'utilisateur existe";
}
```

---

### `getSchedulableModel(Model $schedulable): ?string`

Retourne la classe du modèle planifiable si l'entité existe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$schedulable` | `Model` | Entité planifiable |

**Retourne :** `string|null` - Classe du modèle ou null si inexistante

**Exemple :**
```php
$user = User::find(42);
$class = $service->getSchedulableModel($user);
// 'App\Models\User'
```

---

## Cas d'utilisation

### Cas 1 : Création d'une disponibilité avec validation

```php
try {
    $user = User::find(42);
    $record = AvailabilityRecord::from([
        'name' => 'Heures de travail',
        'type' => 'standard',
        'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        'daily_start' => '09:00:00',
        'daily_end' => '17:00:00',
        'validity_start' => '2024-01-01T00:00:00Z',
        'validity_end' => '2024-12-31T23:59:59Z',
        'schedulable_type' => get_class($user),
        'schedulable_id' => $user->id,
    ]);

    $availability = $service->create($record);
    echo "Disponibilité créée avec l'ID: " . $availability->id;

} catch (ValidationException $e) {
    echo "Erreur de validation: " . $e->getMessage();
} catch (Throwable $e) {
    echo "Erreur: " . $e->getMessage();
}
```

### Cas 2 : Mise à jour d'une disponibilité

```php
try {
    $availability = $service->find(42);
    
    if ($availability === null) {
        throw new RuntimeException('Disponibilité non trouvée');
    }

    $record = AvailabilityRecord::from([
        'name' => 'Nouveaux horaires',
        'daily_start' => '08:00:00',
        'daily_end' => '18:00:00',
    ]);

    $updated = $service->update(42, $record);
    echo "Disponibilité mise à jour: " . $updated->name;

} catch (ModelNotFoundException $e) {
    echo "Disponibilité non trouvée";
} catch (ValidationException $e) {
    echo "Erreur de validation: " . $e->getMessage();
}
```

### Cas 3 : Suppression d'une disponibilité

```php
try {
    $service->delete(42);
    echo "Disponibilité supprimée avec succès";

} catch (ModelNotFoundException $e) {
    echo "Disponibilité non trouvée";
} catch (ValidationException $e) {
    echo "Impossible de supprimer: " . $e->getMessage();
}
```

### Cas 4 : Recherche par entité planifiable

```php
$user = User::find(42);
$availabilities = $service->findBySchedulable($user);

foreach ($availabilities as $availability) {
    echo $availability->name . "\n";
}
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Disponibilité inexistante | `ModelNotFoundException` | `Availability with ID X not found` |
| Validation échoue | `ValidationException` | Messages des règles de validation |
| Entité planifiable inexistante | `Throwable` | Variable selon le contexte |
| Création échoue | `Throwable` | Variable selon le contexte |
| Mise à jour échoue | `Throwable` | Variable selon le contexte |

---

## Intégration

```mermaid
graph TD
    A[AvailabilityService] --> B[AvailabilityRepositoryInterface]
    A --> C[ValidatorInterface]
    A --> D[ServiceContext]
    A --> E[ChronosMutationContext]
    B --> F[Availability Model]
    C --> G[Validation Rules]
```

Le service s'intègre avec :
- **AvailabilityRepositoryInterface** : Pour les opérations de persistance
- **ValidatorInterface** : Pour la validation des règles métier
- **ServiceContext** : Pour le tracking des opérations
- **ChronosMutationContext** : Pour le contrôle des mutations

---

## Performance

| Aspect | Considération |
|--------|---------------|
| **Complexité** | O(1) - Opérations CRUD simples |
| **Validation** | Exécute toutes les règles enregistrées |
| **Contexts** | Overhead minimal pour le tracking |
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

use AndyDefer\LaravelChronos\Services\AvailabilityService;
use AndyDefer\LaravelChronos\Records\AvailabilityRecord;
use AndyDefer\LaravelChronos\ValueObjects\DateTimeZuluVO;
use AndyDefer\LaravelChronos\Exceptions\ValidationException;
use AndyDefer\LaravelChronos\Exceptions\ModelNotFoundException;

$service = $app->make(AvailabilityService::class);
$user = User::find(42);

// 1. Créer une disponibilité
try {
    $record = AvailabilityRecord::from([
        'name' => 'Heures de bureau',
        'type' => 'standard',
        'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        'daily_start' => '09:00:00',
        'daily_end' => '17:00:00',
        'validity_start' => '2024-01-01T00:00:00Z',
        'validity_end' => '2024-12-31T23:59:59Z',
        'schedulable_type' => get_class($user),
        'schedulable_id' => $user->id,
    ]);

    $availability = $service->create($record);
    echo "Créé: " . $availability->id . "\n";

    // 2. Trouver la disponibilité
    $found = $service->find($availability->id);
    echo "Trouvé: " . $found->name . "\n";

    // 3. Vérifier les disponibilités actives aujourd'hui
    $today = DateTimeZuluVO::now();
    $active = $service->findActiveAtDate($user, $today);
    echo "Disponibilités actives aujourd'hui: " . $active->count() . "\n";

    // 4. Mettre à jour
    $updateRecord = AvailabilityRecord::from([
        'name' => 'Heures étendues',
        'daily_end' => '18:00:00',
    ]);
    $updated = $service->update($availability->id, $updateRecord);
    echo "Mis à jour: " . $updated->name . "\n";

    // 5. Supprimer
    $service->delete($availability->id);
    echo "Supprimé\n";

} catch (ValidationException $e) {
    echo "Erreur de validation: " . $e->getMessage() . "\n";
} catch (ModelNotFoundException $e) {
    echo "Ressource non trouvée: " . $e->getMessage() . "\n";
} catch (Throwable $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
```

---

## Voir aussi

- `AvailabilityServiceInterface` - Interface du service
- `AvailabilityRepositoryInterface` - Repository des disponibilités
- `ValidatorInterface` - Interface de validation
- `AvailabilityRecord` - Record de données
- `Availability` - Modèle Eloquent
- `ModelNotFoundException` - Exception métier
- `ValidationException` - Exception de validation
- `ChronosMutationContext` - Contexte de mutation
- `ServiceContext` - Contexte de service
<?php

declare(strict_types=1);

// src/Collections/AvailabilityDataCollection.php

namespace AndyDefer\LaravelChronos\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelChronos\Datas\AvailabilityData;

final class AvailabilityDataCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(AvailabilityData::class);
    }
}

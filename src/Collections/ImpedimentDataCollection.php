<?php

declare(strict_types=1);

// src/Collections/ImpedimentDataCollection.php

namespace AndyDefer\LaravelChronos\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelChronos\Datas\ImpedimentData;

final class ImpedimentDataCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(ImpedimentData::class);
    }
}

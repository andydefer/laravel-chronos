<?php

declare(strict_types=1);

// src/Collections/ScheduleDataCollection.php

namespace AndyDefer\LaravelChronos\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelChronos\Datas\ScheduleData;

final class ScheduleDataCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(ScheduleData::class);
    }
}

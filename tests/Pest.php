<?php

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->in('Feature');

pest()->use(LazilyRefreshDatabase::class)
    ->in('Feature/Smoke');

pest()->use(DatabaseTruncation::class)
    ->in('Feature/Critical');

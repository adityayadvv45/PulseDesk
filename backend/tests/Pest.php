<?php

use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => TenantContext::clear())
    ->in('Feature');

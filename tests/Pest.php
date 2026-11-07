<?php

use App\Models\Shift;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Open a shift (or return the one already open), so orders can be taken.
 */
function openShift(): Shift
{
    return Shift::current() ?? app(ShiftService::class)->start();
}

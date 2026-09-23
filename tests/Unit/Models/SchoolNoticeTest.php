<?php

namespace Tests\Unit\Models;

use App\Models\LegacySchool;
use App\Models\SchoolNotice;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Tests\TestCase;

uses(TestCase::class);

test('permite associar multiplas escolas a um comunicado', function () {
    $notice = new SchoolNotice();
    $relation = $notice->schools();

    expect($relation)->toBeInstanceOf(BelongsToMany::class)
        ->and($relation->getTable())->toBe('school_notice_schools')
        ->and($relation->getForeignPivotKeyName())->toBe('school_notice_id')
        ->and($relation->getRelatedPivotKeyName())->toBe('school_id')
        ->and($relation->getRelated())->toBeInstanceOf(LegacySchool::class);
});

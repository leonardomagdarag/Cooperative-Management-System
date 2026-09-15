<?php

use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
});

test('officer with reports.financial can download balance sheet PDF', function () {
    $accountant = User::factory()->create([
        'profile_completion_required' => false,
    ]);
    $accountant->assignRole('accountant');

    $this->actingAs($accountant);

    $response = $this->get('/api/reports/balance-sheet/download');

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');

    $disposition = $response->headers->get('content-disposition');
    expect($disposition)->toContain('attachment;')
        ->and($disposition)->toContain('filename=balance-sheet-')
        ->and($disposition)->toContain('.pdf');

    $content = $response->streamedContent();
    expect($content)->not->toBeEmpty()
        ->and(str_starts_with($content, '%PDF'))->toBeTrue();
});

test('regular member without reports.financial permission cannot download balance sheet PDF', function () {
    $member = User::factory()->create([
        'profile_completion_required' => false,
    ]);
    $member->assignRole('member');

    $this->actingAs($member);

    $response = $this->get('/api/reports/balance-sheet/download');

    $response->assertForbidden();
});

test('unauthenticated request to balance sheet PDF is redirected to login or unauthorized', function () {
    $response = $this->get('/api/reports/balance-sheet/download');

    expect($response->status())->toBeIn([302, 401]);
});

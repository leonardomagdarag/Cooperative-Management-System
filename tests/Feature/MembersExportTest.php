<?php

use App\Livewire\Content\Members;
use App\Models\PersonalData;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
});

test('opening export modal closes other member modals', function () {
    $member = User::factory()->create(['role' => 'member']);

    Livewire::test(Members::class)
        ->call('viewProfile', $member->id)
        ->assertSet('showModal', true)
        ->call('openExportModal')
        ->assertSet('showModal', false)
        ->assertSet('showEditModal', false)
        ->assertSet('showExportModal', true);
});

test('member export requires end date on or after start date', function () {
    Livewire::test(Members::class)
        ->call('openExportModal')
        ->set('exportType', 'pdf')
        ->set('exportStartDate', '2026-05-10')
        ->set('exportEndDate', '2026-05-01')
        ->call('exportMembers')
        ->assertHasErrors(['exportEndDate']);
});

test('member export downloads pdf for selected date range', function () {
    $personalData = PersonalData::factory()->create([
        'first_name' => 'Alice',
        'last_name' => 'Rivera',
    ]);

    User::factory()->create([
        'role' => 'member',
        'personal_data_id' => $personalData->id,
        'created_at' => '2026-05-05 10:00:00',
    ]);

    Livewire::test(Members::class)
        ->call('openExportModal')
        ->set('exportType', 'pdf')
        ->set('exportStartDate', '2026-05-01')
        ->set('exportEndDate', '2026-05-31')
        ->call('exportMembers')
        ->assertFileDownloaded('members-2026-05-01-to-2026-05-31.pdf', null, 'application/pdf');
});

test('member export downloads excel for selected date range', function () {
    $personalData = PersonalData::factory()->create([
        'first_name' => 'Marco',
        'last_name' => 'Santos',
    ]);

    User::factory()->create([
        'role' => 'member',
        'personal_data_id' => $personalData->id,
        'created_at' => '2026-05-12 09:30:00',
    ]);

    Livewire::test(Members::class)
        ->call('openExportModal')
        ->set('exportType', 'excel')
        ->set('exportStartDate', '2026-05-01')
        ->set('exportEndDate', '2026-05-31')
        ->call('exportMembers')
        ->assertFileDownloaded('members-2026-05-01-to-2026-05-31.xls', null, 'application/vnd.ms-excel; charset=UTF-8');
});
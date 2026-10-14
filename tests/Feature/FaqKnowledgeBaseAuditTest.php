<?php

use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(SystemSettingSeeder::class);

    $this->member = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();
    $this->manager = User::where('email', 'leo.baluyot039+manager@gmail.com')->firstOrFail();
});

test('F031-1: unauthenticated access to /api/faqs returns 401', function () {
    $this->getJson('/api/faqs')->assertUnauthorized();
});

test('F031-2: authenticated member receives 200 with full categorized knowledge base', function () {
    $response = $this->actingAs($this->member)->getJson('/api/faqs');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'label',
                    'icon',
                    'description',
                    'faqs' => [
                        '*' => ['q', 'a', 'tags'],
                    ],
                ],
            ],
        ]);

    $data = $response->json('data');
    expect($data)->not->toBeEmpty();
});

test('F031-3: FAQ categories contain all core functional areas', function () {
    $response = $this->actingAs($this->member)->getJson('/api/faqs');
    $response->assertOk();

    $categoryIds = collect($response->json('data'))->pluck('id')->all();
    expect($categoryIds)->toContain('membership')
        ->and($categoryIds)->toContain('savings')
        ->and($categoryIds)->toContain('withdrawals')
        ->and($categoryIds)->toContain('loans');
});

test('F031-4: all FAQ items contain non-empty questions, answers, and tags', function () {
    $response = $this->actingAs($this->member)->getJson('/api/faqs');
    $response->assertOk();

    $categories = $response->json('data');
    foreach ($categories as $category) {
        expect($category['label'])->not->toBeEmpty()
            ->and($category['faqs'])->not->toBeEmpty();

        foreach ($category['faqs'] as $faq) {
            expect($faq['q'])->not->toBeEmpty()
                ->and($faq['a'])->not->toBeEmpty()
                ->and($faq['tags'])->toBeArray()->not->toBeEmpty();
        }
    }
});

test('F031-5: authenticated officer receives identical knowledge base categories', function () {
    $response = $this->actingAs($this->manager)->getJson('/api/faqs');
    $response->assertOk();

    $categoryIds = collect($response->json('data'))->pluck('id')->all();
    expect($categoryIds)->toContain('membership')
        ->and($categoryIds)->toContain('loans');
});

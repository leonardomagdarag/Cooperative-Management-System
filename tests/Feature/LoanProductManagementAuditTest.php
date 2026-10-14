<?php

use App\Models\LoanProduct;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);

    $this->admin = User::where('email', 'leo.baluyot039+admin@gmail.com')->firstOrFail();
    $this->member = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();
});

test('F022-1: unauthenticated access to loan products returns 401, authenticated member has can_manage false', function () {
    $this->getJson('/api/loan-products')->assertUnauthorized();

    $this->actingAs($this->member);
    $response = $this->getJson('/api/loan-products');
    $response->assertOk()
        ->assertJsonStructure(['data', 'can_manage'])
        ->assertJsonPath('can_manage', false);
});

test('F022-2: unauthenticated modification of loan products returns 401', function () {
    $this->postJson('/api/loan-products', [])->assertUnauthorized();
    $this->putJson('/api/loan-products/1', [])->assertUnauthorized();
    $this->postJson('/api/loan-products/1/toggle')->assertUnauthorized();
    $this->deleteJson('/api/loan-products/1')->assertUnauthorized();
});

test('F022-3: regular member receives 403 on loan product modifications', function () {
    $product = LoanProduct::create([
        'code' => 'test-mod',
        'name' => 'Test Mod',
        'base_type' => 'business',
        'interest_rate' => 5,
        'max_amount' => 50000,
        'max_term' => 12,
        'minimum_membership_years' => 0,
        'required_co_makers' => 0,
        'sort_order' => 1,
    ]);

    $this->actingAs($this->member);

    $this->postJson('/api/loan-products', [])->assertForbidden();
    $this->putJson("/api/loan-products/{$product->id}", [])->assertForbidden();
    $this->postJson("/api/loan-products/{$product->id}/toggle")->assertForbidden();
    $this->deleteJson("/api/loan-products/{$product->id}")->assertForbidden();
});

test('F022-4: admin creates loan product with full validation', function () {
    $this->actingAs($this->admin);

    $payload = [
        'code' => 'agri-seasonal',
        'name' => 'Agricultural Seasonal Loan',
        'description' => 'Low-interest loan for seasonal crop planting.',
        'base_type' => 'business',
        'icon' => 'leaf',
        'interest_rate' => 3.5,
        'max_amount' => 100000,
        'max_term' => 18,
        'is_active' => true,
        'requires_no_existing_loan' => true,
        'minimum_share_capital' => 2000,
        'minimum_membership_years' => 1,
        'required_co_makers' => 1,
        'requires_interview' => false,
        'requires_special_eligibility' => false,
        'sort_order' => 12,
    ];

    $response = $this->postJson('/api/loan-products', $payload);
    $response->assertStatus(201)
        ->assertJsonStructure(['message', 'data' => ['id', 'code', 'name', 'interest_rate']]);

    $created = LoanProduct::where('code', 'agri-seasonal')->first();
    expect($created)->not->toBeNull()
        ->and($created->name)->toBe('Agricultural Seasonal Loan')
        ->and((float) $created->interest_rate)->toBe(3.5)
        ->and((float) $created->max_amount)->toBe(100000.0)
        ->and((int) $created->max_term)->toBe(18);
});

test('F022-5: loan product creation validates unique code and base type constraints', function () {
    $this->actingAs($this->admin);

    // Create initial product
    LoanProduct::create([
        'code' => 'dup-code',
        'name' => 'Original Product',
        'base_type' => 'group',
        'interest_rate' => 5,
        'max_amount' => 50000,
        'max_term' => 12,
        'minimum_membership_years' => 0,
        'required_co_makers' => 0,
        'sort_order' => 1,
    ]);

    // Attempt duplicate code
    $dupResponse = $this->postJson('/api/loan-products', [
        'code' => 'dup-code',
        'name' => 'Duplicate Product',
        'base_type' => 'special',
        'interest_rate' => 4,
        'max_amount' => 30000,
        'max_term' => 12,
        'minimum_membership_years' => 0,
        'required_co_makers' => 0,
        'sort_order' => 2,
    ]);
    $dupResponse->assertStatus(422)
        ->assertJsonValidationErrors(['code']);

    // Attempt invalid base_type
    $typeResponse = $this->postJson('/api/loan-products', [
        'code' => 'unique-code',
        'name' => 'Invalid Type Product',
        'base_type' => 'unsupported_type',
        'interest_rate' => 4,
        'max_amount' => 30000,
        'max_term' => 12,
        'minimum_membership_years' => 0,
        'required_co_makers' => 0,
        'sort_order' => 2,
    ]);
    $typeResponse->assertStatus(422)
        ->assertJsonValidationErrors(['base_type']);
});

test('F022-6: admin updates an existing loan product', function () {
    $this->actingAs($this->admin);

    $product = LoanProduct::create([
        'code' => 'update-target',
        'name' => 'Original Name',
        'base_type' => 'business',
        'interest_rate' => 6,
        'max_amount' => 40000,
        'max_term' => 12,
        'minimum_membership_years' => 0,
        'required_co_makers' => 0,
        'sort_order' => 5,
    ]);

    $payload = [
        'code' => 'update-target',
        'name' => 'Updated Product Name',
        'base_type' => 'business',
        'interest_rate' => 4.5,
        'max_amount' => 60000,
        'max_term' => 24,
        'minimum_membership_years' => 1,
        'required_co_makers' => 2,
        'sort_order' => 5,
    ];

    $response = $this->putJson("/api/loan-products/{$product->id}", $payload);
    $response->assertOk()
        ->assertJson(['message' => 'Loan product updated.'])
        ->assertJsonPath('data.name', 'Updated Product Name');

    $product->refresh();
    expect($product->name)->toBe('Updated Product Name')
        ->and((float) $product->interest_rate)->toBe(4.5)
        ->and((float) $product->max_amount)->toBe(60000.0);
});

test('F022-7: admin toggles loan product active status', function () {
    $this->actingAs($this->admin);

    $product = LoanProduct::create([
        'code' => 'toggle-test',
        'name' => 'Toggle Test Loan',
        'base_type' => 'special',
        'interest_rate' => 2,
        'max_amount' => 10000,
        'max_term' => 6,
        'is_active' => true,
        'minimum_membership_years' => 0,
        'required_co_makers' => 0,
        'sort_order' => 1,
    ]);

    // Toggle to inactive
    $response1 = $this->postJson("/api/loan-products/{$product->id}/toggle");
    $response1->assertOk()
        ->assertJsonPath('data.is_active', false);
    expect($product->fresh()->is_active)->toBeFalse();

    // Toggle back to active
    $response2 = $this->postJson("/api/loan-products/{$product->id}/toggle");
    $response2->assertOk()
        ->assertJsonPath('data.is_active', true);
    expect($product->fresh()->is_active)->toBeTrue();
});

test('F022-8: admin deletes a loan product', function () {
    $this->actingAs($this->admin);

    $product = LoanProduct::create([
        'code' => 'delete-test',
        'name' => 'Deletable Loan',
        'base_type' => 'group',
        'interest_rate' => 5,
        'max_amount' => 20000,
        'max_term' => 12,
        'minimum_membership_years' => 0,
        'required_co_makers' => 0,
        'sort_order' => 99,
    ]);

    $response = $this->deleteJson("/api/loan-products/{$product->id}");
    $response->assertOk()
        ->assertJson(['message' => 'Loan product deleted.']);

    expect(LoanProduct::where('id', $product->id)->exists())->toBeFalse();
});

test('F022-9: loan products are properly sorted by sort_order and name', function () {
    LoanProduct::create([
        'code' => 'prod-z',
        'name' => 'Zebra Loan',
        'base_type' => 'special',
        'interest_rate' => 5,
        'max_amount' => 10000,
        'max_term' => 12,
        'minimum_membership_years' => 0,
        'required_co_makers' => 0,
        'sort_order' => 10,
    ]);

    LoanProduct::create([
        'code' => 'prod-a',
        'name' => 'Alpha Loan',
        'base_type' => 'special',
        'interest_rate' => 5,
        'max_amount' => 10000,
        'max_term' => 12,
        'minimum_membership_years' => 0,
        'required_co_makers' => 0,
        'sort_order' => 1,
    ]);

    $this->actingAs($this->member);
    $response = $this->getJson('/api/loan-products');
    $response->assertOk();

    $codes = collect($response->json('data'))->pluck('code');
    $indexA = $codes->search('prod-a');
    $indexZ = $codes->search('prod-z');

    expect($indexA)->toBeLessThan($indexZ);
});

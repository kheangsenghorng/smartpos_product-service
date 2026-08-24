<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

class ExceptionHandlingTest extends TestCase
{
    public function test_model_not_found_returns_clean_json_404(): void
    {
        $businessUuid = (string) Str::uuid();

        $response = $this->actingAsJwt($businessUuid)->getJson('/api/v1/products/999999');

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'The requested Product (ID: 999999) was not found.');
    }

    public function test_route_not_found_returns_clean_json_404(): void
    {
        $businessUuid = (string) Str::uuid();

        $response = $this->actingAsJwt($businessUuid)->getJson('/api/v1/non-existing-endpoint-url');

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_validation_error_returns_clean_json_422(): void
    {
        $businessUuid = (string) Str::uuid();

        $response = $this->actingAsJwt($businessUuid)->postJson('/api/v1/categories', []);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed. Please check the submitted fields.')
            ->assertJsonStructure(['success', 'message', 'errors']);
    }
}

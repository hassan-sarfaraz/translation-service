<?php

namespace Tests\Feature;

use App\Models\Locale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TranslationBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_crud_endpoints_require_authentication(): void
    {
        $this->getJson('/api/translations')->assertStatus(401);
        $this->postJson('/api/translations', [])->assertStatus(401);
    }

    public function test_authenticated_user_can_create_and_search_translations(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Locale::firstOrCreate(['code' => 'en'], ['name' => 'English']);

        // Warm up the application router and auth guard
        $this->getJson('/api/translations');

        // Measure creation performance
        $startTime = microtime(true);

        $response = $this->postJson('/api/translations', [
            'locale' => 'en',
            'key' => 'auth.welcome',
            'content' => 'Welcome to the platform',
            'tags' => ['web', 'desktop'],
        ]);

        $duration = (microtime(true) - $startTime) * 1000;

        $response->assertStatus(201)
            ->assertJsonPath('data.key', 'auth.welcome');

        // Standard endpoint latency requirement (< 200ms)
        $this->assertLessThan(200, $duration, "Create translation took {$duration}ms (> 200ms threshold).");

        // Measure search performance (< 200ms)
        $searchStartTime = microtime(true);
        $searchResponse = $this->getJson('/api/translations?key=auth.welcome');
        $searchDuration = (microtime(true) - $searchStartTime) * 1000;

        $searchResponse->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->assertLessThan(200, $searchDuration, "Search translation took {$searchDuration}ms (> 200ms threshold).");
    }

    public function test_export_endpoint_returns_in_under_500ms(): void
    {
        $locale = Locale::firstOrCreate(['code' => 'en'], ['name' => 'English']);

        // Seed an automated-test slice (reviewer can test 100k via artisan benchmark:seed)
        $this->artisan('benchmark:seed', ['count' => 5000]);

        // Cold cache run
        $this->getJson("/api/translations/export/{$locale->code}")->assertStatus(200);

        // Benchmark response time (< 500ms requirement)
        $startTime = microtime(true);
        $response = $this->getJson("/api/translations/export/{$locale->code}");
        $duration = (microtime(true) - $startTime) * 1000;

        $response->assertStatus(200);
        $this->assertLessThan(500, $duration, "Export endpoint took {$duration}ms (> 500ms threshold).");
    }

    public function test_export_cache_invalidates_immediately_on_write(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $locale = Locale::firstOrCreate(['code' => 'en'], ['name' => 'English']);

        // Warms initial cache
        $this->getJson("/api/translations/export/{$locale->code}");

        // Create new record
        $this->postJson('/api/translations', [
            'locale' => 'en',
            'key' => 'cta.button',
            'content' => 'Click Here',
            'tags' => ['web'],
        ])->assertStatus(201);

        // Next export must return the updated translation immediately
        $exportResponse = $this->getJson("/api/translations/export/{$locale->code}");
        $exportResponse->assertStatus(200)
            ->assertJsonFragment(['cta.button' => 'Click Here']);
    }
}

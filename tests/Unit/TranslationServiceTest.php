<?php

namespace Tests\Unit;

use App\Models\Locale;
use App\Models\Tag;
use App\Models\Translation;
use App\Services\TranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TranslationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TranslationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TranslationService;

        Locale::firstOrCreate(['code' => 'en'], ['name' => 'English']);
        Locale::firstOrCreate(['code' => 'es'], ['name' => 'Spanish']);
    }

    public function test_service_can_create_translation_with_tags(): void
    {
        $data = [
            'locale' => 'en',
            'key' => 'unit.test.key',
            'content' => 'Unit Test Content',
            'tags' => ['web', 'mobile'],
        ];

        $translation = $this->service->create($data);

        $this->assertInstanceOf(Translation::class, $translation);
        $this->assertEquals('unit.test.key', $translation->key);
        $this->assertEquals('Unit Test Content', $translation->content);
        $this->assertCount(2, $translation->tags);
        $this->assertDatabaseHas('translations', ['key' => 'unit.test.key']);
        $this->assertDatabaseHas('tags', ['name' => 'web']);
        $this->assertDatabaseHas('tags', ['name' => 'mobile']);
    }

    public function test_service_can_create_translation_without_tags(): void
    {
        $data = [
            'locale' => 'en',
            'key' => 'unit.notags.key',
            'content' => 'No tags content',
        ];

        $translation = $this->service->create($data);

        $this->assertInstanceOf(Translation::class, $translation);
        $this->assertCount(0, $translation->tags);
        $this->assertDatabaseHas('translations', ['key' => 'unit.notags.key']);
    }

    public function test_service_can_update_translation_content_and_tags(): void
    {
        $translation = $this->service->create([
            'locale' => 'en',
            'key' => 'unit.update.key',
            'content' => 'Original Content',
            'tags' => ['initial_tag'],
        ]);

        $updated = $this->service->update($translation, [
            'content' => 'Modified Content',
            'tags' => ['new_tag_1', 'new_tag_2'],
        ]);

        $this->assertEquals('Modified Content', $updated->content);
        $this->assertCount(2, $updated->tags);
        $this->assertTrue($updated->tags->pluck('name')->contains('new_tag_1'));
        $this->assertFalse($updated->tags->pluck('name')->contains('initial_tag'));
    }

    public function test_service_can_update_translation_locale(): void
    {
        $translation = $this->service->create([
            'locale' => 'en',
            'key' => 'unit.switch.locale',
            'content' => 'Switch Locale Content',
        ]);

        $updated = $this->service->update($translation, [
            'locale' => 'es',
        ]);

        $this->assertEquals('es', $updated->locale->code);
    }

    public function test_service_search_filters(): void
    {
        $this->service->create([
            'locale' => 'en',
            'key' => 'messages.welcome',
            'content' => 'Welcome to the platform',
            'tags' => ['web'],
        ]);

        $this->service->create([
            'locale' => 'es',
            'key' => 'messages.goodbye',
            'content' => 'Hasta luego',
            'tags' => ['mobile'],
        ]);

        // Filter by locale and key
        $byLocale = $this->service->search(['locale' => 'es', 'key' => 'messages.']);
        $this->assertEquals(1, $byLocale->total());
        $this->assertEquals('messages.goodbye', $byLocale->items()[0]->key);

        // Filter by tag and key
        $byTag = $this->service->search(['tag' => 'web', 'key' => 'messages.']);
        $this->assertEquals(1, $byTag->total());
        $this->assertEquals('messages.welcome', $byTag->items()[0]->key);

        // Filter by key prefix
        $byKey = $this->service->search(['key' => 'messages.']);
        $this->assertEquals(2, $byKey->total());

        // Filter by content substring and key
        $byContent = $this->service->search(['content' => 'platform', 'key' => 'messages.']);
        $this->assertEquals(1, $byContent->total());
        $this->assertEquals('messages.welcome', $byContent->items()[0]->key);
    }

    public function test_service_export_returns_key_value_map(): void
    {
        $this->service->create([
            'locale' => 'en',
            'key' => 'app.title',
            'content' => 'My Application',
            'tags' => ['web'],
        ]);

        $this->service->create([
            'locale' => 'en',
            'key' => 'app.footer',
            'content' => 'All Rights Reserved',
            'tags' => ['web'],
        ]);

        $this->service->create([
            'locale' => 'en',
            'key' => 'mobile.splash',
            'content' => 'Loading App',
            'tags' => ['mobile'],
        ]);

        // Export all for 'en'
        $allEn = $this->service->export('en');
        $this->assertArrayHasKey('app.title', $allEn);
        $this->assertArrayHasKey('mobile.splash', $allEn);
        $this->assertEquals('My Application', $allEn['app.title']);

        // Export filtered by tag
        $webOnly = $this->service->export('en', 'web');
        $this->assertArrayHasKey('app.title', $webOnly);
        $this->assertArrayNotHasKey('mobile.splash', $webOnly);
    }

    public function test_service_export_returns_empty_array_for_nonexistent_locale(): void
    {
        $result = $this->service->export('nonexistent_lang_code');
        $this->assertSame([], $result);
    }

    public function test_service_export_caches_and_invalidates(): void
    {
        $this->service->create([
            'locale' => 'en',
            'key' => 'cache.key',
            'content' => 'Initial Val',
            'tags' => ['web'],
        ]);

        // First export caches result
        $firstExport = $this->service->export('en', 'web');
        $this->assertEquals('Initial Val', $firstExport['cache.key']);

        // Verify cache key exists
        $this->assertTrue(Cache::has('translations_export:en:web'));

        // Update record
        $translation = Translation::where('key', 'cache.key')->first();
        $this->service->update($translation, [
            'content' => 'Updated Val',
        ]);

        // Cache must have been invalidated
        $secondExport = $this->service->export('en', 'web');
        $this->assertEquals('Updated Val', $secondExport['cache.key']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Locale;
use App\Models\Tag;
use App\Models\Translation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TranslationCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Locale::firstOrCreate(['code' => 'en'], ['name' => 'English']);
        Locale::firstOrCreate(['code' => 'fr'], ['name' => 'French']);
    }

    public function test_cannot_create_translation_without_required_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/translations', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['locale', 'key', 'content']);
    }

    public function test_cannot_create_duplicate_key_for_same_locale(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $payload = [
            'locale' => 'en',
            'key' => 'dup.key',
            'content' => 'First entry',
        ];

        $this->postJson('/api/translations', $payload)->assertStatus(201);
        $this->postJson('/api/translations', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['key']);
    }

    public function test_can_view_single_translation(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $translation = Translation::create([
            'locale_id' => Locale::where('code', 'en')->value('id'),
            'key' => 'view.test',
            'content' => 'Sample Content',
        ]);

        $this->getJson("/api/translations/{$translation->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.key', 'view.test');
    }

    public function test_can_update_translation_and_tags(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $translation = Translation::create([
            'locale_id' => Locale::where('code', 'en')->value('id'),
            'key' => 'update.test',
            'content' => 'Old content',
        ]);

        $this->putJson("/api/translations/{$translation->id}", [
            'content' => 'Updated content',
            'tags' => ['mobile', 'web'],
        ])->assertStatus(200)
            ->assertJsonPath('data.content', 'Updated content');

        $this->assertDatabaseHas('tags', ['name' => 'mobile']);
    }

    public function test_can_filter_by_tag(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $tagName = 'mobile_filter_unique_test';
        $tag = Tag::firstOrCreate(['name' => $tagName]);
        $locale = Locale::where('code', 'en')->first();

        $translation = Translation::create([
            'locale_id' => $locale->id,
            'key' => 'tag.filter.unique',
            'content' => 'App String',
        ]);
        $translation->tags()->syncWithoutDetaching([$tag->id]);

        $this->getJson("/api/translations?tag={$tagName}")
            ->assertStatus(200)
            ->assertJsonPath('total', 1);

        $this->getJson('/api/translations?tag=nonexistent_tag_xyz')
            ->assertStatus(200)
            ->assertJsonPath('total', 0);
    }

    public function test_export_with_tag_filter(): void
    {
        $tag = Tag::firstOrCreate(['name' => 'mobile']);
        $locale = Locale::where('code', 'en')->first();

        $translation = Translation::create([
            'locale_id' => $locale->id,
            'key' => 'export.mobile',
            'content' => 'Mobile Only',
        ]);
        $translation->tags()->syncWithoutDetaching([$tag->id]);

        $response = $this->getJson('/api/translations/export/en?tag=mobile');
        $response->assertStatus(200)
            ->assertJsonFragment(['export.mobile' => 'Mobile Only']);
    }
}

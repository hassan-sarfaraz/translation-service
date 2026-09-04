<?php

namespace Tests\Unit;

use App\Models\Locale;
use App\Models\Tag;
use App\Models\Translation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_locale_has_many_translations(): void
    {
        $locale = Locale::create(['code' => 'it', 'name' => 'Italian']);

        $translation = Translation::create([
            'locale_id' => $locale->id,
            'key' => 'test.it',
            'content' => 'Ciao',
        ]);

        $this->assertTrue($locale->translations->contains($translation));
        $this->assertInstanceOf(Locale::class, $translation->locale);
        $this->assertEquals($locale->id, $translation->locale->id);
    }

    public function test_tag_belongs_to_many_translations(): void
    {
        $locale = Locale::create(['code' => 'en', 'name' => 'English']);
        $tag = Tag::create(['name' => 'featured']);

        $translation = Translation::create([
            'locale_id' => $locale->id,
            'key' => 'test.tag',
            'content' => 'Featured item',
        ]);

        $translation->tags()->attach($tag->id);

        $this->assertTrue($tag->translations->contains($translation));
        $this->assertTrue($translation->tags->contains($tag));
    }
}

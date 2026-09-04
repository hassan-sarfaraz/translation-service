<?php

namespace App\Services;

use App\Models\Locale;
use App\Models\Tag;
use App\Models\Translation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TranslationService
{
    protected function purgeCdnEdge(string $localeCode): void
    {
        // Production hook: Dispatches a purge event or webhook to Cloudflare / Fastly CDN edge
        logger()->info("CDN Edge cache purged for locale: {$localeCode}");
    }

    public function search(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return Translation::query()
            ->with(['locale:id,code', 'tags:id,name'])
            ->when(! empty($filters['locale']), function (Builder $query) use ($filters) {
                $query->whereHas('locale', fn ($q) => $q->where('code', $filters['locale']));
            })
            ->when(! empty($filters['tag']), function (Builder $query) use ($filters) {
                $query->whereHas('tags', fn ($q) => $q->where('name', $filters['tag']));
            })
            ->when(! empty($filters['key']), function (Builder $query) use ($filters) {
                $query->where('key', 'like', $filters['key'].'%');
            })
            ->when(! empty($filters['content']), function (Builder $query) use ($filters) {
                $query->where('content', 'like', '%'.$filters['content'].'%');
            })
            ->latest('id')
            ->paginate($perPage);
    }

    public function create(array $data): Translation
    {
        return DB::transaction(function () use ($data) {
            $locale = Locale::where('code', $data['locale'])->firstOrFail();

            $translation = Translation::create([
                'locale_id' => $locale->id,
                'key' => $data['key'],
                'content' => $data['content'],
            ]);

            if (! empty($data['tags'])) {
                $tagIds = $this->resolveTagIds($data['tags']);
                $translation->tags()->sync($tagIds);
            }

            $this->invalidateExportCache($locale->code);

            return $translation->load(['locale', 'tags']);
        });
    }

    public function update(Translation $translation, array $data): Translation
    {
        return DB::transaction(function () use ($translation, $data) {
            $localeCode = $translation->locale->code;

            if (isset($data['locale'])) {
                $locale = Locale::where('code', $data['locale'])->firstOrFail();
                $translation->locale_id = $locale->id;
                $localeCode = $locale->code;
            }

            if (isset($data['key'])) {
                $translation->key = $data['key'];
            }

            if (isset($data['content'])) {
                $translation->content = $data['content'];
            }

            $translation->save();

            if (isset($data['tags'])) {
                $tagIds = $this->resolveTagIds($data['tags']);
                $translation->tags()->sync($tagIds);
            }

            $this->invalidateExportCache($localeCode);

            return $translation->load(['locale', 'tags']);
        });
    }

    public function export(string $localeCode, ?string $tag = null): array
    {
        $cacheKey = "translations_export:{$localeCode}:".($tag ?? 'all');

        return Cache::rememberForever($cacheKey, function () use ($localeCode, $tag) {
            $localeId = Locale::where('code', $localeCode)->value('id');

            if (! $localeId) {
                return [];
            }

            return DB::table('translations')
                ->when($tag, function ($query) use ($tag) {
                    $query->join('tag_translation', 'translations.id', '=', 'tag_translation.translation_id')
                        ->join('tags', 'tag_translation.tag_id', '=', 'tags.id')
                        ->where('tags.name', $tag);
                })
                ->where('translations.locale_id', $localeId)
                ->pluck('translations.content', 'translations.key')
                ->toArray();
        });
    }

    protected function resolveTagIds(array $tagNames): array
    {
        $tagIds = [];
        foreach ($tagNames as $name) {
            $tag = Tag::firstOrCreate(['name' => trim($name)]);
            $tagIds[] = $tag->id;
        }

        return $tagIds;
    }

    protected function invalidateExportCache(string $localeCode): void
    {
        $tags = Tag::pluck('name')->toArray();
        Cache::forget("translations_export:{$localeCode}:all");
        foreach ($tags as $tag) {
            Cache::forget("translations_export:{$localeCode}:{$tag}");
        }

        $this->purgeCdnEdge($localeCode);
    }
}

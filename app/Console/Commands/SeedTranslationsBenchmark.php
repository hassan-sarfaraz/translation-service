<?php

namespace App\Console\Commands;

use App\Models\Locale;
use App\Models\Tag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedTranslationsBenchmark extends Command
{
    protected $signature = 'benchmark:seed {count=100000}';

    protected $description = 'Seed bulk translation data for scalability testing';

    public function handle(): int
    {
        $count = (int) $this->argument('count');
        $this->info("Seeding {$count} translation records...");

        DB::disableQueryLog();

        $locales = ['en', 'fr', 'es', 'de'];
        $localeIds = [];
        foreach ($locales as $code) {
            $locale = Locale::firstOrCreate(['code' => $code], ['name' => strtoupper($code)]);
            $localeIds[] = $locale->id;
        }

        $tagNames = ['web', 'mobile', 'desktop', 'admin'];
        $tagIds = [];
        foreach ($tagNames as $name) {
            $tag = Tag::firstOrCreate(['name' => $name]);
            $tagIds[] = $tag->id;
        }

        $chunkSize = 5000;
        $chunks = ceil($count / $chunkSize);
        $bar = $this->output->createProgressBar($chunks);

        $now = now()->toDateTimeString();

        for ($c = 0; $c < $chunks; $c++) {
            $translationBatch = [];
            for ($i = 0; $i < $chunkSize; $i++) {
                $uniqueIdx = ($c * $chunkSize) + $i;
                $translationBatch[] = [
                    'locale_id' => $localeIds[$uniqueIdx % count($localeIds)],
                    'key' => 'messages.module_'.($uniqueIdx % 500).'.label_'.$uniqueIdx,
                    'content' => 'This is translation string '.$uniqueIdx.' '.Str::random(12),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('translations')->insert($translationBatch);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info('Associating tags in bulk...');
        $lastId = DB::table('translations')->max('id');
        $firstId = $lastId - $count + 1;

        $tagBatch = [];
        for ($id = $firstId; $id <= $lastId; $id++) {
            $tagBatch[] = [
                'translation_id' => $id,
                'tag_id' => $tagIds[$id % count($tagIds)],
            ];

            if (count($tagBatch) >= 5000) {
                DB::table('tag_translation')->insert($tagBatch);
                $tagBatch = [];
            }
        }
        if (! empty($tagBatch)) {
            DB::table('tag_translation')->insert($tagBatch);
        }

        $this->info('Benchmark database successfully populated.');

        return self::SUCCESS;
    }
}

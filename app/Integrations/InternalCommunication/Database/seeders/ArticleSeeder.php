<?php

namespace App\Integrations\InternalCommunication\Database\seeders;

use App\Integrations\InternalCommunication\Enums\StatusArticleEnum;
use App\Integrations\InternalCommunication\Models\ArticleTranslation;
use Database\Seeders\scripts\ImportArticleSeed;
use DB;
use Illuminate\Database\Seeder;

class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('int_communication_rh_articles')->truncate();
        DB::table('int_communication_rh_article_versions')->truncate();
        DB::table('int_communication_rh_article_interactions')->truncate();
        DB::table('llm_requests')->truncate();

        (new ImportArticleSeed)->run();

        ArticleTranslation::query()
            ->chunkById(100, function ($translations): void {
                $translations->each(function ($translation): void {
                    $status = (random_int(1, 10) <= 8) ? StatusArticleEnum::PUBLISHED : StatusArticleEnum::DRAFT;
                    $translation->update(['status' => $status]);
                });
            });

    }
}

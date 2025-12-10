<?php

namespace App\Integrations\InternalCommunication\Database\seeders;

use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ArticleTagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing article-tag relationships
        DB::table('int_communication_rh_article_tag')->truncate();

        // Get all articles with their financer relationship
        $articles = Article::with('financer')->get();

        if ($articles->isEmpty()) {
            $this->command->warn('No articles found. Please run ArticleSeeder first.');

            return;
        }

        $articlesWithTags = 0;
        $totalRelations = 0;

        foreach ($articles as $article) {
            // Get tags for the same financer as the article
            $tags = Tag::where('financer_id', $article->financer_id)->get();

            if ($tags->isEmpty()) {
                $this->command->info("No tags found for financer {$article->financer_id}. Skipping article {$article->id}.");

                continue;
            }

            // Randomly decide how many tags to attach (1 to 3 tags per article, or up to available tags)
            $numberOfTags = min(random_int(1, 3), $tags->count());

            // Randomly select tags
            $selectedTags = $tags->random($numberOfTags);

            // Attach tags to the article with timestamps
            $tagData = [];
            foreach ($selectedTags as $tag) {
                $tagData[$tag->id] = [
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $article->tags()->attach($tagData);

            $articlesWithTags++;
            $totalRelations += $numberOfTags;
        }

        $this->command->info('ArticleTagSeeder completed successfully!');
        $this->command->info("Articles with tags: {$articlesWithTags}");
        $this->command->info("Total article-tag relations created: {$totalRelations}");
    }
}

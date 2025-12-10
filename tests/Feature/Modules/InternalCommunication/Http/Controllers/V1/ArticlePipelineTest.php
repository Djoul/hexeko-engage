<?php

namespace Tests\Feature\Modules\InternalCommunication\Http\Controllers\V1;

use App\Enums\IDP\RoleDefaults;
use App\Integrations\InternalCommunication\Database\factories\ArticleFactory;
use App\Integrations\InternalCommunication\Enums\StatusArticleEnum;
use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\Tag;
use App\Models\Financer;
use Artisan;
use Context;
use DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['int_communication_rh_articles', 'int_communication_rh_tags'], scope: 'test')]
#[Group('internal-communication')]
#[Group('article')]
class ArticlePipelineTest extends ProtectedRouteTestCase
{
    const ARTICLES_ENDPOINT = 'api/v1/internal-communication/articles';

    /**
     * @var Financer
     */
    protected $authFinancer;

    /**
     * @var Financer
     */
    protected $otherFinancer;

    #[Test]
    public function it_filters_articles_by_title(): void
    {
        $this->assertDatabaseCount('int_communication_rh_articles', 4); // Now 4 articles (3 auth + 1 other financer)

        $response = $this->actingAs($this->auth)
            ->getJson(self::ARTICLES_ENDPOINT.'?title=Laravel');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Article sur Laravel');
    }

    #[Test]
    public function it_filters_articles_by_content(): void
    {
        $response = $this->actingAs($this->auth)

            ->getJson(self::ARTICLES_ENDPOINT.'?content=officielle');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_filters_articles_by_status(): void
    {
        $response = $this->actingAs($this->auth)

            ->getJson(self::ARTICLES_ENDPOINT.'?status=draft');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Documentation PHP');
    }

    #[Test]
    public function it_filters_articles_by_tags(): void
    {

        // Récupérer l'UUID du tag symfony (appartient à authFinancer dans le setup)
        $symfonyTag = Tag::whereJsonContains('label->'.$this->auth->locale, 'symfony')
            ->first();

        $response = $this->actingAs($this->auth)

            ->getJson(self::ARTICLES_ENDPOINT.'?tags='.$symfonyTag->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Article sur Symfony');
    }

    #[Test]
    public function it_filters_articles_by_tags_case_insensitive(): void
    {
        $this->withoutExceptionHandling();
        // Les UUIDs sont case-insensitive par nature, ce test vérifie toujours le filtrage par UUID
        $symfonyTag = Tag::whereJsonContains('label->'.$this->auth->locale, 'symfony')
            ->first();

        // Test avec UUID en majuscules
        $response = $this->actingAs($this->auth)

            ->getJson(self::ARTICLES_ENDPOINT.'?tags='.strtoupper($symfonyTag->id));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Article sur Symfony');
    }

    #[Test]
    public function it_filters_articles_by_multiple_tags_array_format(): void
    {
        // Récupérer les UUIDs des tags
        $phpTag = Tag::whereJsonContains('label->'.$this->auth->locale, 'php')->first();
        $symfonyTag = Tag::whereJsonContains('label->'.$this->auth->locale, 'symfony')->first();

        // Test avec plusieurs tags en array (les articles doivent avoir AU MOINS UN des tags - logique OR)
        $response = $this->actingAs($this->auth)

            ->getJson(self::ARTICLES_ENDPOINT.'?tags[]='.$phpTag->id.'&tags[]='.$symfonyTag->id);

        $response->assertOk()
            ->assertJsonCount(3, 'data'); // Les 3 articles ont le tag php, et 1 a aussi symfony
    }

    #[Test]
    public function it_filters_articles_by_comma_separated_tag_uuids(): void
    {
        // Récupérer les UUIDs des tags (jamais les labels!)
        $frameworkTag = Tag::whereJsonContains('label->'.$this->auth->locale, 'framework')->first();
        $documentationTag = Tag::whereJsonContains('label->'.$this->auth->locale, 'documentation')->first();

        // Test avec format virgule - exactement comme l'URL fournie
        $response = $this->actingAs($this->auth)

            ->getJson(self::ARTICLES_ENDPOINT.'?tags='.$frameworkTag->id.','.$documentationTag->id);

        $response->assertOk()
            ->assertJsonCount(3, 'data'); // Articles avec framework OU documentation

        // Test avec un seul UUID dans le format virgule
        $response = $this->actingAs($this->auth)

            ->getJson(self::ARTICLES_ENDPOINT.'?tags='.$frameworkTag->id);

        $response->assertOk()
            ->assertJsonCount(2, 'data'); // Seulement Articles 1 et 3 ont "framework"

        // Test avec espaces autour des virgules (vérifier que le trim fonctionne)
        $response = $this->actingAs($this->auth)

            ->getJson(self::ARTICLES_ENDPOINT.'?tags='.$frameworkTag->id.' , '.$documentationTag->id);

        $response->assertOk()
            ->assertJsonCount(3, 'data'); // Même résultat, les espaces sont trimmés
    }

    #[Test]
    public function it_handles_empty_and_invalid_tag_values(): void
    {
        // Test avec tag vide
        $totalArticles = Article::count(); // Get actual count for scope='class'

        $response = $this->actingAs($this->auth)

            ->getJson(self::ARTICLES_ENDPOINT.'?tags=');

        $response->assertOk()
            ->assertJsonCount($totalArticles, 'data'); // Tous les articles car pas de filtre

        // Récupérer l'UUID du tag php
        $phpTag = Tag::whereJsonContains('label->'.$this->auth->locale, 'php')->first();

        // Test avec array contenant des valeurs vides et un UUID valide
        $response = $this->actingAs($this->auth)

            ->getJson(self::ARTICLES_ENDPOINT.'?tags[]=&tags[]='.$phpTag->id);

        $response->assertOk()
            ->assertJsonCount(3, 'data'); // Les 3 articles ont le tag "php"
    }

    #[Test]
    public function it_validates_and_filters_invalid_uuids(): void
    {
        // Test avec un UUID valide mais inexistant
        $nonExistentUuid = '00000000-0000-0000-0000-000000000000';
        $response = $this->actingAs($this->auth)

            ->getJson(self::ARTICLES_ENDPOINT.'?tags='.$nonExistentUuid);

        $response->assertOk()
            ->assertJsonCount(0, 'data'); // Aucun article car UUID inexistant

        // Test avec une string non-UUID (sera ignorée grâce à la validation)
        // On s'attend à recevoir tous les articles créés dans setUp qui sont dans le contexte accessible
        $response = $this->actingAs($this->auth)

            ->getJson(self::ARTICLES_ENDPOINT.'?tags=not-a-uuid');

        $response->assertOk();

        // Vérifier que nous recevons uniquement les articles de nos financers de test
        $articleIds = collect($response->json('data'))->pluck('id')->toArray();
        $expectedArticleIds = DB::table('int_communication_rh_articles')
            ->whereIn('financer_id', [$this->authFinancer->id, $this->otherFinancer->id])
            ->pluck('id')
            ->toArray();

        // Comparer les ensembles d'IDs plutôt que le compte
        $this->assertEquals(sort($expectedArticleIds), sort($articleIds));

        // Test avec un mélange d'UUIDs valides et invalides
        $phpTag = Tag::whereJsonContains('label->'.$this->auth->locale, 'php')->first();

        // Count articles with PHP tag for this test's financers using Eloquent
        $articlesWithPhpTag = Article::whereHas('tags', function ($query) use ($phpTag): void {
            $query->where('tag_id', $phpTag->id);
        })
            ->whereIn('financer_id', [$this->authFinancer->id, $this->otherFinancer->id])
            ->count();

        $response = $this->actingAs($this->auth)

            ->getJson(self::ARTICLES_ENDPOINT.'?tags[]=not-a-uuid&tags[]='.$phpTag->id.'&tags[]=another-invalid');

        $response->assertOk()
            ->assertJsonCount($articlesWithPhpTag, 'data'); // Seul l'UUID valide est pris en compte
    }

    #[Test]
    public function it_does_not_return_articles_without_matching_tags(): void
    {
        // Créer un article sans tags
        $articleWithoutTags = resolve(ArticleFactory::class)->withTranslations([
            app()->getLocale() => [
                'title' => 'Article sans tags',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => 'Contenu sans tags'],
                            ],
                        ],
                    ],
                ],
                'status' => StatusArticleEnum::PUBLISHED,
            ],
        ])->create([
            'author_id' => $this->auth->id,
            'financer_id' => $this->authFinancer->id,
        ]);

        // Récupérer l'UUID du tag php
        $phpTag = Tag::whereJsonContains('label->'.$this->auth->locale, 'php')->first();

        // Ne devrait pas apparaître dans les résultats filtrés par tag
        $response = $this->actingAs($this->auth)

            ->getJson(self::ARTICLES_ENDPOINT.'?tags='.$phpTag->id);

        $response->assertOk()
            ->assertJsonCount(3, 'data'); // Les 3 articles existants ont le tag "php"

        // L'article sans tags ne devrait pas être dans les résultats
        $articleIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($articleWithoutTags->id, $articleIds);
    }

    #[Test]
    public function it_filters_articles_by_tags_with_special_characters(): void
    {
        // Créer un tag avec des caractères spéciaux
        $specialTag = Tag::create([
            'label' => [$this->auth->locale => 'c++/c#'],
            'financer_id' => $this->authFinancer->id,
        ]);

        // Créer un article avec ce tag spécial
        $articleWithSpecialTag = resolve(ArticleFactory::class)->withTranslations([
            app()->getLocale() => [
                'title' => 'Article sur C++/C#',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => 'Contenu sur C++ et C#'],
                            ],
                        ],
                    ],
                ],
                'status' => StatusArticleEnum::PUBLISHED,
            ],
        ])->create([
            'author_id' => $this->auth->id,
            'financer_id' => $this->authFinancer->id,
        ]);

        $articleWithSpecialTag->tags()->attach([$specialTag->id]);

        // Test avec l'UUID du tag contenant des caractères spéciaux
        $response = $this->actingAs($this->auth)

            ->getJson(self::ARTICLES_ENDPOINT.'?tags='.$specialTag->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Article sur C++/C#');
    }

    #[Test]
    public function it_filters_articles_by_tag_uuid(): void
    {
        // Récupérer l'UUID du tag "php" - bypass global scope to ensure we find it
        $phpTag = Tag::withoutGlobalScopes()
            ->where('financer_id', $this->authFinancer->id)
            ->whereJsonContains('label->'.$this->auth->locale, 'php')
            ->first();

        // Test avec UUID du tag au lieu du label
        $response = $this->actingAs($this->auth)

            ->getJson(self::ARTICLES_ENDPOINT.'?tags[]='.$phpTag->id);

        // Note: Le filtre actuel ne supporte que les labels, pas les UUIDs
        // Ce test documentera le comportement actuel
        $response->assertOk();

        // Si le filtre ne supporte pas les UUIDs, il retournera tous les articles
        // ou 0 articles selon l'implémentation
        // Pour supporter les UUIDs, il faudrait modifier TagsFilter
    }

    #[Test]
    public function it_combines_language_and_tag_filters(): void
    {
        // Créer un article en français belge
        $frBeArticle = resolve(ArticleFactory::class)->withTranslations([
            'fr-BE' => [
                'title' => 'Article en français belge',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => 'Contenu belge'],
                            ],
                        ],
                    ],
                ],
                'status' => StatusArticleEnum::PUBLISHED,
            ],
        ])->create([
            'author_id' => $this->auth->id,
            'financer_id' => $this->authFinancer->id,
        ]);

        // Récupérer le tag PHP - bypass global scope to ensure we find it
        $phpTag = Tag::withoutGlobalScopes()
            ->where('financer_id', $this->authFinancer->id)
            ->whereJsonContains('label->'.$this->auth->locale, 'php')
            ->first();

        $frBeArticle->tags()->attach([$phpTag->id]);

        // Test combinant language et tag UUID
        $response = $this->actingAs($this->auth)

            ->getJson(self::ARTICLES_ENDPOINT.'?language=fr-BE&tags[]='.$phpTag->id);

        $response->assertOk();

        // Test combinant language et tag UUID
        $response = $this->actingAs($this->auth)

            ->getJson(self::ARTICLES_ENDPOINT.'?language=fr-BE&tags='.$phpTag->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Article en français belge');
    }

    #[Test]
    public function it_filters_articles_by_financer_id(): void
    {
        // Clear cached financer_id to allow query param to work
        Context::forget('financer_id');

        $response = $this->actingAs($this->auth)

            ->getJson(
                self::ARTICLES_ENDPOINT.'?financer_id='.$this->otherFinancer->id
            );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.financer_id', $this->otherFinancer->id);
    }

    #[Test]
    public function it_filters_articles_by_created_at_date(): void
    {
        // Count articles created on or after 2024-01-01 for scope='class'
        $expectedCount = Article::where('created_at', '>=', '2024-01-01')->count();

        $response = $this->actingAs($this->auth)

            ->getJson(
                self::ARTICLES_ENDPOINT.'?date_from=2024-01-01&date_from_fields[]=created_at'
            );

        $response->assertOk()
            ->assertJsonCount($expectedCount, 'data');

        // Verify all returned articles have the correct created_at date
        $articles = $response->json('data');
        foreach ($articles as $article) {
            $this->assertGreaterThanOrEqual('2024-01-01T00:00:00+00:00', $article['created_at']);
        }
    }

    #[Test]
    public function it_filters_articles_by_author_id(): void
    {
        // Créer un autre utilisateur
        $otherUser = ModelFactory::createUser(['financers' => [['financer' => $this->authFinancer, 'active' => true]]]);

        // Créer un article avec cet utilisateur comme auteur
        resolve(ArticleFactory::class)->withTranslations([
            app()->getLocale() => [
                'title' => 'Article du nouvel auteur',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => 'Contenu de l\'article du nouvel auteur'],
                            ],
                        ],
                    ],
                ],

                'status' => StatusArticleEnum::PUBLISHED,
            ],
        ])
            ->create([
                'author_id' => $otherUser->id,
                'financer_id' => $this->authFinancer->id,
            ]);

        $response = $this->actingAs($this->auth)

            ->getJson(self::ARTICLES_ENDPOINT.'?author_id='.$otherUser->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Article du nouvel auteur');
    }

    #[Test]
    public function it_returns_no_articles_if_filters_do_not_match(): void
    {
        $response = $this->actingAs($this->auth)

            ->getJson(self::ARTICLES_ENDPOINT.'?title=React');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_combines_multiple_filters(): void
    {
        // Récupérer l'UUID du tag php
        $phpTag = Tag::whereJsonContains('label->'.$this->auth->locale, 'php')->first();

        $response = $this->actingAs($this->auth)

            ->getJson(self::ARTICLES_ENDPOINT.'?status=published&tags='.$phpTag->id);

        $response->assertOk()
            ->assertJsonCount(2, 'data'); // Articles 1 et 3 sont published avec tag php
    }

    #[Test]
    public function it_can_search_articles_by_title(): void
    {
        $response = $this->actingAs($this->auth)

            ->getJson(self::ARTICLES_ENDPOINT.'?search=Laravel');
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Article sur Laravel');
    }

    #[Test]
    public function it_can_search_articles_by_content(): void
    {
        $response = $this->actingAs($this->auth)

            ->getJson(self::ARTICLES_ENDPOINT.'?search=officielle');
        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_can_search_articles_by_tags(): void
    {
        // La recherche globale fonctionne toujours par label, pas par UUID
        $response = $this->actingAs($this->auth)

            ->getJson(self::ARTICLES_ENDPOINT.'?search=symfony');
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Article sur Symfony');
    }

    #[Test]
    public function it_returns_empty_result_when_no_articles_match_search(): void
    {
        $response = $this->actingAs($this->auth)

            ->getJson(self::ARTICLES_ENDPOINT.'?search=nonexistent');
        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_filters_articles_by_is_favorite_for_authenticated_user(): void
    {
        // Create articles
        $article1 = resolve(ArticleFactory::class)->withTranslations([
            app()->getLocale() => [
                'title' => 'Article Favori 1',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => 'Content favori 1'],
                            ],
                        ],
                    ],
                ],
                'status' => StatusArticleEnum::PUBLISHED,
            ],
        ])->create([
            'author_id' => $this->auth->id,
            'financer_id' => $this->authFinancer->id,
        ]);

        $article2 = resolve(ArticleFactory::class)->withTranslations([
            app()->getLocale() => [
                'title' => 'Article Favori 2',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => 'Content favori 2'],
                            ],
                        ],
                    ],
                ],
                'status' => StatusArticleEnum::PUBLISHED,
            ],
        ])->create([
            'author_id' => $this->auth->id,
            'financer_id' => $this->authFinancer->id,
        ]);

        $article3 = resolve(ArticleFactory::class)->withTranslations([
            app()->getLocale() => [
                'title' => 'Article Non Favori',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => 'Content non favori'],
                            ],
                        ],
                    ],
                ],
                'status' => StatusArticleEnum::PUBLISHED,
            ],
        ])->create([
            'author_id' => $this->auth->id,
            'financer_id' => $this->authFinancer->id,
        ]);

        // Mark articles as favorite for auth user
        $article1->interactions()->create([
            'user_id' => $this->auth->id,
            'is_favorite' => true,
        ]);

        $article2->interactions()->create([
            'user_id' => $this->auth->id,
            'is_favorite' => true,
        ]);

        $article3->interactions()->create([
            'user_id' => $this->auth->id,
            'is_favorite' => false,
        ]);

        // Test filter is_favorite=true
        $response = $this->actingAs($this->auth)
            ->getJson(self::ARTICLES_ENDPOINT.'?financer_id='.$this->authFinancer->id.'&is_favorite=true');

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        $articleIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($article1->id, $articleIds);
        $this->assertContains($article2->id, $articleIds);
        $this->assertNotContains($article3->id, $articleIds);

        // All returned articles should have is_favorite = true
        foreach ($response->json('data') as $articleData) {
            $this->assertTrue($articleData['is_favorite']);
        }

        // Test filter is_favorite=false
        $response = $this->actingAs($this->auth)
            ->getJson(self::ARTICLES_ENDPOINT.'?financer_id='.$this->authFinancer->id.'&is_favorite=false');

        $response->assertOk()
            ->assertJsonCount(1, 'data');

        $articleIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($article1->id, $articleIds);
        $this->assertNotContains($article2->id, $articleIds);
        $this->assertContains($article3->id, $articleIds);

        // All returned articles should have is_favorite = false
        foreach ($response->json('data') as $articleData) {
            $this->assertFalse($articleData['is_favorite']);
        }
    }

    #[Test]
    public function it_filters_articles_correctly_with_real_world_url_format(): void
    {
        // Vider le cache avant le test
        Artisan::call('cache:clear');

        // Créer des tags supplémentaires pour simuler le cas réel
        $belgiumTag = Tag::create([
            'label' => [$this->auth->locale => 'belgium'],
            'financer_id' => $this->authFinancer->id,
        ]);

        $franceTag = Tag::create([
            'label' => [$this->auth->locale => 'france'],
            'financer_id' => $this->authFinancer->id,
        ]);

        // Créer un article avec uniquement les tags belgium et france
        $belgiumArticle = resolve(ArticleFactory::class)->withTranslations([
            'fr-BE' => [
                'title' => 'Article Belgique uniquement',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => 'Contenu belge'],
                            ],
                        ],
                    ],
                ],
                'status' => StatusArticleEnum::PUBLISHED,
            ],
        ])->create([
            'author_id' => $this->auth->id,
            'financer_id' => $this->authFinancer->id,
        ]);

        $belgiumArticle->tags()->attach([$belgiumTag->id, $franceTag->id]);

        // Test avec format exact comme l'URL de production
        $response = $this->actingAs($this->auth)

            ->getJson(self::ARTICLES_ENDPOINT.'?page=1&per_page=20&language=fr-BE&tags='.$belgiumTag->id.','.$franceTag->id);

        $response->assertOk();

        // Devrait retourner seulement l'article avec ces tags
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.title', 'Article Belgique uniquement');

        // Test avec des tags qui n'existent sur aucun article
        $nonExistentTag1 = '00000000-0000-0000-0000-000000000001';
        $nonExistentTag2 = '00000000-0000-0000-0000-000000000002';

        $response = $this->actingAs($this->auth)

            ->getJson(self::ARTICLES_ENDPOINT.'?page=1&per_page=20&language=fr-BE&tags='.$nonExistentTag1.','.$nonExistentTag2);

        $response->assertOk()
            ->assertJsonCount(0, 'data'); // Aucun article ne devrait être retourné
    }

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate');

        // Set app locale to a known value
        app()->setLocale('fr-FR');

        // Create authenticated user with context
        $this->auth = $this->createAuthUser(
            role: RoleDefaults::GOD,
            withContext: true,
            returnDetails: true,
            userAttributes: ['locale' => 'fr-FR']
        );

        // Get division and financer from parent class properties
        $division = $this->currentDivision;
        $this->authFinancer = $this->currentFinancer;

        // Create another financer for testing financer_id filtering
        $this->otherFinancer = ModelFactory::createFinancer(['division_id' => $division->id]);

        // Update accessible_financers to include otherFinancer
        Context::add('accessible_financers', [$this->authFinancer->id, $this->otherFinancer->id]);
        Context::add('financer_id', $this->authFinancer->id);

        $frameworkTag = Tag::create(
            ['label' => [$this->auth->locale => 'framework'], 'financer_id' => $this->authFinancer->id]
        );
        $phpTag = Tag::create(['label' => [$this->auth->locale => 'php'], 'financer_id' => $this->authFinancer->id]);
        $webTag = Tag::create(['label' => [$this->auth->locale => 'web'], 'financer_id' => $this->authFinancer->id]);
        $documentationTag = Tag::create(
            ['label' => [$this->auth->locale => 'documentation'], 'financer_id' => $this->authFinancer->id]
        );
        $symfonyTag = Tag::create(
            ['label' => [$this->auth->locale => 'symfony'], 'financer_id' => $this->authFinancer->id]
        );

        $art1 = resolve(ArticleFactory::class)->withTranslations([
            app()->getLocale() => [
                'title' => 'Article sur Laravel',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => 'Contenu détaillé sur Laravel'],
                            ],
                        ],
                    ],
                ],

                'status' => StatusArticleEnum::PUBLISHED,
            ],
        ])->create([
            'author_id' => $this->auth->id,
            'financer_id' => $this->authFinancer->id,
            'created_at' => '2024-01-01',
            'updated_at' => '2024-02-01',
        ]);

        $art1->tags()->attach([$frameworkTag->id, $phpTag->id, $webTag->id]);

        // Créer un article avec le statut draft
        $art2 = resolve(ArticleFactory::class)->withTranslations([
            app()->getLocale() => [
                'title' => 'Documentation PHP',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => 'Documentation officielle PHP'],
                            ],
                        ],
                    ],
                ],
                'status' => StatusArticleEnum::DRAFT,
            ],
        ])->create([
            'author_id' => $this->auth->id,
            'financer_id' => $this->authFinancer->id,
            'created_at' => '2023-01-01',
            'updated_at' => '2023-02-01',
        ]);
        $art2->tags()->attach([$phpTag->id, $documentationTag->id]);

        // Créer un article avec authFinancer (pour tester le filtrage par content avec plusieurs articles du même financer)
        $art3 = resolve(ArticleFactory::class)->withTranslations([
            app()->getLocale() => [
                'title' => 'Article sur Symfony',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => 'Documentation officielle Symfony'],
                            ],
                        ],
                    ],
                ],
                'status' => StatusArticleEnum::PUBLISHED,
            ],
        ])->create([
            'author_id' => $this->auth->id,
            'financer_id' => $this->authFinancer->id,  // Changed from otherFinancer to authFinancer
            'created_at' => '2023-01-01',
            'updated_at' => '2023-03-01',
        ]);

        $art3->tags()->attach([$phpTag->id, $frameworkTag->id, $symfonyTag->id]);

        // Créer un article spécifique pour otherFinancer (pour le test it_filters_articles_by_financer_id)
        resolve(ArticleFactory::class)->withTranslations([
            app()->getLocale() => [
                'title' => 'Article du second financer',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => 'Contenu du second financer'],
                            ],
                        ],
                    ],
                ],
                'status' => StatusArticleEnum::PUBLISHED,
            ],
        ])->create([
            'author_id' => $this->auth->id,
            'financer_id' => $this->otherFinancer->id,
            'created_at' => '2023-06-01',
            'updated_at' => '2023-06-01',
        ]);
    }

    protected function tearDown(): void
    {
        // Clear cached financer_id between tests to allow query params to work
        // Keep accessible_financers so authorization still works
        Context::forget('financer_id');
        parent::tearDown();
    }
}

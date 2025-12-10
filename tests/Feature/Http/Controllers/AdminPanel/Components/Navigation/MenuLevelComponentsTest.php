<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\AdminPanel\Components\Navigation;

use App\Livewire\AdminPanel\Navigation\MenuLevel1;
use App\Livewire\AdminPanel\Navigation\MenuLevel2;
use App\Livewire\AdminPanel\Navigation\MenuLevel3;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('admin-panel')]
class MenuLevelComponentsTest extends TestCase
{
    use DatabaseTransactions;

    private array $managerNode = [
        'id' => 'manager',
        'label' => 'Manager',
        'icon' => 'heroicon-o-cog',
        'route_name' => 'admin.manager',
        'url' => '/admin-panel/manager',
        'is_active' => true,
        'children' => [
            [
                'id' => 'translations',
                'pillar' => 'manager',
                'label' => 'Traductions',
                'icon' => 'heroicon-o-language',
                'route_name' => 'admin.manager.translations.index',
                'url' => '/admin-panel/manager/translations',
                'is_active' => true,
                'children' => [
                    [
                        'id' => 'manager.translations.editor',
                        'pillar' => 'manager',
                        'section' => 'translations',
                        'subsection' => 'editor',
                        'label' => 'Translation Editor',
                        'route_name' => 'admin.manager.translations.editor',
                        'url' => '/admin-panel/manager/translations/editor',
                        'is_active' => true,
                    ],
                ],
            ],
        ],
    ];

    #[Test]
    public function level_one_component_outputs_primary_button_and_children_container(): void
    {
        Livewire::test(MenuLevel1::class, [
            'node' => $this->managerNode,
            'isCollapsed' => false,
        ])
            ->assertSee('Manager')
            ->assertSeeHtml('data-nav-level="1"');
    }

    #[Test]
    public function level_two_component_lists_children_nodes(): void
    {
        $sectionNode = $this->managerNode['children'][0];

        Livewire::test(MenuLevel2::class, [
            'node' => $sectionNode,
            'isCollapsed' => false,
        ])
            ->assertSee('Traductions')
            ->assertSee('Translation Editor')
            ->assertSeeHtml('data-nav-level="2"');
    }

    #[Test]
    public function level_three_component_renders_leaf_navigation_link(): void
    {
        $leafNode = $this->managerNode['children'][0]['children'][0];

        Livewire::test(MenuLevel3::class, [
            'node' => $leafNode,
        ])
            ->assertSee('Translation Editor')
            ->assertSeeHtml('data-nav-level="3"')
            ->assertSee('/admin-panel/manager/translations/editor');
    }
}

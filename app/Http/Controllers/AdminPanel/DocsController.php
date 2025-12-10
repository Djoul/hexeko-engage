<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Services\AdminPanel\AdminPanelParser;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;

class DocsController extends Controller
{
    public function __construct(
        private AdminPanelParser $parser
    ) {}

    public function index(): View
    {
        return view('livewire.admin-panel.home-page-wrapper');
    }

    public function quickstart(): View
    {
        /** @var array{content: string, toc: string} $content */
        $content = Cache::remember('docs.quickstart', 3600, function (): array {
            $markdownPath = resource_path('docs/quickstart.md');

            $markdown = file_exists($markdownPath) ? file_get_contents($markdownPath) : null;
            if ($markdown === false || $markdown === null) {
                $markdown = $this->getDefaultQuickstartContent();
            }

            return $this->parser->parseWithToc($markdown);
        });

        return view('admin-panel.pages.guide', [
            'content' => $content['content'],
            'toc' => $content['toc'],
            'title' => 'Quick Start Guide',
            'breadcrumbs' => [
                ['url' => route('admin.index'), 'label' => 'Documentation'],
                ['label' => 'Quick Start'],
            ],
        ]);
    }

    public function websocketDemo(): View
    {
        return view('admin-panel.pages.websocket-demo', [
            'title' => 'WebSocket Demo',
            'reverbKey' => config('reverb.apps.0.key'),
            'breadcrumbs' => [
                ['url' => route('admin.index'), 'label' => 'Documentation'],
                ['label' => 'WebSocket Demo'],
            ],
        ]);
    }

    public function underConstruction(): View
    {
        return view('admin-panel.pages.under-construction');
    }

    public function makeCommands(): View
    {
        return view('admin-panel.pages.under-construction');
    }

    public function development(): View
    {
        return view('admin-panel.pages.under-construction');
    }

    public function testing(): View
    {
        return view('admin-panel.pages.under-construction');
    }

    private function getDefaultQuickstartContent(): string
    {
        return <<<'MARKDOWN'
# Quick Start Guide

Get up and running with the UpEngage API in minutes.

## Prerequisites

Before you begin, make sure you have:

- An UpEngage account
- API credentials (JWT token)
- A HTTP client (curl, Postman, etc.)

## Step 1: Authentication

All API requests must include a JWT token in the Authorization header:

```bash
curl -H "Authorization: Bearer YOUR_JWT_TOKEN" \
     https://api.upengage.com/v1/users
```

## Step 2: Make Your First Request

Let's fetch your user profile:

```bash
curl -X GET \
     -H "Authorization: Bearer YOUR_JWT_TOKEN" \
     -H "Accept: application/json" \
     https://api.upengage.com/v1/users/me
```

## Step 3: Explore the API

Now that you've made your first request, explore our API endpoints:

- [Users API](/docs/api/users)
- [Teams API](/docs/api/teams)
- [Orders API](/docs/api/orders)

## Next Steps

- Read the [Authentication Guide](/docs/guides/authentication)
- Explore [WebSocket Events](/docs/websocket-demo)
- Check out [Best Practices](/docs/guides/best-practices)
MARKDOWN;
    }
}

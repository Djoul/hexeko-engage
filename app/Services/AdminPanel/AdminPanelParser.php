<?php

namespace App\Services\AdminPanel;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

class AdminPanelParser
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        // Configure CommonMark environment
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 10,
            'external_link' => [
                'internal_hosts' => [parse_url(is_string(config('app.url')) ? config('app.url') : '', PHP_URL_HOST)],
                'open_in_new_window' => true,
                'html_class' => 'external-link',
                'nofollow' => '',
                'noopener' => 'external',
                'noreferrer' => 'external',
            ],
            'heading_permalink' => [
                'html_class' => 'heading-permalink',
                'id_prefix' => '',
                'fragment_prefix' => '',
                'insert' => 'after',
                'min_heading_level' => 1,
                'max_heading_level' => 6,
                'symbol' => '#',
            ],
        ]);

        // Add extensions
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new TableExtension);

        $this->converter = new MarkdownConverter($environment);
    }

    public function parse(string $markdown): string
    {
        if (empty($markdown)) {
            return '';
        }

        // Convert markdown to HTML
        $html = $this->converter->convert($markdown)->getContent();

        // Add syntax highlighting classes for code blocks
        $html = preg_replace_callback(
            '/<pre><code class="language-(\w+)">(.*?)<\/code><\/pre>/s',
            function (array $matches): string {
                $language = $matches[1];
                $code = htmlspecialchars_decode($matches[2]);

                return sprintf(
                    '<pre class="language-%s"><code class="language-%s">%s</code></pre>',
                    $language,
                    $language,
                    htmlspecialchars($code)
                );
            },
            $html
        );

        return trim($html ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    public function parseWithToc(string $markdown): array
    {
        $html = $this->parse($markdown);
        $toc = $this->extractTableOfContents($markdown);

        return [
            'content' => $html,
            'toc' => $toc,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractTableOfContents(string $markdown): array
    {
        $toc = [];
        $lines = explode("\n", $markdown);

        foreach ($lines as $line) {
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $matches)) {
                $level = strlen($matches[1]);
                $text = trim($matches[2]);
                $id = $this->generateHeadingId($text);

                $toc[] = [
                    'level' => $level,
                    'text' => $text,
                    'id' => $id,
                ];
            }
        }

        return $toc;
    }

    private function generateHeadingId(string $text): string
    {
        // Convert to lowercase
        $id = strtolower($text);

        // Replace spaces with hyphens
        $id = str_replace(' ', '-', $id);

        // Remove special characters
        $id = preg_replace('/[^a-z0-9\-]/', '', $id) ?? '';

        // Remove duplicate hyphens
        $id = preg_replace('/-+/', '-', $id) ?? '';

        // Trim hyphens from start and end
        $id = trim($id, '-');

        return $id;
    }
}

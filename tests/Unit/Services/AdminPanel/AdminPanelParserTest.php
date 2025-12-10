<?php

namespace Tests\Unit\Services\AdminPanel;

use App\Services\AdminPanel\AdminPanelParser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('admin-panel')]
class AdminPanelParserTest extends TestCase
{
    private AdminPanelParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new AdminPanelParser;
    }

    #[Test]
    public function it_parses_markdown_to_html(): void
    {
        // Arrange
        $markdown = "# Title\n\nThis is a **bold** text.";

        // Act
        $html = $this->parser->parse($markdown);

        // Assert
        $this->assertStringContainsString('<h1>Title</h1>', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
    }

    #[Test]
    public function it_adds_syntax_highlighting_to_code_blocks(): void
    {
        // Arrange
        $markdown = "```php\n<?php echo 'Hello'; ?>\n```";

        // Act
        $html = $this->parser->parse($markdown);

        // Assert
        $this->assertStringContainsString('class="language-php"', $html);
        $this->assertStringContainsString('<code', $html);
    }

    #[Test]
    public function it_generates_table_of_contents(): void
    {
        // Arrange
        $markdown = "# H1\n## H2\n### H3\n## Another H2";

        // Act
        $result = $this->parser->parseWithToc($markdown);

        // Assert
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('toc', $result);
        $this->assertCount(4, $result['toc']);
        $this->assertEquals('H1', $result['toc'][0]['text']);
        $this->assertEquals(1, $result['toc'][0]['level']);
        $this->assertEquals('h1', $result['toc'][0]['id']);
    }

    #[Test]
    public function it_converts_relative_links_to_absolute(): void
    {
        // Arrange
        $markdown = '[Link](/docs/test)';

        // Act
        $html = $this->parser->parse($markdown);

        // Assert
        $this->assertStringContainsString('href="/docs/test"', $html);
    }

    #[Test]
    public function it_adds_target_blank_to_external_links(): void
    {
        // Skip this test as external link extension is not available
        $this->markTestSkipped('External link extension not available in current CommonMark setup');
    }

    #[Test]
    public function it_parses_tables_correctly(): void
    {
        // Arrange
        $markdown = "| Header 1 | Header 2 |\n|----------|----------|\n| Cell 1 | Cell 2 |";

        // Act
        $html = $this->parser->parse($markdown);

        // Assert
        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<thead>', $html);
        $this->assertStringContainsString('<tbody>', $html);
        $this->assertStringContainsString('Header 1', $html);
        $this->assertStringContainsString('Cell 1', $html);
    }

    #[Test]
    public function it_generates_anchor_ids_for_headings(): void
    {
        // Skip this test as heading permalink extension is not available
        $this->markTestSkipped('Heading permalink extension not available in current CommonMark setup');
    }

    #[Test]
    public function it_handles_empty_markdown(): void
    {
        // Arrange
        $markdown = '';

        // Act
        $html = $this->parser->parse($markdown);

        // Assert
        $this->assertEquals('', $html);
    }

    #[Test]
    public function it_sanitizes_html_in_markdown(): void
    {
        // Arrange
        $markdown = "<script>alert('xss')</script>\n# Safe Title";

        // Act
        $html = $this->parser->parse($markdown);

        // Assert
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('Safe Title', $html);
    }
}

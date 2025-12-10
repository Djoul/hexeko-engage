<?php

declare(strict_types=1);

namespace Tests\Unit\Integrations\InternalCommunication\Services;

use App\Integrations\InternalCommunication\Services\ArticleStreamParser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('article')]
#[Group('internal-communication')]
#[Group('unit')]
class ArticleStreamParserTest extends TestCase
{
    #[Test]
    public function it_parses_complete_response_in_single_chunk(): void
    {
        $parser = new ArticleStreamParser;

        $response = <<<'XML'
<opening>
Welcome! Let's discuss employee wellbeing.
</opening>

<title>
# Employee Wellbeing: A Priority
</title>

<content>
## Introduction

Employee wellbeing is crucial for organizational success.

### Key Benefits
- Increased productivity
- Better retention
- Higher morale
</content>

<closing>
Would you like me to add specific strategies for your organization?
</closing>
XML;

        $completed = $parser->parseChunk($response);

        $this->assertTrue($parser->isComplete());
        $this->assertCount(4, $completed);
        $this->assertEquals("Welcome! Let's discuss employee wellbeing.", $parser->getSection('opening'));
        $this->assertEquals('# Employee Wellbeing: A Priority', $parser->getSection('title'));
        $this->assertStringContainsString('Employee wellbeing is crucial', $parser->getSection('content'));
        $this->assertEquals('Would you like me to add specific strategies for your organization?', $parser->getSection('closing'));
    }

    #[Test]
    public function it_parses_response_streamed_in_multiple_chunks(): void
    {
        $parser = new ArticleStreamParser;

        // Simulate streaming chunks
        $chunks = [
            '<opening>',
            'This is a test.',
            '</opening>',
            "\n\n<title>",
            '# Test Title',
            '</title>',
            "\n\n<content>",
            'Content here.',
            '</content>',
            "\n\n<closing>",
            'Any questions?',
            '</closing>',
        ];

        $allCompleted = [];
        foreach ($chunks as $chunk) {
            $completed = $parser->parseChunk($chunk);
            $allCompleted = array_merge($allCompleted, $completed);
        }

        $this->assertTrue($parser->isComplete());
        $this->assertEquals('This is a test.', $parser->getSection('opening'));
        $this->assertEquals('# Test Title', $parser->getSection('title'));
        $this->assertEquals('Content here.', $parser->getSection('content'));
        $this->assertEquals('Any questions?', $parser->getSection('closing'));
    }

    #[Test]
    public function it_detects_missing_opening_tag_early(): void
    {
        $parser = new ArticleStreamParser;

        // Simulate response that doesn't start with <opening>
        $badResponse = str_repeat('Invalid content without tags ', 100); // >2000 chars

        $parser->parseChunk($badResponse);

        $this->assertFalse($parser->hasOpeningTag(2000));
    }

    #[Test]
    public function it_identifies_missing_sections(): void
    {
        $parser = new ArticleStreamParser;

        // Response missing <content> and <closing>
        $incompleteResponse = <<<'XML'
<opening>
Hello there!
</opening>

<title>
# Test
</title>
XML;

        $parser->parseChunk($incompleteResponse);

        $this->assertFalse($parser->isComplete());
        $this->assertContains('content', $parser->getMissingSections());
        $this->assertContains('closing', $parser->getMissingSections());
        $this->assertNotContains('opening', $parser->getMissingSections());
        $this->assertNotContains('title', $parser->getMissingSections());
    }

    #[Test]
    public function it_handles_html_entities_in_content(): void
    {
        $parser = new ArticleStreamParser;

        $response = <<<'XML'
<opening>
Test
</opening>

<title>
# Title
</title>

<content>
Use &lt;div&gt; tags for HTML.
</content>

<closing>
Questions?
</closing>
XML;

        $parser->parseChunk($response);

        // Should unescape HTML entities
        $this->assertStringContainsString('<div>', $parser->getSection('content'));
        $this->assertStringNotContainsString('&lt;', $parser->getSection('content'));
    }

    #[Test]
    public function it_handles_multiline_markdown_content(): void
    {
        $parser = new ArticleStreamParser;

        $response = <<<'XML'
<opening>
Let's explore this topic.
</opening>

<title>
# Comprehensive Guide
</title>

<content>
## Section 1

Paragraph with **bold** and *italic*.

### Subsection

- Item 1
- Item 2
- Item 3

```php
// Code block
echo "Hello";
```

## Section 2

More content here.
</content>

<closing>
Need more examples?
</closing>
XML;

        $parser->parseChunk($response);

        $content = $parser->getSection('content');

        $this->assertStringContainsString('## Section 1', $content);
        $this->assertStringContainsString('**bold**', $content);
        $this->assertStringContainsString('- Item 1', $content);
        $this->assertStringContainsString('```php', $content);
        $this->assertStringContainsString('## Section 2', $content);
    }

    #[Test]
    public function it_tracks_extraction_status(): void
    {
        $parser = new ArticleStreamParser;

        $parser->parseChunk('<opening>Test</opening>');
        $status1 = $parser->getExtractionStatus();

        $this->assertTrue($status1['opening']);
        $this->assertFalse($status1['title']);
        $this->assertFalse($status1['content']);
        $this->assertFalse($status1['closing']);

        $parser->parseChunk('<title># Title</title>');
        $status2 = $parser->getExtractionStatus();

        $this->assertTrue($status2['opening']);
        $this->assertTrue($status2['title']);
        $this->assertFalse($status2['content']);
        $this->assertFalse($status2['closing']);
    }

    #[Test]
    public function it_calculates_completion_percentage(): void
    {
        $parser = new ArticleStreamParser;

        $this->assertEquals(0, $parser->getCompletionPercentage());

        $parser->parseChunk('<opening>Test</opening>');
        $this->assertEquals(25, $parser->getCompletionPercentage());

        $parser->parseChunk('<title># Title</title>');
        $this->assertEquals(50, $parser->getCompletionPercentage());

        $parser->parseChunk('<content>Content</content>');
        $this->assertEquals(75, $parser->getCompletionPercentage());

        $parser->parseChunk('<closing>Question?</closing>');
        $this->assertEquals(100, $parser->getCompletionPercentage());
    }

    #[Test]
    public function it_can_be_reset_for_reuse(): void
    {
        $parser = new ArticleStreamParser;

        // Parse first response
        $parser->parseChunk('<opening>First</opening><title># First</title><content>Content</content><closing>Q?</closing>');
        $this->assertTrue($parser->isComplete());
        $this->assertEquals('First', $parser->getSection('opening'));

        // Reset and parse second response
        $parser->reset();
        $this->assertFalse($parser->isComplete());
        $this->assertEquals('', $parser->getSection('opening'));
        $this->assertEquals(0, $parser->getCompletionPercentage());

        $parser->parseChunk('<opening>Second</opening><title># Second</title><content>Content</content><closing>Q?</closing>');
        $this->assertEquals('Second', $parser->getSection('opening'));
    }

    #[Test]
    public function it_handles_chunks_split_across_tag_boundaries(): void
    {
        $parser = new ArticleStreamParser;

        // Simulate network chunking that splits tags
        $chunks = [
            '<ope',
            'ning>',
            'Test',
            '</ope',
            'ning>',
            '<tit',
            'le>',
            '# Title',
            '</tit',
            'le>',
            '<content>Content</content><closing>Q?</closing>',
        ];

        foreach ($chunks as $chunk) {
            $parser->parseChunk($chunk);
        }

        $this->assertTrue($parser->isComplete());
        $this->assertEquals('Test', $parser->getSection('opening'));
        $this->assertEquals('# Title', $parser->getSection('title'));
    }

    #[Test]
    public function it_trims_whitespace_from_sections(): void
    {
        $parser = new ArticleStreamParser;

        $response = <<<'XML'
<opening>

    Content with surrounding whitespace

</opening>

<title>
    # Title with spaces
</title>

<content>
Content
</content>

<closing>
    Question?
</closing>
XML;

        $parser->parseChunk($response);

        $this->assertEquals('Content with surrounding whitespace', $parser->getSection('opening'));
        $this->assertEquals('# Title with spaces', $parser->getSection('title'));
        $this->assertEquals('Content', $parser->getSection('content'));
        $this->assertEquals('Question?', $parser->getSection('closing'));
    }

    #[Test]
    public function it_tracks_bytes_processed(): void
    {
        $parser = new ArticleStreamParser;

        $this->assertEquals(0, $parser->getBytesProcessed());

        $chunk1 = '<opening>Test</opening>';
        $parser->parseChunk($chunk1);
        $this->assertEquals(strlen($chunk1), $parser->getBytesProcessed());

        $chunk2 = '<title># Title</title>';
        $parser->parseChunk($chunk2);
        $this->assertEquals(strlen($chunk1) + strlen($chunk2), $parser->getBytesProcessed());
    }

    #[Test]
    public function it_handles_empty_sections_gracefully(): void
    {
        $parser = new ArticleStreamParser;

        // Some sections might be empty (though not recommended)
        $response = <<<'XML'
<opening>
</opening>

<title>
# Title
</title>

<content>
</content>

<closing>
</closing>
XML;

        $parser->parseChunk($response);

        $this->assertTrue($parser->isComplete());
        $this->assertEquals('', $parser->getSection('opening'));
        $this->assertEquals('# Title', $parser->getSection('title'));
        $this->assertEquals('', $parser->getSection('content'));
        $this->assertEquals('', $parser->getSection('closing'));
    }
}

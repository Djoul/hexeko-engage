<?php

namespace Tests\Unit\Modules\Vouchers\Amilon;

use App\Integrations\Vouchers\Amilon\Helpers\SearchHelper;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('amilon')]
#[Group('vouchers')]
#[Group('helper')]
class SearchHelperTest extends TestCase
{
    #[Test]
    public function test_search_helper_normalize_search_terms(): void
    {
        // Test basic normalization
        $this->assertEquals('fnac', SearchHelper::normalizeSearchTerms('FNAC'));
        $this->assertEquals('fnac electronics', SearchHelper::normalizeSearchTerms('Fnac Electronics'));

        // Test whitespace normalization
        $this->assertEquals('fnac electronics', SearchHelper::normalizeSearchTerms('  Fnac   Electronics  '));
        $this->assertEquals('fnac electronics store', SearchHelper::normalizeSearchTerms('Fnac    Electronics   Store'));

        // Test special characters removal
        $this->assertEquals('fnac electronics', SearchHelper::normalizeSearchTerms('Fnac & Electronics!'));
        $this->assertEquals('fnac electronics', SearchHelper::normalizeSearchTerms('Fnac @ Electronics #'));
        $this->assertEquals('fnac electronics', SearchHelper::normalizeSearchTerms('Fnac $ Electronics %'));

        // Test keeping allowed characters
        $this->assertEquals('jean-luc store', SearchHelper::normalizeSearchTerms('Jean-Luc Store'));
        $this->assertEquals("o'reilly books", SearchHelper::normalizeSearchTerms("O'Reilly Books"));
        $this->assertEquals('u.s. store', SearchHelper::normalizeSearchTerms('U.S. Store'));

        // Test empty and edge cases
        $this->assertEquals('', SearchHelper::normalizeSearchTerms(''));
        $this->assertEquals('', SearchHelper::normalizeSearchTerms('   '));
        $this->assertEquals('a', SearchHelper::normalizeSearchTerms('A'));

        // Test unicode characters
        $this->assertEquals('café', SearchHelper::normalizeSearchTerms('Café'));
        $this->assertEquals('résumé', SearchHelper::normalizeSearchTerms('Résumé'));
    }

    #[Test]
    public function test_search_helper_split_search_terms(): void
    {
        // Test basic splitting
        $this->assertEquals(['fnac', 'electronics'], SearchHelper::splitSearchTerms('Fnac Electronics'));
        $this->assertEquals(['amazon', 'prime'], SearchHelper::splitSearchTerms('Amazon Prime'));

        // Test single word
        $this->assertEquals(['fnac'], SearchHelper::splitSearchTerms('Fnac'));

        // Test filtering short terms
        $this->assertEquals(['fnac'], SearchHelper::splitSearchTerms('A Fnac')); // 'A' is too short
        $this->assertEquals(['fnac', 'electronics'], SearchHelper::splitSearchTerms('Fnac & Electronics')); // '&' is removed

        // Test empty cases
        $this->assertEquals([], SearchHelper::splitSearchTerms(''));
        $this->assertEquals([], SearchHelper::splitSearchTerms('   '));
        $this->assertEquals([], SearchHelper::splitSearchTerms('A B')); // Both too short

        // Test with normalization
        $this->assertEquals(['jean-luc', 'store'], SearchHelper::splitSearchTerms('Jean-Luc Store'));
        $this->assertEquals(['multiple', 'word', 'search'], SearchHelper::splitSearchTerms('  Multiple   Word   Search  '));
    }

    #[Test]
    public function test_search_helper_build_search_query(): void
    {
        // Test basic query building
        $this->assertEquals('%fnac%', SearchHelper::buildSearchQuery('Fnac'));
        $this->assertEquals('%fnac electronics%', SearchHelper::buildSearchQuery('Fnac Electronics'));

        // Test with normalization
        $this->assertEquals('%fnac electronics%', SearchHelper::buildSearchQuery('  Fnac   Electronics  '));
        $this->assertEquals('%fnac electronics%', SearchHelper::buildSearchQuery('FNAC & ELECTRONICS!'));

        // Test empty cases
        $this->assertEquals('', SearchHelper::buildSearchQuery(''));
        $this->assertEquals('', SearchHelper::buildSearchQuery('   '));

        // Test special characters
        $this->assertEquals('%jean-luc%', SearchHelper::buildSearchQuery('Jean-Luc'));
        $this->assertEquals("%o'reilly%", SearchHelper::buildSearchQuery("O'Reilly"));
    }

    #[Test]
    public function test_search_helper_highlight_search_terms(): void
    {
        // Test basic highlighting
        $text = 'Fnac is a great electronics store';
        $result = SearchHelper::highlightSearchTerms($text, 'fnac');
        $this->assertEquals('<mark>Fnac</mark> is a great electronics store', $result);

        // Test multiple terms
        $text = 'Fnac is a great electronics store';
        $result = SearchHelper::highlightSearchTerms($text, 'fnac electronics');
        $this->assertEquals('<mark>Fnac</mark> is a great <mark>electronics</mark> store', $result);

        // Test case insensitive
        $text = 'FNAC is a great Electronics store';
        $result = SearchHelper::highlightSearchTerms($text, 'fnac electronics');
        $this->assertEquals('<mark>FNAC</mark> is a great <mark>Electronics</mark> store', $result);

        // Test partial matches (should not highlight)
        $text = 'Fnac is fantastic';
        $result = SearchHelper::highlightSearchTerms($text, 'fan');
        $this->assertEquals('Fnac is <mark>fan</mark>tastic', $result);

        // Test empty search term
        $text = 'Fnac is a great store';
        $result = SearchHelper::highlightSearchTerms($text, '');
        $this->assertEquals('Fnac is a great store', $result);

        // Test no matches
        $text = 'Amazon is a great store';
        $result = SearchHelper::highlightSearchTerms($text, 'fnac');
        $this->assertEquals('Amazon is a great store', $result);

        // Test with special characters in search
        $text = "O'Reilly books are great";
        $result = SearchHelper::highlightSearchTerms($text, "o'reilly");
        $this->assertEquals("<mark>O'Reilly</mark> books are great", $result);
    }
}

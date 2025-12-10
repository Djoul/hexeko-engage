<?php

namespace Tests\Unit\Resolvers;

use App\Attributes\GlobalScopedModel;
use App\Models\TranslationKey;
use App\Models\TranslationValue;
use App\Models\User;
use App\Resolvers\FinancerIdResolver;
use App\Traits\AuditableModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use OwenIt\Auditing\Contracts\Auditable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

#[Group('financer')]
#[Group('environnement')]
#[Group('core')]
class FinancerIdResolverTest extends TestCase
{
    #[Test]
    public function it_returns_null_for_global_scoped_models(): void
    {
        // Test with TranslationKey which has the attribute
        $translationKey = new TranslationKey;
        $result = FinancerIdResolver::resolve($translationKey);
        $this->assertNull($result);

        // Test with TranslationValue which has the attribute
        $translationValue = new TranslationValue;
        $result = FinancerIdResolver::resolve($translationValue);
        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_financer_id_for_non_global_models(): void
    {
        // Set up a request with financer_id
        $request = Request::create('/api/test', 'GET', ['financer_id' => 'test-financer-123']);
        $this->app->instance('request', $request);

        // Test with a regular User model (doesn't have GlobalScopedModel attribute)
        $user = new User;

        $result = FinancerIdResolver::resolve($user);

        $this->assertEquals('test-financer-123', $result);
    }

    #[Test]
    public function it_correctly_identifies_models_with_global_attribute(): void
    {
        // Create a test class with the attribute
        $globalModel = new class extends Model implements Auditable
        {
            use AuditableModel;
        };

        // Add attribute via reflection (for testing)
        new ReflectionClass($globalModel);

        // Since we can't dynamically add attributes, we test with actual models
        $translationKey = new TranslationKey;
        $result = FinancerIdResolver::resolve($translationKey);

        $this->assertNull($result);
    }
}

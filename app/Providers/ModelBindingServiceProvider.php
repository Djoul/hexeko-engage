<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;

class ModelBindingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerConventionalBindings();
        $this->registerDynamicFallbackBinding();
    }

    /**
     * Register conventional Route::model() bindings for discovered models.
     */
    protected function registerConventionalBindings(): void
    {
        $registered = [];

        foreach ($this->discoverModelClasses() as $fqcn) {
            if (! is_subclass_of($fqcn, Model::class)) {
                continue;
            }

            $base = class_basename($fqcn);
            $camel = Str::camel($base);      // e.g. stripePayment
            $snake = Str::snake($base);      // e.g. stripe_payment
            $lower = Str::lower($base);      // e.g. stripepayment

            // Generate common parameter name variants
            $candidates = array_unique([
                // canonical singulars
                $camel,
                $snake,
                $lower,
                // common "id" suffixes used in many codebases
                $camel.'Id',               // e.g. stripePaymentId
                $snake.'_id',              // e.g. stripe_payment_id
                $lower.'id',               // e.g. stripepaymentid
            ]);
            foreach ($candidates as $param) {
                if ($param === '') {
                    continue;
                }
                if (array_key_exists($param, $registered)) {
                    continue;
                }
                Route::model($param, is_string($fqcn) ? $fqcn : get_class($fqcn));
                $registered[$param] = true;
            }
        }
    }

    /**
     * Dynamic fallback: when route parameter names don't match controller parameter names,
     * attempt to resolve Eloquent models by inspecting the controller signature at runtime.
     */
    protected function registerDynamicFallbackBinding(): void
    {
        Event::listen(RouteMatched::class, function (RouteMatched $event): void {
            $route = $event->route;
            if ($route === null) {
                return;
            }

            $action = $route->getAction();
            $controller = is_array($action) && array_key_exists('controller', $action) ? $action['controller'] : null;
            if (! is_string($controller)) {
                return; // Not a class@method style controller
            }

            // Parse "Class@method"
            if (! str_contains($controller, '@')) {
                // Invokable controller: Class::__invoke
                $class = $controller;
                $method = '__invoke';
            } else {
                [$class, $method] = explode('@', $controller, 2);
            }

            if (! class_exists($class) || ! method_exists($class, $method)) {
                return;
            }

            $parameters = $route->parameters();
            $paramKeys = array_keys($parameters);

            try {
                $reflection = new ReflectionMethod($class, $method);
            } catch (ReflectionException) {
                return;
            }

            // Count model parameters to allow using generic key "id" when unambiguous
            $modelParams = [];
            foreach ($reflection->getParameters() as $param) {
                $type = $param->getType();
                if (! $type instanceof ReflectionNamedType) {
                    continue;
                }
                if ($type->isBuiltin()) {
                    continue;
                }
                $fqcn = $type->getName();
                if (is_subclass_of($fqcn, Model::class)) {
                    $modelParams[] = [$param->getName(), $fqcn];
                }
            }

            if ($modelParams === []) {
                return;
            }

            foreach ($modelParams as [$paramName, $fqcn]) {
                // Already resolved by Laravel implicit binding
                if (array_key_exists($paramName, $parameters) && $parameters[$paramName] instanceof Model) {
                    continue;
                }

                // Candidate keys in the route parameters that may hold the identifier
                $candidates = array_unique(array_filter([
                    $paramName,
                    Str::camel($paramName),
                    Str::snake($paramName),
                    Str::lower($paramName),
                    $paramName.'Id',
                    Str::camel($paramName).'Id',
                    Str::snake($paramName).'_id',
                    Str::lower($paramName).'id',
                ]));

                // If unambiguous single model parameter, allow generic 'id'
                if (count($modelParams) === 1) {
                    $candidates[] = 'id';
                }

                $matchedKey = Arr::first($candidates, fn (string $key): bool => array_key_exists($key, $parameters) && ! ($parameters[$key] instanceof Model));
                if (! $matchedKey) {
                    continue;
                }

                $raw = $parameters[$matchedKey];
                // Resolve model by its route key name
                $instance = new $fqcn;
                $routeKey = method_exists($instance, 'getRouteKeyName') ? $instance->getRouteKeyName() : 'id';
                $model = $fqcn::query()->where($routeKey, $raw)->first();

                if ($model instanceof Model) {
                    // Set for both the original key and the controller param name
                    $route->setParameter($matchedKey, $model);
                    $route->setParameter($paramName, $model);
                } else {
                    abort(404);
                }
            }
        });
    }

    /**
     * @return array<int, class-string<Model>>
     */
    protected function discoverModelClasses(): array
    {
        $roots = [
            base_path('app/Models'),
            base_path('app/Integrations'),
        ];

        $classes = [];

        foreach ($roots as $root) {
            if (! File::exists($root)) {
                continue;
            }

            foreach (File::allFiles($root) as $file) {
                $path = $file->getPathname();

                if (! str_contains($path, DIRECTORY_SEPARATOR.'Models'.DIRECTORY_SEPARATOR)) {
                    continue;
                }

                $relative = Str::after($path, base_path('app').DIRECTORY_SEPARATOR);
                $relative = str_replace(['/', '\\'], '\\', $relative);
                $relative = Str::beforeLast($relative, '.php');
                $fqcn = 'App\\'.$relative;

                if (class_exists($fqcn) && is_subclass_of($fqcn, Model::class)) {
                    $classes[] = $fqcn;
                }
            }
        }

        return array_values(array_unique($classes));
    }
}

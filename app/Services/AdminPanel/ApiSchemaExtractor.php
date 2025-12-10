<?php

namespace App\Services\AdminPanel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

class ApiSchemaExtractor
{
    /**
     * @return array<string, mixed>
     */
    public function extractEndpoint(Route $route): array
    {
        $uri = $route->uri();
        $methods = $route->methods();
        $method = in_array('GET', $methods) ? 'GET' : $methods[0];

        return [
            'method' => $method,
            'path' => '/'.$uri,
            'name' => $route->getName(),
            'middleware' => $this->extractMiddleware($route),
            'parameters' => $this->extractParameters($route),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function extractFullEndpointDetails(Route $route): array
    {
        $basic = $this->extractEndpoint($route);
        $controller = $route->getActionName();

        // Extract group name from controller
        $group = 'General';
        if (str_contains($controller, '@')) {
            $controllerClass = Str::before($controller, '@');
            $controllerName = class_basename($controllerClass);
            $group = Str::before($controllerName, 'Controller');
        }

        return array_merge($basic, [
            'description' => $this->generateDescription($route),
            'group' => $group,
            'controller' => $controller,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function extractValidationRules(FormRequest $request): array
    {
        /** @var array<string, mixed> $rules */
        $rules = method_exists($request, 'rules') ? $request->rules() : [];
        $parsed = [];

        foreach ($rules as $field => $rule) {
            $parsed[$field] = $this->parseValidationRule($rule);
        }

        return $parsed;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function groupEndpointsByController(): array
    {
        $routes = RouteFacade::getRoutes();
        $grouped = [];

        $routeArray = $routes->getRoutes();
        /** @var \Illuminate\Routing\Route $route */
        foreach ($routeArray as $route) {
            if (! Str::startsWith($route->uri(), 'api/')) {
                continue;
            }

            $details = $this->extractFullEndpointDetails($route);
            $group = $details['group'];
            $groupKey = is_string($group) ? $group : 'General';

            if (! array_key_exists($groupKey, $grouped)) {
                $grouped[$groupKey] = [];
            }

            $grouped[$groupKey][] = $details;
        }

        return $grouped;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function generateCurlExample(Route $route, array $data = []): string
    {
        $method = $route->methods()[0];
        $url = url($route->uri());

        $curl = "curl -X {$method}";
        $curl .= " \\\n  '{$url}'";

        // Add headers
        $curl .= " \\\n  -H 'Content-Type: application/json'";
        $curl .= " \\\n  -H 'Accept: application/json'";
        $curl .= " \\\n  -H 'Authorization: Bearer YOUR_JWT_TOKEN'";

        // Add data for POST/PUT/PATCH
        if (in_array($method, ['POST', 'PUT', 'PATCH']) && $data !== []) {
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $curl .= " \\\n  -d '{$json}'";
        }

        return $curl;
    }

    /**
     * @return array<int, string>
     */
    private function extractMiddleware(Route $route): array
    {
        $middleware = $route->gatherMiddleware() ?? [];

        // Filter out internal Laravel middleware
        return array_values(array_filter($middleware, function ($m): bool {
            return ! in_array($m, [
                'web',
                'api',
                'Illuminate\Routing\Middleware\SubstituteBindings',
            ]);
        }));
    }

    /**
     * @return array<int, array<string, string|bool>>
     */
    private function extractParameters(Route $route): array
    {
        $parameters = [];

        // Extract path parameters
        preg_match_all('/\{(\w+)\}/', $route->uri(), $matches);

        foreach ($matches[1] as $param) {
            $parameters[] = [
                'name' => $param,
                'in' => 'path',
                'required' => true,
                'type' => 'string',
            ];
        }

        return $parameters;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseValidationRule($rule): array
    {
        if (is_string($rule)) {
            $rules = explode('|', $rule);
        } elseif (is_array($rule)) {
            $rules = $rule;
        } else {
            $rules = [];
        }

        $parsed = [
            'required' => false,
            'type' => 'string',
        ];

        foreach ($rules as $r) {
            if (! is_string($r) && ! is_numeric($r)) {
                continue;
            }
            $ruleString = (string) $r;

            if ($ruleString === 'required') {
                $parsed['required'] = true;
            } elseif ($ruleString === 'nullable') {
                $parsed['required'] = false;
            } elseif (in_array($ruleString, ['string', 'integer', 'boolean', 'array', 'file'])) {
                $parsed['type'] = $ruleString;
            } elseif ($ruleString === 'email') {
                $parsed['type'] = 'email';
            } elseif (Str::startsWith($ruleString, 'max:')) {
                $parsed['max'] = (int) Str::after($ruleString, 'max:');
            } elseif (Str::startsWith($ruleString, 'min:')) {
                $parsed['min'] = (int) Str::after($ruleString, 'min:');
            } elseif (Str::startsWith($ruleString, 'unique:')) {
                $parsed['unique'] = Str::after($ruleString, 'unique:');
            }
        }

        return $parsed;
    }

    private function generateDescription(Route $route): string
    {
        $method = $route->methods()[0];
        $uri = $route->uri();
        $name = $route->getName();

        // Generate a description based on route name
        if ($name) {
            $parts = explode('.', $name);
            $resource = $parts[0] ?? 'resource';
            $action = $parts[1] ?? 'action';

            if ($action === 'index') {
                return "List all {$resource}";
            }
            if ($action === 'show') {
                return "Get a specific {$resource}";
            }
            if (in_array($action, ['store', 'create'])) {
                return "Create a new {$resource}";
            }
            if ($action === 'update') {
                return "Update a {$resource}";
            }
            if (in_array($action, ['destroy', 'delete'])) {
                return "Delete a {$resource}";
            }

            return "{$method} {$uri}";
        }

        return "{$method} {$uri}";
    }
}

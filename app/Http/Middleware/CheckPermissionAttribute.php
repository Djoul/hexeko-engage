<?php

namespace App\Http\Middleware;

use App\Attributes\RequiresPermission;
use App\Models\Permission;
use Closure;
use Exception;
use Illuminate\Http\Request;
use ReflectionMethod;

class CheckPermissionAttribute
{
    /**
     * Handle an incoming request.
     *
     *
     * @throws Exception
     */
    // @phpstan-ignore-next-line
    public function handle(Request $request, Closure $next)
    {
        // Get the controller and method
        $controller = $request->route()?->getController();
        $method = $request->route()?->getActionMethod();

        if ($controller === null) {
            throw new Exception('Controller not found');
        }
        if ($method === null) {
            throw new Exception('Method not found');
        }

        // For invokable controllers, the method should be __invoke
        if (is_object($controller) && $method === get_class($controller)) {
            $method = '__invoke';
        }

        // @phpstan-ignore-next-line
        $reflection = new ReflectionMethod($controller, $method);
        $attributes = $reflection->getAttributes(RequiresPermission::class);

        if ($attributes !== []) {
            $attribute = $attributes[0]->newInstance();

            $permissions = is_array($attribute->permission) ? $attribute->permission : [$attribute->permission];
            sort($permissions);
            // Validate all permissions exist
            $existingPermissions = Permission::whereIn('name', $permissions)->pluck('name')->toArray();
            sort($existingPermissions);

            $missingPermissions = array_diff($permissions, $existingPermissions);

            if ($missingPermissions !== []) {
                $missingList = implode(', ', $missingPermissions);

                return response()->json(['error' => 'Invalid permissions defined: '.$missingList], 400);
            }

            if (! auth()->user()?->canAny($permissions)) {
                $permissionsList = implode(', ', $permissions);

                return response()->json(['error' => 'Unauthorized... missing any of these permissions: '.$permissionsList], 403);
            }
        }

        return $next($request);
    }
}

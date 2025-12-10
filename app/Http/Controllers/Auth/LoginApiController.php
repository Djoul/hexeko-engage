<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\LoginUserAction;
use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

#[Group('Authentication')]
class LoginApiController extends Controller
{
    public function __construct(protected LoginUserAction $loginUserAction) {}

    /**
     * Login user
     *
     * only use for testing purposes or postman
     *
     *
     * @throws ValidationException
     *
     * @bodyParam email
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6', // pragma: allowlist secret
        ]);

        /** @var array{email: string, password: string} $credentials */
        $credentials = [
            'email' => $validated['email'],
            'password' => $validated['password'],
        ];

        //        if (config('app.url') === 'http://159.65.196.100/') {
        //            $resp = Http::post('https://engage-main-api-master-7fxqjc.laravel.cloud/api/v1/login', $credentials);
        //
        //            return response()->json($resp->json());
        //        }

        $result = $this->loginUserAction->handle($credentials);
        if (array_key_exists('error', $result)) {
            throw ValidationException::withMessages([
                $result['error'],
            ]);
        }

        if (array_key_exists('ChallengeName', $result)) {
            return response()->json(['response' => $result['ChallengeName']]);
        }

        return response()->json(['response' => 'Login successfull', 'authentication_result' => $result['AuthenticationResult']]);
    }
}

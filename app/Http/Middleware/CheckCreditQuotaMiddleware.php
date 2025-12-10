<?php

namespace App\Http\Middleware;

use App\Estimators\AiTokenEstimator;
use App\Models\CreditBalance;
use App\Models\Financer;
use App\Models\User;
use Auth;
use Closure;
use Illuminate\Http\Request;
use Log;
use Symfony\Component\HttpFoundation\Response;

class CheckCreditQuotaMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $type = 'ai_token'): Response
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Determine the amount to be consumed
        $amount = resolve(AiTokenEstimator::class)->estimate($request);
        $userBalance = CreditBalance::where([
            'owner_type' => User::class,
            'owner_id' => $user->id,
            'type' => $type,
        ])->first();
        $divisionContext = null;

        if ($userBalance) {
            $userBalance->setRelation('owner', $user);
            $user->loadMissing(['financers.division']);
            $divisionContext = $user->financers->first()?->division;
        }

        // Fallback: check financeur credit (if linked)
        $financerBalance = null;
        if (! $userBalance || ! $userBalance->hasEnough($amount)) {
            /** @var Financer|null $financer */
            $user->loadMissing(['financers.division']);
            $financer = $user->financers->first();

            if ($financer && $financer->id) {
                $divisionContext ??= $financer->division;
                $financerBalance = CreditBalance::where([
                    'owner_type' => Financer::class,
                    'owner_id' => $financer->id,
                    'type' => $type,
                ])->first();

                if ($financerBalance) {
                    $financerBalance->setRelation('owner', $financer);
                    $divisionContext ??= $financerBalance->division;
                }
            }
        }

        $sufficient = $userBalance?->hasEnough($amount)
            || $financerBalance?->hasEnough($amount);

        if (! $sufficient) {
            Log::info('Not enough credits to perform this action.', [
                'user_id' => $user->id,
                'financer_id' => $financerBalance?->owner_id,
                'division_id' => $divisionContext?->id,
                'division_name' => $divisionContext?->name,
                'amount_required' => $amount,
                'user_balance' => $userBalance?->balance,
                'type' => $type,
            ]);

            return response()->json([
                'message' => __('Not enough credits to perform this action.'),
                'required' => $amount,
                'type' => $type,
                'division_id' => $divisionContext?->id,
            ], 403);
        }

        // Inject for later use if needed
        $request->merge([
            'credit_amount_required' => $amount,
            'credit_type' => $type,
            'credit_division_id' => $divisionContext?->id,
            'credit_division_name' => $divisionContext?->name,
        ]);

        return $next($request);
    }
}

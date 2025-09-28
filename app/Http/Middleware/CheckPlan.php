<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;

class CheckPlan
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $requiredPlan = 'free'): Response
    {
        $user = auth()->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Reset monthly usage if needed
        $this->resetMonthlyUsageIfNeeded($user);

        // Check plan level requirement
        $planLevels = ['free' => 1, 'pro' => 2, 'enterprise' => 3];

        if ($planLevels[$user->plan] < $planLevels[$requiredPlan]) {
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => 'This feature requires ' . ucfirst($requiredPlan) . ' plan or higher.',
                    'current_plan' => $user->plan,
                    'required_plan' => $requiredPlan,
                    'upgrade_url' => route('plans.index')
                ], 403);
            }

            return redirect()->route('plans.index')
                ->with('error', 'This feature requires ' . ucfirst($requiredPlan) . ' plan or higher.');
        }

        return $next($request);
    }

    private function resetMonthlyUsageIfNeeded($user): void
    {
        $lastReset = $user->plan_renewed_at ?? $user->created_at;
        $shouldReset = !$lastReset || $lastReset->lt(Carbon::now()->startOfMonth());

        if ($shouldReset) {
            $user->update([
                'tokens_used' => 0,
                'documents_used' => 0,
                'plan_renewed_at' => Carbon::now()
            ]);
        }
    }
}
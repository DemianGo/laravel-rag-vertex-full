<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\UserPlan;

class PlanMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$requirements): Response
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $userPlan = UserPlan::firstOrCreate(
            ['user_id' => $user->id],
            ['plan' => 'free']
        );

        // Check if plan is expired
        if ($userPlan->isExpired()) {
            $userPlan->update([
                'plan' => 'free',
                'tokens_limit' => 100,
                'documents_limit' => 1,
                'plan_expires_at' => null
            ]);
        }

        foreach ($requirements as $requirement) {
            if (!$this->checkRequirement($userPlan, $requirement)) {
                return $this->handleFailedRequirement($requirement, $userPlan);
            }
        }

        // Add user plan to request for controllers
        $request->attributes->set('userPlan', $userPlan);

        return $next($request);
    }

    private function checkRequirement(UserPlan $userPlan, string $requirement): bool
    {
        return match ($requirement) {
            'pro' => in_array($userPlan->plan, ['pro', 'enterprise']),
            'enterprise' => $userPlan->plan === 'enterprise',
            'tokens:1' => $userPlan->canUseTokens(1),
            'tokens:5' => $userPlan->canUseTokens(5),
            'documents' => $userPlan->canAddDocument(),
            'advanced_features' => $userPlan->plan !== 'free',
            default => true
        };
    }

    private function handleFailedRequirement(string $requirement, UserPlan $userPlan): Response
    {
        $message = match ($requirement) {
            'pro' => 'This feature requires Pro or Enterprise plan.',
            'enterprise' => 'This feature requires Enterprise plan.',
            'tokens:1', 'tokens:5' => 'You have exceeded your token limit. Upgrade your plan for more usage.',
            'documents' => 'You have reached your document limit. Upgrade your plan to add more documents.',
            'advanced_features' => 'This feature requires a paid plan.',
            default => 'Insufficient permissions.'
        };

        if (request()->wantsJson()) {
            return response()->json([
                'error' => $message,
                'upgrade_url' => route('plans.index'),
                'current_plan' => $userPlan->plan,
                'plan_config' => $userPlan->getPlanConfig()
            ], 403);
        }

        return redirect()->route('plans.index')->with('error', $message);
    }
}
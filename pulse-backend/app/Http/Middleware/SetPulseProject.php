<?php

namespace App\Http\Middleware;

use App\Support\PulseProjectRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetPulseProject
{
    public function __construct(
        private readonly PulseProjectRegistry $projects,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $key = $request->header('X-Pulse-Project')
            ?? $request->query('project')
            ?? $this->projects->defaultKeyForUser($user->id);

        if ($key === null || $key === '') {
            return response()->json([
                'message' => 'No project selected. Create a project first.',
            ], 422);
        }

        try {
            $project = $this->projects->assertOwnedBy((string) $key, $user->id);
        } catch (\InvalidArgumentException) {
            return response()->json([
                'message' => "Unknown Pulse project [{$key}].",
            ], 422);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 403);
        }

        $request->attributes->set('pulse_project', $project);
        $request->attributes->set('pulse_project_key', $project->key);

        return $next($request);
    }
}

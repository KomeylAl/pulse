<?php

namespace App\Http\Middleware;

use App\Support\PulseProjectRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePulseProject
{
    public function __construct(
        private readonly PulseProjectRegistry $projects,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-Pulse-Api-Key');

        $project = $this->projects->fromApiKey($apiKey);

        if ($project === null) {
            return response()->json([
                'message' => 'Invalid or missing Pulse project API key.',
            ], 401);
        }

        $request->attributes->set('pulse_project', $project);
        $request->attributes->set('pulse_project_key', $project->key);

        return $next($request);
    }
}

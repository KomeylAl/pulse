<?php

namespace App\Http\Controllers\Concerns;

use App\Support\PulseProject;
use Illuminate\Http\Request;

trait ResolvesPulseProject
{
    protected function pulseProject(Request $request): PulseProject
    {
        /** @var PulseProject $project */
        $project = $request->attributes->get('pulse_project');

        return $project;
    }

    protected function pulseProjectKey(Request $request): string
    {
        return (string) $request->attributes->get('pulse_project_key');
    }
}

<?php

namespace App\Support;

use App\Models\Project;
use InvalidArgumentException;
use RuntimeException;

class PulseProjectRegistry
{
    public function find(string $key): ?PulseProject
    {
        $project = Project::query()->where('key', $key)->first();

        return $project ? PulseProject::fromModel($project) : null;
    }

    public function findOrFail(string $key): PulseProject
    {
        $project = $this->find($key);

        if ($project === null) {
            throw new InvalidArgumentException("Unknown Pulse project [{$key}].");
        }

        return $project;
    }

    public function findModelOrFail(string $key): Project
    {
        return Project::query()->where('key', $key)->firstOrFail();
    }

    public function fromApiKey(?string $apiKey): ?PulseProject
    {
        if ($apiKey === null || $apiKey === '') {
            return null;
        }

        $project = Project::query()->where('api_key', $apiKey)->first();

        return $project ? PulseProject::fromModel($project) : null;
    }

    public function fromApiKeyOrFail(?string $apiKey): PulseProject
    {
        $project = $this->fromApiKey($apiKey);

        if ($project === null) {
            throw new InvalidArgumentException('Invalid Pulse project API key.');
        }

        return $project;
    }

    /**
     * @return list<PulseProject>
     */
    public function forUser(int $userId): array
    {
        return Project::query()
            ->where('user_id', $userId)
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project) => PulseProject::fromModel($project))
            ->all();
    }

    public function defaultKeyForUser(int $userId): ?string
    {
        return Project::query()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->value('key');
    }

    public function assertOwnedBy(string $key, int $userId): PulseProject
    {
        $project = $this->findOrFail($key);

        if ($project->ownerId !== $userId) {
            throw new RuntimeException('You do not have access to this project.');
        }

        return $project;
    }
}

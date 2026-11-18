<?php

namespace Whilesmart\Skills\Imports;

use Illuminate\Contracts\Container\Container;
use Whilesmart\Skills\Contracts\SkillSourceProvider;
use Whilesmart\Skills\Exceptions\ImportSourceRejected;

class SkillSourceRegistry
{
    public function __construct(protected readonly Container $container) {}

    public function enabled(): bool
    {
        return config('skills.imports.enabled', false) === true;
    }

    public function provider(string $key): SkillSourceProvider
    {
        if (! $this->enabled()) {
            throw ImportSourceRejected::disabled();
        }

        $provider = $this->make($key);

        if ($provider === null || ! $provider->enabled()) {
            throw ImportSourceRejected::unknown($key);
        }

        return $provider;
    }

    /**
     * @return array<string, SkillSourceProvider>
     */
    public function available(): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $providers = [];

        foreach (array_keys((array) config('skills.imports.sources', [])) as $key) {
            $provider = is_string($key) ? $this->make($key) : null;

            if ($provider !== null && $provider->enabled()) {
                $providers[$key] = $provider;
            }
        }

        return $providers;
    }

    protected function make(string $key): ?SkillSourceProvider
    {
        $config = config('skills.imports.sources.'.str_replace('.', '', $key));

        if (! is_array($config) || ! is_string($config['provider'] ?? null) || ! is_a($config['provider'], SkillSourceProvider::class, true)) {
            return null;
        }

        return $this->container->make($config['provider'], ['config' => $config]);
    }
}

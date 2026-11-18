<?php

namespace Whilesmart\Skills\Imports;

use Whilesmart\Skills\Contracts\SkillSourceProvider;
use Whilesmart\Skills\Exceptions\ImportSourceRejected;
use Whilesmart\Skills\Exceptions\InvalidSkillBundle;
use Whilesmart\Skills\Support\FileBundle;

class LocalPathSourceProvider implements SkillSourceProvider
{
    public function __construct(protected readonly array $config = []) {}

    public function enabled(): bool
    {
        return ($this->config['enabled'] ?? false) === true && $this->roots() !== [];
    }

    public function rules(): array
    {
        return [
            'root' => ['required', 'string', 'max:64'],
            'path' => ['nullable', 'string', 'max:1024'],
        ];
    }

    public function describe(): array
    {
        return [
            'fields' => ['root', 'path'],
            'roots' => array_keys($this->roots()),
        ];
    }

    public function prepare(array $input, ImportContext $context): PreparedSource
    {
        $rootKey = $input['root'] ?? null;
        $roots = $this->roots();

        if (! is_string($rootKey) || ! array_key_exists($rootKey, $roots)) {
            throw ImportSourceRejected::because('The source root is not configured.');
        }

        $base = $roots[$rootKey];
        $relative = $input['path'] ?? '';

        if ($relative !== '' && $relative !== null) {
            try {
                $relative = FileBundle::normalizePath($relative);
            } catch (InvalidSkillBundle) {
                throw ImportSourceRejected::because('The source path must be a relative path inside its root.');
            }
        } else {
            $relative = '';
        }

        $target = realpath($relative === '' ? $base : $base.DIRECTORY_SEPARATOR.$relative);

        if ($target === false || ! is_dir($target) || ($target !== $base && ! str_starts_with($target, $base.DIRECTORY_SEPARATOR))) {
            throw ImportSourceRejected::because('The source path must be a folder inside its root.');
        }

        return new PreparedSource(
            $target,
            $context->options(allowedRoots: [$base]),
            $relative === '' ? $rootKey.':' : $rootKey.':'.$relative,
            false,
            [$target, $base],
        );
    }

    /**
     * @return array<string, string>
     */
    protected function roots(): array
    {
        $roots = [];

        foreach ((array) ($this->config['roots'] ?? []) as $key => $path) {
            if (! is_string($key) || ! is_string($path) || $path === '') {
                continue;
            }

            $real = realpath($path);

            if ($real !== false && is_dir($real)) {
                $roots[$key] = rtrim($real, DIRECTORY_SEPARATOR) ?: DIRECTORY_SEPARATOR;
            }
        }

        return $roots;
    }
}

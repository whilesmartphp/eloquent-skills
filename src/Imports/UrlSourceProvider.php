<?php

namespace Whilesmart\Skills\Imports;

use Whilesmart\Skills\Contracts\SkillSourceProvider;
use Whilesmart\Skills\Exceptions\ImportSourceRejected;

class UrlSourceProvider implements SkillSourceProvider
{
    public function __construct(protected readonly array $config = []) {}

    public function enabled(): bool
    {
        return ($this->config['enabled'] ?? false) === true && $this->hosts() !== [];
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2048'],
        ];
    }

    public function describe(): array
    {
        return [
            'fields' => ['url'],
            'allowed_hosts' => $this->hosts(),
            'schemes' => $this->schemes(),
        ];
    }

    public function prepare(array $input, ImportContext $context): PreparedSource
    {
        $url = $input['url'] ?? null;

        if (! is_string($url) || $url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f]/', $url)) {
            throw ImportSourceRejected::because('The source URL is not valid.');
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw ImportSourceRejected::because('The source URL is not valid.');
        }

        if (! in_array(strtolower($parts['scheme']), $this->schemes(), true)) {
            throw ImportSourceRejected::because('The source URL must use '.implode(' or ', $this->schemes()).'.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw ImportSourceRejected::because('The source URL cannot carry credentials.');
        }

        $host = strtolower(rtrim($parts['host'], '.'));

        if (! in_array($host, $this->hosts(), true)) {
            throw ImportSourceRejected::because('The host "'.mb_substr($host, 0, 255).'" is not an allowed skill source.');
        }

        return new PreparedSource($url, $context->options(allowedHosts: $this->hosts()), $url);
    }

    /**
     * @return list<string>
     */
    protected function hosts(): array
    {
        $hosts = [];

        foreach ((array) ($this->config['allowed_hosts'] ?? []) as $host) {
            if (is_string($host) && ($host = strtolower(trim($host))) !== '' && ! str_contains($host, '*')) {
                $hosts[] = $host;
            }
        }

        return array_values(array_unique($hosts));
    }

    /**
     * @return list<string>
     */
    protected function schemes(): array
    {
        return ($this->config['allow_http'] ?? false) === true ? ['https', 'http'] : ['https'];
    }
}

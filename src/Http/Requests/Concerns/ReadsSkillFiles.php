<?php

namespace Whilesmart\Skills\Http\Requests\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;
use Whilesmart\Skills\Exceptions\InvalidSkillBundle;
use Whilesmart\Skills\Support\FileBundle;
use Whilesmart\Skills\Support\SkillsConfig;

trait ReadsSkillFiles
{
    private ?array $skillFiles = null;

    /**
     * @return array<string, mixed>
     */
    protected function fileRules(bool $required): array
    {
        $maxFiles = SkillsConfig::limit('max_files');
        $maxKilobytes = (int) ceil(SkillsConfig::limit('max_file_bytes') / 1024);
        $presence = $required ? 'required_without:uploads' : 'sometimes';

        return [
            'files' => [$presence, 'array', 'min:1', 'max:'.$maxFiles],
            'files.*' => ['array:path,content,encoding'],
            'files.*.path' => ['required', 'string', 'max:255'],
            'files.*.content' => ['present', 'nullable', 'string'],
            'files.*.encoding' => ['nullable', 'string', 'in:'.FileBundle::UTF8.','.FileBundle::BASE64],
            'uploads' => [$required ? 'required_without:files' : 'sometimes', 'prohibits:files', 'array', 'min:1', 'max:'.$maxFiles],
            'uploads.*' => ['file', 'max:'.$maxKilobytes],
            'paths' => ['required_with:uploads', 'array'],
            'paths.*' => ['required', 'string', 'max:255'],
        ];
    }

    public function hasSkillFiles(): bool
    {
        return $this->has('files') || $this->hasFile('uploads');
    }

    protected function checkSkillFiles(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty() || ! $this->hasSkillFiles()) {
            return;
        }

        try {
            $this->skillFiles = $this->collectSkillFiles();
        } catch (InvalidSkillBundle $exception) {
            $validator->errors()->add('files', $exception->getMessage());
        }
    }

    /**
     * @return array<string, string>
     */
    public function skillFiles(): array
    {
        return $this->skillFiles ??= $this->collectSkillFiles();
    }

    /**
     * @return array<string, string>
     */
    private function collectSkillFiles(): array
    {
        $files = [];

        foreach ($this->rawFileEntries() as $entry) {
            $path = FileBundle::normalizePath($entry['path'] ?? null);

            if (array_key_exists($path, $files)) {
                throw InvalidSkillBundle::duplicate($path);
            }

            $content = $entry['content'] ?? '';

            if (! is_string($content)) {
                throw InvalidSkillBundle::content($path);
            }

            $files[$path] = FileBundle::decodeContent($path, $content, $entry['encoding'] ?? FileBundle::UTF8);
        }

        $uploads = $this->file('uploads', []);

        foreach ((is_array($uploads) ? $uploads : []) as $index => $upload) {
            $path = FileBundle::normalizePath($this->input('paths.'.$index));

            if (! $upload instanceof UploadedFile || ! $upload->isValid()) {
                throw InvalidSkillBundle::content($path);
            }

            if (array_key_exists($path, $files)) {
                throw InvalidSkillBundle::duplicate($path);
            }

            $bytes = file_get_contents($upload->getRealPath());

            if ($bytes === false) {
                throw InvalidSkillBundle::content($path);
            }

            $files[$path] = $bytes;
        }

        return FileBundle::check($files);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rawFileEntries(): array
    {
        $entries = null;

        if ($this->isJson()) {
            $raw = json_decode($this->getContent(), true, 16);
            $entries = is_array($raw) && is_array($raw['files'] ?? null) ? $raw['files'] : null;
        }

        $entries ??= $this->input('files', []);

        return array_values(array_filter(is_array($entries) ? $entries : [], 'is_array'));
    }
}

<?php

namespace Tonysm\TurboLaravel\Testing;

use Closure;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;

class TurboStreamMatcher
{
    private $turboStream;
    private array $wheres = [];
    private array $contents = [];

    public function __construct($turboStream)
    {
        $this->turboStream = $turboStream;
    }

    public function where(string $prop, string $value): self
    {
        $this->wheres[$prop] = $value;

        return $this;
    }

    public function see(string $content): self
    {
        $this->contents[] = $content;

        return $this;
    }

    public function matches(Closure $callback): bool
    {
        $callback($this);

        if (! $this->matchesProps()) {
            return false;
        }

        return $this->matchesContents();
    }

    public function attrs(): string
    {
        return trim(collect($this->wheres)
            ->reduce(fn ($acc, $val, $prop) => $acc . ' ' . sprintf('%s="%s"', $prop, $val), ''));
    }

    private function matchesProps()
    {
        foreach ($this->wheres as $prop => $value) {
            $actualProp = $this->turboStream['@attributes'][$prop] ?? false;

            if ($actualProp === false || $actualProp !== $value) {
                return false;
            }
        }

        return true;
    }

    private function matchesContents()
    {
        if (empty($this->contents)) {
            return true;
        }

        $content = new TestResponse(new Response($this->makeElements($this->turboStream['template'] ?? [])));

        foreach ($this->contents as $expectedContent) {
            $content->assertSee($expectedContent);
        }

        return true;
    }

    private function makeElements($tags)
    {
        if (is_string($tags)) {
            return $tags;
        }

        $content = '';

        foreach ($tags as $tag => $contents) {
            $attrs = trim(collect($contents['@attributes'] ?? [])
                ->reduce(fn ($acc, $val, $prop) => $acc . ' ' . sprintf('%s="%s"', $prop, $val), ''));

            $strContent = $this->makeElements(is_array($contents) ? Arr::except($contents, '@attributes') : $contents);
            $opening = trim(sprintf('%s %s', $tag, $attrs));

            $content .= "<{$opening}>{$strContent}</{$tag}>";
        }

        return $content;
    }
}

<?php

namespace Tonysm\TurboLaravel\Testing;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;

class AssertableTurboStream
{
    public TestResponse $response;
    public Collection $parsedCollection;

    public function __construct(TestResponse $response)
    {
        $this->response = $response;
    }

    public function has(int $expectedTurboStreamsCount): self
    {
        Assert::assertCount($expectedTurboStreamsCount, $this->parsed());

        return $this;
    }

    public function hasTurboStream(Closure $callback): self
    {
        $attrs = collect();

        $matches = $this->parsed()
            ->mapInto(TurboStreamMatcher::class)
            ->filter(function ($matcher) use ($callback, $attrs) {
                if (! $matcher->matches($callback)) {
                    $attrs->add($matcher->attrs());

                    return false;
                }

                return true;
            });

        Assert::assertTrue(
            $matches->count() === 1,
            sprintf(
                'Expected to find a matching Turbo Stream for `%s`, but %s',
                $attrs->unique()->join(' '),
                trans_choice('{0} none was found.|[2,*] :count were found.', $matches->count()),
            )
        );

        return $this;
    }

    private function parsed(): Collection
    {
        if (! isset($this->parsedCollection)) {
            $parsed = simplexml_load_string(<<<XML
            <xml>{$this->response->content()}</xml>
            XML);

            $this->parsedCollection = collect(json_decode(json_encode($parsed), true)['turbo-stream']);
        }

        return $this->parsedCollection;
    }
}

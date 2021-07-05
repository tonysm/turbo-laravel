<?php

namespace Tonysm\TurboLaravel\Tests\Http;

use Illuminate\Support\Facades\View;
use Tonysm\TurboLaravel\Testing\AssertableTurboStream;
use Tonysm\TurboLaravel\Tests\TestCase;

class TurboStreamResponseTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__ . '/../Stubs/views');
    }

    protected function defineRoutes($router)
    {
        $router->get('/testing/turbo-stream', function () {
            return response()->turboStreamView('turbo_streams');
        })->name('testing.turbo-stream');

        $router->get('/testing/non-turbo-stream', function () {
            return 'No Turbo Stream';
        })->name('testing.non-turbo-stream');
    }

    /** @test */
    public function turbo_stream_response()
    {
        $this->get(route('testing.turbo-stream'))
            ->assertTurboStream();

        $this->get(route('testing.non-turbo-stream'))
            ->assertNotTurboStream();
    }

    /** @test */
    public function turbo_assert_count_of_turbo_streams()
    {
        $this->get(route('testing.turbo-stream'))
            ->assertTurboStream(fn (AssertableTurboStream $turboStream) => (
                $turboStream->has(3)
            ));
    }

    /** @test */
    public function turbo_assert_has_turbo_stream()
    {
        $this->get(route('testing.turbo-stream'))
            ->assertTurboStream(fn (AssertableTurboStream $turboStreams) => (
                $turboStreams->has(3)
                && $turboStreams->hasTurboStream(fn ($turboStream) => (
                    $turboStream->where('target', 'posts')
                                ->where('action', 'append')
                                ->see('Post Title')
                ))
                && $turboStreams->hasTurboStream(fn ($turboStream) => (
                    $turboStream->where('target', 'inline_post_123')
                                ->where('action', 'replace')
                                ->see('Inline Post Title')
                ))
                && $turboStreams->hasTurboStream(fn ($turboStream) => (
                    $turboStream->where('target', 'empty_posts')
                                ->where('action', 'remove')
                ))
            ));
    }
}

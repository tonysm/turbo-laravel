<?php

namespace Tonysm\TurboLaravel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\View\View;
use PHPUnit\Framework\Assert;
use Tonysm\TurboLaravel\Broadcasters\Broadcaster;
use Tonysm\TurboLaravel\Broadcasters\LaravelBroadcaster;
use Tonysm\TurboLaravel\Commands\TurboInstallCommand;
use Tonysm\TurboLaravel\Facades\Turbo as TurboFacade;
use Tonysm\TurboLaravel\Http\PendingTurboStreamResponse;
use Tonysm\TurboLaravel\Http\TurboResponseFactory;
use Tonysm\TurboLaravel\Testing\AssertableTurboStream;

class TurboServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/turbo-laravel.php' => config_path('turbo-laravel.php'),
            ], 'config');

            $this->publishes([
                __DIR__ . '/../resources/views' => base_path('resources/views/vendor/turbo-laravel'),
            ], 'views');

            $this->commands([
                TurboInstallCommand::class,
            ]);
        }

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'turbo-laravel');

        $this->bindBladeMacros();
        $this->bindRequestAndResponseMacros();
        $this->bindTestResponseMacros();
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/turbo-laravel.php', 'turbo-laravel');

        $this->app->scoped(Turbo::class);
        $this->app->bind(Broadcaster::class, LaravelBroadcaster::class);
    }

    private function bindBladeMacros(): void
    {
        Blade::if('turbonative', function () {
            return TurboFacade::isTurboNativeVisit();
        });

        Blade::if('unlessturbonative', function () {
            return ! TurboFacade::isTurboNativeVisit();
        });

        Blade::directive('domid', function ($expression) {
            return "<?php echo e(\\Tonysm\\TurboLaravel\\dom_id($expression)); ?>";
        });

        Blade::directive('domclass', function ($expression) {
            return "<?php echo e(\\Tonysm\\TurboLaravel\\dom_class($expression)); ?>";
        });

        Blade::directive('channel', function ($expression) {
            return "<?php echo {$expression}->broadcastChannel(); ?>";
        });
    }

    private function bindRequestAndResponseMacros(): void
    {
        Response::macro('turboStream', function (?Model $model = null, string $action = null) {
            if ($model === null) {
                return new PendingTurboStreamResponse();
            }

            return PendingTurboStreamResponse::forModel($model, $action);
        });

        Response::macro('turboStreamView', function ($view, array $data = []) {
            if (! $view instanceof View) {
                $view = view($view, $data);
            }

            return TurboResponseFactory::makeStream($view->render());
        });

        Request::macro('wantsTurboStream', function () {
            return Str::contains($this->header('Accept'), Turbo::TURBO_STREAM_FORMAT);
        });
    }

    protected function bindTestResponseMacros()
    {
        if (! app()->environment('testing')) {
            return;
        }

        TestResponse::macro('assertTurboStream', function (callable $callback = null) {
            Assert::assertStringContainsString(
                Turbo::TURBO_STREAM_FORMAT,
                $this->headers->get('Content-Type'),
            );

            if ($callback === null) {
                return;
            }

            $callback(new AssertableTurboStream($this));
        });

        TestResponse::macro('assertNotTurboStream', function () {
            Assert::assertStringNotContainsString(
                Turbo::TURBO_STREAM_FORMAT,
                $this->headers->get('Content-Type'),
            );
        });
    }
}

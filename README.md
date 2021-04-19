<p align="center"><img src="/art/turbo-laravel-logo.svg" alt="Logo Turbo Laravel" /></p>

<p align="center">
    <a href="https://github.com/tonysm/turbo-laravel/workflows/Tests/badge.svg">
        <img src="https://img.shields.io/github/workflow/status/tonysm/turbo-laravel/Tests?label=tests" />
    </a>
    <a href="https://packagist.org/packages/tonysm/turbo-laravel">
        <img src="https://img.shields.io/packagist/dt/tonysm/turbo-laravel" alt="Total Downloads">
    </a>
    <a href="https://packagist.org/packages/tonysm/turbo-laravel">
        <img src="https://img.shields.io/packagist/v/tonysm/turbo-laravel" alt="Latest Stable Version">
    </a>
    <a href="https://packagist.org/packages/tonysm/turbo-laravel">
        <img src="https://img.shields.io/packagist/l/tonysm/turbo-laravel" alt="License">
    </a>
</p>

**This package gives you a set of conventions to make the most out of [Hotwire](https://hotwire.dev/) in Laravel** (inspired by the [turbo-rails](https://github.com/hotwired/turbo-rails) gem). There is a [companion application](https://github.com/tonysm/turbo-demo-app) that shows how to use the package and its conventions in your application.

<a name="installation"></a>
## Installation

You may install the package via composer:

```bash
composer require tonysm/turbo-laravel
```

In case you're NOT using Jetstream, you may publish the asset files with:

```bash
php artisan turbo:install
```

You may also use Turbo Laravel with Jetstream if you use the Livewire stack. If you want to do so, you may want to publish the assets using the `--jet` flag:

```bash
php artisan turbo:install --jet
```

The *turbo:install* command will make sure you have the needed JS libs on your `package.json` file and will also publish some JS files to your application. By default, it will add `@hotwired/turbo` to your `package.json` file and publish another custom HTML tag to integrate Turbo with Laravel Echo in the `resources/js/elements` folder. With the `--jet` flag, it will also add a couple needed bridge libs to make sure you can use Hotwire combined with Jetstream and Livewire, these are:

* [Alpine Turbo Bridge](https://github.com/SimoTod/alpine-turbo-drive-adapter), needed so Alpine.js works nicely; and
* [Livewire Turbo Plugin](https://github.com/livewire/turbolinks) needed so Livewire works nicely. This one will be added to your Jetstream layouts as script tags fetching from a CDN (both `app.blade.php` and `guest.blade.php`)

You may also optionally install Stimulus on top of this all by passing `--stimulus` flag to the `turbo:install` command:

```bash
php artisan turbo:install --jet --stimulus
```
Stimulus is optional because you may want to stick with Alpine.js (or both /shrug).

The package ships with a middleware that applies some conventions on your redirects, specially around how failed validations are handled automatically by Laravel. Read more about this in the [Conventions](#conventions) section of the documentation.

You may add the middleware to the "web" route group on your HTTP Kernel, like so:

```php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    protected $middlewareGroups = [
        'web' => [
            // ...
            \Tonysm\TurboLaravel\Http\Middleware\TurboMiddleware::class,
        ],
    ];
}
```

Keep reading the documentation to have a full picture on how you can make the most out of the technique.

<a name="documentation"></a>
## Documentation

It's highly recommended reading the [Turbo Handbook](https://turbo.hotwire.dev/). Out of everything Turbo provides, it's Turbo Streams that benefits the most from a tight integration with Laravel. We can generate [Turbo Streams](#turbo-streams) from your models and either [return them from HTTP responses](#turbo-stream-request-macro) or *broadcast* your model changes over [WebSockets using Laravel Echo](#turbo-streams-and-laravel-echo).

* [Conventions](#conventions)
* [Overview](#overview)
* [Notes on Turbo Drive and Turbo Frames](#notes-on-turbo-drive-and-turbo-frames)
* [Blade Directives and Helper Functions](#blade-directives-and-helper-functions)
* [Turbo Streams](#turbo-streams)
* [Custom Turbo Stream Views](#custom-turbo-stream-views)
* [Broadcasting Turbo Streams Over WebSockets with Laravel Echo](#broadcasting)
    * [Broadcasting Model Changes](#broadcasting-model-changes)
    * [Listening to Broadcasts](#listening-to-broadcasts)
    * [Broadcasting to Others](#broadcasting-to-others)
* [Validation Response Redirects](#redirects)
* [Turbo Native](#turbo-native)
* [Testing Helpers](#testing-helpers)
* [Closing Notes](#closing-notes)

<a name="conventions"></a>
### Conventions

First of all, none of these conventions are mandatory. Feel free to pick the ones you like and also add your own. With that out of the way, here's a list of some conventions that I find helpful:

* You may want to use resource routes for most things (`posts.index`, `posts.store`, etc)
* You may want to split your views into smaller chunks or _partials_ (small portions of HTML for specific fragments), such as `comments/_comment.blade.php` that displays a comment resource, or `comments/_form.blade.php` for the form to either create/update comments. This will allow you to reuse these partials in [Turbo Streams](#turbo-streams)
* Your model's partial (such as the `comments/_comment.blade.php` for a `Comment` model, for example) may only rely on having a `$comment` instance passed to it. When broadcasting your model changes and generating the Turbo Streams in background, the package will pass the model instance using the model's basename in _camelCase_ to that partial - although you can fully control this behavior
* You may use the model's Fully Qualified Class Name, or FQCN for short, on your Broadcasting Channel authorizations with a wildcard such as `.{id}`, such as `App.Models.Comment.{comment}` for a `Comment` model living in `App\\Models\\` - the wildcard's name doesn't matter

In the [Overview section](#overview) below you will see how to override most of the default behaviors, if you want to.

<a name="overview"></a>
### Overview

Once the assets are compiled, you will have a couple custom HTML tags that you may annotate your Turbo Frames and Turbo Streams with. This is vanilla Hotwire. Again, it's recommended to read the [Turbo Handbook](https://turbo.hotwire.dev/handbook/introduction). Once you understand how these few pieces work together, the challenge will be in decomposing your UI to work as you want them to.

<a name="notes-on-turbo-drive-and-turbo-frames"></a>
### Notes on Turbo Drive and Turbo Frames

To keep it short, Turbo Drive will turn links and form submissions into AJAX requests and will replace the page with the response. That's useful when you want to navigate to another page entirely.

If you want some elements to persist across these navigations, you may annotate these elements with a DOM ID and add the `data-turbo-permanent` custom attribute to them. As long as the response also contains an element with the same ID and `data-turbo-permanent`, Turbo will not touch it.

Sometimes you don't want the entire page to change, but instead just a portion of the page. That's what [Turbo Frames](https://turbo.hotwire.dev/handbook/frames) are all about. Links and Form submissions that are trapped inside a Turbo Frame tag (or that point to one!) will instruct Turbo Drive to **NOT** replace the entire body of the document, but instead to look for a matching Turbo Frame in the response using its DOM ID and replace that specific portion of the page.

Here's how you can use Turbo Frames:

```html
<turbo-frame id="my_frame">
    <h1>Hello, World!</h1>
    <a href="/somewhere">
        I'm a trigger. My response must have a matching Turbo Frame tag (same ID)
    </a>
</turbo-frame>
```

Turbo Frames also allows you to lazy-load the frame's content. You may do so by adding a `src` attribute to the Turbo Frame tag. The conetnt of a lazy-loading Turbo Frame tag can be used to indicate "loading states", such as:

```blade
<turbo-frame id="my_frame" src="{{ route('my.page') }}">
    <p>Loading...</p>
</turbo-frame>
```

Turbo will automatically fire a GET AJAX request as soon as a lazy-loading Turbo Frame enters the DOM and replace its content with a matching Turbo Frame in the response.

You may also trigger a Turbo Frame with forms and links that are _outside_ of such frames by pointing to them like so:

```blade
<div>
    <a href="/somewhere" data-turbo-frame="my_frame">I'm a link</a>

    <turbo-frame id="my_frame"></turbo-frame>
</div>
```

You could also "hide" this link and trigger a "click" event with JavaScript programmatically to trigger the Turbo Frame to reload, for example.

So far, all vanilla Hotwire and Turbo.

<a name="blade-directives-and-helper-functions"></a>
### Blade Directives and Helper Functions

Since Turbo rely a lot on DOM IDs, the package offers a helper to generate unique DOM IDs based on your models. You may use the `@domid` Blade Directive in your Blade views like so:

```blade
<turbo-frame id="@domid($comment)">
    <!-- Content -->
</turbo-frame>
```

This will generate a DOM ID string using your model's basename and its ID, such as `comment_123`. You may also give it a _content_ that will prefix your DOM ID, such as:

```blade
<turbo-frame id="@domid($post, 'comments_count')">(99)</turbo-frame>
```

Which will generate a `comments_count_post_123` DOM ID.

The package also ships with a namespaced `dom_id()` helper function so you can use it outside of your own views:

```php
use function Tonysm\TurboLaravel\dom_id;

dom_id($comment);
```

When a new instance of a model is passed to any of these DOM ID helpers, since it doesn't have an ID, it will prefix the resource anme with a `create_` prefix. This way, new instances of an `App\\Models\\Comment` model will generate a `create_comment` DOM ID.

These helpers strip out the model's FQCN (see [config/turbo-laravel.php](config/turbo-laravel.php) if you use an unconventional location for your models).

<a name="turbo-streams"></a>
### Turbo Streams

As mentioned earlier, out of everything Turbo provides, it's Turbo Streams that benefit the most from a back-end integration.

Turbo Drive will get your pages behaving like an SPA and Turbo Frames will allow you to have a finer grained control of chunks of your page instead of replace the entire page when a form is submitted or a link is clicked.

However, sometimes you want to update _multiple_ parts of you page at the same time. For instance, after a form submission to create a comment, you may want to append the comment to the comment's list and also update the comment's count in the page. You may achieve that with Turbo Streams.

Any non-GET form submission will get annotated by Turbo with a `Content-Type: text/vnd.turbo-stream.html` header (besides the other normal Content Types). This will indicate your back-end that you can return a Turbo Stream response for that form submission if you want to.

Here's an example of a route handler detecting and returning a Turbo Stream response to a form submission:

```php
Route::post('posts/{post}/comments', function (Post $post) {
    $comment = $post->comments()->create(/** params */);

    if (request()->wantsTurboStream()) {
        return response()->turboStreamView('comments.turbo.created_stream', [
            'comment' => $comment,
        ]);
    }

    return back();
});
```

The `request()->wantsTurboStream()` macro added to the request will check if the request accepts Turbo Stream and return `true` or `false` accordingly.

Here's what that `comments.turbo.created_stream.blade.php` view could look like:

```blade
<turbo-stream action="append" target="comments">
    <template>
        @include('comments._comment', ['comment' => $comment])
    </template>
</turbo-stream>
```

There are 5 _actions_ in Turbo Streams. They are:

* `append` & `prepend`: to add the elements in the target element after the existing contents or before, respectively
* `replace`: will replace the existing element entirely with the contents of the `template` tag in the Turbo Stream
* `update`: will keep the target and only replace the contents of it with the contents of the `template` tag in the Turbo Stream
* `remove`: will remove the element. This one doesn't need a `<template>` tag.

You can read more about Turbo Streams in the [Turbo Handbook](https://turbo.hotwire.dev/handbook/streams).

If you notice, all we're doing in the `comments.turbo.created_stream.blade.php` view is wrapping the comment's partial with a Turbo Stream tag. You can alternatically delegate the generation of the Turbo Stream tag to a `response()->turboStream()` macro, which will essentially do the same thing, but in this case you wouldn't need that `comments.turbo.created_stream.blade.php` anymore:

```php
Route::post('posts/{post}/comments', function (Post $post) {
    $comment = $post->comments()->create(/** params */);

    if (request()->wantsTurboStream()) {
        return response()->turboStream($comment);
    }

    return back();
});
```

Again, this will detect that your model was recently created and generate an `append` Turbo Stream to a `comments` target (using the plural name of your model's basename for that) and render the model's partial inside a `template` tag, similarly to what we were doing manually.

The `response()->turboStream()` macro will generate a _replace_ Turbo Stream action targeting your model's DOM ID when you are only updating the model. In the same way, if you have deleted the model, it will generate a _remove_ Turbo Stream also using the model's DOM ID as target.

If you're not using the model partial convention, you may stick with the `response()->turboStreamView()` version instead and specify your own Turbo Stream views. See the [conventions section](#ceventions) to read more about this.

You may override the partial name by implementing a `hotwirePartialName` method in your model. You may also have more control over the data that is passed down to the partial by implementing the `hotwirePartialData` method, like so:

```php
class Comment extends Model
{
  public function hotwirePartialName()
  {
    return 'my.non.conventional.partial.name';
  }

  public function hotwirePartialData()
  {
    return [
      'lorem' => false,
      'ipsum' => true,
      'comment' => $this,
    ];
  }
}
```

You may also override the resource name used as target in the case wher you're generating a Turbo Stream for recently created models, as well as the DOM ID that will be used as targets when your model was either updated or deleted by implementing the following methods:

```php
class Comment extends Model
{
  public function hotwireTargetResourcesName()
  {
    return 'admin_comments';
  }

  public function hotwireTargetDomId()
  {
    return "admin_comment_{$this->id}";
  }
}
```

<a name="custom-turbo-stream-views"></a>
### Custom Turbo Stream Views

Erlier I showed you a custom Turbo Stream view before I showed you how to auto-generate Turbo Stream views for you models. The name and location of that view was no accident. When auto-generating the Turbo Stream views for your model using the `response()->turboStream()` helper function, it will first check if you have a Custom Turbo Stream View in place for this model and, if so, it will use that view instead of generating one from scratch.

This way, you can have more control over your Turbo Stream responses. To use custom Turbo Stream views, you may create a `turbo` folder in the model's resource views folder and name them after the model event you want to override, like so:

| Model Event | Expected View |
|---|---|
| `created` | `{resource}/turbo/created_stream.blade.php` |
| `updated` | `{resource}/turbo/updated_stream.blade.php` |
| `deleted` | `{resource}/turbo/deleted_stream.blade.php` |

**Note: these will only be used when you're using the `response()->turboStream()` macro.**

<a name="broadcasting"></a>
### Broadcasting Turbo Streams Over WebSockets With Laravel Echo

So far, we have used Turbo Streams over HTTP to handle the case of updating multiple parts of the page for a single user after a form submission. In addition to that, you may want to broadcast model changes over WebSockets to all users that are viewing the same page. Although nice, **you don't have to use WebSockets if you don't have the need for it. You may still benefit from Turbo Streams over HTTP.**

Those same Turbo Stream responses we are returning to a user after a form submission, we can also send those to other users connected to a Laravel Echo channel and have their pages update reflecting the model change made by other users.

You may still feed the user making the changes with Turbo Streams over HTTP and broadcast the changes to other users over WebSockets. This way, the user making the change will have an instant feedback compared to having to wait for a background worker to pick up the job and send it to them over WebSockets.

First, setup the [Laravel Broadcasting](https://laravel.com/docs/8.x/broadcasting) component for your app. One of the first steps is to configure your environment variables to something that looks like this:

```dotenv
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=us2
PUSHER_APP_HOST=websockets.test

MIX_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
MIX_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
MIX_PUSHER_HOST="localhost"
MIX_PUSHER_PORT="${LARAVEL_WEBSOCKETS_PORT}"
MIX_PUSHER_USE_SSL=false
```

Notice that some of these environment variables are used by your front-end assets during compilation. That's why you see some duplicates that are just prefixed with `MIX_`.

These settings assume you're using the [Laravel WebSockets](https://github.com/beyondcode/laravel-websockets) package. Check out the [resources/js/echo.js](resources/js/echo.js) for the suggested dotenv credentials you may need to configure. You may also use [Pusher](https://pusher.com/) instead of the Laravel WebSockets package, if you don't want to host it yourself.

<a name="broadasting-model-changes"></a>
#### Broadcasting Model Changes

With Laravel Echo properly configured, you may now broadcast model changes using WebSockets. First thing you need to do is use the `Broadcasts` trait in your model:

```php
use Tonysm\TurboLaravel\Models\Broadcasts;

class Comment extends Model
{
    use Broadcasts;
}
```

This trait will add some methods to your model that you can use to trigger broadcasts. Here's how you can broadcast appending a new comment to all users visiting the post page:

```php
Route::post('posts/{post}/comments', function (Post $post) {
    $comment = $post->comments()->create(/** params */);

    $comment->broadcastAppend()->later();

    if (request()->wantsTurboStream()) {
        return response()->turboStream($comment);
    }

    return back();
});
```

Here are the methods now available to your model:

```php
$comment->broadcastAppend();
$comment->broadcastPrepend();
$comment->broadcastReplace();
$comment->broadcastUpdate();
$comment->broadcastRemove();
```

These methods will assume you want to broadcast the Turbo Streams to your model's channel. However, you will also find alternative methods where you can specify either a model or the broadcasting channels you want to send the broadcasts to:

```php
$comment->broadcastAppendTo($post);
$comment->broadcastPrependTo($post);
$comment->broadcastReplaceTo($post);
$comment->broadcastUpdateTo($post);
$comment->broadcastRemoveTo($post);
```

These `broadcastXTo()` methods accept either a model, a channel instance or an array containing both of these. When it receives a model, it will guess the channel name using the broadcasting channel convention (see [#conventions](#conventions)).

All of these broadcasting methods return an instance of a `PendingBroadcast` class that will only dispatch the broadcasting job when that pending object is being garbage collected. Which means that you can control a lot of the properties of the broadcast by chaining on that instance before it goes out of scope, like so:

```php
$comment->broadcastAppend()
    ->to($post)
    ->partial('comments/_custom_partial', [
        'comment' => $comment,
        'post' => $post,
    ])
    ->toOthers() // do not send to the current user
    ->later(); // dispatch a background job to send
```

You may want to hook those methods in the model events of your model to trigger Turbo Stream broadcasts whenever your models are changed in any context, such as:

```php
class Comment extends Model
{
    use Broadcasts;

    protected static function booted()
    {
        static::created(function (Comment $comment) {
            $comment->broadcastPrependTo($comment->post)
                ->toOthers()
                ->later();
        });

        static::updated(function (Comment $comment) {
            $comment->broadcastReplaceTo($comment->post)
                ->toOthers()
                ->later();
        });

        static::deleted(function (Comment $comment) {
            $comment->broadcastRemoveTo($comment->post)
                ->toOthers()
                ->later();
        });
    }
}
```

In case you want to broadcast all these changes automatically, instead of specifying them all, you may want to add a `$broadcasts` property to your model, which will instruct the `Broadcasts` trait to trigger the Turbo Stream broadcasts for the created, updated and deleted model events, like so:

```php
class Comment extends Model
{
    use Broadcasts;

    protected $broadcasts = true;
}
```

This will achieve almost the same thing as the example where we registered the model events manually, with a couple nuanced differences. First, by default, it will broadcast an `append` Turbo Stream to newly created models. You may want to use `prepend` instead. You can do so by using an array with a `insertsBy` key and `prepend` action as value instead of a boolean, like so:

```php
class Comment extends Model
{
    use Broadcasts;

    protected $broadcasts = [
        'insertsBy' => 'prepend',
    ];
}
```

This will also automatically hook into the model events, but instead of broadcasting new instances as `append` it will use `prepend`.

Secondly, it will send all changes to this model's broadacsting channel. In our case, we want to direct the broadcasts to the post linked to this model instead. We can achieve that by adding a `$broadcastsTo` property to the model, like so:

```php
class Comment extends Model
{
    use Broadcasts;

    protected $broadcasts = [
        'insertsBy' => 'prepend',
    ];

    protected $broadcastsTo = 'post';

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
```

That property can either be a string that contains to the name of a relationship of this model or an array of relationships.

Alternatively, you may prefer to have more control over where these broadcasts are being sent to by implementing a `broadcastsTo` method in your model instead of using the property. This way, you can return a single model, a broadcasting channel instance or an array containing either of them, like so:

```php
use Illuminate\Broadcasting\Channel;

class Comment extends Model
{
    use Broadcasts;

    protected $broadcasts = [
        'insertsBy' => 'prepend',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function broadcastsTo()
    {
        return [
            $this,
            $this->post,
            new Channel('full-control'),
        ];
    }
}
```

<a name="listening-to-broadcasts"></a>
#### Listening to Turbo Stream Broadcasts

You may listen to a Turbo Stream broadcast message on your pages by adding the custom HTML tag `<turbo-echo-stream-source>` that is published to your application's assets (see [here](./stubs/resources/js/elements/turbo-echo-stream-tag.js)). You need to pass the channel you want to listen to broadcasts on using the `channel` attribute of this element, like so.

```blade
<turbo-echo-stream-source
    channel="App.Models.Comments.{{ $comment->id }}"
/>
```

By default, it expects a private channel, so the tag must be used in a page for already authenticated users. You can control the channel type in the tag with a `type` attribute.

```blade
<turbo-echo-stream-source
    channel="App.Models.Comments.{{ $comment->id }}"
    type="presence"
/>
```

There is a helper blade directive that you can use to generate the channel name for your models using the same convention:

```blade
<turbo-echo-stream-source
    channel="@channel($comment)"
/>
```

Alternatively, the package also offers some helper functions to generate the channel names following the package's conventions, like so:

```php
use function Tonysm\TurboLaravel\turbo_channel;

// returns "App.Models.Comment.123"
turbo_channel($comment);
```

There is also a helper you can use to generate the channel name on your broadcasting authorization:

```php
// file: routes/channels.php

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

use function Tonysm\TurboLaravel\turbo_channel_auth as channelFor;

Broadcast::channel(channelFor(Post::class), function (User $user, Post $post) {
    return $user->belongsToTeam($post->team);
});
```

You may want to read the [Laravel Broadcasting](https://laravel.com/docs/8.x/broadcasting) documentation.

<a name="broadcasting-to-others"></a>
#### Broadcasting Turbo Streams to Other Users Only

As mentioned erlier, you may want to feed the current user with Turbo Streams using HTTP requests and only send the broadcasts to other users. There are a couple ways you can achieve that.

First, you can chain on the broadcasting methods, like so:

```php
$comment->broadcastAppendTo($post)
    ->toOthers();
```

Second, you can use the Turbo Facade like so:

```php
use Tonysm\TurboLaravel\Facades\Turbo;

Turbo::broadcastToOthers(function () {
    // ...
});
```

This way, any broadcast that happens inside the scope of the closure will only be sent to other users.

Third, you may use that same method but without the closure inside a ServiceProvider, for instance, to instruct the package to only send turbo stream broadcasts to other users globally:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Tonysm\TurboLaravel\Facades\Turbo;

class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Turbo::broadcastToOthers();
    }
}
```

<a name="redirects"></a>
### Validation Response Redirects

By default, Laravel will redirect failed validation exceptions "back" to the page the triggered the request. This is a bit problematic when it comes to Turbo Frames, since a form might be included in a page that don't render the form initially, and after a failed validation exception from a form submission we would want to re-render the form with the invalid messages.

In other words, a Turbo Frame inherits the context of the page where it was inserted in, and a form might not be part of that page itself. We can't redirect "back" to display the form again with the error messages, because the form might not be re-rendered there by default. Instead, we have two options:

1. Render a Blade view with the form as a non-200 HTTP Status Code, then Turbo will look for a matching Turbo Frame inside the response and replace only that portion or page, but it won't update the URL as it would for other Turbo Visits; or
2. Redirect the request to a page that renders the form directly instead of "back". There you can render the validation messages and all that. Turbo will follow the redirect (303 Status Code) and fetch the Turbo Frame with the form and invalid messages and update the existing one.

When using the `\Tonysm\TurboLaravel\Http\Middleware\TurboMiddleware` middleware that ships with the package on your HTTP Kernel's "web" route group, it will override Laravel's default handling for failed validation exceptions.

For any route name ending in `.store`, it will redirect to a `.create` route for the same resource with all the route params from the previous request. In the same way, for any `.update` routes, it will redirect to a `.edit` route of the same resource.

Examples:

- `posts.comments.store` will redirect to `posts.comments.create` with the `{post}` route param.
- `comments.store` will redirect to `comments.create` with no route params.
- `comments.update` will redirect to `comments.edit` with the `{comment}` param.

If a guessed route name doesn't exist, the middleware will not change the redirect response. You may override this behavior by catching the `ValidationException` yourself and re-throwing it overriding the redirect with the `redirectTo` method. If the exception has that, the middleware will respect it.

```php
public function store()
{
  try {
     request()->validate(['name' => 'required']);
  } catch (\Illuminate\Validation\ValidationException $exception) {
    throw $exception->redirectTo(url('/somewhere'));
  }
}
```

You may also catch the `ValidationException` and return a non-200 response, if you want to.


<a name="turbo-native"></a>
### Turbo Native

Hotwire also has a [mobile side](https://turbo.hotwire.dev/handbook/native), and the package provides some goodies on this front too.

Turbo Visits made by a Turbo Native client will send a custom `User-Agent` header. So we added another Blade helper you may use to toggle fragments or assets (such as mobile specific stylesheets) on and off depending on whether your page is being rendered for a Native app or a Web app:

```blade
@turbonative
    <h1>Hello, Mobile Users!</h1>
@endturbonative
```

You may also check if the request was made from a Turbo Native visit using the TurboFacade, like so:

```php
if (\Tonysm\TurboLaravel\Facades\Turbo::isTurboNativeVisit()) {
    // Do something for mobile specific requests.
}
```

<a name="testing-helpers"></a>
### Testing Helpers

There is a [companion package](https://github.com/tonysm/turbo-laravel-test-helpers) that you may use as a dev dependency on your application to help with testing your apps using Turbo Laravel. First, install the package:

```bash
composer require tonysm/turbo-laravel-test-helpers --dev
```

And then you will be able to test your application like:

``` php
use Tonysm\TurboLaravelTestHelpers\Testing\InteractsWithTurbo;

class ExampleTest extends TestCase
{
    use InteractsWithTurbo;

    /** @test */
    public function turbo_stream_test()
    {
        $response = $this->turbo()->post('my-route');

        $response->assertTurboStream();

        $response->assertHasTurboStream($target = 'users', $action = 'append');

        $response->assertDoesntHaveTurboStream($target = 'empty_users', $action = 'remove');
    }

    /** @test */
    public function turbo_native_shows()
    {
        $response = $this->turboNative()->get('my-route');

        $response->assertSee('Only rendered in Turbo Native');
    }
}
```

Check out the [package repository](https://github.com/tonysm/turbo-laravel-test-helpers) if you want to know more about it.

All model's broadcast will dispatch a `Tonysm\TurboLaravel\Jobs\BroadcastAction` job (either to a worker or process them immediately). You may also use that to test your broadcasts like so:

```php
use App\Models\Post;
use Tonysm\TurboLaravel\Jobs\BroadcastAction;

use function Tonysm\TurboLaravel\turbo_channel;

class CreatesCommentsTest extends TestCase
{
    /** @test */
    public function creates_comments()
    {
        Bus::fake(BroadcastAction::class);

        $post = Post::factory()->create();

        $this->turbo()->post(route('posts.comments.store', $post), [
            'content' => 'Hello, World',
        ])->assertTurboStream();

        Bus::assertDispatched(function (BroadcastAction $job) use($post) {
            return count($job->channels) === 1
                && $job->channels[0]->name === turbo_channel($post)
                && $job->target === 'comments'
                && $job->action === 'append'
                && $job->partial === 'comments._comment'
                && $job->partialData['comment']->is(
                    $post->comments->first()
                );
        });
    }
}
```

**Note: make sure your `turbo-laravel.queue` config key is set to false, otherwise actions may not be dispatched during test because the model observer only fires them after the transaction is commited, which never happens in tests since they run inside a transaction.**

<a name="closing-notes"></a>
### Closing Notes

Try the package out. Use your Browser's DevTools to inspect the responses. You will be able to spot every single Turbo Frame and Turbo Stream happening.

> "The proof of the pudding is in the eating."

Make something awesome!

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Drop me an email at [tonysm@hey.com](mailto:tonysm@hey.com?subject=Security%20Vulnerability) if you want to report
security vulnerabilities.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [Tony Messias](https://github.com/tonysm)
- [All Contributors](./CONTRIBUTORS.md)

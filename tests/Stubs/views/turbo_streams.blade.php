<turbo-stream target="posts" action="append">
    <template>
        <div id="post_123">
            <h1>Post Title</h1>
            <p>Lorem Ipsum</p>
        </div>
    </template>
</turbo-stream>

<turbo-stream target="inline_post_123" action="replace">
    <template>
        <div>
            <h1>Inline Post Title</h1>
        </div>
    </template>
</turbo-stream>

<turbo-stream target="empty_posts" action="remove"></turbo-stream>

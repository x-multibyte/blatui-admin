<div class="{{ $column->getClass() }}">
    @foreach ($column->getContents() as $content)
        @if ($content instanceof \BlatUI\Admin\Layout\Row)
            @include('blatui-admin::layouts.partials.row', ['row' => $content])
        @elseif (is_array($content))
            @foreach ($content as $item)
                {{ $item }}
            @endforeach
        @elseif ($content instanceof \Closure)
            {{ new \Illuminate\Support\HtmlString(\BlatUI\Admin\Support\Helper::render($content)) }}
        @else
            {{ $content }}
        @endif
    @endforeach
</div>

<div class="{{ $column->getClass() }}">
    @foreach ($column->getContents() as $content)
        @if ($content instanceof \BlatUI\Admin\Layout\Row)
            @include('blatui-admin::layouts.partials.row', ['row' => $content])
        @elseif ($content instanceof \BlatUI\Admin\Layout\Column)
            @include('blatui-admin::layouts.partials.column', ['column' => $content])
        @elseif (is_array($content))
            @foreach ($content as $item)
                @if ($item instanceof \BlatUI\Admin\Layout\Row)
                    @include('blatui-admin::layouts.partials.row', ['row' => $item])
                @elseif ($item instanceof \BlatUI\Admin\Layout\Column)
                    @include('blatui-admin::layouts.partials.column', ['column' => $item])
                @else
                    {{ $item }}
                @endif
            @endforeach
        @elseif ($content instanceof \Closure)
            {{ new \Illuminate\Support\HtmlString(\BlatUI\Admin\Support\Helper::render($content)) }}
        @else
            {{ $content }}
        @endif
    @endforeach
</div>

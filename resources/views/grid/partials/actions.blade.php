@if(!$row->isActionsDisabled())
    @php
        $actions = $row->getActions();
    @endphp
    @if(count($actions) > 0)
        <div class="inline-flex items-center gap-3">
            @foreach($actions as $action)
                @if($action instanceof \Closure)
                    {{ new \Illuminate\Support\HtmlString((string) $action($row)) }}
                @elseif($action instanceof \BlatUI\Admin\Grid\RowAction)
                    {{ new \Illuminate\Support\HtmlString($action->render($row)) }}
                @elseif($action instanceof \Illuminate\Contracts\Support\Renderable)
                    {{ new \Illuminate\Support\HtmlString((string) $action->render()) }}
                @elseif($action instanceof \Illuminate\Contracts\Support\Htmlable)
                    {{ $action }}
                @else
                    {{ new \Illuminate\Support\HtmlString((string) $action) }}
                @endif
            @endforeach
        </div>
    @endif
@endif

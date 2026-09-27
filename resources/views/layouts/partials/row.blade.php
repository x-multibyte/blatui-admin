<div class="{{ $row->getClass() }}">
    @foreach ($row->getColumns() as $column)
        @include('blatui-admin::layouts.partials.column', ['column' => $column])
    @endforeach
</div>

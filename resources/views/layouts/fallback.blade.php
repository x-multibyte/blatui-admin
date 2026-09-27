<div class="admin-content">
    @if ($title !== '')
        <h1 class="text-2xl font-bold">{{ $title }}</h1>
    @endif

    @if ($description !== '')
        <p class="text-sm text-gray-500">{{ $description }}</p>
    @endif

    <div class="admin-content-body">
        @foreach ($rows as $row)
            {!! $row->render() !!}
        @endforeach
    </div>
</div>

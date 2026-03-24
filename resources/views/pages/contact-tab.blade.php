@extends('ngotools::layouts.app')

@section('content')
<div class="p-4">
    <h2 class="text-lg font-semibold">{{ config('app.name') }}</h2>

    <div class="mt-3 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Contact: <strong id="ngt-entity-id">...</strong>
        </p>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('ngotools:init', function(e) {
        var state = e.detail;
        var entityId = state.context?.entityId ?? 'N/A';
        document.getElementById('ngt-entity-id').textContent = '#' + entityId;
    });
</script>
@endpush
@endsection

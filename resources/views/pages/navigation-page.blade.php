@extends('ngotools::layouts.app')

@section('content')
<div class="p-6">
    <h1 class="text-2xl font-bold">{{ config('app.name') }}</h1>

    <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Connected as <strong id="ngt-user-name">...</strong>
        </p>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-500">
            Tenant: <span id="ngt-tenant-id">...</span> &middot;
            Locale: <span id="ngt-locale">...</span>
        </p>
    </div>

    <div class="mt-6">
        <p class="text-gray-600 dark:text-gray-400">
            Your app is running. Edit
            <code class="rounded bg-gray-100 px-1.5 py-0.5 text-sm dark:bg-gray-800">resources/views/ngotools/pages/navigation-page.blade.php</code>
            to get started.
        </p>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('ngotools:init', function(e) {
        var state = e.detail;
        document.getElementById('ngt-user-name').textContent = state.user.name;
        document.getElementById('ngt-tenant-id').textContent = state.tenantId;
        document.getElementById('ngt-locale').textContent = state.locale;
    });
</script>
@endpush
@endsection

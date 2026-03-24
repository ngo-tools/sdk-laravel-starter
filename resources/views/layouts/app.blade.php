<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
</head>
<body class="bg-white text-gray-900 dark:bg-gray-900 dark:text-gray-100">
    @yield('content')

    <script src="{{ rtrim(config('ngotools.api_url'), '/') }}/../sdk/bridge.js"></script>
    <script>
        NGOTools.onInit(function(state) {
            NGOTools.applyTheme(state.theme);
            document.dispatchEvent(new CustomEvent('ngotools:init', { detail: state }));
        });
    </script>
    @stack('scripts')
</body>
</html>

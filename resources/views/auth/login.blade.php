<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-gray-50 dark:bg-gray-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ __('blatui-admin::admin.login') ?? 'Login' }} - {{ \BlatUI\Admin\Admin::title() }}</title>

    <!-- BlatUI Admin Styles -->
    <link rel="stylesheet" href="{{ asset('vendor/blatui-admin/admin.css') }}">

    <!-- BlatUI Admin Scripts -->
    <script defer src="{{ asset('vendor/blatui-admin/admin.js') }}"></script>
</head>
<body class="h-full flex items-center justify-center p-4 antialiased text-gray-900 dark:text-gray-100">
    <div class="w-full max-w-md">
        <!-- Logo Branding Header -->
        <div class="flex flex-col items-center mb-8">
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-600 text-white font-black text-2xl shadow-md mb-3">
                B
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-100">
                {{ config('blatui-admin.name', 'BlatUI Admin') }}
            </h1>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Modern administrative control panel
            </p>
        </div>

        <!-- Login Card -->
        <x-blatui-admin::ui.card>
            <div class="mb-4">
                <h2 class="text-lg font-semibold tracking-tight text-gray-900 dark:text-gray-100">Sign In</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Enter your administrative credentials to continue</p>
            </div>

            <!-- Error Summary -->
            @if ($errors->any())
                <div class="mb-4 rounded-lg bg-red-50 p-3 text-xs text-red-700 dark:bg-red-950/50 dark:text-red-300 border border-red-200 dark:border-red-800">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login') }}" class="space-y-4">
                @csrf

                <!-- Username -->
                <div>
                    <label for="username" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        {{ __('blatui-admin::admin.username') ?? 'Username' }}
                    </label>
                    <x-blatui-admin::ui.input
                        id="username"
                        name="username"
                        type="text"
                        :value="old('username')"
                        required
                        autofocus
                        placeholder="admin"
                        :error="$errors->first('username')"
                    />
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        {{ __('blatui-admin::admin.password') ?? 'Password' }}
                    </label>
                    <x-blatui-admin::ui.input
                        id="password"
                        name="password"
                        type="password"
                        required
                        placeholder="••••••••"
                        :error="$errors->first('password')"
                    />
                </div>

                <!-- Remember Me -->
                <div class="flex items-center justify-between pt-1">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            name="remember"
                            id="remember"
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-800"
                        />
                        <span class="text-xs text-gray-600 dark:text-gray-400">{{ __('blatui-admin::admin.remember_me') }}</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <div class="pt-2">
                    <x-blatui-admin::ui.button type="submit" class="w-full">
                        {{ __('blatui-admin::admin.login') ?? 'Sign In' }}
                    </x-blatui-admin::ui.button>
                </div>
            </form>
        </x-blatui-admin::ui.card>

        <!-- Footer Notice -->
        <p class="text-center text-xs text-gray-400 dark:text-gray-600 mt-6">
            &copy; {{ date('Y') }} {{ config('blatui-admin.name', 'BlatUI Admin') }}. All rights reserved.
        </p>
    </div>

    <!-- Sonner Flash Toasts -->
    <x-blatui-admin::ui.sonner />
</body>
</html>

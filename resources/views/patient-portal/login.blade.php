<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Acceso Paciente — Neurofeedback</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <div class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-8">
        <div class="mb-6 text-center">
            @php
                $logoRelativePath = 'storage/logos/logo.png';
                $hasLogoFile = file_exists(public_path($logoRelativePath));
            @endphp

            @if ($hasLogoFile)
                <img
                    src="{{ asset($logoRelativePath) }}"
                    alt="Neurofeedback"
                    class="mx-auto mb-3 max-h-12 w-auto object-contain"
                >
            @endif

            <span class="inline-flex items-center rounded-full bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-800 ring-1 ring-inset ring-teal-600/20">
                Portal del Paciente
            </span>
            <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">
                Consulta tu seguimiento
            </h1>
            <p class="mt-1 text-sm text-slate-600">
                Revisa tus sesiones, pagos, terapeutas y recomendaciones desde tu teléfono.
            </p>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xs sm:p-6">
            @if ($errors->any())
                <div role="alert" class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-medium text-rose-800">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('patient.login.submit') }}" class="space-y-4" novalidate>
                @csrf

                <div>
                    <label for="email" class="block text-xs font-semibold uppercase tracking-wide text-slate-700">
                        Correo electrónico
                    </label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        inputmode="email"
                        autocomplete="email"
                        required
                        autofocus
                        value="{{ old('email') }}"
                        placeholder="paciente@correo.com"
                        class="mt-1.5 min-h-[48px] w-full rounded-2xl border border-slate-300 bg-slate-50/60 px-4 text-base text-slate-900 placeholder-slate-400 transition focus:border-teal-600 focus:bg-white focus:outline-none focus:ring-2 focus:ring-teal-600/20 sm:text-sm"
                    >
                </div>

                <div>
                    <label for="password" class="block text-xs font-semibold uppercase tracking-wide text-slate-700">
                        Contraseña
                    </label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        required
                        placeholder="••••••••"
                        class="mt-1.5 min-h-[48px] w-full rounded-2xl border border-slate-300 bg-slate-50/60 px-4 text-base text-slate-900 placeholder-slate-400 transition focus:border-teal-600 focus:bg-white focus:outline-none focus:ring-2 focus:ring-teal-600/20 sm:text-sm"
                    >
                </div>

                <div class="flex items-center justify-between pt-0.5">
                    <label for="remember" class="inline-flex min-h-[36px] cursor-pointer items-center gap-2.5 text-xs font-medium text-slate-600">
                        <input
                            id="remember"
                            name="remember"
                            type="checkbox"
                            value="1"
                            @checked(old('remember'))
                            class="h-4 w-4 rounded border-slate-300 text-teal-700 focus:ring-teal-600"
                        >
                        <span>Mantener sesión iniciada</span>
                    </label>
                </div>

                <button
                    type="submit"
                    class="mt-2 flex min-h-[50px] w-full items-center justify-center rounded-2xl bg-teal-700 px-4 py-3 text-sm font-semibold text-white shadow-xs transition active:scale-[0.99] hover:bg-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2"
                >
                    Entrar a mi expediente
                </button>
            </form>
        </div>

        <p class="mt-5 text-center text-xs text-slate-500">
            ¿Problemas para acceder? Solicita apoyo en recepción de tu sucursal.
        </p>
    </div>
</body>
</html>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Expediente — Neurofeedback</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-xl items-center justify-between px-4 py-3">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-teal-700">Portal del Paciente</p>
                <h1 class="text-base font-bold text-slate-900">Mi Seguimiento Clínico</h1>
            </div>
            @if (\Illuminate\Support\Facades\Route::has('logout'))
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button
                        type="submit"
                        class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 active:scale-[0.99] hover:bg-slate-100"
                    >
                        Salir
                    </button>
                </form>
            @endif
        </div>
    </header>

    <main class="mx-auto max-w-xl space-y-4 px-4 py-4 pb-10">
        @if (! $patient)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                Tu usuario aún no tiene un expediente de paciente vinculado. Solicita en tu sucursal que asocien tu ficha.
            </div>
        @else
            {{-- Ficha del paciente --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="truncate text-lg font-bold text-slate-900">{{ $patient->full_name }}</h2>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Sucursal: <span class="font-medium text-slate-700">{{ $patient->clinic?->name ?? 'Por asignar' }}</span>
                        </p>
                    </div>
                    <span class="shrink-0 rounded-full bg-teal-50 px-2.5 py-1 text-xs font-semibold text-teal-800 ring-1 ring-inset ring-teal-600/20">
                        {{ $latestProtocol?->title ?? 'Ficha activa' }}
                    </span>
                </div>

                @if ($patient->eeg_scheduled_at)
                    <div class="mt-3 rounded-xl bg-slate-100 px-3 py-2 text-xs text-slate-700">
                        EEG programado: <span class="font-semibold text-slate-900">{{ $patient->eeg_scheduled_at->format('d/m/Y H:i') }}</span>
                    </div>
                @endif
            </section>

            {{-- Contadores clave en móvil --}}
            <section class="grid grid-cols-3 gap-2.5">
                <div class="rounded-2xl border border-slate-200 bg-white p-3 text-center shadow-xs">
                    <p class="text-[11px] font-medium text-slate-500">Programadas</p>
                    <p class="mt-1 text-xl font-bold text-indigo-600">{{ $scheduledSessions->count() }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-3 text-center shadow-xs">
                    <p class="text-[11px] font-medium text-slate-500">Tomadas</p>
                    <p class="mt-1 text-xl font-bold text-slate-900">{{ $completedSessions->count() }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-3 text-center shadow-xs">
                    <p class="text-[11px] font-medium text-slate-500">Pagadas</p>
                    <p class="mt-1 text-xl font-bold text-emerald-600">{{ $paidSessions->count() }}</p>
                </div>
            </section>

            {{-- Terapeutas que han atendido --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                <h3 class="text-sm font-bold text-slate-900">Terapeutas que te han atendido</h3>
                <div class="mt-2.5 flex flex-wrap gap-2">
                    @forelse ($attendingTherapists as $therapist)
                        <span class="rounded-xl bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-800">
                            {{ $therapist->name }}
                        </span>
                    @empty
                        <p class="text-xs text-slate-500">Aún no hay sesiones registradas con terapeuta.</p>
                    @endforelse
                </div>
            </section>

            {{-- Sesiones tomadas y pagadas --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                <h3 class="text-sm font-bold text-slate-900">Sesiones tomadas y estado de pago</h3>
                <div class="mt-3 space-y-2.5">
                    @forelse ($completedSessions as $session)
                        @php
                            $isPaid = in_array($session->payment_status, ['paid', 'covered_by_package'], true);
                            $dateText = ($session->started_at ?? $session->created_at)?->format('d/m/Y H:i') ?? 'Fecha pendiente';
                            $therapistName = $session->therapist?->name ?? $session->user?->name ?? 'Especialista NFC';
                        @endphp
                        <article class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-semibold text-slate-900">
                                    Sesión #{{ $session->session_number ?? $session->id }} · {{ $dateText }}
                                </span>
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $isPaid ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $isPaid ? 'Pagada' : 'Pendiente' }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-slate-600">
                                Terapeuta: <span class="font-medium text-slate-800">{{ $therapistName }}</span>
                            </p>
                            @if (! is_null($session->charged_amount))
                                <p class="mt-0.5 text-[11px] text-slate-500">
                                    Monto: ${{ number_format((float) $session->charged_amount, 2) }}
                                </p>
                            @endif
                        </article>
                    @empty
                        <p class="text-xs text-slate-500">Todavía no tienes sesiones tomadas registradas.</p>
                    @endforelse
                </div>
            </section>

            {{-- Sesiones programadas --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                <h3 class="text-sm font-bold text-slate-900">Sesiones programadas</h3>
                <div class="mt-3 space-y-2">
                    @forelse ($scheduledSessions as $planned)
                        <div class="flex items-center justify-between rounded-xl border border-indigo-100 bg-indigo-50/60 px-3 py-2.5">
                            <div>
                                <p class="text-xs font-semibold text-slate-900">Sesión #{{ $planned->session_number }}</p>
                                <p class="text-[11px] text-slate-600">{{ $planned->phase ?? 'Seguimiento de protocolo' }}</p>
                            </div>
                            <span class="rounded-full bg-indigo-100 px-2.5 py-0.5 text-[11px] font-medium text-indigo-800">
                                Programada
                            </span>
                        </div>
                    @empty
                        <p class="text-xs text-slate-500">No hay sesiones programadas pendientes.</p>
                    @endforelse
                </div>
            </section>

            {{-- Recomendaciones y comentarios --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                <h3 class="text-sm font-bold text-slate-900">Recomendaciones y comentarios</h3>
                <div class="mt-3 space-y-2.5">
                    @forelse ($recommendations as $item)
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-teal-800">{{ $item['source'] }}</p>
                            <p class="mt-1 text-xs leading-relaxed text-slate-700">{{ $item['text'] }}</p>
                        </div>
                    @empty
                        <p class="text-xs text-slate-500">Sin comentarios o recomendaciones registradas por ahora.</p>
                    @endforelse
                </div>
            </section>
        @endif
    </main>
</body>
</html>
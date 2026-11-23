<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Protocolo #{{ $protocol->id }}</title>
    <style>
        @page { margin: 28px 34px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1f2937; line-height: 1.45; }
        .header { border-bottom: 2px solid #5b21b6; padding-bottom: 10px; margin-bottom: 14px; }
        .header h1 { font-size: 17px; margin: 0 0 4px 0; color: #5b21b6; }
        .header .sub { font-size: 10px; color: #4b5563; margin: 0; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 14px; background: #f9fafb; }
        .meta td { border: 1px solid #e5e7eb; padding: 6px 8px; font-size: 10px; vertical-align: top; }
        .meta strong { color: #111827; }
        h2 { font-size: 12px; margin: 14px 0 6px 0; border-bottom: 1px solid #d1d5db; padding-bottom: 3px; color: #374151; text-transform: uppercase; letter-spacing: 0.4px; }
        .box { background: #ffffff; border: 1px solid #e5e7eb; padding: 8px 10px; border-radius: 3px; margin-bottom: 10px; }
        table.sessions { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.sessions th, table.sessions td { border: 1px solid #d1d5db; padding: 4px 6px; text-align: left; font-size: 9.5px; }
        table.sessions th { background: #f3f4f6; color: #111827; font-weight: bold; }
        .signatures { width: 100%; border-collapse: collapse; margin-top: 28px; }
        .signatures td { width: 50%; text-align: center; padding-top: 28px; font-size: 9.5px; color: #4b5563; }
        .sig-line { border-top: 1px solid #6b7280; width: 75%; margin: 0 auto 4px auto; }
    </style>
</head>
<body>
    @php
        $statusLabel = match ($protocol->status) {
            'active'    => 'Activo',
            'completed' => 'Concluido',
            default     => 'Borrador',
        };
    @endphp

    <div class="header">
        <h1>Protocolo de Neurofeedback: {{ $protocol->title }}</h1>
        <p class="sub">
            Folio #{{ $protocol->id }} · Estado: <strong>{{ $statusLabel }}</strong> ·
            Total de sesiones: <strong>{{ $protocol->total_sessions }}</strong> ·
            Emisión: {{ now()->format('d/m/Y H:i') }}
        </p>
    </div>

    <table class="meta">
        <tr>
            <td style="width: 50%;">
                <strong>Paciente:</strong> {{ $protocol->patient?->full_name ?? $protocol->patient?->name ?? '—' }}
            </td>
            <td style="width: 50%;">
                <strong>Sucursal / Clínica:</strong> {{ $protocol->clinic?->name ?? '—' }}
            </td>
        </tr>
        <tr>
            <td>
                <strong>Estudio EEG Base:</strong>
                @if ($protocol->eegStudy)
                    #{{ $protocol->eegStudy->id }} · {{ $protocol->eegStudy->title }}
                    ({{ $protocol->eegStudy->study_date?->format('d/m/Y') ?? 'Sin fecha' }})
                @else
                    No vinculado
                @endif
            </td>
            <td>
                <strong>Frecuencia Sugerida:</strong> {{ $protocol->recommended_frequency_per_week }} sesión(es) / semana
            </td>
        </tr>
        <tr>
            <td>
                <strong>Electrodos / Montaje:</strong> {{ $protocol->electrode_sites ?: '—' }}
            </td>
            <td>
                <strong>Elaborado por:</strong> {{ $protocol->creator?->name ?? '—' }}
            </td>
        </tr>
    </table>

    <h2>Hallazgos Relevantes del EEG</h2>
    <div class="box">{!! nl2br(e($protocol->eeg_findings ?: 'Sin hallazgos registrados.')) !!}</div>

    <h2>Parámetros / Frecuencias Objetivo</h2>
    <div class="box">{!! nl2br(e($protocol->frequency_targets ?: 'Sin parámetros registrados.')) !!}</div>

    <h2>Objetivos Clínicos del Tratamiento</h2>
    <div class="box">{!! nl2br(e($protocol->clinical_goals ?: 'Sin objetivos registrados.')) !!}</div>

    @if (filled($protocol->clinical_notes))
        <h2>Observaciones Clínicas</h2>
        <div class="box">{!! nl2br(e($protocol->clinical_notes)) !!}</div>
    @endif

    <h2>Plan de Sesiones ({{ $protocol->sessions->count() }})</h2>
    <table class="sessions">
        <thead>
            <tr>
                <th style="width: 8%;">#</th>
                <th style="width: 47%;">Fase</th>
                <th style="width: 18%;">Estado</th>
                <th style="width: 27%;">Fecha / Notas</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($protocol->sessions as $session)
                @php
                    $sessionStatus = match ($session->status) {
                        'completed' => 'Completada',
                        'missed'    => 'Inasistencia',
                        default     => 'Pendiente',
                    };
                @endphp
                <tr>
                    <td>{{ $session->session_number }}</td>
                    <td>{{ $session->phase ?: '—' }}</td>
                    <td>{{ $sessionStatus }}</td>
                    <td>
                        {{ $session->scheduled_date?->format('d/m/Y') ?? 'Por programar' }}
                        @if (filled($session->session_notes))
                            · {{ $session->session_notes }}
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center; color: #6b7280;">Sin sesiones generadas en este protocolo.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td>
                <div class="sig-line"></div>
                Especialista / Responsable del Protocolo
            </td>
            <td>
                <div class="sig-line"></div>
                Paciente / Tutor Legal
            </td>
        </tr>
    </table>
</body>
</html>
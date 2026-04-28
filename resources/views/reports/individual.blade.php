<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 10px;
        color: #2d3748;
        background: #fff;
    }

    /* ── HEADER ── */
    .header {
        padding: 20px 28px 16px;
        border-bottom: 3px solid #3aaa35;
        position: relative;
    }
    .header-logo {
        position: absolute;
        top: 16px;
        right: 28px;
        width: 140px;
    }
    .header-title {
        font-size: 16px;
        font-weight: 700;
        color: #1e3a5f;
        line-height: 1.3;
        max-width: 72%;
    }
    .header-meta {
        font-size: 8.5px;
        color: #718096;
        margin-top: 5px;
    }
    .header-meta span {
        margin-right: 10px;
    }
    .header-meta .sep {
        color: #cbd5e0;
        margin-right: 10px;
    }

    /* ── SECTION LABEL ── */
    .section-label {
        font-size: 9px;
        font-weight: 700;
        color: #1e3a5f;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        padding: 14px 28px 6px;
        border-bottom: 1px solid #e2e8f0;
        margin-bottom: 0;
    }

    /* ── EMPLEADO ── */
    .empleado-block {
        padding: 10px 28px 12px;
        border-bottom: 1px solid #e2e8f0;
    }
    .empleado-name {
        font-size: 12px;
        font-weight: 700;
        color: #2d3748;
    }
    .empleado-code {
        font-size: 9px;
        color: #718096;
        margin-top: 2px;
    }

    /* ── RESUMEN TABLE ── */
    .resumen-wrap {
        padding: 12px 28px 4px;
    }
    table.resumen {
        width: 100%;
        border-collapse: collapse;
        font-size: 9.5px;
    }
    table.resumen thead tr {
        background: #1e3a5f;
        color: #fff;
    }
    table.resumen thead th {
        padding: 7px 12px;
        text-align: left;
        font-size: 8.5px;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        width: 25%;
    }
    table.resumen tbody tr:nth-child(even) { background: #f7fafc; }
    table.resumen tbody td {
        padding: 6px 12px;
        border: 1px solid #e2e8f0;
        color: #2d3748;
    }
    table.resumen tbody td.metric-label {
        font-weight: 700;
        color: #4a5568;
        background: #edf2f7;
    }
    table.resumen tbody td.metric-value {
        color: #1e3a5f;
        font-weight: 600;
    }

    /* ── DETALLE TABLE ── */
    .detalle-wrap {
        padding: 12px 28px 20px;
    }
    table.detalle {
        width: 100%;
        border-collapse: collapse;
        font-size: 8.5px;
    }
    table.detalle thead tr {
        background: #1e3a5f;
        color: #fff;
    }
    table.detalle thead th {
        padding: 7px 6px;
        text-align: center;
        font-size: 8px;
        font-weight: 700;
        letter-spacing: 0.4px;
        text-transform: uppercase;
        white-space: nowrap;
    }
    table.detalle thead th:first-child { text-align: left; padding-left: 8px; }
    table.detalle tbody tr:nth-child(even) { background: #f7fafc; }
    table.detalle tbody td {
        padding: 5px 6px;
        border-bottom: 1px solid #e2e8f0;
        text-align: center;
        color: #4a5568;
        white-space: nowrap;
    }
    table.detalle tbody td:first-child {
        text-align: left;
        padding-left: 8px;
        font-weight: 600;
        color: #2d3748;
    }

    /* badges */
    .badge {
        display: inline-block;
        padding: 2px 7px;
        border-radius: 3px;
        font-size: 7.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .badge-present    { background: #c6f6d5; color: #276749; }
    .badge-absent     { background: #fed7d7; color: #c53030; }
    .badge-incomplete { background: #feebc8; color: #c05621; }
    .badge-rest       { background: #e9d8fd; color: #553c9a; }
    .badge-holiday    { background: #bee3f8; color: #2c5282; }

    /* value colors */
    .val-red    { color: #c53030; font-weight: 700; }
    .val-green  { color: #276749; font-weight: 700; }
    .val-orange { color: #c05621; font-weight: 700; }
    .val-muted  { color: #a0aec0; }

    /* ── FOOTER ── */
    .footer {
        border-top: 2px solid #3aaa35;
        padding: 8px 28px;
        font-size: 7.5px;
        color: #a0aec0;
        display: flex;
        justify-content: space-between;
    }
    .footer .brand { color: #3aaa35; font-weight: 700; }
</style>
</head>
<body>

@php
    $logoBase64 = base64_encode(file_get_contents(public_path('LOGO-04-1.png')));

    function fmtMin(int $min): string {
        if ($min <= 0) return '0m';
        $h = intdiv($min, 60);
        $m = $min % 60;
        return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
    }
@endphp

{{-- ════ HEADER ════ --}}
<div class="header">
    <img class="header-logo" src="data:image/png;base64,{{ $logoBase64 }}" alt="Insumma BG">

    <div class="header-title">{{ $summary['employee_name'] }} &nbsp; {{ $report->date_from->format('Y-m-d') }} / {{ $report->date_to->format('Y-m-d') }}</div>
    <div class="header-meta">
        <span>Reporte #{{ $report->id }}</span>
        <span class="sep">|</span>
        <span>Individual</span>
        <span class="sep">|</span>
        <span>Periodo: {{ $report->date_from->format('Y-m-d') }} &mdash; {{ $report->date_to->format('Y-m-d') }}</span>
        <span class="sep">|</span>
        <span>Generado: {{ now()->format('d/m/Y, H:i:s') }}</span>
    </div>
</div>

{{-- ════ EMPLEADO ════ --}}
<div class="section-label">Empleado</div>
<div class="empleado-block">
    <div class="empleado-name">{{ $summary['employee_name'] }}</div>
    <div class="empleado-code">Código: {{ $summary['employee_internal_id'] }}</div>
</div>

{{-- ════ RESUMEN ════ --}}
<div class="section-label">Resumen</div>
<div class="resumen-wrap">
    <table class="resumen">
        <thead>
            <tr>
                <th>Métrica</th>
                <th>Valor</th>
                <th>Métrica</th>
                <th>Valor</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="metric-label">Días Trabajados</td>
                <td class="metric-value">{{ $summary['total_days'] }}</td>
                <td class="metric-label">Días presentes</td>
                <td class="metric-value val-green">{{ $summary['days_present'] }}</td>
            </tr>
            <tr>
                <td class="metric-label">Días ausentes</td>
                <td class="metric-value {{ $summary['days_absent'] > 0 ? 'val-red' : '' }}">{{ $summary['days_absent'] }}</td>
                <td class="metric-label">Días incompletos</td>
                <td class="metric-value {{ $summary['days_incomplete'] > 0 ? 'val-orange' : '' }}">{{ $summary['days_incomplete'] }}</td>
            </tr>
            <tr>
                <td class="metric-label">Dias tarde</td>
                <td class="metric-value {{ $summary['times_late'] > 0 ? 'val-red' : '' }}">{{ $summary['times_late'] }}</td>
                <td class="metric-label">Tiempo trabajado</td>
                <td class="metric-value">{{ fmtMin($summary['total_worked_minutes']) }}</td>
            </tr>
            <tr>
                <td class="metric-label">Min. tardanza total</td>
                <td class="metric-value {{ $summary['total_late_minutes'] > 0 ? 'val-red' : '' }}">{{ fmtMin($summary['total_late_minutes']) }}</td>
                <td class="metric-label"></td>
                <td class="metric-value"></td>
            </tr>
            <tr>
                <td class="metric-label">Min. salida temprana</td>
                <td class="metric-value {{ $summary['total_early_departure_minutes'] > 0 ? 'val-orange' : '' }}">{{ fmtMin($summary['total_early_departure_minutes']) }}</td>
                <td class="metric-label"></td>
                <td class="metric-value"></td>
            </tr>
        </tbody>
    </table>
</div>

{{-- ════ DETALLE ════ --}}
<div class="section-label">Detalle</div>
<div class="detalle-wrap">
    <table class="detalle">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Entrada</th>
                <th>Salida</th>
                <th>Trabajado</th>
                <th>Tardanza</th>
                <th>Salida temprana</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
        @foreach($rows as $row)
            @php
                $st = match($row['status']) {
                    'present'    => ['label' => 'present',    'class' => 'badge-present'],
                    'absent'     => ['label' => 'absent',     'class' => 'badge-absent'],
                    'incomplete' => ['label' => 'incomplete', 'class' => 'badge-incomplete'],
                    'rest'       => ['label' => 'rest',       'class' => 'badge-rest'],
                    'holiday'    => ['label' => 'holiday',    'class' => 'badge-holiday'],
                    default      => ['label' => $row['status'], 'class' => 'badge-rest'],
                };
            @endphp
            <tr>
                <td>{{ $row['date'] }}</td>
                <td>{{ $row['first_check_in'] ?? '—' }}</td>
                <td>{{ $row['last_check_out'] ?? '—' }}</td>
                <td>{{ fmtMin($row['worked_minutes']) }}</td>
                <td class="{{ $row['late_minutes'] > 0 ? 'val-red' : 'val-muted' }}">{{ fmtMin($row['late_minutes']) }}</td>
                <td class="{{ $row['early_departure_minutes'] > 0 ? 'val-orange' : 'val-muted' }}">{{ fmtMin($row['early_departure_minutes']) }}</td>
                <td><span class="badge {{ $st['class'] }}">{{ $st['label'] }}</span></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

{{-- ════ FOOTER ════ --}}
<div class="footer">
    <span><span class="brand">Insumma BG</span> &bull; Reporte generado automáticamente por Chronology</span>
    <span>{{ now()->format('d/m/Y H:i') }}</span>
</div>

</body>
</html>

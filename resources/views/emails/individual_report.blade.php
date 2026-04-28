<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: Arial, sans-serif; font-size: 14px; color: #2d3748; background: #f7fafc; margin: 0; padding: 0; }
    .container { max-width: 560px; margin: 32px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
    .header { background: #1a1a2e; padding: 28px 32px; color: #fff; }
    .header h1 { font-size: 20px; margin: 0 0 4px; }
    .header p { font-size: 12px; color: #a0aec0; margin: 0; }
    .body { padding: 28px 32px; }
    .greeting { font-size: 15px; margin-bottom: 16px; }
    .summary-box { background: #edf2f7; border-radius: 6px; padding: 16px; margin: 20px 0; }
    .summary-box h3 { font-size: 12px; text-transform: uppercase; color: #718096; letter-spacing: 1px; margin: 0 0 12px; }
    .summary-row { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px; }
    .summary-row span:last-child { font-weight: 700; }
    .footer { padding: 16px 32px; background: #f7fafc; border-top: 1px solid #e2e8f0; font-size: 11px; color: #a0aec0; text-align: center; }
    .cta { background: #0f3460; color: #fff; text-decoration: none; padding: 10px 24px; border-radius: 5px; font-size: 13px; display: inline-block; margin-top: 12px; }
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Reporte de Asistencia</h1>
        <p>Período: {{ $dateFrom }} al {{ $dateTo }}</p>
    </div>
    <div class="body">
        <p class="greeting">Estimado/a <strong>{{ $employeeName }}</strong>,</p>
        <p>Adjunto encontrará su reporte individual de asistencia correspondiente al período indicado.</p>

        <div class="summary-box">
            <h3>Resumen</h3>
            <div class="summary-row"><span>Días presentes</span><span>{{ $summary['days_present'] }}</span></div>
            <div class="summary-row"><span>Días ausentes</span><span>{{ $summary['days_absent'] }}</span></div>
            <div class="summary-row"><span>Días incompletos</span><span>{{ $summary['days_incomplete'] }}</span></div>
            <div class="summary-row"><span>Tardanzas</span><span>{{ $summary['times_late'] }}</span></div>
            <div class="summary-row"><span>Horas trabajadas</span><span>{{ number_format($summary['total_worked_minutes'] / 60, 1) }} h</span></div>
            <div class="summary-row"><span>Horas extra</span><span>{{ number_format($summary['total_overtime_minutes'] / 60, 1) }} h</span></div>
        </div>

        <p style="font-size:12px;color:#718096;">El detalle completo día por día se encuentra en el archivo PDF adjunto.</p>
    </div>
    <div class="footer">
        Este correo fue generado automáticamente por Chronology. Por favor no responda a este mensaje.
    </div>
</div>
</body>
</html>

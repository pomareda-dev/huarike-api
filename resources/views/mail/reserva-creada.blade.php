<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reserva {{ $reserva->folio }}</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1f2933; line-height: 1.6;">
    <h1 style="font-size: 20px;">¡Tu reserva está confirmada!</h1>

    <p>Hola {{ $reserva->nombre }}, te esperamos en El Huarike.</p>

    <ul>
        <li><strong>Folio:</strong> {{ $reserva->folio }}</li>
        <li><strong>Fecha:</strong> {{ $datos['fecha_larga'] }}</li>
        <li><strong>Hora:</strong> {{ $reserva->hora }} (turno {{ $datos['turno'] ?? 'a confirmar' }})</li>
        <li><strong>Personas:</strong> {{ $reserva->personas }}</li>
        <li>
            <strong>Mesa:</strong>
            @if ($reserva->mesa)
                Mesa {{ $reserva->mesa->numero }} · {{ $reserva->mesa->zona->value }}
            @else
                Lista de espera (te avisamos en cuanto se libere una mesa)
            @endif
        </li>
        <li><strong>Estado:</strong> {{ $datos['estado_etiqueta'] }}</li>
    </ul>

    @if (count($datos['platos'] ?? []) > 0)
        <h2 style="font-size: 16px;">Tu preorden</h2>
        <table cellpadding="6" cellspacing="0" border="0" style="border-collapse: collapse;">
            <thead>
                <tr>
                    <th align="left">Plato</th>
                    <th align="right">Cantidad</th>
                    <th align="right">Importe</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($datos['platos'] as $plato)
                    <tr>
                        <td>{{ $plato['nombre'] }}</td>
                        <td align="right">{{ $plato['cantidad'] }}</td>
                        <td align="right">S/ {{ number_format($plato['importe'], 2) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="2" align="right"><strong>Subtotal</strong></td>
                    <td align="right"><strong>S/ {{ number_format($datos['subtotal'], 2) }}</strong></td>
                </tr>
            </tbody>
        </table>
    @endif

    @if ($reserva->notas)
        <p><strong>Notas:</strong> {{ $reserva->notas }}</p>
    @endif

    <p>Te guardamos la mesa por {{ $datos['tolerancia_minutos'] }} minutos de tolerancia.</p>
    <p style="color: #616e7c; font-size: 12px;">El Huarike · San Borja, Lima</p>
</body>
</html>

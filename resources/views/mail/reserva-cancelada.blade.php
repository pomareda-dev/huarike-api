<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reserva {{ $reserva->folio }} cancelada</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1f2933; line-height: 1.6;">
    <h1 style="font-size: 20px;">Tu reserva fue cancelada</h1>

    <p>Hola {{ $reserva->nombre }}, la reserva {{ $reserva->folio }} quedó cancelada.</p>

    <ul>
        <li><strong>Fecha:</strong> {{ $datos['fecha_larga'] }}</li>
        <li><strong>Hora:</strong> {{ $reserva->hora }}</li>
        <li><strong>Personas:</strong> {{ $reserva->personas }}</li>
        <li><strong>Cancelada por:</strong> {{ $autor }}</li>
    </ul>

    <p>Recuerda que las cancelaciones del cliente se aceptan hasta 2 horas antes del inicio.</p>
    <p>¿Quieres reservar de nuevo? Escríbenos y te buscamos otra mesa.</p>
    <p style="color: #616e7c; font-size: 12px;">El Huarike · San Borja, Lima</p>
</body>
</html>

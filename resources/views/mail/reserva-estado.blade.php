<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reserva {{ $reserva->folio }}</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1f2933; line-height: 1.6;">
    <h1 style="font-size: 20px;">Tu reserva pasó a estado {{ $estado }}</h1>

    <p>Hola {{ $reserva->nombre }}, te contamos el cambio de tu reserva en El Huarike.</p>

    <ul>
        <li><strong>Folio:</strong> {{ $reserva->folio }}</li>
        <li><strong>Estado:</strong> {{ $estado }}</li>
        <li><strong>Fecha:</strong> {{ $datos['fecha_larga'] }}</li>
        <li><strong>Hora:</strong> {{ $reserva->hora }}</li>
        <li><strong>Personas:</strong> {{ $reserva->personas }}</li>
        <li><strong>Actualizado por:</strong> {{ $autor }}</li>
    </ul>

    <p>Si algo no cuadra, respóndenos este correo y lo revisamos.</p>
    <p style="color: #616e7c; font-size: 12px;">El Huarike · San Borja, Lima</p>
</body>
</html>

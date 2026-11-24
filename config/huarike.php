<?php

/*
|--------------------------------------------------------------------------
| Reglas de negocio de El Huarike
|--------------------------------------------------------------------------
|
| Valores que el prototipo React tenía como constantes en src/lib/*.js.
| Se centralizan aquí para que Services, Requests y Resources consulten una
| única fuente y para poder ajustarlos por entorno sin tocar código.
|
*/

return [

    // Zona horaria operativa del restaurante.
    'zona_horaria' => env('HUARIKE_TIMEZONE', 'America/Lima'),

    // URL del SPA: usada en enlaces de correo (recuperación de contraseña).
    'frontend_url' => rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/'),

    // Bandeja del restaurante que recibe copia de las reservas creadas.
    'correo_restaurante' => env('HUARIKE_CORREO_RESTAURANTE', 'reservas@elhuarike.pe'),

    // Ocupación de una reserva (minutos). Dos reservas de la misma mesa
    // chocan si empiezan a menos de esta distancia.
    'duracion_minutos' => 90,

    // Tolerancia de llegada informada al cliente en la confirmación.
    'tolerancia_minutos' => 15,

    // Ventana de cancelación del cliente, en horas antes del inicio.
    'ventana_cancelacion_horas' => 2,

    'personas' => [
        'minimo' => 1,
        'maximo' => 6,
    ],

    'mesas' => [
        'capacidad_minima' => 1,
        'capacidad_maxima' => 8,
        'zonas' => ['Salón', 'Terraza'],
    ],

    // Paginación por defecto de los listados del panel.
    'paginacion' => 6,

    // Horas pico informativas para la disponibilidad del sitio público.
    'horas_pico' => ['13:00', '19:30', '20:00', '20:30'],

    // Umbrales para clasificar un slot de reserva según mesas libres.
    'disponibilidad' => [
        'disponible_desde' => 3, // más de 2 mesas libres => disponible
        'alta_demanda_desde' => 1, // 1 o 2 mesas libres => alta demanda
    ],

    // Filtros de la página pública de promociones.
    'filtros_promociones' => ['desayunos', 'almuerzos', 'familia'],

    // Monto a partir del cual se omite el costo de envío (promociones demo).
    'promocion_destacada' => true,

    // Rango máximo de días que acepta el reporte por rango.
    'reportes_rango_maximo_dias' => 366,
];

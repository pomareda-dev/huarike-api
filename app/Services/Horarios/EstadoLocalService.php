<?php

namespace App\Services\Horarios;

use App\Models\Horario;
use App\Support\Fechas;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Estado del local ("Abierto ahora · cierra a las…") sobre las franjas
 * guardadas, replicando `estadoLocal` de src/lib/horario.js (docs §5.9 RH4).
 */
class EstadoLocalService
{
    /**
     * @return array<string, mixed>
     */
    public function estado(?CarbonImmutable $ahora = null): array
    {
        $ahora ??= Fechas::ahora();

        /** @var Collection<int, Horario> $franjas */
        $franjas = Horario::query()->activos()->orderBy('abre')->get();

        $dia = (int) $ahora->dayOfWeek;
        $minutos = $ahora->hour * 60 + $ahora->minute;

        $enCurso = $franjas->first(function (Horario $franja) use ($dia, $minutos): bool {
            $dias = array_map('intval', $franja->dias ?? []);

            return in_array($dia, $dias, true)
                && $minutos >= Fechas::minutos($franja->abre)
                && $minutos < Fechas::minutos($franja->cierra);
        });

        if ($enCurso !== null) {
            return [
                'abierto' => true,
                'franja' => $this->slug($enCurso->nombre),
                'franja_nombre' => $enCurso->nombre,
                'cierra_a' => substr((string) $enCurso->cierra, 0, 5),
                'mensaje' => 'Abierto ahora · cierra a las '.Fechas::horaSinCero($enCurso->cierra),
                'proxima_apertura' => null,
            ];
        }

        $proxima = null;

        for ($desplazamiento = 0; $desplazamiento <= 7 && $proxima === null; $desplazamiento++) {
            $fecha = $ahora->addDays($desplazamiento);
            $diaBuscado = (int) $fecha->dayOfWeek;

            foreach ($franjas as $franja) {
                if (! in_array($diaBuscado, array_map('intval', $franja->dias ?? []), true)) {
                    continue;
                }

                $apertura = Fechas::minutos($franja->abre);

                if ($desplazamiento === 0 && $apertura <= $minutos) {
                    continue;
                }

                $proxima = [
                    'dia' => Fechas::nombreDia($diaBuscado),
                    'hora' => substr((string) $franja->abre, 0, 5),
                    'franja' => $this->slug($franja->nombre),
                ];

                break;
            }
        }

        return [
            'abierto' => false,
            'franja' => null,
            'franja_nombre' => null,
            'cierra_a' => null,
            'mensaje' => $proxima === null
                ? 'Cerrado'
                : 'Cerrado · abre el '.$proxima['dia'].' a las '.Fechas::horaSinCero($proxima['hora']),
            'proxima_apertura' => $proxima,
        ];
    }

    /**
     * Identificador estable para el SPA ("desayunos", "almuerzos").
     */
    private function slug(string $nombre): string
    {
        return str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ', ' '], ['a', 'e', 'i', 'o', 'u', 'n', '-'], mb_strtolower(trim($nombre)));
    }
}

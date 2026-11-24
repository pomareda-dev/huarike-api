<?php

namespace App\Http\Controllers\Api\V1\Panel;

use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\ActualizarUsuarioRequest;
use App\Http\Requests\Panel\CambiarRolRequest;
use App\Http\Requests\Panel\GuardarUsuarioRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Panel\UsuarioService;
use App\Support\Csv;
use App\Support\Fechas;
use App\Support\Paginador;
use App\Support\Texto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Usuarios del panel (docs §5.7): listado filtrado y paginado, resumen,
 * alta/edición, activación, cambio de rol y export CSV.
 */
class UsuarioController extends Controller
{
    public function __construct(private readonly UsuarioService $usuarios) {}

    public function index(Request $request): JsonResponse
    {
        $consulta = $this->consultaFiltrada($request);

        $paginator = $consulta
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(Paginador::porPagina(), ['*'], 'page', Paginador::pagina($request))
            ->through(fn (User $usuario) => (new UserResource($usuario))->resolve());

        return Paginador::respuesta($paginator);
    }

    public function resumen(): JsonResponse
    {
        return response()->json([
            'total' => User::query()->count(),
            'administradores' => User::query()->administradores()->count(),
            'inactivas' => User::query()->where('activa', false)->count(),
        ]);
    }

    public function store(GuardarUsuarioRequest $request): JsonResponse
    {
        $usuario = $this->usuarios->crear([
            'nombre' => $request->validated('nombre'),
            'correo' => $request->validated('correo'),
            'telefono' => $request->validated('telefono'),
            'rol' => Rol::from($request->validated('rol')),
            'password' => $request->validated('password'),
        ]);

        return (new UserResource($usuario))->response()->setStatusCode(201);
    }

    public function update(ActualizarUsuarioRequest $request, User $usuario): UserResource
    {
        /** @var User $actor */
        $actor = $request->user();

        $actualizado = $this->usuarios->actualizar($usuario, [
            'nombre' => $request->validated('nombre'),
            'correo' => $request->validated('correo'),
            'telefono' => $request->validated('telefono'),
            'rol' => Rol::from($request->validated('rol')),
        ], $actor);

        return new UserResource($actualizado);
    }

    public function activar(Request $request, User $usuario): UserResource
    {
        return new UserResource($this->usuarios->activar($usuario));
    }

    public function desactivar(Request $request, User $usuario): UserResource
    {
        /** @var User $actor */
        $actor = $request->user();

        return new UserResource($this->usuarios->desactivar($usuario, $actor));
    }

    public function rol(CambiarRolRequest $request, User $usuario): UserResource
    {
        /** @var User $actor */
        $actor = $request->user();

        return new UserResource(
            $this->usuarios->cambiarRol($usuario, Rol::from($request->validated('rol')), $actor),
        );
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $filas = $this->consultaFiltrada($request)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursor()
            ->map(fn (User $usuario) => [
                Fechas::numerica($usuario->created_at->toDateString()),
                $usuario->nombre,
                $usuario->correo,
                $usuario->rol->etiqueta(),
                $usuario->activa ? 'Activa' : 'Inactiva',
            ]);

        return Csv::descargar('usuarios.csv', ['Alta', 'Usuario', 'Correo', 'Rol', 'Estado'], $filas);
    }

    /**
     * Filtros del panel: texto en nombre/correo (sin tildes), rol y estado.
     *
     * @return Builder<User>
     */
    private function consultaFiltrada(Request $request)
    {
        $texto = is_string($request->query('texto')) ? Texto::normalizar($request->query('texto')) : '';
        $rol = $request->query('rol', 'todos');
        $estado = $request->query('estado', 'todas');

        return User::query()
            ->when($texto !== '', function ($query) use ($texto): void {
                $patron = '%'.$texto.'%';
                $query->where(function ($sub) use ($patron): void {
                    $sub->whereRaw('LOWER(nombre) LIKE ?', [$patron])
                        ->orWhereRaw('LOWER(correo) LIKE ?', [$patron]);
                });
            })
            ->when(in_array($rol, Rol::valores(), true), fn ($query) => $query->where('rol', $rol))
            ->when($estado === 'activa', fn ($query) => $query->where('activa', true))
            ->when($estado === 'inactiva', fn ($query) => $query->where('activa', false));
    }
}

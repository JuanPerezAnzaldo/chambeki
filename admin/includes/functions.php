<?php
/*
    Funciones del panel de administracion (/admin).

    Reglas de esta infraestructura:
      1. La sesion de admin es independiente de la del sitio publico:
         cookie propia "session_admin" con path /admin (ver admin/index.php).
      2. La autorizacion NUNCA se decide con lo que quedo guardado en la
         sesion: en cada peticion se vuelve a leer al usuario en la BD
         (rol, estatus y rol administrativo). Si lo degradan o lo
         suspenden, pierde el acceso en su siguiente clic.
      3. Cada pantalla declara el permiso que necesita; el middleware
         (exigirPermisoAdmin) lo revisa antes de mostrar nada.
*/

//=============================================================
// Catalogos
//=============================================================

define('ROL_ADMIN_SUPER',   1);   // Super Administrador
define('ROL_ADMIN_SOPORTE', 2);   // Supervisor / Soporte

define('MINUTOS_INACTIVIDAD_ADMIN', 15);   // mas estricto que el sitio publico (30)
define('MAX_INTENTOS_LOGIN_ADMIN',   5);   // fallos permitidos...
define('MINUTOS_BLOQUEO_LOGIN_ADMIN', 15); // ...dentro de esta ventana

function nombreRolAdmin($idRolAdmin)
{
    switch ((int) $idRolAdmin)
    {
        case ROL_ADMIN_SUPER:   return 'Super Administrador';
        case ROL_ADMIN_SOPORTE: return 'Supervisor / Soporte';
        default:                return 'Sin rol administrativo';
    }
}

/*
    Matriz de permisos por rol. Para dar o quitar un permiso basta con
    editar esta lista; las pantallas solo preguntan por el nombre del permiso.
*/
function matrizPermisosAdmin()
{
    return [
        ROL_ADMIN_SUPER => [
            'dashboard.ver',
            'usuarios.ver',
            'usuarios.editar',
            'usuarios.suspender',
            'usuarios.verificar',
            'admins.gestionar',
            'reportes.ver',
            'reportes.resolver',
            'reembolsos.aprobar',
            'moderacion.gestionar',
            'bitacora.ver'
        ],
        ROL_ADMIN_SOPORTE => [
            'dashboard.ver',
            'usuarios.ver',
            'usuarios.editar',
            'usuarios.suspender',
            'usuarios.verificar',
            'reportes.ver',
            'reportes.resolver',
            'moderacion.gestionar'
        ]
    ];
}

//permisos que SOLO tiene el rol Super Administrador: no se pueden otorgar a
//otros ni quitar desde la pantalla de personal (evita quedarse sin quien administre)
function permisosReservadosAdmin()
{
    return ['admins.gestionar'];
}

//catalogo completo, en el orden en que se muestran en los formularios
function todosLosPermisosAdmin()
{
    return [
        'dashboard.ver',
        'usuarios.ver',
        'usuarios.editar',
        'usuarios.suspender',
        'usuarios.verificar',
        'reportes.ver',
        'reportes.resolver',
        'reembolsos.aprobar',
        'moderacion.gestionar',
        'bitacora.ver',
        'admins.gestionar'
    ];
}

/*
    Permisos efectivos de un empleado = permisos del rol
                                        + los otorgados extra
                                        - los revocados
    (los reservados ignoran cualquier ajuste: siguen al rol).
*/
function permisosEfectivosAdmin($idUsuario, $idRolAdmin)
{
    $efectivos = matrizPermisosAdmin()[(int) $idRolAdmin] ?? [];
    $reservados = permisosReservadosAdmin();

    $conexion = conexionBD();

    $sentencia = $conexion->prepare('SELECT permiso, tipo FROM admin_permisos WHERE id_usuario = :id');
    $sentencia->execute([':id' => $idUsuario]);

    foreach ($sentencia->fetchAll() as $ajuste)
    {
        if (in_array($ajuste['permiso'], $reservados, true))
        {
            continue;
        }

        if ((int) $ajuste['tipo'] === 1)
        {
            $efectivos[] = $ajuste['permiso'];
        }
        else
        {
            $efectivos = array_diff($efectivos, [$ajuste['permiso']]);
        }
    }

    return array_values(array_unique($efectivos));
}

function adminTienePermiso($admin, $permiso)
{
    if ($admin === null || $permiso === null)
    {
        return false;
    }

    return in_array($permiso, $admin['permisos'] ?? [], true);
}

function adminEsSuper($admin)
{
    return $admin !== null && (int) $admin['id_rol_admin'] === ROL_ADMIN_SUPER;
}

//=============================================================
// Consultas (sentencias preparadas, RS-02)
//=============================================================

function obtenerAdminPorCorreo($correo)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'SELECT id_usuario, nombre, correo, password_hash, rol, estatus, id_rol_admin
         FROM usuarios
         WHERE correo = :correo
         LIMIT 1'
    );
    $sentencia->execute([':correo' => $correo]);

    $fila = $sentencia->fetch();

    return $fila === false ? null : $fila;
}

function obtenerAdminPorId($idUsuario)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'SELECT id_usuario, nombre, correo, rol, estatus, id_rol_admin, debe_cambiar_contrasena
         FROM usuarios
         WHERE id_usuario = :id
         LIMIT 1'
    );
    $sentencia->execute([':id' => $idUsuario]);

    $fila = $sentencia->fetch();

    return $fila === false ? null : $fila;
}

//=============================================================
// Anti fuerza bruta del login de admin
// Se cuentan los fallos en bitacora_accesos (ya existe, RLN-07)
// por correo O por IP en la ventana de bloqueo.
//=============================================================

function loginAdminBloqueado($correo)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        "SELECT COUNT(*) AS total
         FROM bitacora_accesos
         WHERE evento LIKE 'admin_login_fallido%'
           AND fecha_evento >= DATE_SUB(NOW(), INTERVAL :minutos MINUTE)
           AND (correo = :correo OR ip_origen = :ip)"
    );
    $sentencia->bindValue(':minutos', MINUTOS_BLOQUEO_LOGIN_ADMIN, PDO::PARAM_INT);
    $sentencia->bindValue(':correo',  $correo);
    $sentencia->bindValue(':ip',      $_SERVER['REMOTE_ADDR'] ?? '');
    $sentencia->execute();

    $fila = $sentencia->fetch();

    return (int) $fila['total'] >= MAX_INTENTOS_LOGIN_ADMIN;
}

//=============================================================
// CSRF propio del panel (nombres distintos a los del sitio publico
// para que ambos archivos puedan convivir sin redeclarar funciones)
//=============================================================

function csrfAdminToken()
{
    if (empty($_SESSION['csrf_admin']))
    {
        $_SESSION['csrf_admin'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_admin'];
}

function csrfAdminValido($tokenRecibido)
{
    return isset($_SESSION['csrf_admin'])
        && is_string($tokenRecibido)
        && hash_equals($_SESSION['csrf_admin'], $tokenRecibido);
}

//=============================================================
// Sesion de admin
//=============================================================

function huellaNavegador()
{
    return hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
}

function iniciarSesionAdmin($admin)
{
    //evita la fijacion de sesion: siempre un identificador nuevo al entrar
    session_regenerate_id(true);

    $_SESSION['admin'] = [
        'id_usuario' => (int) $admin['id_usuario'],
        'huella'     => huellaNavegador(),
        'inicio'     => time()
    ];
    $_SESSION['admin_ultima_actividad'] = time();

    unset($_SESSION['csrf_admin']);
}

//guarda fecha/hora e IP del ultimo acceso al panel (se muestra en Gestion de personal)
function registrarUltimoAccesoAdmin($idUsuario)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'UPDATE usuarios SET ultimo_acceso_admin = NOW(), ip_ultimo_acceso_admin = :ip WHERE id_usuario = :id'
    );
    $sentencia->execute([':ip' => $_SERVER['REMOTE_ADDR'] ?? null, ':id' => $idUsuario]);
}

function destruirSesionAdmin()
{
    $_SESSION = [];

    if (ini_get('session.use_cookies'))
    {
        $p = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => 'Strict'
        ]);
    }

    session_destroy();

    //se deja una sesion nueva (ID nuevo, vacia) para poder guardar el token
    //CSRF del login y los avisos; nunca se reutiliza el ID anterior
    session_start();
    session_regenerate_id(true);
}

/*
    Devuelve al administrador autenticado (datos FRESCOS de la BD) o null.
    Si la sesion es invalida la destruye y deja el motivo en $motivo.
*/
function adminAutenticado(&$motivo = null)
{
    $motivo = null;

    if (empty($_SESSION['admin']['id_usuario']))
    {
        return null;
    }

    //1) inactividad
    $ultima = $_SESSION['admin_ultima_actividad'] ?? 0;

    if ((time() - $ultima) > (MINUTOS_INACTIVIDAD_ADMIN * 60))
    {
        $motivo = 'inactividad';
        destruirSesionAdmin();
        return null;
    }

    //2) la sesion debe seguir en el mismo navegador
    if (!hash_equals($_SESSION['admin']['huella'] ?? '', huellaNavegador()))
    {
        $motivo = 'huella';
        destruirSesionAdmin();
        return null;
    }

    //3) rol y estatus actuales segun la BD (no segun lo guardado al entrar)
    $admin = obtenerAdminPorId($_SESSION['admin']['id_usuario']);

    if ($admin === null
        || (int) $admin['rol'] !== ROL_ADMIN
        || (int) $admin['estatus'] !== ESTATUS_ACTIVO
        || empty($admin['id_rol_admin']))
    {
        $motivo = 'permisos_revocados';
        registrarEnBitacora($admin['correo'] ?? '', 'admin_acceso_revocado');
        destruirSesionAdmin();
        return null;
    }

    $_SESSION['admin_ultima_actividad'] = time();

    //permisos vigentes en este momento (rol + ajustes por empleado)
    $admin['permisos'] = permisosEfectivosAdmin($admin['id_usuario'], $admin['id_rol_admin']);

    return $admin;
}

//=============================================================
// MIDDLEWARE: se llama al inicio de cada pantalla protegida
//=============================================================

/*
    $permiso = null  -> basta con ser administrador autenticado
    $permiso = '...' -> ademas el rol debe tener ese permiso
    Devuelve los datos del admin para usarlos en la pantalla.
*/
function exigirPermisoAdmin($permiso = null)
{
    $admin = adminAutenticado($motivo);

    if ($admin === null)
    {
        if ($motivo === 'inactividad')
        {
            $mensaje = 'Tu sesion de administrador expiro por inactividad.';
        }
        elseif ($motivo === 'permisos_revocados')
        {
            $mensaje = 'Tu acceso al panel ya no esta autorizado.';
        }
        else
        {
            $mensaje = null;
        }

        //destruirSesionAdmin() ya dejo una sesion nueva: sirve para guardar el aviso
        if ($mensaje !== null)
        {
            guardarMensaje('error', $mensaje);
        }

        redirigir(URL_ADMIN . 'login');
    }

    //contrasena temporal: solo puede abrir la pantalla de cambio de contrasena
    if ((int) $admin['debe_cambiar_contrasena'] === 1 && ($GLOBALS['rutaActual'] ?? '') !== 'cambiar-contrasena')
    {
        guardarMensaje('error', 'Tu contrasena es temporal. Cambiala para continuar.');
        redirigir(URL_ADMIN . 'cambiar-contrasena');
    }

    if ($permiso !== null && !adminTienePermiso($admin, $permiso))
    {
        registrarEnBitacora($admin['correo'], 'admin_acceso_denegado:' . $permiso);

        http_response_code(403);

        //variables que usan header.php y acceso-denegado.php
        $permisoDenegado = $permiso;
        $tituloPagina    = 'Acceso denegado';
        $layoutAdmin     = 'panel';
        $rutaActual      = $GLOBALS['rutaActual'] ?? '';

        include DOCROOT . 'admin/includes/header.php';
        include DOCROOT . 'admin/pages/acceso-denegado.php';
        include DOCROOT . 'admin/includes/footer.php';
        exit;
    }

    return $admin;
}

//=============================================================
// Rutas del panel (el router y el menu lateral leen esta tabla).
// Para agregar un modulo nuevo: una linea aqui + su archivo en admin/pages/
//   'publica' => true      no requiere sesion (solo login)
//   'permiso' => '...'     permiso minimo que exige el middleware
//   'menu'    => 'Texto'   si aparece en el menu lateral (solo a quien tenga el permiso)
//=============================================================

function rutasAdmin()
{
    return [
        'login'     => ['archivo' => 'login.php',     'publica' => true, 'titulo' => 'Acceso administradores'],
        'dashboard' => ['archivo' => 'dashboard.php', 'permiso' => 'dashboard.ver',   'titulo' => 'Resumen',               'menu' => 'Resumen'],
        'cuentas'       => ['archivo' => 'cuentas.php',       'permiso' => 'usuarios.ver',    'titulo' => 'Cuentas',            'menu' => 'Cuentas'],
        'cuenta'        => ['archivo' => 'cuenta.php',        'permiso' => 'usuarios.ver',    'titulo' => 'Perfil de cuenta',   'padre' => 'cuentas'],
        'cuenta-editar' => ['archivo' => 'cuenta-editar.php', 'permiso' => 'usuarios.editar', 'titulo' => 'Editar cuenta',      'padre' => 'cuentas'],
        'equipo'    => ['archivo' => 'equipo.php',    'permiso' => 'admins.gestionar', 'titulo' => 'Gestion de personal',    'menu' => 'Gestion de personal'],
        'equipo-nuevo'  => ['archivo' => 'equipo-formulario.php', 'permiso' => 'admins.gestionar', 'titulo' => 'Nuevo empleado',   'padre' => 'equipo', 'modo' => 'nuevo'],
        'equipo-editar' => ['archivo' => 'equipo-formulario.php', 'permiso' => 'admins.gestionar', 'titulo' => 'Editar empleado', 'padre' => 'equipo', 'modo' => 'editar'],
        'correos'         => ['archivo' => 'correos.php',         'permiso' => 'usuarios.editar', 'titulo' => 'Correos institucionales', 'menu' => 'Correos'],
        'enviar-correo'   => ['archivo' => 'correos.php',         'permiso' => 'usuarios.editar', 'titulo' => 'Enviar correo oficial', 'padre' => 'correos'],
        'reportes'        => ['archivo' => 'reportes.php',        'permiso' => 'reportes.ver',    'titulo' => 'Reportes e Incidencias', 'menu' => 'Reportes'],
        'reporte-detalle' => ['archivo' => 'reporte-detalle.php', 'permiso' => 'reportes.ver',    'titulo' => 'Detalle de Reporte', 'padre' => 'reportes'],
        //cualquier administrador autenticado (tambien la abre quien tiene contrasena temporal)
        'cambiar-contrasena' => ['archivo' => 'cambiar-contrasena.php', 'permiso' => null, 'titulo' => 'Cambiar contrasena']
    ];
}

//etiquetas legibles de los permisos (dashboard y pagina de acceso denegado)
function etiquetaPermiso($permiso)
{
    $etiquetas = [
        'dashboard.ver'        => 'Ver el resumen del panel',
        'usuarios.ver'         => 'Buscar e inspeccionar cuentas de usuarios y freelancers',
        'usuarios.editar'      => 'Editar datos basicos de una cuenta',
        'usuarios.suspender'   => 'Activar o suspender cuentas de usuarios y freelancers',
        'usuarios.verificar'   => 'Cambiar el estatus de verificacion de freelancers',
        'admins.gestionar'     => 'Ver y administrar el equipo administrativo',
        'reportes.ver'         => 'Ver reportes de soporte',
        'reportes.resolver'    => 'Atender y cerrar reportes de soporte',
        'reembolsos.aprobar'   => 'Aprobar o rechazar reembolsos',
        'moderacion.gestionar' => 'Moderar contenido de servicios',
        'bitacora.ver'         => 'Consultar la bitacora de accesos'
    ];

    return $etiquetas[$permiso] ?? $permiso;
}
?>
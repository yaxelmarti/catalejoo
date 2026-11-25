<?php
// ============================================
// GESTIÓN DE USUARIOS
// Versión 29 Oct 2026
// Ubicación: /gestionar_usuarios.php
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/proveedor_utils.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/header.php';

// ============================================
// VALIDAR SESIÓN
// ============================================
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit;
}

$rol_actual = trim($_SESSION['rol'] ?? '');
$usuario_id_actual = intval($_SESSION['usuario_id'] ?? 0);

// ============================================
// PERMISOS
// ============================================
$roles_permitidos = ['Super Admin', 'Gerente', 'Proveedor'];
if (!in_array($rol_actual, $roles_permitidos, true)) {
    error_log("[gestionar_usuarios] Permiso denegado. Rol: $rol_actual, usuario: $usuario_id_actual");
    header('Location: /admin.php?error=permiso');
    exit;
}

$es_super_admin = ($rol_actual === 'Super Admin');
$es_gerente = ($rol_actual === 'Gerente');
$es_proveedor = ($rol_actual === 'Proveedor');

// ============================================
// DETECTAR PROVEEDOR DEL USUARIO
// ============================================
$proveedor_id_usuario = intval(obtenerProveedorId($conn, $usuario_id_actual, $rol_actual));
if ($es_proveedor && $proveedor_id_usuario == 0) {
    $_SESSION['mensaje'] = '❌ No se encontró una tienda asociada a tu usuario';
    $_SESSION['tipo_mensaje'] = 'error';
    header('Location: /admin.php');
    exit;
}

// ============================================
// ROLES PERMITIDOS SEGÚN QUIÉN CREA
// ============================================
// Obtener IDs reales de roles desde la BD (no hardcodear)
$roles_tienda_ids = [];
$roles_globales_ids = [];
$rol_super_admin_id = 0;

$res_roles_meta = $conn->query("SELECT id, nombre, nivel FROM roles ORDER BY nivel DESC");
$roles_meta = [];
if ($res_roles_meta) {
    while ($r = $res_roles_meta->fetch_assoc()) {
        $roles_meta[] = $r;
        if (in_array($r['nombre'], ['Proveedor', 'Inventario', 'Vendedor', 'Empaquetador', 'Transportista'], true)) {
            $roles_tienda_ids[] = intval($r['id']);
        } else {
            $roles_globales_ids[] = intval($r['id']);
        }
        if ($r['nombre'] === 'Super Admin') {
            $rol_super_admin_id = intval($r['id']);
        }
    }
}

// ============================================
// COLORES DE CONFIGURACIÓN
// ============================================
$color_primario = '#667eea';
$color_secundario = '#764ba2';
$stmt_c = $conn->prepare("SELECT valor FROM configuracion WHERE clave = 'color_primario' LIMIT 1");
if ($stmt_c) {
    $stmt_c->execute();
    $row_c = $stmt_c->get_result()->fetch_assoc();
    if ($row_c && !empty($row_c['valor'])) $color_primario = $row_c['valor'];
    $stmt_c->close();
}
$stmt_c2 = $conn->prepare("SELECT valor FROM configuracion WHERE clave = 'color_secundario' LIMIT 1");
if ($stmt_c2) {
    $stmt_c2->execute();
    $row_c2 = $stmt_c2->get_result()->fetch_assoc();
    if ($row_c2 && !empty($row_c2['valor'])) $color_secundario = $row_c2['valor'];
    $stmt_c2->close();
}

// ============================================
// LISTA DE PROVEEDORES (Super Admin / Gerente / Proveedor)
// ============================================
$lista_proveedores = [];
if ($es_super_admin || $es_gerente) {
    $res_prov = $conn->query("SELECT id, nombre_tienda FROM proveedores WHERE activo = 1 ORDER BY nombre_tienda ASC");
    if ($res_prov) {
        while ($p = $res_prov->fetch_assoc()) {
            $lista_proveedores[] = $p;
        }
    }
} elseif ($es_proveedor) {
    // El Proveedor solo ve su propia tienda
    $stmt_prov = $conn->prepare("SELECT id, nombre_tienda FROM proveedores WHERE id = ? AND activo = 1 LIMIT 1");
    if ($stmt_prov) {
        $stmt_prov->bind_param("i", $proveedor_id_usuario);
        $stmt_prov->execute();
        $row_prov = $stmt_prov->get_result()->fetch_assoc();
        if ($row_prov) {
            $lista_proveedores[] = $row_prov;
        }
        $stmt_prov->close();
    }
}

// ============================================
// PROCESAR ACCIONES (POST con CSRF)
// ============================================
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!function_exists('csrf_validar') || !csrf_validar($token)) {
        error_log("[gestionar_usuarios] CSRF inválido. Usuario: $usuario_id_actual");
        $_SESSION['mensaje'] = '❌ Token de seguridad inválido';
        $_SESSION['tipo_mensaje'] = 'error';
        header('Location: /gestionar_usuarios.php');
        exit;
    }

    $accion = $_POST['accion'] ?? '';

    // ---- CREAR USUARIO
    if ($accion === 'crear') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $nombre_completo = trim($_POST['nombre_completo'] ?? '');
        $rol_id_post = intval($_POST['rol_id'] ?? 0);
        $proveedor_id_post = isset($_POST['proveedor_id']) && !empty($_POST['proveedor_id']) ? intval($_POST['proveedor_id']) : null;

        // Validaciones
        $errores = [];
        if ($username === '') $errores[] = "Usuario requerido";
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = "Email no válido";
        if (strlen($password) < 6) $errores[] = "Contraseña debe tener al menos 6 caracteres";
        if ($nombre_completo === '') $errores[] = "Nombre completo requerido";
        if ($rol_id_post <= 0) $errores[] = "Rol requerido";

        // Verificar que el rol exista
        $rol_valido = false;
        $rol_nombre_post = '';
        foreach ($roles_meta as $r) {
            if (intval($r['id']) === $rol_id_post) {
                $rol_valido = true;
                $rol_nombre_post = $r['nombre'];
                break;
            }
        }
        if (!$rol_valido) $errores[] = "Rol no válido";

        // Verificar que el creador pueda asignar ese rol
        if ($rol_valido) {
            if ($es_gerente && $rol_nombre_post === 'Super Admin') {
                $errores[] = "Un Gerente no puede crear Super Admins";
            }
            if ($es_proveedor) {
                if (!in_array($rol_nombre_post, ['Inventario', 'Vendedor', 'Empaquetador', 'Transportista'], true)) {
                    $errores[] = "Solo puedes crear trabajadores de tu tienda";
                }
                // Forzar proveedor_id al del usuario
                $proveedor_id_post = $proveedor_id_usuario;
            }
        }

        // Verificar proveedor si aplica
        if (in_array($rol_nombre_post, ['Proveedor', 'Inventario', 'Vendedor', 'Empaquetador', 'Transportista'], true)) {
            if ($proveedor_id_post === null || $proveedor_id_post <= 0) {
                $errores[] = "Debes asignar un proveedor a este rol";
            } else {
                $stmt_chk = $conn->prepare("SELECT id FROM proveedores WHERE id = ? AND activo = 1 LIMIT 1");
                if ($stmt_chk) {
                    $stmt_chk->bind_param("i", $proveedor_id_post);
                    $stmt_chk->execute();
                    if ($stmt_chk->get_result()->num_rows === 0) {
                        $errores[] = "Proveedor no válido o inactivo";
                    }
                    $stmt_chk->close();
                }
            }
        } else {
            $proveedor_id_post = null;
        }

        if (!empty($errores)) {
            $_SESSION['mensaje'] = "❌ " . implode("<br>❌ ", $errores);
            $_SESSION['tipo_mensaje'] = "error";
            header('Location: /gestionar_usuarios.php');
            exit;
        }

        // Verificar unicidad username/email
        $check = $conn->prepare("SELECT id FROM usuarios WHERE username = ? OR email = ? LIMIT 1");
        if ($check) {
            $check->bind_param("ss", $username, $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $check->close();
                $_SESSION['mensaje'] = "❌ El usuario o email ya existe";
                $_SESSION['tipo_mensaje'] = "error";
                header('Location: /gestionar_usuarios.php');
                exit;
            }
            $check->close();
        }

        // Crear usuario en transacción
        $conn->begin_transaction();
        try {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO usuarios (username, email, password, nombre_completo, rol_id) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt) throw new Exception("prepare insert: " . $conn->error);
            $stmt->bind_param("ssssi", $username, $email, $password_hash, $nombre_completo, $rol_id_post);
            if (!$stmt->execute()) throw new Exception("execute insert: " . $stmt->error);
            $nuevo_usuario_id = intval($conn->insert_id);
            $stmt->close();

            // Asignar proveedor si aplica
            if ($proveedor_id_post !== null && $proveedor_id_post > 0) {
                $stmt_up = $conn->prepare("INSERT INTO usuario_proveedor (usuario_id, proveedor_id, activo) VALUES (?, ?, 1)");
                if (!$stmt_up) throw new Exception("prepare usuario_proveedor: " . $conn->error);
                $stmt_up->bind_param("ii", $nuevo_usuario_id, $proveedor_id_post);
                if (!$stmt_up->execute()) throw new Exception("execute usuario_proveedor: " . $stmt_up->error);
                $stmt_up->close();
            }

            $conn->commit();
            error_log("[gestionar_usuarios] crear OK. Usuario: $usuario_id_actual, nuevo_id: $nuevo_usuario_id, rol: $rol_nombre_post");
            $_SESSION['mensaje'] = "✅ Usuario creado correctamente";
            $_SESSION['tipo_mensaje'] = "exito";
        } catch (Throwable $e) {
            $conn->rollback();
            error_log("[gestionar_usuarios] crear ERROR: " . $e->getMessage());
            $_SESSION['mensaje'] = "❌ Error al crear: " . $e->getMessage();
            $_SESSION['tipo_mensaje'] = "error";
        }
        header('Location: /gestionar_usuarios.php');
        exit;
    }

    // ---- EDITAR USUARIO
    if ($accion === 'editar') {
        $id = intval($_POST['id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $nombre_completo = trim($_POST['nombre_completo'] ?? '');
        $rol_id_post = intval($_POST['rol_id'] ?? 0);
        $activo = isset($_POST['activo']) ? 1 : 0;
        $proveedor_id_post = isset($_POST['proveedor_id']) && !empty($_POST['proveedor_id']) ? intval($_POST['proveedor_id']) : null;

        if ($id <= 0) {
            header('Location: /gestionar_usuarios.php');
            exit;
        }

        // Verificar que el usuario exista
        $stmt_chk = $conn->prepare("SELECT id, rol_id FROM usuarios WHERE id = ? LIMIT 1");
        $stmt_chk->bind_param("i", $id);
        $stmt_chk->execute();
        $usuario_target = $stmt_chk->get_result()->fetch_assoc();
        $stmt_chk->close();

        if (!$usuario_target) {
            $_SESSION['mensaje'] = "❌ Usuario no encontrado";
            $_SESSION['tipo_mensaje'] = "error";
            header('Location: /gestionar_usuarios.php');
            exit;
        }

        // Verificar permisos sobre el target
        if ($es_proveedor) {
            // El Proveedor solo puede editar trabajadores de su tienda
            $stmt_own = $conn->prepare("SELECT id FROM usuario_proveedor WHERE usuario_id = ? AND proveedor_id = ? AND activo = 1 LIMIT 1");
            $stmt_own->bind_param("ii", $id, $proveedor_id_usuario);
            $stmt_own->execute();
            if ($stmt_own->get_result()->num_rows === 0) {
                $stmt_own->close();
                $_SESSION['mensaje'] = "❌ No tienes permiso para editar este usuario";
                $_SESSION['tipo_mensaje'] = "error";
                header('Location: /gestionar_usuarios.php');
                exit;
            }
            $stmt_own->close();
            // Forzar proveedor_id
            $proveedor_id_post = $proveedor_id_usuario;
        }

        // Validaciones
        $errores = [];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = "Email no válido";

        // Verificar rol
        $rol_nombre_post = '';
        foreach ($roles_meta as $r) {
            if (intval($r['id']) === $rol_id_post) {
                $rol_nombre_post = $r['nombre'];
                break;
            }
        }
        if ($rol_nombre_post === '') $errores[] = "Rol no válido";

        // Restricciones
        if ($es_gerente && $rol_nombre_post === 'Super Admin') {
            $errores[] = "Un Gerente no puede asignar Super Admin";
        }
        if ($es_proveedor && !in_array($rol_nombre_post, ['Inventario', 'Vendedor', 'Empaquetador', 'Transportista'], true)) {
            $errores[] = "Solo puedes asignar roles de trabajador de tu tienda";
        }

        // Proveedor si aplica
        if (in_array($rol_nombre_post, ['Proveedor', 'Inventario', 'Vendedor', 'Empaquetador', 'Transportista'], true)) {
            if ($proveedor_id_post === null || $proveedor_id_post <= 0) {
                $errores[] = "Debes asignar un proveedor";
            }
        } else {
            $proveedor_id_post = null;
        }

        if (!empty($errores)) {
            $_SESSION['mensaje'] = "❌ " . implode("<br>❌ ", $errores);
            $_SESSION['tipo_mensaje'] = "error";
            header('Location: /gestionar_usuarios.php?editar=' . $id);
            exit;
        }

        // Unicidad
        $check = $conn->prepare("SELECT id FROM usuarios WHERE (username = ? OR email = ?) AND id != ? LIMIT 1");
        if ($check) {
            $check->bind_param("ssi", $username, $email, $id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $check->close();
                $_SESSION['mensaje'] = "❌ Usuario o email ya en uso";
                $_SESSION['tipo_mensaje'] = "error";
                header('Location: /gestionar_usuarios.php?editar=' . $id);
                exit;
            }
            $check->close();
        }

        // Actualizar en transacción
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE usuarios SET username = ?, email = ?, nombre_completo = ?, rol_id = ?, activo = ? WHERE id = ?");
            if (!$stmt) throw new Exception("prepare update: " . $conn->error);
            $stmt->bind_param("sssiii", $username, $email, $nombre_completo, $rol_id_post, $activo, $id);
            if (!$stmt->execute()) throw new Exception("execute update: " . $stmt->error);
            $stmt->close();

            // Actualizar usuario_proveedor
            $stmt_del = $conn->prepare("DELETE FROM usuario_proveedor WHERE usuario_id = ?");
            if ($stmt_del) {
                $stmt_del->bind_param("i", $id);
                $stmt_del->execute();
                $stmt_del->close();
            }

            if ($proveedor_id_post !== null && $proveedor_id_post > 0) {
                $stmt_up = $conn->prepare("INSERT INTO usuario_proveedor (usuario_id, proveedor_id, activo) VALUES (?, ?, 1)");
                if (!$stmt_up) throw new Exception("prepare usuario_proveedor: " . $conn->error);
                $stmt_up->bind_param("ii", $id, $proveedor_id_post);
                if (!$stmt_up->execute()) throw new Exception("execute usuario_proveedor: " . $stmt_up->error);
                $stmt_up->close();
            }

            $conn->commit();
            error_log("[gestionar_usuarios] editar OK. Usuario: $usuario_id_actual, target: $id");
            $_SESSION['mensaje'] = "✅ Usuario actualizado";
            $_SESSION['tipo_mensaje'] = "exito";
        } catch (Throwable $e) {
            $conn->rollback();
            error_log("[gestionar_usuarios] editar ERROR: " . $e->getMessage());
            $_SESSION['mensaje'] = "❌ Error al actualizar: " . $e->getMessage();
            $_SESSION['tipo_mensaje'] = "error";
        }
        header('Location: /gestionar_usuarios.php');
        exit;
    }

    // ---- CAMBIAR PASSWORD
    if ($accion === 'cambiar_password') {
        $id = intval($_POST['id'] ?? 0);
        $nueva_password = $_POST['nueva_password'] ?? '';
        $confirmar = $_POST['confirmar_password'] ?? '';

        if ($id <= 0) {
            header('Location: /gestionar_usuarios.php');
            exit;
        }

        // Verificar permisos sobre el target
        if ($es_proveedor) {
            $stmt_own = $conn->prepare("SELECT id FROM usuario_proveedor WHERE usuario_id = ? AND proveedor_id = ? AND activo = 1 LIMIT 1");
            $stmt_own->bind_param("ii", $id, $proveedor_id_usuario);
            $stmt_own->execute();
            if ($stmt_own->get_result()->num_rows === 0) {
                $stmt_own->close();
                $_SESSION['mensaje'] = "❌ No tienes permiso";
                $_SESSION['tipo_mensaje'] = "error";
                header('Location: /gestionar_usuarios.php');
                exit;
            }
            $stmt_own->close();
        }

        if ($nueva_password !== $confirmar) {
            $_SESSION['mensaje'] = "❌ Las contraseñas no coinciden";
            $_SESSION['tipo_mensaje'] = "error";
        } elseif (strlen($nueva_password) < 6) {
            $_SESSION['mensaje'] = "❌ Contraseña mínimo 6 caracteres";
            $_SESSION['tipo_mensaje'] = "error";
        } else {
            $hash = password_hash($nueva_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("si", $hash, $id);
                if ($stmt->execute()) {
                    error_log("[gestionar_usuarios] cambiar_password OK. Usuario: $usuario_id_actual, target: $id");
                    $_SESSION['mensaje'] = "✅ Contraseña actualizada";
                    $_SESSION['tipo_mensaje'] = "exito";
                } else {
                    $_SESSION['mensaje'] = "❌ Error al actualizar contraseña";
                    $_SESSION['tipo_mensaje'] = "error";
                }
                $stmt->close();
            }
        }
        header('Location: /gestionar_usuarios.php');
        exit;
    }

    // ---- ELIMINAR USUARIO
    if ($accion === 'eliminar') {
        $id = intval($_POST['id'] ?? 0);

        if ($id <= 0) {
            header('Location: /gestionar_usuarios.php');
            exit;
        }

        if ($id === $usuario_id_actual) {
            $_SESSION['mensaje'] = "❌ No puedes eliminarte a ti mismo";
            $_SESSION['tipo_mensaje'] = "error";
            header('Location: /gestionar_usuarios.php');
            exit;
        }

        // Verificar permisos sobre el target
        if ($es_proveedor) {
            $stmt_own = $conn->prepare("SELECT id FROM usuario_proveedor WHERE usuario_id = ? AND proveedor_id = ? AND activo = 1 LIMIT 1");
            $stmt_own->bind_param("ii", $id, $proveedor_id_usuario);
            $stmt_own->execute();
            if ($stmt_own->get_result()->num_rows === 0) {
                $stmt_own->close();
                $_SESSION['mensaje'] = "❌ No tienes permiso";
                $_SESSION['tipo_mensaje'] = "error";
                header('Location: /gestionar_usuarios.php');
                exit;
            }
            $stmt_own->close();
        }

        // Verificar que no sea el último Super Admin
        $stmt_target = $conn->prepare("SELECT rol_id FROM usuarios WHERE id = ? LIMIT 1");
        $stmt_target->bind_param("i", $id);
        $stmt_target->execute();
        $target_rol = $stmt_target->get_result()->fetch_assoc();
        $stmt_target->close();

        if ($target_rol && intval($target_rol['rol_id']) === $rol_super_admin_id) {
            $stmt_count = $conn->prepare("SELECT COUNT(*) as total FROM usuarios WHERE rol_id = ? AND activo = 1");
            $stmt_count->bind_param("i", $rol_super_admin_id);
            $stmt_count->execute();
            $total_sa = intval($stmt_count->get_result()->fetch_assoc()['total'] ?? 0);
            $stmt_count->close();
            if ($total_sa <= 1) {
                $_SESSION['mensaje'] = "❌ No puedes eliminar al último Super Admin activo";
                $_SESSION['tipo_mensaje'] = "error";
                header('Location: /gestionar_usuarios.php');
                exit;
            }
        }

        // Eliminar en transacción
        $conn->begin_transaction();
        try {
            $stmt_del_up = $conn->prepare("DELETE FROM usuario_proveedor WHERE usuario_id = ?");
            if ($stmt_del_up) {
                $stmt_del_up->bind_param("i", $id);
                $stmt_del_up->execute();
                $stmt_del_up->close();
            }
            $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
            if (!$stmt) throw new Exception("prepare delete: " . $conn->error);
            $stmt->bind_param("i", $id);
            if (!$stmt->execute()) throw new Exception("execute delete: " . $stmt->error);
            $stmt->close();

            $conn->commit();
            error_log("[gestionar_usuarios] eliminar OK. Usuario: $usuario_id_actual, target: $id");
            $_SESSION['mensaje'] = "✅ Usuario eliminado";
            $_SESSION['tipo_mensaje'] = "exito";
        } catch (Throwable $e) {
            $conn->rollback();
            error_log("[gestionar_usuarios] eliminar ERROR: " . $e->getMessage());
            $_SESSION['mensaje'] = "❌ Error al eliminar: " . $e->getMessage();
            $_SESSION['tipo_mensaje'] = "error";
        }
        header('Location: /gestionar_usuarios.php');
        exit;
    }
}

// ============================================
// OBTENER DATOS PARA LA VISTA
// ============================================
// Filtro por proveedor si es Proveedor
$where = [];
$params = [];
$types = "";
if ($es_proveedor) {
    $where[] = "u.id IN (SELECT usuario_id FROM usuario_proveedor WHERE proveedor_id = ? AND activo = 1)";
    $params[] = $proveedor_id_usuario;
    $types .= "i";
}
$sql_where = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$sql_usuarios = "SELECT u.*, r.nombre as rol_nombre, r.nivel,
                 (SELECT proveedor_id FROM usuario_proveedor WHERE usuario_id = u.id LIMIT 1) AS proveedor_asignado
                 FROM usuarios u 
                 INNER JOIN roles r ON u.rol_id = r.id 
                 $sql_where
                 ORDER BY u.fecha_registro DESC";
$stmt_usuarios = $conn->prepare($sql_usuarios);
$usuarios = null;
if ($stmt_usuarios) {
    if (!empty($params)) $stmt_usuarios->bind_param($types, ...$params);
    $stmt_usuarios->execute();
    $usuarios = $stmt_usuarios->get_result();
}

// Roles filtrados según quién crea
$roles_disponibles = [];
if ($es_super_admin) {
    $roles_disponibles = $roles_meta;
} elseif ($es_gerente) {
    foreach ($roles_meta as $r) {
        if ($r['nombre'] !== 'Super Admin') $roles_disponibles[] = $r;
    }
} elseif ($es_proveedor) {
    foreach ($roles_meta as $r) {
        if (in_array($r['nombre'], ['Inventario', 'Vendedor', 'Empaquetador', 'Transportista'], true)) {
            $roles_disponibles[] = $r;
        }
    }
}

// Usuario a editar
$usuario_editar = null;
if (isset($_GET['editar']) && is_numeric($_GET['editar'])) {
    $id_editar = intval($_GET['editar']);
    $stmt = $conn->prepare("SELECT u.*, 
                            (SELECT proveedor_id FROM usuario_proveedor WHERE usuario_id = u.id LIMIT 1) as proveedor_asignado
                            FROM usuarios u WHERE u.id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $id_editar);
        $stmt->execute();
        $usuario_editar = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// Mensajes de sesión
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    $tipo_mensaje = $_SESSION['tipo_mensaje'] ?? 'info';
    unset($_SESSION['mensaje']);
    unset($_SESSION['tipo_mensaje']);
}
?>

<style>
:root {
    --primary: <?php echo htmlspecialchars($color_primario); ?>;
    --secondary: <?php echo htmlspecialchars($color_secundario); ?>;
}

.gestion-container {
    width: 100%;
    max-width: 1400px;
    margin: 1rem auto;
    padding: 0 1rem;
}
@media (min-width: 768px) {
    .gestion-container { margin: 2rem auto; padding: 0 2rem; }
}

.page-title {
    text-align: center;
    margin-bottom: 1.5rem;
    font-size: clamp(1.3rem, 5vw, 2rem);
    color: #2d3748;
}

.card {
    background: white;
    border-radius: 20px;
    padding: clamp(1.25rem, 3vw, 2rem);
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    width: 100%;
}

.card h3 {
    margin-bottom: 1.25rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--primary, #667eea);
    color: #2d3748;
    font-size: clamp(1.1rem, 3vw, 1.4rem);
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}
@media (min-width: 640px) {
    .form-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (min-width: 1024px) {
    .form-grid { grid-template-columns: repeat(4, 1fr); }
}

.form-group { margin-bottom: 1rem; width: 100%; }
.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #4a5568;
    font-size: 0.85rem;
}
.form-group input,
.form-group select {
    width: 100%;
    padding: 0.7rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 16px;
    min-height: 44px;
}

.form-group input[type="checkbox"] {
    width: auto;
    margin-right: 0.5rem;
    min-height: auto;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}

.form-actions {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-top: 1rem;
}
@media (min-width: 480px) {
    .form-actions { flex-direction: row; justify-content: flex-end; }
}

.btn {
    padding: 0.7rem 1.25rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    text-align: center;
    font-size: 0.9rem;
    min-height: 44px;
    width: 100%;
}
@media (min-width: 480px) {
    .btn { width: auto; }
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary, #667eea) 0%, var(--secondary, #764ba2) 100%);
    color: white;
}
.btn-secondary { background: #edf2f7; color: #4a5568; }

.tabla-usuarios {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    margin-top: 1rem;
    width: 100%;
}
.tabla-usuarios table {
    width: 100%;
    min-width: 900px;
    border-collapse: collapse;
}
.tabla-usuarios th {
    background: #f8fafc;
    padding: 0.7rem 0.5rem;
    text-align: left;
    font-weight: 600;
    color: #4a5568;
    border-bottom: 2px solid #e2e8f0;
    font-size: 0.75rem;
    white-space: nowrap;
}
.tabla-usuarios td {
    padding: 0.7rem 0.5rem;
    border-bottom: 1px solid #edf2f7;
    font-size: 0.8rem;
}

.rol-badge, .estado {
    display: inline-block;
    padding: 0.25rem 0.6rem;
    border-radius: 20px;
    font-size: 0.7rem;
    white-space: nowrap;
}
.estado.activo { background: #f0fff4; color: #22543d; border: 1px solid #9ae6b4; }
.estado.inactivo { background: #fff5f5; color: #742a2a; border: 1px solid #feb2b2; }

.acciones-tabla {
    display: flex;
    gap: 0.3rem;
    flex-wrap: wrap;
}
.btn-edit, .btn-password, .btn-delete {
    display: inline-flex;
    align-items: center;
    padding: 0.35rem 0.7rem;
    border-radius: 6px;
    font-size: 0.75rem;
    text-decoration: none;
    border: none;
    cursor: pointer;
    min-height: 32px;
}
.btn-edit { background: #4299e1; color: white; }
.btn-password { background: #ecc94b; color: #744210; }
.btn-delete { background: #f56565; color: white; }

.mensaje {
    padding: 0.8rem 1rem;
    border-radius: 10px;
    margin-bottom: 1rem;
    font-size: 0.9rem;
    word-break: break-word;
}
.mensaje.exito { background: #f0fff4; color: #22543d; border-left: 4px solid #48bb78; }
.mensaje.error { background: #fff5f5; color: #742a2a; border-left: 4px solid #f56565; }

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0; top: 0;
    width: 100%; height: 100%;
    background-color: rgba(0,0,0,0.5);
}
.modal-content {
    background: white;
    margin: 10% auto;
    padding: 1.5rem;
    border-radius: 20px;
    width: 90%;
    max-width: 450px;
}
.close {
    float: right;
    font-size: 24px;
    cursor: pointer;
    color: #a0aec0;
}

.text-center { text-align: center; }
.sin-datos { text-align: center; padding: 2rem; color: #a0aec0; }
</style>

<div class="gestion-container">
    <h2 class="page-title">👥 Gestión de Usuarios</h2>

    <?php if (!empty($mensaje)): ?>
        <div class="mensaje <?php echo htmlspecialchars($tipo_mensaje); ?>"><?php echo $mensaje; ?></div>
    <?php endif; ?>

    <!-- FORMULARIO CREAR/EDITAR -->
    <div class="card">
        <h3><?php echo $usuario_editar ? '✏️ Editar Usuario' : '➕ Crear Nuevo Usuario'; ?></h3>

        <form method="POST" class="form-admin">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="accion" value="<?php echo $usuario_editar ? 'editar' : 'crear'; ?>">
            <?php if ($usuario_editar): ?>
                <input type="hidden" name="id" value="<?php echo intval($usuario_editar['id']); ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div class="form-group">
                    <label for="username">Usuario *</label>
                    <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($usuario_editar['username'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($usuario_editar['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="nombre_completo">Nombre Completo *</label>
                    <input type="text" id="nombre_completo" name="nombre_completo" required value="<?php echo htmlspecialchars($usuario_editar['nombre_completo'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="rol_id">Rol *</label>
                    <select id="rol_id" name="rol_id" required>
                        <?php foreach ($roles_disponibles as $rol): ?>
                            <option value="<?php echo intval($rol['id']); ?>" 
                                <?php echo ($usuario_editar && intval($usuario_editar['rol_id']) === intval($rol['id'])) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($rol['nombre']); ?> (Nivel <?php echo intval($rol['nivel']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group" id="proveedor_container" style="display: none;">
                <label for="proveedor_id">🏪 Asignar a Proveedor</label>
                <select id="proveedor_id" name="proveedor_id">
                    <option value="">-- Seleccionar proveedor --</option>
                    <?php foreach ($lista_proveedores as $prov): ?>
                        <option value="<?php echo intval($prov['id']); ?>"
                            <?php echo (isset($usuario_editar['proveedor_asignado']) && intval($usuario_editar['proveedor_asignado']) === intval($prov['id'])) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($prov['nombre_tienda']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($usuario_editar): ?>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="activo" value="1" <?php echo $usuario_editar['activo'] ? 'checked' : ''; ?>>
                        <span>Usuario activo</span>
                    </label>
                </div>
            <?php endif; ?>

            <?php if (!$usuario_editar): ?>
                <div class="form-group">
                    <label for="password">Contraseña * (mín. 6)</label>
                    <input type="password" id="password" name="password" required minlength="6">
                </div>
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?php echo $usuario_editar ? 'Actualizar' : 'Crear'; ?></button>
                <?php if ($usuario_editar): ?>
                    <a href="/gestionar_usuarios.php" class="btn btn-secondary">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- LISTA -->
    <div class="card">
        <h3>📋 Usuarios Registrados</h3>
        <div class="tabla-usuarios">
            <table>
                <thead>
                    <tr>
                        <th>ID</th><th>Usuario</th><th>Email</th><th>Nombre</th>
                        <th>Rol</th><th>Estado</th><th>Registro</th><th>Acceso</th><th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($usuarios && $usuarios->num_rows > 0): ?>
                        <?php while($user = $usuarios->fetch_assoc()): 
                            $ts_reg = strtotime($user['fecha_registro'] ?? '');
                            $fecha_reg = ($ts_reg !== false) ? date('d/m/Y', $ts_reg) : '—';
                            $fecha_acc = 'Nunca';
                            if (!empty($user['ultimo_acceso'])) {
                                $ts_acc = strtotime($user['ultimo_acceso']);
                                if ($ts_acc !== false) $fecha_acc = date('d/m/Y H:i', $ts_acc);
                            }
                            $username_js = htmlspecialchars(json_encode($user['username']), ENT_QUOTES, 'UTF-8');
                        ?>
                        <tr>
                            <td><?php echo intval($user['id']); ?></td>
                            <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['nombre_completo']); ?></td>
                            <td><span class="rol-badge" style="background: <?php echo intval($user['nivel']) >= 8 ? '#667eea' : (intval($user['nivel']) >= 5 ? '#4299e1' : '#4ecdc4'); ?>; color: white;"><?php echo htmlspecialchars($user['rol_nombre']); ?></span></td>
                            <td><span class="estado <?php echo $user['activo'] ? 'activo' : 'inactivo'; ?>"><?php echo $user['activo'] ? '✅ Activo' : '❌ Inactivo'; ?></span></td>
                            <td><?php echo $fecha_reg; ?></td>
                            <td><?php echo $fecha_acc; ?></td>
                            <td class="acciones-tabla">
                                <a href="?editar=<?php echo intval($user['id']); ?>" class="btn-edit">✏️ Editar</a>
                                <button onclick="mostrarModalPassword(<?php echo intval($user['id']); ?>, <?php echo $username_js; ?>)" class="btn-password">🔑 Pass</button>
                                <?php if (intval($user['id']) !== $usuario_id_actual): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar usuario?')">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id" value="<?php echo intval($user['id']); ?>">
                                        <button type="submit" class="btn-delete">🗑️ Eliminar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="9" class="sin-datos">📭 No hay usuarios</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL PASSWORD -->
<div id="modalPassword" class="modal">
    <div class="modal-content">
        <span class="close" onclick="cerrarModal()">&times;</span>
        <h3>🔑 Cambiar Contraseña</h3>
        <p id="modalUsuario"></p>
        <form method="POST" id="formPassword">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="accion" value="cambiar_password">
            <input type="hidden" name="id" id="modalUserId">
            <div class="form-group">
                <label>Nueva Contraseña:</label>
                <input type="password" name="nueva_password" required minlength="6">
            </div>
            <div class="form-group">
                <label>Confirmar:</label>
                <input type="password" name="confirmar_password" required minlength="6">
            </div>
            <div class="form-actions" style="margin-top:1.5rem;">
                <button type="submit" class="btn btn-primary" style="width:100%;">Actualizar</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('rol_id').addEventListener('change', function() {
    const rol_id = parseInt(this.value);
    const proveedorContainer = document.getElementById('proveedor_container');
    // Roles de tienda verificados contra roles_meta (IDs reales)
    const rolesTienda = <?php echo json_encode($roles_tienda_ids); ?>;
    if (rolesTienda.includes(rol_id)) {
        proveedorContainer.style.display = 'block';
    } else {
        proveedorContainer.style.display = 'none';
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const rol_id = document.getElementById('rol_id').value;
    if (rol_id) {
        document.getElementById('rol_id').dispatchEvent(new Event('change'));
    }
});

function mostrarModalPassword(userId, username) {
    document.getElementById('modalUserId').value = userId;
    document.getElementById('modalUsuario').innerHTML = 'Cambiando contraseña de: <strong></strong>';
    document.getElementById('modalUsuario').querySelector('strong').textContent = username;
    document.getElementById('modalPassword').style.display = 'block';
}

function cerrarModal() {
    document.getElementById('modalPassword').style.display = 'none';
}

window.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal')) {
        cerrarModal();
    }
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
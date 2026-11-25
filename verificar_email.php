<?php
require_once 'conexion.php';

$mensaje = '';
$tipo = '';

// Obtener token de la URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $mensaje = "❌ Token no proporcionado";
    $tipo = 'error';
} else {
    // Buscar cliente con este token no expirado
    $stmt = $conn->prepare("SELECT id, email, nombre FROM clientes 
                            WHERE verification_token = ? 
                            AND token_expires > NOW() 
                            AND email_verified = FALSE");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($cliente = $result->fetch_assoc()) {
        // Marcar como verificado
        $stmt = $conn->prepare("UPDATE clientes SET 
                                email_verified = TRUE, 
                                verification_token = NULL,
                                token_expires = NULL 
                                WHERE id = ?");
        $stmt->bind_param("i", $cliente['id']);
        $stmt->execute();
        
        $mensaje = "✅ ¡Email verificado correctamente! Ya puedes realizar compras.";
        $tipo = 'success';
    } else {
        $mensaje = "❌ Enlace inválido o expirado. Solicita un nuevo enlace.";
        $tipo = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Email</title>
    <link rel="stylesheet" href="estilo.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
        }
        .verificacion-container {
            background: white;
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 90%;
            text-align: center;
        }
        .success { color: #48bb78; }
        .error { color: #f56565; }
        h1 { margin-bottom: 1.5rem; }
        .btn {
            display: inline-block;
            margin-top: 2rem;
            padding: 0.8rem 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="verificacion-container">
        <h1 class="<?php echo $tipo; ?>">
            <?php echo $tipo === 'success' ? '✅' : '❌'; ?>
        </h1>
        <p><?php echo $mensaje; ?></p>
        <a href="index.php" class="btn">Ir a la tienda</a>
    </div>
</body>
</html>
<?php $conn->close(); ?>
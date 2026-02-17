<?php
session_start();

if (isset($_SESSION['usuario_id'])) {
    header('Location: principal.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/database.php';

    $password = trim($_POST['password'] ?? '');

    if ($password === '') {
        $error = "Ingresa la contraseña.";
    } else {

        // 1) Traer SOLO usuarios activos y SOLO columnas necesarias
        $sql = "SELECT id, nombre_completo, password
                FROM usuarios
                WHERE activo = 1";

        $result = $conn->query($sql);

        if (!$result) {
            $error = "Error consultando usuarios.";
        } else {

            $usuario_encontrado = null;

            // 2) Buscar match de contraseña (bcrypt o md5 legacy)
            while ($u = $result->fetch_assoc()) {
                $hash_db = (string)($u['password'] ?? '');

                // bcrypt ($2y$ ...)
                if (strpos($hash_db, '$2y$') === 0 || strpos($hash_db, '$2a$') === 0 || strpos($hash_db, '$2b$') === 0) {
                    if (password_verify($password, $hash_db)) {
                        $usuario_encontrado = $u;
                        break;
                    }
                }
                // md5 legacy (32 hex)
                elseif (strlen($hash_db) === 32 && ctype_xdigit($hash_db)) {
                    if (md5($password) === strtolower($hash_db)) {
                        $usuario_encontrado = $u;
                        break;
                    }
                }
            }

            if ($usuario_encontrado) {

                // 3) Si era md5, migrar a bcrypt de forma segura
                $hash_db = (string)$usuario_encontrado['password'];
                if (strlen($hash_db) === 32 && ctype_xdigit($hash_db)) {
                    $bcrypt_new = password_hash($password, PASSWORD_BCRYPT);

                    $stmtUp = $conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                    if ($stmtUp) {
                        $uid = (int)$usuario_encontrado['id'];
                        $stmtUp->bind_param("si", $bcrypt_new, $uid);
                        $stmtUp->execute();
                        $stmtUp->close();
                    }
                }

                $_SESSION['usuario_id'] = (int)$usuario_encontrado['id'];
                $_SESSION['usuario_nombre'] = (string)$usuario_encontrado['nombre_completo'];

                header('Location: principal.php');
                exit;

            } else {
                // Mensaje genérico para no dar pistas
                $error = "Contraseña incorrecta.";
            }
        }

        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Captura de Precios</title>

    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/components.css">
    <link rel="stylesheet" href="css/animations.css">
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box" style="animation: fadeInUp 0.6s ease;">

            <div class="company-logo">
                <img src="images/progel.png" alt="Logo Progel">
            </div>

            <h1 class="login-title">Captura de Precios</h1>
            <p class="login-subtitle">Ingresa tu contraseña para continuar</p>

            <?php if ($error): ?>
                <div class="login-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <input
                    type="password"
                    name="password"
                    class="password-input"
                    placeholder="Ingresa tu contraseña"
                    required
                    autofocus
                >

                <button type="submit" class="login-btn">
                    🚀 Entrar al Sistema
                </button>
            </form>
        </div>
    </div>
</body>
</html>

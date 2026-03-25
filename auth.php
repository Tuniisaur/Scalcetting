<?php
ob_start();

require_once 'session_config.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

require_once 'database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    $input = json_decode(file_get_contents('php://input'), true);
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    if ($action === 'register') {
        $nome = trim($input['nome'] ?? '');
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';

        if (empty($nome) || empty($username) || empty($password)) {
            echo json_encode(['success' => false, 'error' => 'Compila tutti i campi']);
            exit;
        }

        $check = $conn->prepare("SELECT id FROM giocatori WHERE username = ?");
        $check->execute([$username]);
        if ($check->rowCount() > 0) {
            echo json_encode(['success' => false, 'error' => 'Username già in uso']);
            exit;
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO giocatori (nome, username, password) VALUES (?, ?, ?)");
        $stmt->execute([$nome, $username, $hashed_password]);
        
        $_SESSION['user_id'] = $conn->lastInsertId();
        $_SESSION['user_nome'] = $nome;
        $_SESSION['user_username'] = $username;
        $_SESSION['user_avatar'] = null;
        $_SESSION['user_name_color'] = null;
        $_SESSION['user_name_style'] = null;
        $_SESSION['user_aura'] = null;

        echo json_encode(['success' => true, 'message' => 'Registrazione completata!']);
    } 

    elseif ($action === 'login') {
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';

        $stmt = $conn->prepare("SELECT * FROM giocatori WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nome'] = $user['nome'];
            $_SESSION['user_username'] = $user['username'];
            $_SESSION['user_avatar'] = $user['avatar_url'];
            $_SESSION['user_name_color'] = $user['active_name_color'];
            $_SESSION['user_name_style'] = $user['active_name_style'];
            $_SESSION['user_aura'] = $user['active_aura'];
            
            unset($user['password']);
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Credenziali non valide']);
        }
    }

    elseif ($action === 'update_profile') {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'error' => 'Devi essere loggato']);
            exit;
        }

        $nome = trim($_POST['nome'] ?? '');
        // Default to current avatar if no new file/url provided
        $avatar = $_SESSION['user_avatar']; 

        if (empty($nome)) {
            echo json_encode(['success' => false, 'error' => 'Il nome non può essere vuoto']);
            exit;
        }

        // Handle File Upload
        if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar_file'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            
            if (!in_array($file['type'], $allowedTypes)) {
                echo json_encode(['success' => false, 'error' => 'Formato file non supportato (solo JPG, PNG, GIF, WEBP)']);
                exit;
            }

            // Create uploads dir if not exists
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            // Generate unique name
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'user_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
            $targetPath = $uploadDir . $filename;
            $publicUrl = 'uploads/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $avatar = $publicUrl;
            } else {
                echo json_encode(['success' => false, 'error' => 'Errore nel caricamento del file']);
                exit;
            }
        } elseif (isset($_POST['avatar_url']) && !empty(trim($_POST['avatar_url']))) {
            // Fallback to URL if provided and no file
            $avatar = trim($_POST['avatar_url']);
        }

        try {
            $stmt = $conn->prepare("UPDATE giocatori SET nome = ?, avatar_url = ? WHERE id = ?");
            $stmt->execute([$nome, $avatar, $_SESSION['user_id']]);

            // Update Session
            $_SESSION['user_nome'] = $nome;
            $_SESSION['user_avatar'] = $avatar;

            echo json_encode(['success' => true, 'message' => 'Profilo aggiornato', 'user' => [
                'nome' => $nome,
                'avatar_url' => $avatar
            ]]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Errore durante l\'aggiornamento']);
        }
    }

    elseif ($action === 'change_password') {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'error' => 'Devi essere loggato']);
            exit;
        }

        $currentPass = $input['current_password'] ?? '';
        $newPass = $input['new_password'] ?? '';

        if (empty($currentPass) || empty($newPass)) {
            echo json_encode(['success' => false, 'error' => 'Compila tutti i campi']);
            exit;
        }

        $stmt = $conn->prepare("SELECT password FROM giocatori WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $storedHash = $stmt->fetchColumn();

        if ($storedHash && password_verify($currentPass, $storedHash)) {
            $newHash = password_hash($newPass, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE giocatori SET password = ? WHERE id = ?");
            $update->execute([$newHash, $_SESSION['user_id']]);
            
            echo json_encode(['success' => true, 'message' => 'Password aggiornata con successo']);
        } else {
            echo json_encode(['success' => false, 'error' => 'La password attuale non è corretta']);
        }
    }

    elseif ($action === 'check') {
        if (isset($_SESSION['user_id'])) {
            $stmt = $conn->prepare("SELECT id, nome, username, is_admin, avatar_url FROM giocatori WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                echo json_encode(['success' => true, 'logged_in' => true, 'user' => $user]);
            } else {
                session_destroy();
                echo json_encode(['success' => true, 'logged_in' => false]);
            }
        } else {
            echo json_encode(['success' => true, 'logged_in' => false]);
        }
    } 

    elseif ($action === 'logout') {
        session_destroy();
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Azione non valida']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Errore server: ' . $e->getMessage()]);
}

ob_end_flush();
?>
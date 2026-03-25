<?php
session_start();

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Connessione al database fallita', 'message' => $e->getMessage()]);
    exit();
}

function isAdmin($conn) {
    if (!isset($_SESSION['user_id'])) return false;
    $stmt = $conn->prepare("SELECT is_admin FROM giocatori WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return (bool)$stmt->fetchColumn();
}

// Router delle azioni
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        handleGetGiocatori($conn);
        break;
        
    case 'POST':
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Devi essere loggato']);
            exit;
        }

        $action = $_GET['action'] ?? '';

        if ($action === 'upload_avatar') {
            handleUploadAvatar($conn);
        } elseif ($action === 'update_info') {
            handleUpdateProfile($conn);
        } else {
            // Default: Creazione nuovo giocatore
            handleCreateGiocatore($conn);
        }
        break;
        
    case 'DELETE':
        if (!isAdmin($conn)) {
            http_response_code(403);
            echo json_encode(['error' => 'Accesso negato: Solo Admin']);
            exit;
        }
        handleDeleteGiocatore($conn);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Metodo non permesso']);
}

// --- FUNZIONI ---

function handleGetGiocatori($conn)
{
    try {
        $sql = "SELECT 
                    id, 
                    nome,
                    username, 
                    avatar_url,
                    crediti,
                    CASE WHEN partite_portiere > 0 THEN elo_portiere ELSE NULL END as display_elo_portiere,
                    CASE WHEN partite_attaccante > 0 THEN elo_attaccante ELSE NULL END as display_elo_attaccante,
                    CASE 
                        WHEN partite_portiere > 0 AND partite_attaccante > 0 THEN GREATEST(elo_portiere, elo_attaccante)
                        WHEN partite_portiere > 0 THEN elo_portiere
                        WHEN partite_attaccante > 0 THEN elo_attaccante
                        ELSE 1500 
                    END as elo_medio,
                    partite_portiere,
                    partite_attaccante,
                    partite_totali,
                    vittorie_portiere,
                    vittorie_attaccante,
                    vittorie_totali,
                    sconfitte_portiere,
                    sconfitte_attaccante,
                    sconfitte_totali,
                    active_aura,
                    active_title,
                    active_name_color,
                    active_name_style,
                    created_at 
                FROM giocatori 
                WHERE id != 9999
                ORDER BY 
                    CASE 
                        WHEN partite_portiere > 0 AND partite_attaccante > 0 THEN GREATEST(elo_portiere, elo_attaccante)
                        WHEN partite_portiere > 0 THEN elo_portiere
                        WHEN partite_attaccante > 0 THEN elo_attaccante
                        ELSE 1500 
                    END DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();

        $giocatori = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Arricchimento dati dinamici (Titoli speciali come Bomber)
        enrichPlayerData($giocatori, $conn);

        foreach ($giocatori as &$giocatore) {
            $giocatore['id'] = (int)$giocatore['id'];
            $giocatore['elo_portiere'] = $giocatore['display_elo_portiere'] !== null ? (int)$giocatore['display_elo_portiere'] : null;
            $giocatore['elo_attaccante'] = $giocatore['display_elo_attaccante'] !== null ? (int)$giocatore['display_elo_attaccante'] : null;
            $giocatore['elo_medio'] = (int)$giocatore['elo_medio'];
            $giocatore['partite_portiere'] = (int)$giocatore['partite_portiere'];
            $giocatore['partite_attaccante'] = (int)$giocatore['partite_attaccante'];
            $giocatore['partite_totali'] = (int)$giocatore['partite_totali'];
            $giocatore['vittorie_portiere'] = (int)$giocatore['vittorie_portiere'];
            $giocatore['vittorie_attaccante'] = (int)$giocatore['vittorie_attaccante'];
            $giocatore['vittorie_totali'] = (int)$giocatore['vittorie_totali'];
            $giocatore['sconfitte_portiere'] = (int)$giocatore['sconfitte_portiere'];
            $giocatore['sconfitte_attaccante'] = (int)$giocatore['sconfitte_attaccante'];
            $giocatore['sconfitte_totali'] = (int)$giocatore['sconfitte_totali'];
        }

        echo json_encode($giocatori);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Query failed', 'code' => $e->getCode(), 'message' => $e->getMessage(), 'sql_debug' => 'Controllare query giocatori.php']);
    }
}

function handleCreateGiocatore($conn)
{
    try {
        $input = file_get_contents('php://input');

        if (empty($input)) {
            http_response_code(400);
            echo json_encode(['error' => 'Empty request body']);
            return;
        }

        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            return;
        }

        if (empty($data['nome'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing nome']);
            return;
        }

        $nome = trim($data['nome']);

        $sql = "SELECT COUNT(*) FROM giocatori WHERE LOWER(nome) = LOWER(?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$nome]);

        if ($stmt->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode(['error' => 'Player already exists']);
            return;
        }

        $username = strtolower(str_replace(' ', '_', $nome));
        $username = preg_replace('/[^a-z0-9_]/', '', $username);
        $password = password_hash('1234', PASSWORD_DEFAULT);

        $sql = "INSERT INTO giocatori (nome, username, password) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        try {
            $stmt->execute([$nome, $username, $password]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { 
                $username .= rand(10, 99);
                $stmt->execute([$nome, $username, $password]);
            } else {
                throw $e;
            }
        }

        $newId = $conn->lastInsertId();

        $sql = "SELECT * FROM giocatori WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$newId]);
        $newPlayer = $stmt->fetch(PDO::FETCH_ASSOC);

        unset($newPlayer['password']);

        http_response_code(201);
        echo json_encode(['success' => true, 'data' => $newPlayer, 'message' => "Giocatore creato! Username: $username, Pass: 1234"]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Errore database', 'message' => $e->getMessage()]);
    }
}

function handleDeleteGiocatore($conn)
{
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!isset($data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID giocatore mancante']);
            return;
        }

        $playerId = (int)$data['id'];

        $sql = "SELECT * FROM giocatori WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$playerId]);
        $player = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$player) {
            http_response_code(404);
            echo json_encode(['error' => 'Giocatore non trovato']);
            return;
        }

        $sql = "SELECT COUNT(*) FROM partite WHERE 
                squadra1_portiere = ? OR squadra1_attaccante = ? OR 
                squadra2_portiere = ? OR squadra2_attaccante = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$playerId, $playerId, $playerId, $playerId]);
        
        if ($stmt->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode(['error' => 'Non è possibile eliminare un giocatore che ha delle partite']);
            return;
        }

        $sql = "DELETE FROM giocatori WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$playerId]);

        echo json_encode(['success' => true, 'message' => "Giocatore eliminato"]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

function handleUploadAvatar($conn) {
    $userId = $_SESSION['user_id'];
    
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['error' => 'Errore nel caricamento file']);
        return;
    }

    $file = $_FILES['avatar'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['error' => 'Formato non supportato (JPG, PNG, GIF, WEBP)']);
        return;
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['error' => 'File troppo grande (Max 5MB)']);
        return;
    }

    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'avatar_' . $userId . '_' . time() . '.' . $extension;
    $targetPath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $stmt = $conn->prepare("UPDATE giocatori SET avatar_url = ? WHERE id = ?");
        $stmt->execute([$targetPath, $userId]);
        echo json_encode(['success' => true, 'avatar_url' => $targetPath]);
    } else {
        echo json_encode(['error' => 'Errore salvataggio file su server']);
    }
}

function handleUpdateProfile($conn) {
    $userId = $_SESSION['user_id'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    $newName = isset($input['nome']) ? trim($input['nome']) : null;
    $newUsername = isset($input['username']) ? trim($input['username']) : null;

    if (empty($newName) && empty($newUsername)) {
        echo json_encode(['error' => 'Nessun dato da modificare']);
        return;
    }

    $updates = [];
    $params = [];

    if (!empty($newName)) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM giocatori WHERE nome = ? AND id != ?");
        $stmt->execute([$newName, $userId]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['error' => "Nome '$newName' già in uso"]);
            return;
        }
        $updates[] = "nome = ?";
        $params[] = $newName;
    }

    if (!empty($newUsername)) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM giocatori WHERE username = ? AND id != ?");
        $stmt->execute([$newUsername, $userId]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['error' => "Username '$newUsername' già in uso"]);
            return;
        }
        $updates[] = "username = ?";
        $params[] = $newUsername;
        $_SESSION['username'] = $newUsername;
    }

    if (empty($updates)) {
        echo json_encode(['success' => true, 'message' => 'Nessuna modifica']);
        return;
    }

    $sql = "UPDATE giocatori SET " . implode(', ', $updates) . " WHERE id = ?";
    $params[] = $userId;

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'message' => 'Profilo aggiornato']);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}
?>
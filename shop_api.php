<?php
require_once 'session_config.php';
require_once 'database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non loggato']);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

try {
    $database = new Database();
    $conn = $database->getConnection();

    // --- LIST SHOP ITEMS ---
    if ($action === 'list') {
        $stmt = $conn->prepare("SELECT si.*, 
                                (SELECT quantity FROM user_inventory ui WHERE ui.item_id = si.id AND ui.user_id = ?) as owned_quantity 
                                FROM shop_items si WHERE is_exclusive = 0 ORDER BY cost ASC");
        $stmt->execute([$userId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get user credits for convenience
        $stmt = $conn->prepare("SELECT crediti FROM giocatori WHERE id = ?");
        $stmt->execute([$userId]);
        $credits = $stmt->fetchColumn();

        echo json_encode(['success' => true, 'items' => $items, 'user_credits' => $credits]);
        exit;
    }

    // --- GET INVENTORY ---
    if ($action === 'inventory') {
        $sql = "SELECT ui.quantity, ui.item_id, si.* 
                FROM user_inventory ui 
                JOIN shop_items si ON ui.item_id = si.id 
                WHERE ui.user_id = ? AND ui.quantity > 0";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$userId]);
        $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'inventory' => $inventory]);
        exit;
    }

    // --- BUY ITEM ---
    if ($action === 'buy') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        
        $conn->beginTransaction();

        // 1. Check Item cost
        $stmt = $conn->prepare("SELECT cost, name, item_type FROM shop_items WHERE id = ?");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) throw new Exception("Oggetto non trovato");

        // 2. Check User Credits
        $stmt = $conn->prepare("SELECT crediti FROM giocatori WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $credits = $stmt->fetchColumn();

        if ($credits < $item['cost']) {
            throw new Exception("Strisciate insufficienti");
        }

        // 3. Prevent duplicate aesthetic purchase
        if ($item['item_type'] === 'aesthetic') {
            $stmtCheck = $conn->prepare("SELECT quantity FROM user_inventory WHERE user_id = ? AND item_id = ?");
            $stmtCheck->execute([$userId, $itemId]);
            if ($stmtCheck->fetchColumn() > 0) {
                throw new Exception("Possiedi già questo oggetto estetico!");
            }
        }

        // 3. Deduct Credits
        $stmt = $conn->prepare("UPDATE giocatori SET crediti = crediti - ? WHERE id = ?");
        $stmt->execute([$item['cost'], $userId]);

        // 4. Add to Inventory
        $sql = "INSERT INTO user_inventory (user_id, item_id, quantity) 
                VALUES (?, ?, 1) 
                ON DUPLICATE KEY UPDATE quantity = quantity + 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$userId, $itemId]);

        $conn->commit();
        echo json_encode(['success' => true, 'message' => "Hai comprato: {$item['name']}", 'new_credits' => $credits - $item['cost']]);
        exit;
    }

    // --- USE ITEM ---
    if ($action === 'use') {
        $itemId = (int)($_POST['item_id'] ?? 0);

        $conn->beginTransaction();

        // 1. Verify Inventory
        $sql = "SELECT ui.quantity, si.key_name, si.name 
                FROM user_inventory ui 
                JOIN shop_items si ON ui.item_id = si.id 
                WHERE ui.user_id = ? AND ui.item_id = ? FOR UPDATE";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$userId, $itemId]);
        $owned = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$owned || $owned['quantity'] < 1) {
            throw new Exception("Non possiedi questo oggetto");
        }

        // 2. Verify Live Match Status (Must be seated?)
        $stmt = $conn->query("SELECT * FROM live_match WHERE id = 1 FOR UPDATE");
        $match = $stmt->fetch(PDO::FETCH_ASSOC);

        $isPlayer = false;
        $myTeam = 0; // 1 or 2
        
        if ($match['s1_portiere'] == $userId || $match['s1_attaccante'] == $userId) {
            $isPlayer = true;
            $myTeam = 1;
        } elseif ($match['s2_portiere'] == $userId || $match['s2_attaccante'] == $userId) {
            $isPlayer = true;
            $myTeam = 2;
        }

        if (!$isPlayer) {
            throw new Exception("Devi essere seduto al tavolo per usare un bonus!");
        }

        // 3. APPLY EFFECT
        $key = $owned['key_name'];

        if ($key === 'switch') {
            // Logic: Swap ROLES of the OPPONENT team
            $oppTeam = ($myTeam == 1) ? 2 : 1;
            
            $pCol = "s{$oppTeam}_portiere";
            $aCol = "s{$oppTeam}_attaccante";
            
            $gk = $match[$pCol];
            $atk = $match[$aCol];

            if (!$gk && !$atk) {
                 throw new Exception("Nessun avversario da scambiare!");
            }

            // Swap in DB
            $sqlSwap = "UPDATE live_match SET $pCol = ?, $aCol = ? WHERE id = 1";
            $stmtSwap = $conn->prepare($sqlSwap);
            $stmtSwap->execute([$atk, $gk]); // Swap values
        } 
        
        // All items (including switch) get logged as "used" or "active"
        // Palla matta / X2 need to be tracked for end match
        // Switch is instant, but maybe we log it anyway? 
        // Plan says: "X2/Palla Matta Effect: Inserts into active_bonuses"
        
        
        if ($key === 'x2_elo' || $key === 'palla_matta') {
            // Check if player already has an active bonus for this match
            $checkStmt = $conn->prepare("SELECT COUNT(*) FROM live_match_bonuses WHERE match_id = 1 AND user_id = ? AND status = 'active'");
            $checkStmt->execute([$userId]);
            $activeCount = $checkStmt->fetchColumn();
            
            if ($activeCount > 0) {
                throw new Exception("Puoi usare solo un bonus per partita!");
            }
            
            $sqlLog = "INSERT INTO live_match_bonuses (match_id, user_id, item_key, status) VALUES (1, ?, ?, 'active')";
            $conn->prepare($sqlLog)->execute([$userId, $key]);
        }

        // 4. Consume Item
        $stmt = $conn->prepare("UPDATE user_inventory SET quantity = quantity - 1 WHERE user_id = ? AND item_id = ?");
        $stmt->execute([$userId, $itemId]);

        $conn->commit();
        echo json_encode(['success' => true, 'message' => "Bonus attivato: {$owned['name']}!", 'key' => $key]);
        exit;
    }

    // --- EQUIP AESTHETIC ITEM ---
    if ($action === 'equip') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        
        // 1. Verify Ownership & Type
        $stmt = $conn->prepare("SELECT si.key_name, si.name, si.item_type 
                               FROM user_inventory ui 
                               JOIN shop_items si ON ui.item_id = si.id 
                               WHERE ui.user_id = ? AND ui.item_id = ?");
        $stmt->execute([$userId, $itemId]);
        $item = $stmt->fetch();

        if (!$item || $item['item_type'] !== 'aesthetic') {
            throw new Exception("Oggetto non posseduto o non equipaggiabile");
        }

        $key = $item['key_name'];
        $column = '';
        $value = '';

        if (strpos($key, 'aura_') === 0) {
            $column = 'active_aura';
            $value = str_replace('aura_', '', $key);
        } elseif (strpos($key, 'title_') === 0) {
            $column = 'active_title';
            // Extract title from name: "Titolo: Il Bomber" -> "Il Bomber"
            $value = str_replace('Titolo: ', '', $item['name']);
        } elseif (strpos($key, 'color_') === 0) {
            $column = 'active_name_color';
            $value = str_replace('color_', '', $key);
            $_SESSION['user_name_color'] = $value;
        } elseif (strpos($key, 'style_') === 0) {
            $column = 'active_name_style';
            $value = str_replace('style_', '', $key);
            $_SESSION['user_name_style'] = $value;
        }

        if ($column) {
            $stmt = $conn->prepare("UPDATE giocatori SET $column = ? WHERE id = ?");
            $stmt->execute([$value, $userId]);
            echo json_encode(['success' => true, 'message' => "Equipaggiato: {$item['name']}"]);
        } else {
            throw new Exception("Tipo di oggetto estetico sconosciuto");
        }
        exit;
    }

    // --- UNEQUIP AESTHETIC ITEM ---
    if ($action === 'unequip') {
        $type = $_POST['type'] ?? ''; // 'aura', 'title', 'color'
        $column = '';
        
        if ($type === 'aura') { $column = 'active_aura'; unset($_SESSION['user_aura']); }
        elseif ($type === 'title') { $column = 'active_title'; unset($_SESSION['user_title']); }
        elseif ($type === 'color') { $column = 'active_name_color'; unset($_SESSION['user_name_color']); }
        elseif ($type === 'style') { $column = 'active_name_style'; unset($_SESSION['user_name_style']); }

        if ($column) {
            $stmt = $conn->prepare("UPDATE giocatori SET $column = NULL WHERE id = ?");
            $stmt->execute([$userId]);
            echo json_encode(['success' => true, 'message' => "Rimosso: $type"]);
        } else {
            throw new Exception("Tipo non valido");
        }
        exit;
    }


    // --- ACTIVE BONUSES ---
    if ($action === 'active_bonuses') {
        $stmt = $conn->query("SELECT item_key FROM live_match_bonuses WHERE match_id = 1 AND status = 'active'");
        $bonuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode(['success' => true, 'active_bonuses' => $bonuses]);
        exit;
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

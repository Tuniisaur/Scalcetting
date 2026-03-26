<?php
require_once 'session_config.php';
header('Content-Type: application/json; charset=utf-8');

require_once 'database.php';
require_once 'season_pass_engine.php';
require_once 'objectives_engine.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autenticato']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$userId = $_SESSION['user_id'];

$action = $_GET['action'] ?? 'get_progress';

switch ($action) {
    case 'get_progress':
        handleGetProgress($conn, $userId);
        break;
    case 'claim_reward':
        handleClaimReward($conn, $userId);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Azione non valida']);
}

function handleGetProgress($conn, $userId) {
    try {
        // 1. Fetch player progress
        $stmt = $conn->prepare("SELECT xp, level FROM giocatori WHERE id = ?");
        $stmt->execute([$userId]);
        $player = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$player) {
            echo json_encode(['success' => false, 'error' => 'Giocatore non trovato']);
            return;
        }

        // 2. Fetch all rewards
        $stmtRewards = $conn->prepare("SELECT * FROM season_pass_rewards ORDER BY level ASC");
        $stmtRewards->execute();
        $rewards = $stmtRewards->fetchAll(PDO::FETCH_ASSOC);

        // 3. XP Info — curva graduale: xpPerLevel = 500 + (level-1)*200
        require_once 'season_pass_engine.php'; // assicura le funzioni disponibili
        $currentLevel = (int)$player['level'];
        $totalXP = (int)$player['xp'];
        $xpStart = xpAtLevelStart($currentLevel);
        $xpInCurrentLevel = $totalXP - $xpStart;
        $xpToNextLevel = xpRequiredForLevel($currentLevel);

        // 3.5 Fetch Claimed Rewards
        $claimedLevels = [];
        try {
            $stmtClaimed = $conn->prepare("SELECT level FROM user_season_claimed WHERE user_id = ?");
            $stmtClaimed->execute([$userId]);
            $claimedLevels = $stmtClaimed->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("Missing user_season_claimed table: " . $e->getMessage());
        }

        // 4. Fetch Objectives
        try {
            checkDailyReset($conn, $userId); // Ensure fresh daily missions
        } catch (Exception $e) {
            error_log("checkDailyReset failed: " . $e->getMessage());
        }

        $stmtObjectives = $conn->prepare("SELECT o.*, 
                                          COALESCE(uo.current_value, 0) as current_value, 
                                          COALESCE(uo.completed, 0) as completed 
                                          FROM objectives o 
                                          LEFT JOIN user_objectives uo ON o.id = uo.objective_id AND uo.user_id = ? 
                                          ORDER BY o.type ASC, o.target_value ASC");
        $stmtObjectives->execute([$userId]);
        $objectives = $stmtObjectives->fetchAll(PDO::FETCH_ASSOC);

        $response = [
            'success' => true,
            'level' => $currentLevel,
            'total_xp' => $totalXP,
            'xp_current' => $xpInCurrentLevel,
            'xp_next' => $xpPerLevel,
            'rewards' => $rewards,
            'claimed_levels' => $claimedLevels,
            'objectives' => $objectives
        ];
        
        // DEBUG LOGGING
        file_put_contents('debug_api.log', date('Y-m-d H:i:s') . " - Response: " . json_encode($response) . "\n", FILE_APPEND);
        
        echo json_encode($response);
    } catch (Exception $e) {
        error_log("handleGetProgress error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function handleClaimReward($conn, $userId) {
    // Check both GET and POST for level
    $level = (int)($_GET['level'] ?? 0);
    if ($level <= 0) {
        $data = json_decode(file_get_contents('php://input'), true);
        $level = (int)($data['level'] ?? 0);
    }
    
    if ($level <= 0) {
        echo json_encode(['success' => false, 'error' => 'Livello non specificato']);
        return;
    }
    
    $result = claimReward($conn, $userId, $level);
    echo json_encode($result);
}

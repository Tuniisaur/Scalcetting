<?php
require_once 'session_config.php';
header('Content-Type: application/json; charset=utf-8');
require_once 'database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non loggato']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Fetch the last 5 matches for the user
    $sql = "SELECT id, squadra1_portiere, squadra1_attaccante, squadra2_portiere, squadra2_attaccante, 
                   vincitore, data, elo_delta_s1p, elo_delta_s1a, elo_delta_s2p, elo_delta_s2a
            FROM partite 
            WHERE squadra1_portiere = ? OR squadra1_attaccante = ? 
               OR squadra2_portiere = ? OR squadra2_attaccante = ?
            ORDER BY data DESC LIMIT 5";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId, $userId, $userId, $userId]);
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalEloChange = 0;
    $historySummary = [];

    foreach ($matches as $match) {
        $isTeam1 = ($match['squadra1_portiere'] == $userId || $match['squadra1_attaccante'] == $userId);
        $wonMatch = ($isTeam1 && $match['vincitore'] == 1) || (!$isTeam1 && $match['vincitore'] == 2);
        
        $eloDelta = 0;
        if ($match['squadra1_portiere'] == $userId) $eloDelta = $match['elo_delta_s1p'];
        elseif ($match['squadra1_attaccante'] == $userId) $eloDelta = $match['elo_delta_s1a'];
        elseif ($match['squadra2_portiere'] == $userId) $eloDelta = $match['elo_delta_s2p'];
        elseif ($match['squadra2_attaccante'] == $userId) $eloDelta = $match['elo_delta_s2a'];
        
        $totalEloChange += $eloDelta;
        
        $historySummary[] = [
            'id' => $match['id'],
            'won' => $wonMatch,
            'delta' => $eloDelta,
            'date' => $match['data']
        ];
    }

    echo json_encode([
        'success' => true,
        'matches' => $historySummary,
        'total_elo_change' => $totalEloChange
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>

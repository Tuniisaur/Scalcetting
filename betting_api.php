<?php
require_once 'session_config.php';
header('Content-Type: application/json; charset=utf-8');
require_once 'database.php';
require_once 'objectives_engine.php';

// Check Auth
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non loggato']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $action = $_GET['action'] ?? '';

if ($action === 'get_history') {
    // Fetch betting history for current user
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Non autorizzato']);
        exit;
    }
    
    $stmt = $conn->prepare("SELECT * FROM scommesse WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$_SESSION['user_id']]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'history' => $history]);
    exit;
}

    // --- GET ODDS ---
    if ($action === 'odds') {
        // Fetch current live match players
        $stmt = $conn->query("SELECT *, TIMESTAMPDIFF(SECOND, data_inizio_match, NOW()) as db_elapsed FROM live_match WHERE id = 1");
        $match = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$match) {
            echo json_encode(['success' => false, 'error' => 'Nessuna partita attiva trovata']);
            exit;
        }

        $s1p = (int)$match['s1_portiere'];
        $s1a = (int)$match['s1_attaccante'];
        $s2p = (int)$match['s2_portiere'];
        $s2a = (int)$match['s2_attaccante'];

        if (!$s1p || !$s1a || !$s2p || !$s2a) {
            echo json_encode(['success' => false, 'status' => 'waiting', 'message' => 'In attesa dei giocatori']);
            exit;
        }

        // Check Timer
        $timelimit = 60; // seconds
        $remaining = 0;
        $timerStatus = 'closed';
        
        if (!empty($match['data_inizio_match'])) {
            $elapsed = (int)$match['db_elapsed'];
            if ($elapsed < 0) $elapsed = 0;
            $remaining = max(0, $timelimit - $elapsed);
            if ($remaining > 0) $timerStatus = 'open';
        } else {
            // data_inizio_match is NULL but table is full: race condition or DB update lag.
            // Treat as freshly opened with full time available.
            $remaining = $timelimit;
            $timerStatus = 'open';
        }

        // Calculate Team Elos
        $team1Base = getTeamElo($conn, $s1p, $s1a);
        $team2Base = getTeamElo($conn, $s2p, $s2a);
        
        // Apply Form (Weighted 10 matches + Streak)
        $form1 = (getPlayerForm($conn, $s1p) + getPlayerForm($conn, $s1a)) / 2;
        $form2 = (getPlayerForm($conn, $s2p) + getPlayerForm($conn, $s2a)) / 2;
        
        // Apply Team Synergy
        $syn1 = getTeamSynergy($conn, $s1p, $s1a);
        $syn2 = getTeamSynergy($conn, $s2p, $s2a);
        
        // Apply Daily Fatigue
        $fatigue1 = (getDailyFatigue($conn, $s1p) + getDailyFatigue($conn, $s1a)) / 2;
        $fatigue2 = (getDailyFatigue($conn, $s2p) + getDailyFatigue($conn, $s2a)) / 2;
        
        // Apply Matchup Advantage (Attacker vs Goalie)
        $matchupT1 = getMatchupAdvantage($conn, $s1a, $s2p); // S1 Att_vs_S2 Gk
        $matchupT2 = getMatchupAdvantage($conn, $s2a, $s1p); // S2 Att_vs_S1 Gk
        
        $team1Elo = $team1Base + $form1 + $syn1 - $fatigue1 + $matchupT1 - $matchupT2;
        $team2Elo = $team2Base + $form2 + $syn2 - $fatigue2 + $matchupT2 - $matchupT1;

        $divisor = 400; // V5 Gravitational Divisor (Aligned with Elo Standard)

        // Calculate Win Probabilities
        $prob1 = 1 / (1 + pow(10, ($team2Elo - $team1Elo) / $divisor));
        $prob2 = 1 - $prob1;

        // Calculate Odds (Margin included for the house :P or just raw fair odds)
        // Let's add a small margin to make it realistic, e.g., 5%
        $margin = 1.05; 
        $odds1 = round(1 / $prob1 * 0.95, 2); // Taking a cut? Or giving fair odds? Let's give fair odds for now.
        $odds2 = round(1 / $prob2 * 0.95, 2);

        // Cap odds to reasonable limits
        $odds1 = max(1.05, min(10.00, $odds1));
        $odds2 = max(1.05, min(10.00, $odds2));

        // Deuce Odds (Experimental based on balance)
        // Closer match = Higher chance of deuce
        $eloDiff = abs($team1Elo - $team2Elo);
        // Base deuce prob: maybe 30% if equal? dropping as diff increases
        $probDeuce = 0.30 * exp(-$eloDiff / $divisor); 
        
        $oddsDeuceYes = max(1.50, round(1 / $probDeuce, 2));
        $oddsDeuceNo = max(1.10, round(1 / (1 - $probDeuce), 2));

        // Get user credits
        $stmtUser = $conn->prepare("SELECT crediti FROM giocatori WHERE id = ?");
        $stmtUser->execute([$userId]);
        $credits = $stmtUser->fetchColumn();

        // Check if user already bet on this match (status pending)
        // Since we don't have match_id for live matches, we check if they have any pending bets created recently (e.g. last 1 hour)
        // OR we just allow multiple bets. Let's allow multiple bets for now.
        
        echo json_encode([
            'success' => true,
            'status' => 'active',
            'team1_elo' => $team1Elo,
            'team2_elo' => $team2Elo,
            'odds' => [
                'team1' => $odds1,
                'team2' => $odds2,
                'deuce_yes' => $oddsDeuceYes,
                'deuce_no' => $oddsDeuceNo
            ],
            'breakdown' => [
                'team1' => [
                    'base_elo' => round($team1Base, 1),
                    'form' => round($form1, 1),
                    'synergy' => round($syn1, 1),
                    'fatigue' => round($fatigue1, 1),
                    'matchup' => round($matchupT1 - $matchupT2, 1) // Net matchup advantage for UI
                ],
                'team2' => [
                    'base_elo' => round($team2Base, 1),
                    'form' => round($form2, 1),
                    'synergy' => round($syn2, 1),
                    'fatigue' => round($fatigue2, 1),
                    'matchup' => round($matchupT2 - $matchupT1, 1) // Net matchup advantage for UI
                ],
                'divisor' => $divisor // Expose gravitational divisor
            ],
            'user_credits' => $credits,
            'timer' => [
                'status' => $timerStatus,
                'remaining_seconds' => $remaining,
                'total_seconds' => $timelimit
            ]
        ]);
    }

    // --- PLACE BET ---
    elseif ($action === 'place_bet') {
        $input = json_decode(file_get_contents('php://input'), true);
        $type = $input['type'] ?? ''; // 'winner', 'deuce'
        $value = $input['value'] ?? ''; // '1', '2' or 'yes', 'no'
        $amount = (int)($input['amount'] ?? 0);
        $currentOdds = (float)($input['odds'] ?? 0); // User sends odds they saw, for verification? Or we recalc. Recalc safer.

        if ($amount <= 0) { echo json_encode(['success' => false, 'error' => 'Importo non valido']); exit; }

        // Check Timer Logic
        $stmtM = $conn->query("SELECT data_inizio_match, TIMESTAMPDIFF(SECOND, data_inizio_match, NOW()) as db_elapsed FROM live_match WHERE id = 1");
        $matchData = $stmtM->fetch(PDO::FETCH_ASSOC);
        $startTime = $matchData['data_inizio_match'] ?? null;
        
        if ($startTime) {
            $elapsed = (int)$matchData['db_elapsed'];
            if ($elapsed > 60) {
                 echo json_encode(['success' => false, 'error' => 'Tempo scommesse scaduto!']);
                 exit;
            }
        } else {
             // If no start time, betting shouldn't be valid yet
             echo json_encode(['success' => false, 'error' => 'Scommesse non ancora aperte']);
             exit;
        }

        $conn->beginTransaction();

        // Check Balance
        $stmt = $conn->prepare("SELECT crediti FROM giocatori WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $balance = $stmt->fetchColumn();

        if ($balance < $amount) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'error' => 'Strisciate insufficienti']);
            exit;
        }

        // Recalculate Odds to ensure they haven't changed drastically (optional, skipping for simplicity)
        // But we DO need to record the odds at time of betting.
        // Re-fetching logic from above...
        $stmtM = $conn->query("SELECT * FROM live_match WHERE id = 1");
        $match = $stmtM->fetch(PDO::FETCH_ASSOC);
        if (!$match || !$match['s1_portiere']) { $conn->rollBack(); echo json_encode(['success'=>false, 'error'=>'Partita non iniziata']); exit; }
        
        $t1Base = getTeamElo($conn, $match['s1_portiere'], $match['s1_attaccante']);
        $t2Base = getTeamElo($conn, $match['s2_portiere'], $match['s2_attaccante']);
        
        // Apply Form (Weighted 10 matches + Streak)
        $f1 = (getPlayerForm($conn, $match['s1_portiere']) + getPlayerForm($conn, $match['s1_attaccante'])) / 2;
        $f2 = (getPlayerForm($conn, $match['s2_portiere']) + getPlayerForm($conn, $match['s2_attaccante'])) / 2;
        
        // Apply Team Synergy
        $syn1 = getTeamSynergy($conn, $match['s1_portiere'], $match['s1_attaccante']);
        $syn2 = getTeamSynergy($conn, $match['s2_portiere'], $match['s2_attaccante']);
        
        // Apply Daily Fatigue
        $fatigue1 = (getDailyFatigue($conn, $match['s1_portiere']) + getDailyFatigue($conn, $match['s1_attaccante'])) / 2;
        $fatigue2 = (getDailyFatigue($conn, $match['s2_portiere']) + getDailyFatigue($conn, $match['s2_attaccante'])) / 2;
        
        // Apply Matchup Advantage (Attacker vs Goalie)
        $matchupT1 = getMatchupAdvantage($conn, $match['s1_attaccante'], $match['s2_portiere']);
        $matchupT2 = getMatchupAdvantage($conn, $match['s2_attaccante'], $match['s1_portiere']);
        
        $t1Elo = $t1Base + $f1 + $syn1 - $fatigue1 + $matchupT1 - $matchupT2;
        $t2Elo = $t2Base + $f2 + $syn2 - $fatigue2 + $matchupT2 - $matchupT1;
        
        $finalQuota = 0;
        $divisor = 800;
        
        // ... (Logic duplicated from odds calc, should extract function) ...
        $prob1 = 1 / (1 + pow(10, ($t2Elo - $t1Elo) / $divisor));
        $probDeuce = 0.30 * exp(-abs($t1Elo - $t2Elo) / $divisor);

        if ($type === 'winner') {
            if ($value == '1') $finalQuota = max(1.05, round(1 / $prob1 * 0.95, 2));
            elseif ($value == '2') $finalQuota = max(1.05, round(1 / (1 - $prob1) * 0.95, 2));
        } elseif ($type === 'deuce') {
            if ($value == 'yes') $finalQuota = max(1.50, round(1 / $probDeuce, 2));
            elseif ($value == 'no') $finalQuota = max(1.10, round(1 / (1 - $probDeuce), 2));
        }

        if ($finalQuota <= 0) { $conn->rollBack(); echo json_encode(['success'=>false, 'error'=>'Scommessa non valida']); exit; }

        // Deduct Credits
        $stmtDed = $conn->prepare("UPDATE giocatori SET crediti = crediti - ? WHERE id = ?");
        $stmtDed->execute([$amount, $userId]);

        // Record Bet
        $stmtBet = $conn->prepare("INSERT INTO scommesse (user_id, bet_type, bet_value, amount, quota) VALUES (?, ?, ?, ?, ?)");
        $stmtBet->execute([$userId, $type, $value, $amount, $finalQuota]);

        checkBetObjectives($conn, $userId, 'place');

        $conn->commit();
        echo json_encode(['success' => true, 'new_balance' => $balance - $amount, 'message' => "Scommessa piazzata!"]);
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function getTeamElo($conn, $pId, $aId) {
    // Helper to get average ELO of a team (Portiere + Attaccante)
    // Needs to handle GHOST_ID
    return (getPlayerElo($conn, $pId, 'portiere') + getPlayerElo($conn, $aId, 'attaccante')) / 2;
}

function getPlayerElo($conn, $id, $role) {
    if ($id == 9999) return 1500; // Ghost default
    $stmt = $conn->prepare("SELECT elo_$role FROM giocatori WHERE id = ?");
    $stmt->execute([$id]);
    $elo = $stmt->fetchColumn();
    return $elo ? $elo : 1500;
}

function getPlayerForm($conn, $playerId) {
    if ($playerId == 9999 || $playerId <= 0) return 0;
    
    // Last 10 matches
    $sql = "SELECT vincitore, squadra1_portiere, squadra1_attaccante, squadra2_portiere, squadra2_attaccante 
            FROM partite 
            WHERE squadra1_portiere = ? OR squadra1_attaccante = ? OR squadra2_portiere = ? OR squadra2_attaccante = ? 
            ORDER BY data DESC LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$playerId, $playerId, $playerId, $playerId]);
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($matches) == 0) return 0;

    $form = 0;
    $streak = 0;
    $isStreakActive = true;
    $streakType = null; // 1 for win, 0 for loss
    
    foreach ($matches as $index => $m) {
        $isTeam1 = ($m['squadra1_portiere'] == $playerId || $m['squadra1_attaccante'] == $playerId);
        $won = ($isTeam1 && $m['vincitore'] == 1) || (!$isTeam1 && $m['vincitore'] == 2);
        
        // Weight: recent matches matter more. 1.0 for most recent, down to 0.1 for 10th.
        $weight = 1.0 - ($index * 0.1); 
        
        if ($won) {
            $form += (5 * $weight);
            if ($isStreakActive) {
                if ($streakType === null) $streakType = 1;
                if ($streakType === 1) $streak++;
                else $isStreakActive = false;
            }
        } else {
            $form -= (5 * $weight);
            if ($isStreakActive) {
                if ($streakType === null) $streakType = 0;
                if ($streakType === 0) $streak++;
                else $isStreakActive = false;
            }
        }
    }
    
    // Apply streak bonus/malus
    $streakBonus = 0;
    if ($streak >= 3) {
        if ($streak == 3) $streakBonus = 10;
        elseif ($streak == 4) $streakBonus = 15;
        elseif ($streak >= 5) $streakBonus = 25;
        
        if ($streakType === 1) $form += $streakBonus;
        else $form -= $streakBonus;
    }
    
    // Cap form between -40 and 40
    return max(-40, min(40, $form));
}

function getMatchupAdvantage($conn, $attId, $portId) {
    if ($attId == 9999 || $portId == 9999 || $attId <= 0 || $portId <= 0) return 0;
    
    $sql = "SELECT vincitore, squadra1_attaccante 
            FROM partite 
            WHERE (squadra1_attaccante = ? AND squadra2_portiere = ?) 
               OR (squadra2_attaccante = ? AND squadra1_portiere = ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$attId, $portId, $attId, $portId]);
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalMatches = count($matches);
    if ($totalMatches === 0) return 0;
    
    $wins = 0;
    foreach ($matches as $m) {
        $isTeam1 = ($m['squadra1_attaccante'] == $attId);
        $won = ($isTeam1 && $m['vincitore'] == 1) || (!$isTeam1 && $m['vincitore'] == 2);
        if ($won) $wins++;
    }
    
    $winRate = $wins / $totalMatches;
    // Max advantage is +/- 30 elo points, scaling down if less than 5 matches played
    $weight = min($totalMatches / 5, 1.0);
    $advantage = ($winRate - 0.5) * 60 * $weight;
    
    return $advantage;
}

function getTeamSynergy($conn, $pId, $aId) {
    if ($pId == 9999 || $aId == 9999 || $pId <= 0 || $aId <= 0) return 0;
    
    // Check matches where they played together
    $sql = "SELECT vincitore, squadra1_portiere, squadra1_attaccante 
            FROM partite 
            WHERE (squadra1_portiere = ? AND squadra1_attaccante = ?) 
               OR (squadra2_portiere = ? AND squadra2_attaccante = ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$pId, $aId, $pId, $aId]);
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalMatches = count($matches);
    if ($totalMatches === 0) return 0;
    
    $wins = 0;
    foreach ($matches as $m) {
        $isTeam1 = ($m['squadra1_portiere'] == $pId && $m['squadra1_attaccante'] == $aId);
        $won = ($isTeam1 && $m['vincitore'] == 1) || (!$isTeam1 && $m['vincitore'] == 2);
        if ($won) $wins++;
    }
    
    $winRate = $wins / $totalMatches;
    // Max synergy is +/- 25 elo points, scaling down if less than 5 matches played
    $weight = min($totalMatches / 5, 1.0);
    $synergy = ($winRate - 0.5) * 50 * $weight;
    
    return $synergy;
}

function getDailyFatigue($conn, $playerId) {
    if ($playerId == 9999 || $playerId <= 0) return 0;
    
    // Count matches in last 12 hours
    $sql = "SELECT COUNT(*) FROM partite 
            WHERE (squadra1_portiere = ? OR squadra1_attaccante = ? OR squadra2_portiere = ? OR squadra2_attaccante = ?)
            AND data >= DATE_SUB(NOW(), INTERVAL 12 HOUR)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$playerId, $playerId, $playerId, $playerId]);
    $matchesPlayed = $stmt->fetchColumn();
    
    // Fatigue starts after 4 matches in 12 hours
    if ($matchesPlayed <= 4) return 0;
    
    // Penalty is 3 points per match after 4th
    $penalty = ($matchesPlayed - 4) * 3;
    
    // Cap penalty at 25 points
    return min(25, $penalty);
}
?>

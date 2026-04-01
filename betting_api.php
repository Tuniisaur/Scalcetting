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
        $tableId = (int)($_GET['table'] ?? 1);
        if ($tableId < 1) $tableId = 1;

        // Fetch current live match players
        $stmt = $conn->prepare("SELECT *, TIMESTAMPDIFF(SECOND, data_inizio_match, NOW()) as db_elapsed FROM live_match WHERE id = 1");
        $stmt->execute();
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
        
        if ($tableId == 1) {
            $remaining = 3600; // Arbitrary large number for Table 1
            $timerStatus = 'open';
        } elseif (!empty($match['data_inizio_match'])) {
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

        $isTable1 = ($tableId == 1);
        $res = calculateMatchOdds($conn, $match, $isTable1);

        if (!$res['success']) {
            echo json_encode($res);
            exit;
        }

        // Get user credits
        $stmtUser = $conn->prepare("SELECT crediti FROM giocatori WHERE id = ?");
        $stmtUser->execute([$userId]);
        $credits = $stmtUser->fetchColumn();

        echo json_encode([
            'success' => true,
            'status' => 'active',
            'team1_elo' => $res['team1_elo'],
            'team2_elo' => $res['team2_elo'],
            'odds' => $res['odds'],
            'breakdown' => $res['breakdown'],
            'user_credits' => $credits,
            'suspended' => $res['suspended'] ?? [],
            'timer' => [
                'status' => $res['timer_status'],
                'remaining_seconds' => $res['remaining_seconds'],
                'total_seconds' => 60
            ]
        ]);
    }

    // --- GET ACTIVE BETS ---
    elseif ($action === 'active_bets') {
        $stmt = $conn->prepare("SELECT * FROM scommesse WHERE user_id = ? AND status = 'pending' ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        $activeBets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'bets' => $activeBets]);
    }

    // --- PLACE BET ---
    elseif ($action === 'place_bet') {
        $input = json_decode(file_get_contents('php://input'), true);
        $type = $input['type'] ?? ''; // 'winner', 'deuce'
        $value = $input['value'] ?? ''; // '1', '2' or 'yes', 'no'
        $amount = (int)($input['amount'] ?? 0);
        $currentOdds = (float)($input['odds'] ?? 0); // User sends odds they saw, for verification? Or we recalc. Recalc safer.

        $tableId = (int)($input['table'] ?? ($_GET['table'] ?? 1));
        if ($tableId < 1) $tableId = 1;

        if ($amount <= 0) { echo json_encode(['success' => false, 'error' => 'Importo non valido']); exit; }

        // Check Timer Logic
        $stmtM = $conn->prepare("SELECT table_id, data_inizio_match, TIMESTAMPDIFF(SECOND, data_inizio_match, NOW()) as db_elapsed FROM live_match WHERE id = 1");
        $stmtM->execute();
        $matchData = $stmtM->fetch(PDO::FETCH_ASSOC);
        $startTime = $matchData['data_inizio_match'] ?? null;
        $activeTable = (int)($matchData['table_id'] ?? 1);
        
        if ($activeTable !== 1) {
            if ($startTime) {
                $elapsed = (int)$matchData['db_elapsed'];
                if ($elapsed > 60) {
                     echo json_encode(['success' => false, 'error' => 'Tempo scommesse scaduto!']);
                     exit;
                }
            } else {
                 // If no start time, betting shouldn't be valid yet (except Table 1 if we want)
                 echo json_encode(['success' => false, 'error' => 'Scommesse non ancora aperte']);
                 exit;
            }
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
        // Verify match players
        $stmt = $conn->prepare("SELECT * FROM live_match WHERE id = 1");
        $stmt->execute();
        $match = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$match || !$match['s1_portiere']) { $conn->rollBack(); echo json_encode(['success'=>false, 'error'=>'Partita non iniziata']); exit; }
        
        $isT1 = ($activeTable == 1);
        $res = calculateMatchOdds($conn, $match, $isT1);
        
        if (!$res['success']) {
            $conn->rollBack();
            file_put_contents(__DIR__ . '/debug_bet.txt', date('[Y-m-d H:i:s] ') . "Odds Calculation Failed: " . json_encode($res) . "\n", FILE_APPEND);
            echo json_encode($res);
            exit;
        }

        $finalQuota = 0;
        $odds = $res['odds'];
        
        // Debug Log
        $debugData = [
            'type' => $type,
            'value' => $value,
            'amount' => $amount,
            'match_id' => 1,
            'activeTable' => $activeTable,
            's1' => $match['s1_score'] ?? 'null',
            's2' => $match['s2_score'] ?? 'null',
            'suspended' => $res['suspended'] ?? []
        ];
        file_put_contents(__DIR__ . '/debug_bet.txt', date('[Y-m-d H:i:s] ') . "Attempting Bet: " . json_encode($debugData) . "\n", FILE_APPEND);

        $finalQuota = 0;
        $odds = $res['odds'];

        $key = '';
        if ($type === 'winner') {
            $key = ($value == '1' ? 'team1' : 'team2');
        } elseif ($type === 'deuce') {
            $key = ($value == 'yes' ? 'deuce_yes' : 'deuce_no');
        } elseif ($type === 'handicap') {
            // Frontend: s1_-0.5 -> h1_m05, s2_+1.5 -> h2_p15
            $t = (strpos($value, 's1') !== false) ? 'h1' : 'h2';
            $op = (strpos($value, '-') !== false) ? 'm' : 'p';
            $num = str_replace(['s1', 's2', '_', '-', '+', '.'], '', $value);
            $key = "{$t}_{$op}{$num}";
        } elseif ($type === 'over_under') {
            // Frontend: 8.5_over -> ou85_o
            $parts = explode('_', $value);
            if (count($parts) >= 2) {
                $m = str_replace('.', '', $parts[0]);
                $o = ($parts[1] === 'over' ? 'o' : 'u');
                $key = "ou{$m}_{$o}";
            }
        } elseif ($type === 'team_over_under') {
            // Frontend: s1_3.5_over -> t1ou35_o
            $parts = explode('_', $value); // s1, 3.5, over
            if (count($parts) >= 3) {
                $t = ($parts[0] === 's1' ? 't1' : 't2');
                $m = str_replace('.', '', $parts[1]);
                $o = ($parts[2] === 'over' ? 'o' : 'u');
                $key = "{$t}ou{$m}_{$o}";
            }
        } elseif ($type === 'btts_threshold') {
            // Frontend: 5_yes -> btts5_y
            $parts = explode('_', $value);
            if (count($parts) >= 2) {
                $key = "btts" . $parts[0] . "_" . ($parts[1] === 'yes' ? 'y' : 'n');
            }
        } elseif ($type === 'deuce_winner') {
            $key = ($value == '1' ? 'dw1' : 'dw2');
        } elseif ($type === 'deuce_parity') {
            // Frontend: 0.5_over -> dp05_o
            $parts = explode('_', $value);
            if (count($parts) >= 2) {
                $m = str_replace('.', '', $parts[0]);
                $o = ($parts[1] === 'over' ? 'o' : 'u');
                $key = "dp{$m}_{$o}";
            }
        } elseif ($type === 'winning_margin') {
            $key = "mE" . $value;
        } elseif ($type === 'winning_diff') {
            $key = "m" . $value;
        } elseif ($type === 'exact_score') {
            $key = "es_" . $value;
        } elseif ($type === 'combo') {
            $key = "cmb_" . $value;
        } elseif (strpos($type, '_yn') !== false) {
            $base = str_replace('_yn', '', $type);
            $key = $base . "_" . ($value === 'yes' ? 'y' : 'n');
        }

        if ($key && isset($odds[$key])) {
            $finalQuota = $odds[$key];
        }

        file_put_contents(__DIR__ . '/debug_bet.txt', date('[Y-m-d H:i:s] ') . "Final Quota: $finalQuota\n", FILE_APPEND);
        if ($finalQuota <= 1.00) { $conn->rollBack(); echo json_encode(['success'=>false, 'error'=>'Scommessa non valida (quota bloccata)']); exit; }

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

function calculateMatchOdds($conn, $match, $isTable1) {
    if (!$match || !$match['s1_portiere'] || !$match['s1_attaccante'] || !$match['s2_portiere'] || !$match['s2_attaccante']) {
        return ['success' => false, 'status' => 'waiting', 'message' => 'In attesa dei giocatori'];
    }

    $s1p = (int)$match['s1_portiere'];
    $s1a = (int)$match['s1_attaccante'];
    $s2p = (int)$match['s2_portiere'];
    $s2a = (int)$match['s2_attaccante'];

    // --- 1. PRE-MATCH STRENGTH (ELO Components) ---
    $team1Base = getTeamElo($conn, $s1p, $s1a);
    $team2Base = getTeamElo($conn, $s2p, $s2a);
    $form1 = (getPlayerForm($conn, $s1p) + getPlayerForm($conn, $s1a)) / 2;
    $form2 = (getPlayerForm($conn, $s2p) + getPlayerForm($conn, $s2a)) / 2;
    $syn1 = getTeamSynergy($conn, $s1p, $s1a);
    $syn2 = getTeamSynergy($conn, $s2p, $s2a);
    $fatigue1 = (getDailyFatigue($conn, $s1p) + getDailyFatigue($conn, $s1a)) / 2;
    $fatigue2 = (getDailyFatigue($conn, $s2p) + getDailyFatigue($conn, $s2a)) / 2;
    $matchupT1 = getMatchupAdvantage($conn, $s1a, $s2p); 
    $matchupT2 = getMatchupAdvantage($conn, $s2a, $s1p); 

    $team1Elo = $team1Base + $form1 + $syn1 - $fatigue1 + $matchupT1 - $matchupT2;
    $team2Elo = $team2Base + $form2 + $syn2 - $fatigue2 + $matchupT2 - $matchupT1;

    $divisor = 400; 
    $probElo1 = 1 / (1 + pow(10, ($team2Elo - $team1Elo) / $divisor));

    // --- 2. LIVE SCORE PROGRESSION (Table 1 Only) ---
    // --- 3. POINT-BASED PROBABILITY (Negative Binomial) ---
    // At any score (s1, s2), Team 1 wins if they reach 8 points before Team 2 does.
    // p is the probability Team 1 wins a SINGLE point (derived from ELO)
    $p = $probElo1;
    $s1 = ($isTable1) ? (int)$match['score_s1'] : 0;
    $s2 = ($isTable1) ? (int)$match['score_s2'] : 0;
    $target = 8;
    
    // Function to calculate match win probability from current score
    $calcWinProb = function($p1, $s1_start, $s2_start, $goal_target) {
        if ($s1_start >= $goal_target) return 1.0;
        if ($s2_start >= $goal_target) return 0.0;
        
        $prob = 0;
        $pointsNeeded1 = $goal_target - $s1_start;
        $pointsNeeded2 = $goal_target - $s2_start;
        
        // Sum of negative binomial probabilities: reach target before opponent does
        for ($k = 0; $k < $pointsNeeded2; $k++) {
            $prob += nCr($pointsNeeded1 - 1 + $k, $k) * pow($p1, $pointsNeeded1) * pow(1 - $p1, $k);
        }
        return max(0.01, min(0.99, $prob));
    };

    $finalProb1 = $calcWinProb($p, $s1, $s2, $target);
    $finalProb2 = 1 - $finalProb1;

    // --- 4. ODDS CALCULATION ---
    $margin = 0.95; // 5% house edge
    $odds1 = round(1 / $finalProb1 * $margin, 2);
    $odds2 = round(1 / $finalProb2 * $margin, 2);

    // Caps
    $odds1 = max(1.02, min(20.00, $odds1));
    $odds2 = max(1.02, min(20.00, $odds2));

    // Deuce Odds logic
    // Deuce happens if score reaches 7-7.
    $probDeuce = 0;
    if ($s1 <= 7 && $s2 <= 7) {
        $probDeuce = nCr((7 - $s1) + (7 - $s2), 7 - $s1) * pow($p, 7 - $s1) * pow(1 - $p, 7 - $s2);
    }
    
    // Market Suspension Logic
    $suspended = [];
    if ($s1 >= 8 || $s2 >= 8) {
        $suspended[] = 'winner';
        $suspended[] = 'deuce';
        $suspended[] = 'handicaps';
    } elseif ($s1 >= 7 && $s2 >= 7) {
        $suspended[] = 'deuce';
    }

    $oddsDeuceYes = max(1.10, round(1 / max(0.01, $probDeuce) * $margin, 2));
    $oddsDeuceNo = max(1.02, round(1 / (1 - min(0.99, $probDeuce)) * $margin, 2));

    // Timer logic
    $timelimit = 60;
    $remaining = 0;
    $timerStatus = 'closed';
    if ($isTable1) {
        $remaining = 3600;
        $timerStatus = 'open';
    } elseif (!empty($match['data_inizio_match'])) {
        $elapsed = (int)$match['db_elapsed'];
        $remaining = max(0, $timelimit - $elapsed);
        if ($remaining > 0) $timerStatus = 'open';
    }

    $result = [
        'success' => true,
        'team1_elo' => round($team1Elo, 0),
        'team2_elo' => round($team2Elo, 0),
        'odds' => [
            'team1' => $odds1,
            'team2' => $odds2,
            'deuce_yes' => $oddsDeuceYes,
            'deuce_no' => $oddsDeuceNo
        ],
        'suspended' => $suspended,
        'breakdown' => [
            'team1' => ['base_elo' => round($team1Base, 0), 'form' => round($form1, 0), 'synergy' => round($syn1, 0), 'fatigue' => round($fatigue1, 0), 'matchup' => round($matchupT1 - $matchupT2, 0)],
            'team2' => ['base_elo' => round($team2Base, 0), 'form' => round($form2, 0), 'synergy' => round($syn2, 0), 'fatigue' => round($fatigue2, 0), 'matchup' => round($matchupT2 - $matchupT1, 0)],
            'divisor' => $divisor
        ],
        'timer_status' => $timerStatus,
        'remaining_seconds' => $remaining
    ];

    $hLines = [
        'h1_m05' => calculateHandicapOdds($p, -0.5, $margin, $s1, $s2),
        'h2_p05' => calculateHandicapOdds($p, -0.5, $margin, $s1, $s2, true),
        'h1_m15' => calculateHandicapOdds($p, -1.5, $margin, $s1, $s2),
        'h2_p15' => calculateHandicapOdds($p, -1.5, $margin, $s1, $s2, true),
        'h1_m25' => calculateHandicapOdds($p, -2.5, $margin, $s1, $s2),
        'h2_p25' => calculateHandicapOdds($p, -2.5, $margin, $s1, $s2, true),
        'h1_m35' => calculateHandicapOdds($p, -3.5, $margin, $s1, $s2),
        'h2_p35' => calculateHandicapOdds($p, -3.5, $margin, $s1, $s2, true),
        'h2_m05' => calculateHandicapOdds(1 - $p, -0.5, $margin, $s2, $s1),
        'h1_p05' => calculateHandicapOdds(1 - $p, -0.5, $margin, $s2, $s1, true),
        'h2_m15' => calculateHandicapOdds(1 - $p, -1.5, $margin, $s2, $s1),
        'h1_p15' => calculateHandicapOdds(1 - $p, -1.5, $margin, $s2, $s1, true),
        'h2_m25' => calculateHandicapOdds(1 - $p, -2.5, $margin, $s2, $s1),
        'h1_p25' => calculateHandicapOdds(1 - $p, -2.5, $margin, $s2, $s1, true),
        'h2_m35' => calculateHandicapOdds(1 - $p, -3.5, $margin, $s2, $s1),
        'h1_p35' => calculateHandicapOdds(1 - $p, -3.5, $margin, $s2, $s1, true)
    ];

    foreach($hLines as $key => $hRes) {
        $result['odds'][$key] = $hRes['odds'];
        if ($hRes['prob'] <= 0.001 || $hRes['prob'] >= 0.999) {
            $result['suspended'][] = $key;
        }
    }

    $ouThresholds = [8.5, 9.5, 10.5, 11.5, 12.5, 13.5, 14.5, 15.5, 16.5, 17.5, 18.5];
    foreach($ouThresholds as $t) {
        $m = str_replace('.', '', (string)$t);
        $ouRes = calculateOverUnderOdds($p, $t, $margin, $s1, $s2);
        $result['odds']["ou{$m}_o"] = $ouRes['over'];
        $result['odds']["ou{$m}_u"] = $ouRes['under'];
        if ($ouRes['prob'] <= 0.001 || $ouRes['prob'] >= 0.999) {
            $result['suspended'][] = "ou{$m}_o";
            $result['suspended'][] = "ou{$m}_u";
        }
    }

    // --- SPECIAL DEUCE MARKETS ---
    // Who wins after deuce (1/2) 
    // Prob = p^2 / (p^2 + (1-p)^2)
    $probDeuceWin1 = pow($p, 2) / (pow($p, 2) + pow(1 - $p, 2));
    $result['odds']['dw1'] = round(1 / max(0.01, $probDeuceWin1) * $margin, 2);
    $result['odds']['dw2'] = round(1 / max(0.01, 1 - $probDeuceWin1) * $margin, 2);
    
    // Parity Count beyond 7-7 (O/U 0.5, 1.5)
    $pReach88 = pow($p, 7 - $s1) * pow(1 - $p, 7 - $s2) * nCr((7 - $s1) + (7 - $s2), 7 - $s1) * (2 * $p * (1 - $p));
    if ($s1 >= 7 && $s2 >= 7) $pReach88 = 2 * $p * (1 - $p); // Already at 7-7 or in deuce
    
    $result['odds']['dp05_o'] = round(1 / max(0.01, $pReach88) * $margin, 2);
    $result['odds']['dp05_u'] = round(1 / max(0.01, 1 - $pReach88) * $margin, 2);
    
    $pReach99 = $pReach88 * (2 * $p * (1 - $p));
    $result['odds']['dp15_o'] = round(1 / max(0.01, $pReach99) * $margin, 2);
    $result['odds']['dp15_u'] = round(1 / max(0.01, 1 - $pReach99) * $margin, 2);

    // --- MARGIN & DIFF MARKETS ---
    $pByMargin = array_fill(1, 8, 0); // Margin: 1 to 8
    for ($k = 0; $k <= 7; $k++) {
        // Normal wins
        $p8k = nCr(7 - $s1 + $k - $s2, $k - $s2) * pow($p, 8 - $s1) * pow(1 - $p, $k - $s2);
        $pk8 = nCr(7 - $s2 + $k - $s1, $k - $s1) * pow(1 - $p, 8 - $s2) * pow($p, $k - $s1);
        $pByMargin[8 - $k] += ($p8k + $pk8);
    }
    // Deuce Margin (assuming clear-by-two)
    $pDeuce = nCr((7 - $s1) + (7 - $s2), 7 - $s1) * pow($p, 7 - $s1) * pow(1 - $p, 7 - $s2);
    if ($s1 <= 7 && $s2 <= 7) $pByMargin[2] += $pDeuce; // Replaced 8-7/7-8 with deuce endings (mostly margin 2)
    
    // Exact Margins 1-5
    for ($m = 1; $m <= 5; $m++) {
        $result['odds']["mE{$m}"] = round(1 / max(0.01, $pByMargin[$m]) * $margin, 2);
        // Suspension: if current scarto > m, it's impossible (exactly m)
        if (abs($s1 - $s2) > $m) $result['suspended'][] = "mE{$m}";
    }
    
    // Difference thresholds
    $p1 = $pByMargin[1];
    $p2p = array_sum(array_slice($pByMargin, 2));
    $p3p = array_sum(array_slice($pByMargin, 3));
    
    $result['odds']['m1_yes'] = round(1 / max(0.01, $p1) * $margin, 2);
    $result['odds']['m1_no'] = round(1 / max(0.01, 1 - $p1) * $margin, 2);
    $result['odds']['m2p_yes'] = round(1 / max(0.01, $p2p) * $margin, 2);
    $result['odds']['m2p_no'] = round(1 / max(0.01, 1 - $p2p) * $margin, 2);
    $result['odds']['m3p_yes'] = round(1 / max(0.01, $p3p) * $margin, 2);
    $result['odds']['m3p_no'] = round(1 / max(0.01, 1 - $p3p) * $margin, 2);

    // --- TEAM TOTALS & BTTS THRESHOLDS ---
    $tTotals = [3.5, 4.5, 5.5, 6.5, 7.5];
    foreach($tTotals as $t) {
        $m = str_replace('.', '', (string)$t);
        // Team 1 Total
        $pT1ou = $result['odds']['team1'] > 1 ? 1/($result['odds']['team1']/$margin) : 0; 
        // A better way: pT1ou = P(Win1) + sum_{k=T..7} P(S1=k, S2=8)
        $pT1 = 0; $pT2 = 0;
        for($k=0; $k<=7; $k++) {
            $p8k = nCr(7 - $s1 + $k - $s2, $k - $s2) * pow($p, 8 - $s1) * pow(1 - $p, $k - $s2);
            $pk8 = nCr(7 - $s2 + $k - $s1, $k - $s1) * pow(1 - $p, 8 - $s2) * pow($p, $k - $s1);
            if (8 > $t) $pT1 += $p8k;
            if ($k > $t) $pT1 += $pk8;
            if (8 > $t) $pT2 += $pk8;
            if ($k > $t) $pT2 += $p8k;
        }
        if ($s1 <= 7 && $s2 <= 7) {
            if ($t < 7) {
                $pT1 += $pDeuce;
                $pT2 += $pDeuce;
            } else {
                // For Threshold 7.5: reaching deuce (7-7)
                // Team 1 is Over 7.5 if they win deuce (score >= 9) 
                // or if they reach 8-8 before losing (score >= 8)
                $pWinD1 = pow($p, 2) / (pow($p, 2) + pow(1 - $p, 2));
                $pReach88 = 2 * $p * (1 - $p);
                $pT1 += $pDeuce * ($pWinD1 + (1 - $pWinD1) * $pReach88);
                
                $pWinD2 = 1 - $pWinD1;
                $pT2 += $pDeuce * ($pWinD2 + (1 - $pWinD2) * $pReach88);
            }
        } 
        
        $result['odds']["t1ou{$m}_o"] = round(1 / max(0.01, $pT1) * $margin, 2);
        $result['odds']["t1ou{$m}_u"] = round(1 / max(0.01, 1 - $pT1) * $margin, 2);
        if ($s1 > $t) {
            $result['suspended'][] = "t1ou{$m}_o";
            $result['suspended'][] = "t1ou{$m}_u";
        }
        
        $result['odds']["t2ou{$m}_o"] = round(1 / max(0.01, $pT2) * $margin, 2);
        $result['odds']["t2ou{$m}_u"] = round(1 / max(0.01, 1 - $pT2) * $margin, 2);
        if ($s2 > $t) $result['suspended'][] = "t2ou{$m}_o";
    }

    // BTTS Thresholds (Both score X+)
    foreach([5, 7] as $x) {
        $pBTTS = 0;
        for($k=$x; $k<=7; $k++) {
            $pBTTS += nCr(7 - $s1 + $k - $s2, $k - $s2) * pow($p, 8 - $s1) * pow(1 - $p, $k - $s2); // 8-k
            $pBTTS += nCr(7 - $s2 + $k - $s1, $k - $s1) * pow(1 - $p, 8 - $s2) * pow($p, $k - $s1); // k-8
        }
        if ($s1 <= 7 && $s2 <= 7) { if ($x <= 7) $pBTTS += $pDeuce; }
        
        $result['odds']["btts{$x}_y"] = round(1 / max(0.01, $pBTTS) * $margin, 2);
        $result['odds']["btts{$x}_n"] = round(1 / max(0.01, 1 - $pBTTS) * $margin, 2);
        if (min($s1, $s2) >= $x) $result['suspended'][] = "btts{$x}_y";
    }

    // --- ADVANCED SPECIALS (Sequence Based) ---
    $goalLogStr = $match['goal_log'] ?? '';
    $logArr = ($goalLogStr !== '') ? explode(',', $goalLogStr) : [];
    
    // 1. Cappotto (8-0 or 0-8)
    $probCappotto = 0;
    if ($s1 == 0 && $s2 == 0) {
        $probCappotto = pow($p, 8) + pow(1 - $p, 8);
    } elseif ($s1 > 0 && $s2 == 0) {
        $probCappotto = pow($p, 8 - $s1);
    } elseif ($s1 == 0 && $s2 > 0) {
        $probCappotto = pow(1 - $p, 8 - $s2);
    }
    $result['odds']['cappotto_y'] = round(1 / max(0.001, $probCappotto) * $margin, 2);
    $result['odds']['cappotto_n'] = round(1 / max(0.01, 1 - $probCappotto) * $margin, 2);
    if ($s1 > 0 && $s2 > 0) $result['suspended'][] = 'cappotto_y';

    // 2. No 3 Consecutive Goals
    $hasStreak3 = false;
    if (count($logArr) >= 3) {
        for ($i = 0; $i <= count($logArr) - 3; $i++) {
            if ($logArr[$i] == $logArr[$i+1] && $logArr[$i+1] == $logArr[$i+2]) {
                $hasStreak3 = true; break;
            }
        }
    }
    // Probability of NOT having a streak of 3. Approx model.
    // For p=0.5, P(no streak 3 in 15 goals) is low.
    $probNoStreak = $hasStreak3 ? 0 : pow(0.85, max(1, 15 - count($logArr))); 
    $result['odds']['no_streak3_y'] = round(1 / max(0.01, $probNoStreak) * $margin, 2);
    $result['odds']['no_streak3_n'] = round(1 / max(0.01, 1 - $probNoStreak) * $margin, 2);
    if ($hasStreak3) $result['suspended'][] = 'no_streak3_y';

    // 3. First Goal Wins
    $firstScorer = count($logArr) > 0 ? (int)$logArr[0] : 0;
    $probFGWin = 0.65; // Base estimate
    if ($firstScorer === 1) {
        $probFGWin = $calcWinProb($p, 1, 0, 8); // Prob win if 1-0
    } elseif ($firstScorer === 2) {
        $probFGWin = 1 - $calcWinProb($p, 0, 1, 8); // Prob win if 0-1
    } else {
        // Not yet scored
        $probFGWin = $p * $calcWinProb($p, 1, 0, 8) + (1 - $p) * (1 - $calcWinProb($p, 0, 1, 8));
    }
    $result['odds']['fgoal_win_y'] = round(1 / max(0.01, $probFGWin) * $margin, 2);
    $result['odds']['fgoal_win_n'] = round(1 / max(0.01, 1 - $probFGWin) * $margin, 2);

    // 4. Killer Point (10-9 or 8-7 if no deuce)
    // Prob of reaching exactly goal_target-1 vs goal_target-1
    $probKP = $probDeuce * (2 * $p * (1 - $p)); // Reaching 8-8
    if ($s1 >= 7 && $s2 >= 7) $probKP = pow(2 * $p * (1 - $p), max(1, 9 - max($s1, $s2)));
    $result['odds']['killer_pt_y'] = round(1 / max(0.001, $probKP) * $margin, 2);
    $result['odds']['killer_pt_n'] = round(1 / max(0.01, 1 - $probKP) * $margin, 2);

    // 5. Ribaltone (Comeback >= 4 goals)
    $maxDeficitS1 = 0; $maxDeficitS2 = 0;
    $curS1 = 0; $curS2 = 0;
    foreach($logArr as $sc) {
        if ($sc == 1) $curS1++; else $curS2++;
        $maxDeficitS1 = max($maxDeficitS1, $curS2 - $curS1);
        $maxDeficitS2 = max($maxDeficitS2, $curS1 - $curS2);
    }
    $hasRibaltone = ($maxDeficitS1 >= 4 && $s1 > $s2) || ($maxDeficitS2 >= 4 && $s2 > $s1);
    // Prob of ribaltone happening in future
    $probRibaltone = 0.05; 
    if ($hasRibaltone) $probRibaltone = 1.0;
    elseif ($maxDeficitS1 >= 4 || $maxDeficitS2 >= 4) {
        // Deficit exists, need win
        $probRibaltone = ($maxDeficitS1 >= 4) ? $calcWinProb($p, $s1, $s2, 8) : (1 - $calcWinProb($p, $s1, $s2, 8));
    }
    $result['odds']['ribaltone_y'] = round(1 / max(0.01, $probRibaltone) * $margin, 2);
    $result['odds']['ribaltone_n'] = round(1 / max(0.01, 1 - $probRibaltone) * $margin, 2);
    if ($hasRibaltone) $result['suspended'][] = 'ribaltone_y';

    // --- EXACT SCORE (RISULTATO ESATTO) ---
    $t1Wins = ["8-0", "8-1", "8-2", "8-3", "8-4", "8-5", "8-6"];
    $t2Wins = ["0-8", "1-8", "2-8", "3-8", "4-8", "5-8", "6-8"];
    $deuceSc = ["9-7", "7-9", "10-8", "8-10", "10-9", "9-10"];

    // Standard Scores Team 1
    foreach ($t1Wins as $sc) {
        $k = (int)explode('-', $sc)[1];
        $prob = ($s1 <= 8 && $s2 <= $k) ? nCr((7 - $s1) + ($k - $s2), $k - $s2) * pow($p, 8 - $s1) * pow(1 - $p, $k - $s2) : 0;
        $result['odds']["es_{$sc}"] = round(1 / max(0.001, $prob) * $margin, 2);
        if ($prob <= 0.001 || $s1 > 8 || $s2 > $k) $result['suspended'][] = "es_{$sc}";
    }
    // Standard Scores Team 2
    foreach ($t2Wins as $sc) {
        $k = (int)explode('-', $sc)[0];
        $prob = ($s1 <= $k && $s2 <= 8) ? nCr((7 - $s2) + ($k - $s1), $k - $s1) * pow(1 - $p, 8 - $s2) * pow($p, $k - $s1) : 0;
        $result['odds']["es_{$sc}"] = round(1 / max(0.001, $prob) * $margin, 2);
        if ($prob <= 0.001 || $s2 > 8 || $s1 > $k) $result['suspended'][] = "es_{$sc}";
    }
    // Deuce scores
    $pToDeuce = ($s1 <= 7 && $s2 <= 7) ? nCr((7 - $s1) + (7 - $s2), 7 - $s1) * pow($p, 7 - $s1) * pow(1 - $p, 7 - $s2) : 0;
    if ($s1 >= 7 && $s2 >= 7) $pToDeuce = 1.0;

    $probsDeuce = [
        "9-7"  => $pToDeuce * pow($p, 2),
        "7-9"  => $pToDeuce * pow(1 - $p, 2),
        "10-8" => $pToDeuce * (2 * $p * (1 - $p)) * pow($p, 2),
        "8-10" => $pToDeuce * (2 * $p * (1 - $p)) * pow(1 - $p, 2),
        "10-9" => $pToDeuce * pow(2 * $p * (1 - $p), 2) * $p,
        "9-10" => $pToDeuce * pow(2 * $p * (1 - $p), 2) * (1 - $p),
    ];

    foreach ($probsDeuce as $sc => $prob) {
        $parts = explode('-', $sc);
        $t1_target = (int)$parts[0];
        $t2_target = (int)$parts[1];
        $result['odds']["es_{$sc}"] = round(1 / max(0.001, $prob) * $margin, 2);
        if ($prob <= 0.001 || $s1 > $t1_target || $s2 > $t2_target) $result['suspended'][] = "es_{$sc}";
    }

    // --- COMBO MARKETS (SQUADRA + CONDIZIONE) ---
    // Pre-calculate score probabilities arrays for easier summation
    $pS1 = []; $pS2 = [];
    foreach ($t1Wins as $sc) {
        $k = (int)explode('-', $sc)[1];
        $pS1[$sc] = ($s1 <= 8 && $s2 <= $k) ? nCr((7 - $s1) + ($k - $s2), $k - $s2) * pow($p, 8 - $s1) * pow(1 - $p, $k - $s2) : 0;
    }
    foreach ($t2Wins as $sc) {
        $k = (int)explode('-', $sc)[0];
        $pS2[$sc] = ($s1 <= $k && $s2 <= 8) ? nCr((7 - $s2) + ($k - $s1), $k - $s1) * pow(1 - $p, 8 - $s2) * pow($p, $k - $s1) : 0;
    }
    
    // Combos: Winner + Total (U/O)
    $lines = [10.5, 12.5, 14.5, 16.5];
    foreach ($lines as $line) {
        $m = str_replace('.', '', (string)$line);
        // Blu + Over/Under
        $pBluO = 0; $pBluU = 0; $pRossaO = 0; $pRossaU = 0;
        
        foreach($pS1 as $sc => $pb) { $tot = 8 + (int)explode('-', $sc)[1]; if ($tot > $line) $pBluO += $pb; else $pBluU += $pb; }
        foreach($probsDeuce as $sc => $pb) { 
            $parts = explode('-', $sc); 
            if ((int)$parts[0] > (int)$parts[1]) { 
                if (((int)$parts[0] + (int)$parts[1]) > $line) $pBluO += $pb; else $pBluU += $pb; 
            } 
        }
        
        foreach($pS2 as $sc => $pb) { $tot = 8 + (int)explode('-', $sc)[0]; if ($tot > $line) $pRossaO += $pb; else $pRossaU += $pb; }
        foreach($probsDeuce as $sc => $pb) { 
            $parts = explode('-', $sc); 
            if ((int)$parts[1] > (int)$parts[0]) { 
                if (((int)$parts[0] + (int)$parts[1]) > $line) $pRossaO += $pb; else $pRossaU += $pb; 
            } 
        }

        $result['odds']["cmb_s1_o{$m}"] = round(1 / max(0.0001, $pBluO) * $margin, 2);
        $result['odds']["cmb_s1_u{$m}"] = round(1 / max(0.0001, $pBluU) * $margin, 2);
        $result['odds']["cmb_s2_o{$m}"] = round(1 / max(0.0001, $pRossaO) * $margin, 2);
        $result['odds']["cmb_s2_u{$m}"] = round(1 / max(0.0001, $pRossaU) * $margin, 2);
        
        if ($pBluO <= 0.001) $result['suspended'][] = "cmb_s1_o{$m}";
        if ($pBluU <= 0.001) $result['suspended'][] = "cmb_s1_u{$m}";
        if ($pRossaO <= 0.001) $result['suspended'][] = "cmb_s2_o{$m}";
        if ($pRossaU <= 0.001) $result['suspended'][] = "cmb_s2_u{$m}";
    }

    // Winner + Deuce Reached
    $pBluD = $probsDeuce['9-7'] + $probsDeuce['10-8'] + $probsDeuce['10-9'];
    $pRossaD = $probsDeuce['7-9'] + $probsDeuce['8-10'] + $probsDeuce['9-10'];
    $pBluNoD = array_sum($pS1);
    $pRossaNoD = array_sum($pS2);

    $result['odds']['cmb_s1_vY'] = round(1 / max(0.0001, $pBluD) * $margin, 2);
    $result['odds']['cmb_s1_vN'] = round(1 / max(0.0001, $pBluNoD) * $margin, 2);
    $result['odds']['cmb_s2_vY'] = round(1 / max(0.0001, $pRossaD) * $margin, 2);
    $result['odds']['cmb_s2_vN'] = round(1 / max(0.0001, $pRossaNoD) * $margin, 2);
    if ($pBluD <= 0.001) $result['suspended'][] = 'cmb_s1_vY';
    if ($pBluNoD <= 0.001) $result['suspended'][] = 'cmb_s1_vN';
    if ($pRossaD <= 0.001) $result['suspended'][] = 'cmb_s2_vY';
    if ($pRossaNoD <= 0.001) $result['suspended'][] = 'cmb_s2_vN';

    // Winner + Margin
    $pBluM1 = $probsDeuce['10-9'];
    $pBluM2 = $pS1['8-6'] + $probsDeuce['9-7'] + $probsDeuce['10-8'];
    $pBluM3p = array_sum(array_slice($pS1, 0, 6)); // 8-0 to 8-5
    
    $pRossaM1 = $probsDeuce['9-10'];
    $pRossaM2 = $pS2['6-8'] + $probsDeuce['7-9'] + $probsDeuce['8-10'];
    $pRossaM3p = array_sum(array_slice($pS2, 0, 6));
    
    $result['odds']['cmb_s1_m1'] = round(1 / max(0.0001, $pBluM1) * $margin, 2);
    $result['odds']['cmb_s1_m2'] = round(1 / max(0.0001, $pBluM2) * $margin, 2);
    $result['odds']['cmb_s1_m3'] = round(1 / max(0.0001, $pBluM3p) * $margin, 2);
    $result['odds']['cmb_s2_m1'] = round(1 / max(0.0001, $pRossaM1) * $margin, 2);
    $result['odds']['cmb_s2_m2'] = round(1 / max(0.0001, $pRossaM2) * $margin, 2);
    $result['odds']['cmb_s2_m3'] = round(1 / max(0.0001, $pRossaM3p) * $margin, 2);
    if ($pBluM1 <= 0.001) $result['suspended'][] = 'cmb_s1_m1';
    if ($pBluM2 <= 0.001) $result['suspended'][] = 'cmb_s1_m2';
    if ($pBluM3p <= 0.001) $result['suspended'][] = 'cmb_s1_m3';
    if ($pRossaM1 <= 0.001) $result['suspended'][] = 'cmb_s2_m1';
    if ($pRossaM2 <= 0.001) $result['suspended'][] = 'cmb_s2_m2';
    if ($pRossaM3p <= 0.001) $result['suspended'][] = 'cmb_s2_m3';

    // Initial suspension: market locked until someone reaches 7
    if (max($s1, $s2) < 7) {
        $result['suspended'][] = 'dw1';
        $result['suspended'][] = 'dw2';
        $result['suspended'][] = 'dp05_o';
        $result['suspended'][] = 'dp05_u';
        $result['suspended'][] = 'dp15_o';
        $result['suspended'][] = 'dp15_u';
    }

    return $result;
}

/**
 * Calculates Handicap odds based on match point probability p.
 */
function calculateHandicapOdds($p, $handicap, $margin, $s1 = 0, $s2 = 0, $isOpposite = false) {
    // Probability of team we are handicrafts winning a point
    $pH = $isOpposite ? (1 - $p) : $p;
    $sH1 = $isOpposite ? $s2 : $s1;
    $sH2 = $isOpposite ? $s1 : $s2;
    $hVal = $handicap;

    $probH = 0;
    
    // Target 8 goals
    // We sum the probability of reaching (8, k) where 8 + H > k
    // and (k, 8) where k + H > 8
    
    for ($k = $sH2; $k <= 7; $k++) {
        $prob8k = nCr(7 - $sH1 + $k - $sH2, $k - $sH2) * pow($pH, 8 - $sH1) * pow(1 - $pH, $k - $sH2);
        if (8 + $hVal > $k) {
            $probH += $prob8k;
        }
    }
    
    for ($k = $sH1; $k <= 7; $k++) {
        $probk8 = nCr(7 - $sH2 + $k - $sH1, $k - $sH1) * pow(1 - $pH, 8 - $sH2) * pow($pH, $k - $sH1);
        if ($k + $hVal > 8) {
            $probH += $probk8;
        }
    }

    $probH = max(0, min(1, $probH));
    $odds = round(1 / max(0.01, $probH) * $margin, 2);
    $odds = max(1.01, min(50.00, $odds));
    
    return ['odds' => $odds, 'prob' => $probH];
}

/**
 * Calculates Over/Under total goals odds.
 */
function calculateOverUnderOdds($p, $threshold, $margin, $s1 = 0, $s2 = 0) {
    if ($s1 + $s2 > $threshold) return ['over' => 1.01, 'under' => 20.00, 'prob' => 1.0];
    
    $probOver = 0;
    
    // Normal Match (Ends at 8)
    for ($k = 0; $k <= 7; $k++) {
        // Probability of Score (8, k)
        // Only consider scores reachable from current (s1, s2)
        if (8 >= $s1 && $k >= $s2) {
            $p8k = nCr(7 - $s1 + $k - $s2, $k - $s2) * pow($p, 8 - $s1) * pow(1 - $p, $k - $s2);
            if (8 + $k > $threshold) $probOver += $p8k;
        }
        
        // Probability of Score (k, 8)
        if (8 >= $s2 && $k >= $s1) {
            $pk8 = nCr(7 - $s2 + $k - $s1, $k - $s1) * pow(1 - $p, 8 - $s2) * pow($p, $k - $s1);
            if (8 + $k > $threshold) $probOver += $pk8;
        }
    }
    
    // Deuce Case (7-7)
    // If threshold > 15, deuce is mandatory
    if ($threshold >= 14.5) {
        $pDeuce = 0;
        if ($s1 <= 7 && $s2 <= 7) {
            $pDeuce = nCr((7 - $s1) + (7 - $s2), 7 - $s1) * pow($p, 7 - $s1) * pow(1 - $p, 7 - $s2);
        } elseif ($s1 >= 7 && $s2 >= 7) {
            $pDeuce = 1.0; // Already in deuce
        }
        
        if ($pDeuce > 0) {
            // Simplified deuce extension: reaching scores (8-8, 9-9, etc)
            // Each deuce step adds 2 goals.
            // P(match reaches sum G+2 | reached sum G) is approx 2p(1-p)
            $pStep = 2 * $p * (1 - $p);
            $currentGoals = max(14, $s1 + $s2);
            if ($currentGoals % 2 != 0) $currentGoals++; // Align to deuce milestones
            
            // Probability of staying in deuce is pStep.
            // Probability of ending is 1 - pStep.
            // We can approximate the extension probabilities.
            $probExtensionOver = 1.0; // All deuce endings have at least 15 goals.
            if ($threshold > 15.5) {
                 // G=16 (needs 8-8 to be reachable)
                 $probExtensionOver = $pStep; 
            }
            if ($threshold > 17.5) {
                 $probExtensionOver *= $pStep;
            }
            // Add deuce contribution only if threshold > 14
            // Since k < 8, k+8 <= 15. 
            // Normal loop handled up to 15.
        }
    }

    $probOver = max(0, min(1, $probOver));
    $oddsO = round(1 / max(0.01, $probOver) * $margin, 2);
    $oddsU = round(1 / max(0.01, 1 - $probOver) * $margin, 2);
    
    return [
        'over' => max(1.01, min(20.00, $oddsO)),
        'under' => max(1.01, min(20.00, $oddsU)),
        'prob' => $probOver
    ];
}

function nCr($n, $r) {
    if ($r < 0 || $r > $n) return 0;
    if ($r == 0 || $r == $n) return 1;
    if ($r > $n / 2) $r = $n - $r;
    $res = 1;
    for ($i = 1; $i <= $r; $i++) {
        $res = $res * ($n - $i + 1) / $i;
    }
    return $res;
}

function getPlayerForm($conn, $playerId) {
    if ($playerId == 9999 || $playerId <= 0) return 0;
    $sql = "SELECT vincitore, squadra1_portiere, squadra1_attaccante, squadra2_portiere, squadra2_attaccante 
            FROM partite 
            WHERE squadra1_portiere = ? OR squadra1_attaccante = ? OR squadra2_portiere = ? OR squadra2_attaccante = ? 
            ORDER BY data DESC LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$playerId, $playerId, $playerId, $playerId]);
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($matches) == 0) return 0;
    $form = 0; $streak = 0; $isStreakActive = true; $streakType = null;
    foreach ($matches as $index => $m) {
        $isTeam1 = ($m['squadra1_portiere'] == $playerId || $m['squadra1_attaccante'] == $playerId);
        $won = ($isTeam1 && $m['vincitore'] == 1) || (!$isTeam1 && $m['vincitore'] == 2);
        $weight = 1.0 - ($index * 0.1); 
        if ($won) {
            $form += (5 * $weight);
            if ($isStreakActive) {
                if ($streakType === null) $streakType = 1;
                if ($streakType === 1) $streak++; else $isStreakActive = false;
            }
        } else {
            $form -= (5 * $weight);
            if ($isStreakActive) {
                if ($streakType === null) $streakType = 0;
                if ($streakType === 0) $streak++; else $isStreakActive = false;
            }
        }
    }
    $streakBonus = 0;
    if ($streak >= 3) {
        if ($streak == 3) $streakBonus = 10;
        elseif ($streak == 4) $streakBonus = 15;
        elseif ($streak >= 5) $streakBonus = 25;
        if ($streakType === 1) $form += $streakBonus; else $form -= $streakBonus;
    }
    return max(-40, min(40, $form));
}

function getMatchupAdvantage($conn, $attId, $portId) {
    if ($attId == 9999 || $portId == 9999 || $attId <= 0 || $portId <= 0) return 0;
    $sql = "SELECT vincitore, squadra1_attaccante FROM partite WHERE (squadra1_attaccante = ? AND squadra2_portiere = ?) OR (squadra2_attaccante = ? AND squadra1_portiere = ?)";
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
    $weight = min($totalMatches / 5, 1.0);
    return ($winRate - 0.5) * 60 * $weight;
}

function getTeamSynergy($conn, $pId, $aId) {
    if ($pId == 9999 || $aId == 9999 || $pId <= 0 || $aId <= 0) return 0;
    $sql = "SELECT vincitore, squadra1_portiere, squadra1_attaccante FROM partite WHERE (squadra1_portiere = ? AND squadra1_attaccante = ?) OR (squadra2_portiere = ? AND squadra2_attaccante = ?)";
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
    $weight = min($totalMatches / 5, 1.0);
    return ($winRate - 0.5) * 50 * $weight;
}

function getDailyFatigue($conn, $playerId) {
    if ($playerId == 9999 || $playerId <= 0) return 0;
    $sql = "SELECT COUNT(*) FROM partite WHERE (squadra1_portiere = ? OR squadra1_attaccante = ? OR squadra2_portiere = ? OR squadra2_attaccante = ?) AND data >= DATE_SUB(NOW(), INTERVAL 12 HOUR)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$playerId, $playerId, $playerId, $playerId]);
    $matchesPlayed = $stmt->fetchColumn();
    if ($matchesPlayed <= 4) return 0;
    return min(25, ($matchesPlayed - 4) * 3);
}
?>

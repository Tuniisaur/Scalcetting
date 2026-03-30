<?php
ob_start();

$sessionDir = __DIR__ . '/sessions';
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0777, true);
}
session_save_path($sessionDir);

$lifetime = 2592000;
ini_set('session.gc_maxlifetime', $lifetime);
session_set_cookie_params([
    'lifetime' => $lifetime,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), $_COOKIE[session_name()], time() + $lifetime, "/", "", false, true);
}

date_default_timezone_set('Europe/Rome');
require_once 'database.php';
require_once 'objectives_engine.php';
require_once 'betting_engine.php';

define('GHOST_ID', 9999);

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$isLoggedIn = isset($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? 0;
$userNome = $_SESSION['user_nome'] ?? 'Ospite';

if ($isLoggedIn && isset($_GET['do'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $database = new Database();
        $conn = $database->getConnection();
        $action = $_GET['do'];
        
        $tableId = (int)($_GET['table'] ?? 1);
        if ($tableId < 1) $tableId = 1;
        
        if ($action === 'sit' || $action === 'sit_guest') {
            $pos = $_GET['pos'] ?? '';
            $map = ['s1p' => 's1_portiere', 's1a' => 's1_attaccante', 's2p' => 's2_portiere', 's2a' => 's2_attaccante'];

            if (isset($map[$pos])) {
                $targetCol = $map[$pos];
                $targetId = ($action === 'sit_guest') ? GHOST_ID : $userId;

                // Unique Guest Check
                if ($targetId === GHOST_ID) {
                    $stmtCount = $conn->prepare("SELECT COUNT(*) FROM live_match WHERE (s1_portiere=9999 OR s1_attaccante=9999 OR s2_portiere=9999 OR s2_attaccante=9999)");
                    $stmtCount->execute();
                    if ($stmtCount->fetchColumn() > 0) {
                         echo json_encode(['success' => false, 'error' => 'Un solo ospite per volta!']);
                         exit();
                    }
                }

                $conn->beginTransaction();
                $stmtCheck = $conn->prepare("SELECT $targetCol FROM live_match WHERE id = 1 FOR UPDATE");
                $stmtCheck->execute();
                $occupanteId = $stmtCheck->fetchColumn();

                if (!$occupanteId || $occupanteId == $targetId) {
                    if ($targetId !== GHOST_ID) {
                        $conn->prepare("UPDATE live_match SET 
                                     s1_portiere = CASE WHEN s1_portiere = ? THEN NULL ELSE s1_portiere END,
                                     s1_attaccante = CASE WHEN s1_attaccante = ? THEN NULL ELSE s1_attaccante END,
                                     s2_portiere = CASE WHEN s2_portiere = ? THEN NULL ELSE s2_portiere END,
                                     s2_attaccante = CASE WHEN s2_attaccante = ? THEN NULL ELSE s2_attaccante END
                                     WHERE id = 1")->execute([$targetId, $targetId, $targetId, $targetId]);
                    }
                    $stmtSit = $conn->prepare("UPDATE live_match SET $targetCol = ?, table_id = ? WHERE id = 1");
                    $stmtSit->execute([$targetId, $tableId]);
                    $conn->commit();


                    echo json_encode(['success' => true]); 
                    exit();
                } else {
                    $conn->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Posto occupato']);
                    exit();
                }
            }
        }
        elseif ($action === 'win') {
            $winningTeam = (int)($_GET['team'] ?? 0);
            $confirm = $_GET['confirm'] ?? 'no';
            
            if ($winningTeam === 1 || $winningTeam === 2) {
                
                $stmtMPre = $conn->prepare("SELECT * FROM live_match WHERE id = 1");
                $stmtMPre->execute();
                $matchPre = $stmtMPre->fetch(PDO::FETCH_ASSOC);
                $playersPre = [(int)$matchPre['s1_portiere'], (int)$matchPre['s1_attaccante'], (int)$matchPre['s2_portiere'], (int)$matchPre['s2_attaccante']];

                if (!in_array($userId, $playersPre) && !isAdmin($conn)) {
                    echo json_encode(['success' => false, 'error' => 'Non sei in partita!']);
                    exit();
                }
                if (in_array(0, $playersPre)) {
                    echo json_encode(['success' => false, 'error' => 'Partita incompleta']);
                    exit();
                }

                if ($confirm !== 'yes') {
                    echo json_encode(['success' => false, 'requires_confirmation' => true, 'team' => $winningTeam]);
                    exit();
                }

                $conn->beginTransaction();
                
                $stmtM = $conn->prepare("SELECT * FROM live_match WHERE table_id = ? FOR UPDATE");
                $stmtM->execute([$tableId]);
                $match = $stmtM->fetch(PDO::FETCH_ASSOC);
                $players = [(int)$match['s1_portiere'], (int)$match['s1_attaccante'], (int)$match['s2_portiere'], (int)$match['s2_attaccante']];

                if (in_array(0, $players)) {
                    $conn->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Qualcuno si è alzato nel frattempo!']);
                    exit();
                }

                $dataCorrente = date('Y-m-d H:i:s');
                $stmtStagione = $conn->query("SELECT id FROM stagioni WHERE is_active = 1 LIMIT 1");
                $activeSeasonId = $stmtStagione->fetchColumn() ?: 1;
                $sql = "INSERT INTO partite (squadra1_portiere, squadra1_attaccante, squadra2_portiere, squadra2_attaccante, vincitore, data, score_s1, score_s2, stagione_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$players[0], $players[1], $players[2], $players[3], $winningTeam, $dataCorrente, null, null, $activeSeasonId]);
                $newMatchId = $conn->lastInsertId(); // Get the match ID
                
                $deltas = aggiornaEloEStatistiche($conn, [
                    'squadra1' => [$players[0], $players[1]], 
                    'squadra2' => [$players[2], $players[3]], 
                    'vincitore' => $winningTeam
                ]);
                $conn->prepare("UPDATE partite SET elo_delta_s1p=?, elo_delta_s1a=?, elo_delta_s2p=?, elo_delta_s2a=? WHERE id=?")
                     ->execute([$deltas['s1p'], $deltas['s1a'], $deltas['s2p'], $deltas['s2a'], $newMatchId]);

                // Award 5 credits to winners
                $pWin = ($winningTeam == 1) ? [$players[0], $players[1]] : [$players[2], $players[3]];
                $pLoss = ($winningTeam == 1) ? [$players[2], $players[3]] : [$players[0], $players[1]];
                awardMatchCredits($conn, $pWin, $pLoss);

                // Season Pass XP & Objectives
                $winPortiere = $pWin[0];
                $winAttaccante = $pWin[1];
                $lossPortiere = $pLoss[0];
                $lossAttaccante = $pLoss[1];

                awardXP($conn, $winPortiere, 100);
                checkMatchObjectives($conn, $winPortiere, true, 'portiere');
                
                awardXP($conn, $winAttaccante, 100);
                checkMatchObjectives($conn, $winAttaccante, true, 'attaccante');

                awardXP($conn, $lossPortiere, 50);
                checkMatchObjectives($conn, $lossPortiere, false, 'portiere');

                awardXP($conn, $lossAttaccante, 50);
                checkMatchObjectives($conn, $lossAttaccante, false, 'attaccante');

                $conn->prepare("UPDATE live_match_bonuses SET match_id = ?, status = 'used' WHERE match_id = ? AND status = 'active'")
                     ->execute([$newMatchId, $match['table_id']]);

                $conn->prepare("UPDATE live_match SET s1_portiere=NULL, s1_attaccante=NULL, s2_portiere=NULL, s2_attaccante=NULL, score_s1=0, score_s2=0, data_inizio_match=NULL, goal_log='' WHERE id=1")
                     ->execute();
                $conn->commit();

                
                echo json_encode(['success' => true, 'message' => 'Vittoria registrata!']);
                exit();
            }
        }
        
        echo json_encode(['success' => false, 'error' => 'Azione non valida']); 
        exit();

    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $tableId = (int)($_GET['table'] ?? 1);
        if ($tableId < 1) $tableId = 1;
        
        $conn->prepare("INSERT IGNORE INTO live_match (id, table_id) VALUES (1, NULL)")->execute();

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $stmt = $conn->prepare("SELECT * FROM live_match WHERE id = 1");
            $stmt->execute();
            $status = $stmt->fetch(PDO::FETCH_ASSOC); // RESTORED
            $response = ['players' => [], 'active_bonuses' => []];
            $roles = ['s1_portiere', 's1_attaccante', 's2_portiere', 's2_attaccante'];
            
            foreach($roles as $r) {
                if (!empty($status[$r])) {
                    if ($status[$r] == GHOST_ID) {
                        $response['players'][$r] = ['id' => GHOST_ID, 'nome' => 'Ospite', 'avatar_url' => null, 'elo_portiere' => 1500, 'elo_attaccante' => 1500];
                    } else {
                        $stmtN = $conn->prepare("SELECT id, nome, avatar_url, elo_portiere, elo_attaccante, active_aura, active_title, active_name_color, partite_attaccante FROM giocatori WHERE id = ?");
                        $stmtN->execute([$status[$r]]);
                        $playerData = $stmtN->fetch(PDO::FETCH_ASSOC);
                        
                        // Arricchimento dati dinamici
                        if ($playerData) {
                            $tmpArr = [$playerData];
                            enrichPlayerData($tmpArr, $conn);
                            $response['players'][$r] = $tmpArr[0];
                        }
                    }
                } else {
                    $response['players'][$r] = null;
                }
            }

            // Fetch active bonuses
            $stmtB = $conn->prepare("SELECT user_id, item_key FROM live_match_bonuses WHERE match_id = ? AND status = 'active'");
            $stmtB->execute([$status['table_id']]);
            $bonusesRaw = $stmtB->fetchAll(PDO::FETCH_ASSOC);
            foreach($bonusesRaw as $b) {
                $uid = (int)$b['user_id'];
                if (!isset($response['active_bonuses'][$uid])) $response['active_bonuses'][$uid] = [];
                $response['active_bonuses'][$uid][] = $b['item_key'];
            }

            // Include Scores and Table
            $response['score_s1'] = (int)($status['score_s1'] ?? 0);
            $response['score_s2'] = (int)($status['score_s2'] ?? 0);
            $response['table_id'] = ($status['table_id'] !== null) ? (int)$status['table_id'] : null;
            
            // Flag for hardware sensors
            $response['is_match_ready'] = (!empty($status['s1_portiere']) && !empty($status['s1_attaccante']) && 
                                           !empty($status['s2_portiere']) && !empty($status['s2_attaccante']));

            // Recent match check for victory animation on scoreboard (matches finished in the last 15 seconds)
            $stmtRecent = $conn->query("SELECT * FROM partite ORDER BY id DESC LIMIT 1");
            $recentMatch = $stmtRecent->fetch(PDO::FETCH_ASSOC);
            if ($recentMatch && (time() - strtotime($recentMatch['data'])) < 15) {
                $response['recent_match'] = $recentMatch;
                $winnerPositions = ($recentMatch['vincitore'] == 1) ? ['squadra1_portiere', 'squadra1_attaccante'] : ['squadra2_portiere', 'squadra2_attaccante'];
                
                $stmtW = $conn->prepare("SELECT id, nome, avatar_url, active_aura, active_title, active_name_color, partite_attaccante FROM giocatori WHERE id = ?");
                $stmtW->execute([$recentMatch[$winnerPositions[0]]]);
                $w1 = $stmtW->fetch(PDO::FETCH_ASSOC);
                if ($w1) {
                    $tmp1 = [$w1];
                    enrichPlayerData($tmp1, $conn);
                    $response['recent_winner_1'] = $tmp1[0];
                }

                $stmtW->execute([$recentMatch[$winnerPositions[1]]]);
                $w2 = $stmtW->fetch(PDO::FETCH_ASSOC);
                if ($w2) {
                    $tmp2 = [$w2];
                    enrichPlayerData($tmp2, $conn);
                    $response['recent_winner_2'] = $tmp2[0];
                }
            }

            echo json_encode($response);
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $action = $input['action'] ?? '';
            $tableId = (int)($input['table'] ?? ($_GET['table'] ?? 1));
            if ($tableId < 1) $tableId = 1;
            
            // Allow hardware sensors to bypass login for score updates
            if (!$isLoggedIn && !in_array($action, ['add_goal', 'sub_goal'])) {
                echo json_encode(['success' => false, 'error' => 'Login richiesto']);
                exit();
            }

            $playerId = $userId; 

            if ($action === 'sit' || $action === 'sit_guest') {
                $pos = $input['pos'];
                $map = ['s1p' => 's1_portiere', 's1a' => 's1_attaccante', 's2p' => 's2_portiere', 's2a' => 's2_attaccante'];
                if (!isset($map[$pos])) throw new Exception("Posizione errata");
                $targetCol = $map[$pos];

                $targetId = $playerId;
                if ($action === 'sit_guest' || (isset($input['player_id']) && (int)$input['player_id'] === GHOST_ID)) {
                    $targetId = GHOST_ID;
                }
                
                // Unique Guest Check
                if ($targetId === GHOST_ID) {
                    $stmtCount = $conn->prepare("SELECT COUNT(*) FROM live_match WHERE (s1_portiere=9999 OR s1_attaccante=9999 OR s2_portiere=9999 OR s2_attaccante=9999)");
                    $stmtCount->execute();
                    if ($stmtCount->fetchColumn() > 0) {
                         echo json_encode(['success' => false, 'error' => 'Un solo ospite per volta!']);
                         exit();
                    }
                }

                $conn->beginTransaction();
                
                $stmtCheck = $conn->prepare("SELECT $targetCol FROM live_match WHERE id = 1 FOR UPDATE");
                $stmtCheck->execute();
                $occupanteId = $stmtCheck->fetchColumn();
                if ($occupanteId && $occupanteId != $targetId) {
                    $stmtName = $conn->prepare("SELECT nome FROM giocatori WHERE id = ?");
                    $stmtName->execute([$occupanteId]);
                    $n = ($occupanteId == GHOST_ID) ? 'Ospite' : $stmtName->fetchColumn();
                    $conn->rollBack();
                    echo json_encode(['success' => false, 'error' => "Posto occupato da " . ($n ?: "un altro")]);
                    exit();
                }

                if ($targetId !== GHOST_ID) {
                    $conn->prepare("UPDATE live_match SET 
                                 s1_portiere = CASE WHEN s1_portiere = ? THEN NULL ELSE s1_portiere END,
                                 s1_attaccante = CASE WHEN s1_attaccante = ? THEN NULL ELSE s1_attaccante END,
                                 s2_portiere = CASE WHEN s2_portiere = ? THEN NULL ELSE s2_portiere END,
                                 s2_attaccante = CASE WHEN s2_attaccante = ? THEN NULL ELSE s2_attaccante END
                                 WHERE id = 1")->execute([$targetId, $targetId, $targetId, $targetId]);
                }

                $stmtSit = $conn->prepare("UPDATE live_match SET $targetCol = ?, table_id = ? WHERE id = 1");
                $stmtSit->execute([$targetId, $tableId]);
                $conn->commit();


                echo json_encode(['success' => true]);
                exit();
            }
            
            elseif ($action === 'add_goal' || $action === 'sub_goal') {
                $team = (int)($input['team'] ?? 0);
                if ($team !== 1 && $team !== 2) {
                    echo json_encode(['success' => false, 'error' => 'Team non valido']);
                    exit();
                }

                $conn->beginTransaction();
                $stmtM = $conn->prepare("SELECT * FROM live_match WHERE id = 1 FOR UPDATE");
                $stmtM->execute();
                $match = $stmtM->fetch(PDO::FETCH_ASSOC);
                
                $players = [(int)$match['s1_portiere'], (int)$match['s1_attaccante'], (int)$match['s2_portiere'], (int)$match['s2_attaccante']];
                if (in_array(0, $players)) {
                    $conn->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Partita non ancora iniziata!']);
                    exit();
                }

                $scoreCol = ($team === 1) ? 'score_s1' : 'score_s2';
                $currentScore = (int)$match[$scoreCol];

                $goalLog = $match['goal_log'] ?: '';
                if ($action === 'add_goal') {
                    $newScore = $currentScore + 1;
                    $goalLog = ($goalLog === '') ? (string)$team : $goalLog . ',' . $team;
                } else {
                    $newScore = max(0, $currentScore - 1);
                    if ($goalLog !== '') {
                        $logArr = explode(',', $goalLog);
                        // Find the last occurrence of this team and remove it? 
                        // Actually, sub_goal usually just removes the very last goal regardless of team if it's a correction.
                        // But here 'team' is passed. Let's assume we remove the last goal of THAT team if possible, 
                        // or just the last goal in general. Standard sports logic: sub_goal is a 'correction' of the last action.
                        array_pop($logArr); 
                        $goalLog = implode(',', $logArr);
                    }
                }

                $conn->prepare("UPDATE live_match SET $scoreCol = ?, goal_log = ? WHERE id = 1")->execute([$newScore, $goalLog]);

                // Check for auto-win (Win at 8, max 2 advantages, golden goal at 10)
                $s1 = ($team === 1) ? $newScore : (int)$match['score_s1'];
                $s2 = ($team === 2) ? $newScore : (int)$match['score_s2'];
                
                $win = false;
                if ($team === 1) {
                    if (($s1 >= 8 && ($s1 - $s2) >= 2) || $s1 >= 10) $win = true;
                } else {
                    if (($s2 >= 8 && ($s2 - $s1) >= 2) || $s2 >= 10) $win = true;
                }

                if ($win && $action === 'add_goal') {
                    $winningTeam = $team;
                    $stmtStagione = $conn->query("SELECT id FROM stagioni WHERE is_active = 1 LIMIT 1");
                    $activeSeasonId = $stmtStagione->fetchColumn() ?: 1;
                    $sql = "INSERT INTO partite (squadra1_portiere, squadra1_attaccante, squadra2_portiere, squadra2_attaccante, vincitore, data, score_s1, score_s2, stagione_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$players[0], $players[1], $players[2], $players[3], $winningTeam, date('Y-m-d H:i:s'), $s1, $s2, $activeSeasonId]);
                    $newMatchId = $conn->lastInsertId();
                    
                    $deltas = aggiornaEloEStatistiche($conn, ['squadra1'=>[$players[0],$players[1]], 'squadra2'=>[$players[2],$players[3]], 'vincitore'=>$winningTeam]);
                    
                    $conn->prepare("UPDATE partite SET elo_delta_s1p=?, elo_delta_s1a=?, elo_delta_s2p=?, elo_delta_s2a=? WHERE id=?")
                         ->execute([$deltas['s1p'], $deltas['s1a'], $deltas['s2p'], $deltas['s2a'], $newMatchId]);

                    // Award 5 credits to winners
                    $pWin = ($winningTeam == 1) ? [$players[0], $players[1]] : [$players[2], $players[3]];
                    $pLoss = ($winningTeam == 1) ? [$players[2], $players[3]] : [$players[0], $players[1]];
                    awardMatchCredits($conn, $pWin, $pLoss);

                    // Season Pass XP & Objectives
                    $winPortiere = $pWin[0];
                    $winAttaccante = $pWin[1];
                    $lossPortiere = $pLoss[0];
                    $lossAttaccante = $pLoss[1];

                    awardXP($conn, $winPortiere, 100);
                    checkMatchObjectives($conn, $winPortiere, true, 'portiere');
                    
                    awardXP($conn, $winAttaccante, 100);
                    checkMatchObjectives($conn, $winAttaccante, true, 'attaccante');

                    awardXP($conn, $lossPortiere, 50);
                    checkMatchObjectives($conn, $lossPortiere, false, 'portiere');

                    awardXP($conn, $lossAttaccante, 50);
                    checkMatchObjectives($conn, $lossAttaccante, false, 'attaccante');

                    // Handle Bonuses
                    processBets($conn, $winningTeam, $s1, $s2, $goalLog);
                    $conn->prepare("UPDATE live_match_bonuses SET match_id = ?, status = 'used' WHERE match_id = ? AND status = 'active'")
                         ->execute([$newMatchId, $match['table_id']]);

                    $conn->prepare("UPDATE live_match SET s1_portiere=NULL, s1_attaccante=NULL, s2_portiere=NULL, s2_attaccante=NULL, score_s1=0, score_s2=0, data_inizio_match=NULL, goal_log='' WHERE id=1")
                         ->execute();
                    $conn->commit();
                    
                    echo json_encode(['success' => true, 'match_ended' => true, 'winner' => $winningTeam]);
                    exit();
                }
                
                $conn->commit();
                echo json_encode(['success' => true, 'new_score' => $newScore]);
                exit();
            }

            elseif ($action === 'reset') {
                $conn->prepare("UPDATE live_match SET s1_portiere=NULL, s1_attaccante=NULL, s2_portiere=NULL, s2_attaccante=NULL, score_s1=0, score_s2=0, goal_log='', table_id=NULL WHERE id=1")->execute();
                

                echo json_encode(['success' => true]);
                exit();
            }
        }
    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
    exit();
}

function getKFactor($partite) { return ($partite < 10) ? 40 : (($partite < 20) ? 35 : (($partite < 30) ? 30 : (($partite < 40) ? 25 : (($partite < 50) ? 20 : 16)))); }
function calcolaElo($eloGiocatore, $eloPropriaSquadra, $eloSfidanteSquadra, $risultato, $partiteGiocate, $multiplier = 1) {
    $K = getKFactor($partiteGiocate) * $multiplier;
    $expected = 1 / (1 + pow(10, ($eloSfidanteSquadra - $eloPropriaSquadra) / 400));
    return max(100, min(3000, round($eloGiocatore + $K * ($risultato - $expected))));
}
function getEloMedioPesatoOverall($conn) {
    $stmt = $conn->query("SELECT elo_portiere, partite_portiere, elo_attaccante, partite_attaccante FROM giocatori WHERE id != " . GHOST_ID . " AND partite_totali > 0");
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $weightedSum = 0; $totalMatches = 0;
    foreach ($players as $p) {
        $weightedSum += ($p['elo_portiere'] * $p['partite_portiere']) + ($p['elo_attaccante'] * $p['partite_attaccante']);
        $totalMatches += $p['partite_portiere'] + $p['partite_attaccante'];
    }
    return ($totalMatches == 0) ? 1500 : round($weightedSum / $totalMatches);
}
function aggiornaEloEStatistiche($conn, $dati, $tableId = 1) {
    // 1. Fetch Active Bonuses for Match tableId
    $stmtB = $conn->prepare("SELECT user_id, item_key FROM live_match_bonuses WHERE match_id = ? AND status = 'active'");
    $stmtB->execute([$tableId]);
    $bonuses = $stmtB->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN); 
    // Format: [user_id => ['x2_elo', ...]]

    $getMult = function($id) use ($bonuses) {
        if (isset($bonuses[$id]) && in_array('x2_elo', $bonuses[$id])) return 2;
        return 1;
    };

    $eloFantasma = getEloMedioPesatoOverall($conn);
    $ids = array_merge($dati['squadra1'], $dati['squadra2']);
    $gs = [];
    foreach($ids as $id) {
        if ($id == GHOST_ID) { 
            $player = ['id' => GHOST_ID, 'elo_portiere' => $eloFantasma, 'elo_attaccante' => $eloFantasma, 'partite_portiere' => 0, 'partite_attaccante' => 0];
        } else {
            $stmt = $conn->prepare("SELECT * FROM giocatori WHERE id = ?"); $stmt->execute([$id]);
            $player = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        $gs[$id] = $player;
    }
    $s1p = $gs[$dati['squadra1'][0]]; $s1a = $gs[$dati['squadra1'][1]];
    $s2p = $gs[$dati['squadra2'][0]]; $s2a = $gs[$dati['squadra2'][1]];
    $res1 = $dati['vincitore'] == 1 ? 1 : 0; $res2 = $dati['vincitore'] == 2 ? 1 : 0;
    
    $eloBase_s1p = $s1p['elo_portiere']   ?: 1500;
    $eloBase_s1a = $s1a['elo_attaccante'] ?: 1500;
    $eloBase_s2p = $s2p['elo_portiere']   ?: 1500;
    $eloBase_s2a = $s2a['elo_attaccante'] ?: 1500;

    $eloMedioSq1 = ($eloBase_s1p + $eloBase_s1a) / 2;
    $eloMedioSq2 = ($eloBase_s2p + $eloBase_s2a) / 2;

    $nElo_s1p = calcolaElo($eloBase_s1p, $eloMedioSq1, $eloMedioSq2, $res1, $s1p['partite_portiere'],   $getMult($s1p['id']));
    $nElo_s1a = calcolaElo($eloBase_s1a, $eloMedioSq1, $eloMedioSq2, $res1, $s1a['partite_attaccante'], $getMult($s1a['id']));
    $nElo_s2p = calcolaElo($eloBase_s2p, $eloMedioSq2, $eloMedioSq1, $res2, $s2p['partite_portiere'],   $getMult($s2p['id']));
    $nElo_s2a = calcolaElo($eloBase_s2a, $eloMedioSq2, $eloMedioSq1, $res2, $s2a['partite_attaccante'], $getMult($s2a['id']));

    
    updatePlayerRole($conn, $s1p['id'], 'elo_portiere',   $nElo_s1p, 'vittorie_portiere',   'sconfitte_portiere',   'partite_portiere',   $res1);
    updatePlayerRole($conn, $s2p['id'], 'elo_portiere',   $nElo_s2p, 'vittorie_portiere',   'sconfitte_portiere',   'partite_portiere',   $res2);
    updatePlayerRole($conn, $s1a['id'], 'elo_attaccante', $nElo_s1a, 'vittorie_attaccante', 'sconfitte_attaccante', 'partite_attaccante', $res1);
    updatePlayerRole($conn, $s2a['id'], 'elo_attaccante', $nElo_s2a, 'vittorie_attaccante', 'sconfitte_attaccante', 'partite_attaccante', $res2);

    return [
        's1p' => $nElo_s1p - $eloBase_s1p,
        's1a' => $nElo_s1a - $eloBase_s1a,
        's2p' => $nElo_s2p - $eloBase_s2p,
        's2a' => $nElo_s2a - $eloBase_s2a,
    ];
}
function updatePlayerRole($conn, $id, $eloField, $newElo, $winField, $loseField, $matchField, $isWin) {
    if ($id == GHOST_ID) return;
    $winAdd = $isWin ? 1 : 0; $loseAdd = $isWin ? 0 : 1;
    $sql = "UPDATE giocatori SET $eloField = ?, $matchField = $matchField + 1, partite_totali = partite_totali + 1, $winField = $winField + ?, $loseField = $loseField + ?, vittorie_totali = vittorie_totali + ?, sconfitte_totali = sconfitte_totali + ? WHERE id = ?";
    $conn->prepare($sql)->execute([$newElo, $winAdd, $loseAdd, $winAdd, $loseAdd, $id]);
}
function isAdmin($conn) {
    if (!isset($_SESSION['user_id'])) return false;
    $stmt = $conn->prepare("SELECT is_admin FROM giocatori WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return (bool)$stmt->fetchColumn();
}


// FILE ENDS AFTER PHP TAG

<?php
require_once 'session_config.php';
header('Content-Type: application/json; charset=utf-8');
require_once 'database.php';

// 1. Auth Check: Must be Logged In AND Admin
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non loggato']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $stmt = $conn->prepare("SELECT is_admin FROM giocatori WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $isAdmin = (bool)$stmt->fetchColumn();

    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Accesso negato: Solo Admin']);
        exit();
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit();
}

// 2. Action Handler
$input = json_decode(file_get_contents('php://input'), true);
// Fallback for GET requests (like backup)
$action = $_POST['action'] ?? ($input['action'] ?? ($_GET['action'] ?? ''));

if (!$action) {
    echo json_encode(['success' => false, 'error' => 'Nessuna azione specificata']);
    exit();
}

try {
    switch ($action) {
        case 'backup_db':
            handleBackup($conn);
            break;
        case 'reset_db':
            handleResetDB($conn);
            break;
        case 'delete_all_matches':
            handleDeleteAllMatches($conn);
            break;
        case 'delete_all_players':
            handleDeleteAllPlayers($conn);
            break;
        case 'get_all_players':
            handleGetAllPlayers($conn);
            break;
        case 'update_player':
             handleUpdatePlayer($conn, $input);
             break;
        case 'delete_player':
             handleDeletePlayer($conn, $input);
             break;
        case 'delete_match':
             handleDeleteMatch($conn, $input);
             break;
        case 'recalculate_elo':
             handleRecalculateElo($conn);
             break;
        case 'create_player':
             handleCreatePlayer($conn, $input);
             break;
        default:
            echo json_encode(['success' => false, 'error' => 'Azione sconosciuta']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}


// --- Functions ---

function handleBackup($conn) {
    // Determine tables to backup
    $tables = ['giocatori', 'partite', 'live_match'];
    $sqlScript = "-- Backup FoosballELO " . date('Y-m-d H:i:s') . "\n\n";

    foreach ($tables as $table) {
        // Drop if exists
        //$sqlScript .= "DROP TABLE IF EXISTS `$table`;\n";
        
        // Create Table (simplified, usually we dump structure, but for restore on same DB we might just want data? 
        // Actually for a proper backup we need data.
        // Let's just dump data INSERTs for critical info. Restoring structure is complex without mysqldump.)
        
        $sqlScript .= "-- Table: $table --\n";
        $stmt = $conn->query("SELECT * FROM $table");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rows as $row) {
            $cols = array_keys($row);
            $vals = array_values($row);
            
            // Escape values
            $vals = array_map(function($v) use ($conn) {
                if ($v === null) return "NULL";
                return $conn->quote($v);
            }, $vals);
            
            $sqlScript .= "INSERT INTO `$table` (`" . implode("`, `", $cols) . "`) VALUES (" . implode(", ", $vals) . ");\n";
        }
        $sqlScript .= "\n";
    }

    // Force download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="backup_' . date('Y-m-d_Hi') . '.sql"');
    header('Content-Length: ' . strlen($sqlScript));
    echo $sqlScript;
    exit();
}

function handleResetDB($conn) {
    // 1. Truncate Matches
    $conn->exec("TRUNCATE TABLE partite");
    
    // 2. Reset Live Match
    $conn->exec("UPDATE live_match SET s1_portiere=NULL, s1_attaccante=NULL, s2_portiere=NULL, s2_attaccante=NULL WHERE id=1");

    // 3. Reset Player Stats (but keep players)
    $sql = "UPDATE giocatori SET 
            elo_portiere = 1500, elo_attaccante = 1500, 
            partite_portiere = 0, partite_attaccante = 0, partite_totali = 0,
            vittorie_portiere = 0, vittorie_attaccante = 0, vittorie_totali = 0,
            sconfitte_portiere = 0, sconfitte_attaccante = 0, sconfitte_totali = 0
            WHERE id != 9999"; 
            // 9999 is ghost, usually ignored but good to reset too just in case
    $conn->exec($sql);
    $conn->exec("UPDATE giocatori SET elo_portiere=1500, elo_attaccante=1500, partite_totali=0 WHERE id=9999");

    echo json_encode(['success' => true, 'message' => 'Database resettato (utenti mantenuti)']);
}

function handleDeleteAllMatches($conn) {
    $conn->exec("TRUNCATE TABLE partite");
    
    // Reset stats to 1500
    $conn->exec("UPDATE giocatori SET 
            elo_portiere = 1500, elo_attaccante = 1500, 
            partite_portiere = 0, partite_attaccante = 0, partite_totali = 0,
            vittorie_portiere = 0, vittorie_attaccante = 0, vittorie_totali = 0,
            sconfitte_portiere = 0, sconfitte_attaccante = 0, sconfitte_totali = 0");
            
    echo json_encode(['success' => true, 'message' => 'Tutte le partite eliminate']);
}

function handleDeleteAllPlayers($conn) {
    // Helper: keep Admin and Ghost
    $currentAdminId = $_SESSION['user_id'];
    
    // 1. Delete Matches first (constraint)
    $conn->exec("TRUNCATE TABLE partite");
    
    // 2. Reset Live Match
    $conn->exec("UPDATE live_match SET s1_portiere=NULL, s1_attaccante=NULL, s2_portiere=NULL, s2_attaccante=NULL WHERE id=1");
    
    // 3. Delete Players
    $sql = "DELETE FROM giocatori WHERE id != ? AND id != 9999";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$currentAdminId]);
    
    // 4. Reset Admin Stats
    $conn->exec("UPDATE giocatori SET 
            elo_portiere = 1500, elo_attaccante = 1500, 
            partite_portiere = 0, partite_attaccante = 0, partite_totali = 0,
            vittorie_portiere = 0, vittorie_attaccante = 0, vittorie_totali = 0,
            sconfitte_portiere = 0, sconfitte_attaccante = 0, sconfitte_totali = 0
            WHERE id = $currentAdminId");

    echo json_encode(['success' => true, 'message' => 'Tutti i giocatori eliminati (eccetto Admin e Ghost)']);
}

function handleGetAllPlayers($conn) {
    $stmt = $conn->query("SELECT id, nome, username, is_admin FROM giocatori WHERE id != 9999 ORDER BY nome ASC");
    echo json_encode(['success' => true, 'players' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function handleUpdatePlayer($conn, $input) {
    $id = (int)($input['id'] ?? 0);
    $name = trim($input['nome'] ?? '');
    $username = trim($input['username'] ?? '');
    // Password reset optional?
    
    if (!$id || !$name || !$username) {
        throw new Exception("Dati mancanti");
    }
    
    // Check duplicates
    $stmt = $conn->prepare("SELECT COUNT(*) FROM giocatori WHERE (nome = ? OR username = ?) AND id != ?");
    $stmt->execute([$name, $username, $id]);
    if ($stmt->fetchColumn() > 0) throw new Exception("Nome o Username già in uso");
    
    $sql = "UPDATE giocatori SET nome = ?, username = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$name, $username, $id]);
    
    echo json_encode(['success' => true, 'message' => 'Giocatore aggiornato']);
}

function handleDeletePlayer($conn, $input) {
    $id = (int)($input['id'] ?? 0);
    if (!$id) throw new Exception("ID mancante");
    if ($id == $_SESSION['user_id']) throw new Exception("Non puoi eliminarti da solo");
    
    // Check matches
    $stmt = $conn->prepare("SELECT COUNT(*) FROM partite WHERE squadra1_portiere=? OR squadra1_attaccante=? OR squadra2_portiere=? OR squadra2_attaccante=?");
    $stmt->execute([$id, $id, $id, $id]);
    if ($stmt->fetchColumn() > 0) throw new Exception("Impossibile eliminare: il giocatore ha delle partite. Elimina prima le partite.");
    
    // Delete
    $stmt = $conn->prepare("DELETE FROM giocatori WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true, 'message' => 'Giocatore eliminato']);
}

function handleCreatePlayer($conn, $input) {
    $nome = trim($input['nome'] ?? '');
    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');
    $isAdmin = isset($input['is_admin']) ? (int)$input['is_admin'] : 0;
    
    if (!$nome) {
        throw new Exception("Nome giocatore obbligatorio");
    }
    
    // Check duplicates
    $stmt = $conn->prepare("SELECT COUNT(*) FROM giocatori WHERE nome = ?");
    $stmt->execute([$nome]);
    if ($stmt->fetchColumn() > 0) throw new Exception("Nome giocatore già in uso");
    
    if ($username) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM giocatori WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) throw new Exception("Username già in uso");
    }
    
    if (!$password) {
        $password = '1234'; // Default password
    }
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO giocatori (nome, username, password, is_admin) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $nome,
        $username ?: null,
        $hashedPassword,
        $isAdmin
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Giocatore e utente creati con successo']);
}

// --- Added functionalities from delete_partita.php and recalculate_history.php ---

function handleDeleteMatch($conn, $input) {
    if (!isset($input['id'])) {
        throw new Exception('Missing match ID');
    }
    
    $matchId = (int)$input['id'];
    
    $sql = "SELECT * FROM partite WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$matchId]);
    $match = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$match) {
        throw new Exception('Match not found');
    }
    
    $conn->beginTransaction();
    
    try {
        revertPlayerStats($conn, $match['squadra1_portiere'], 'portiere', 1, $match);
        revertPlayerStats($conn, $match['squadra1_attaccante'], 'attaccante', 1, $match);
        revertPlayerStats($conn, $match['squadra2_portiere'], 'portiere', 2, $match);
        revertPlayerStats($conn, $match['squadra2_attaccante'], 'attaccante', 2, $match);

        $sql = "DELETE FROM partite WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$matchId]);
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Partita eliminata e statistiche ricalcolate"
        ]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

function revertPlayerStats($conn, $playerId, $ruolo, $squadraNum, $match) {
    $stmt = $conn->prepare("SELECT elo_portiere, elo_attaccante, partite_portiere, partite_attaccante, vittorie_portiere, vittorie_attaccante, partite_totali, vittorie_totali, sconfitte_totali FROM giocatori WHERE id = ?");
    $stmt->execute([$playerId]);
    $giocatore = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$giocatore) return;

    $haVinto = ($match['vincitore'] == $squadraNum);
    
    $team1Ids = [$match['squadra1_portiere'], $match['squadra1_attaccante']];
    $team2Ids = [$match['squadra2_portiere'], $match['squadra2_attaccante']];
    
    $getTeamElo = function($ids) use ($conn) {
        $sum = 0; $count = 0;
        foreach($ids as $id) {
            $stmt = $conn->prepare("SELECT elo_portiere, elo_attaccante FROM giocatori WHERE id = ?");
            $stmt->execute([$id]);
            $d = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($d) {
                $sum += ($d['elo_portiere'] + $d['elo_attaccante']) / 2;
                $count++;
            }
        }
        return ($count > 0) ? ($sum / $count) : 1500;
    };

    $eloMedioSq1 = $getTeamElo($team1Ids);
    $eloMedioSq2 = $getTeamElo($team2Ids);

    $ePropria = ($squadraNum == 1) ? $eloMedioSq1 : $eloMedioSq2;
    $eSfidante = ($squadraNum == 1) ? $eloMedioSq2 : $eloMedioSq1;

    $eloAttuale = ($ruolo == 'portiere') ? $giocatore['elo_portiere'] : $giocatore['elo_attaccante'];
    $eloPrecedente = reverseElo($eloAttuale, $ePropria, $eSfidante, $haVinto ? 1 : 0);

    $colonnaElo = ($ruolo == 'portiere') ? 'elo_portiere' : 'elo_attaccante';
    $colonnaPartite = ($ruolo == 'portiere') ? 'partite_portiere' : 'partite_attaccante';
    $colonnaVittorie = ($ruolo == 'portiere') ? 'vittorie_portiere' : 'vittorie_attaccante';
    $colonnaSconfitte = ($ruolo == 'portiere') ? 'sconfitte_portiere' : 'sconfitte_attaccante';

    $sql = "UPDATE giocatori SET 
            $colonnaElo = ?,
            $colonnaPartite = GREATEST(0, $colonnaPartite - 1),
            partite_totali = GREATEST(0, partite_totali - 1)
            WHERE id = ?";
            
    $params = [$eloPrecedente, $playerId];
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    if ($haVinto) {
        $sqlV = "UPDATE giocatori SET 
                 $colonnaVittorie = GREATEST(0, $colonnaVittorie - 1), 
                 vittorie_totali = GREATEST(0, vittorie_totali - 1) 
                 WHERE id = ?";
        $conn->prepare($sqlV)->execute([$playerId]);
    } else {
        $sqlS = "UPDATE giocatori SET 
                 $colonnaSconfitte = GREATEST(0, $colonnaSconfitte - 1),
                 sconfitte_totali = GREATEST(0, sconfitte_totali - 1) 
                 WHERE id = ?";
        $conn->prepare($sqlS)->execute([$playerId]);
    }
}

function getKFactor($elo) {
    if ($elo < 2000) return 32;
    if ($elo < 2400) return 24;
    return 16;
}

function reverseElo($eloAttuale, $eloPropriaSquadra, $eloSfidanteSquadra, $risultatoPartita) {
    $k = getKFactor($eloAttuale);
    $expected = 1 / (1 + pow(10, ($eloSfidanteSquadra - $eloPropriaSquadra) / 400));
    $change = $k * ($risultatoPartita - $expected);
    return max(100, min(3000, round($eloAttuale - $change)));
}

// Logic from recalculate_history.php

$runningWeightedSum = 0;
$runningTotalMatches = 0;

function handleRecalculateElo($conn) {
    global $runningWeightedSum, $runningTotalMatches;
    
    // reset global vars for recalculation processing
    $runningWeightedSum  = 0;
    $runningTotalMatches = 0;
    
    $conn->exec("UPDATE giocatori SET
        elo_portiere = 1500, elo_attaccante = 1500,
        partite_portiere = 0, partite_attaccante = 0, partite_totali = 0,
        vittorie_portiere = 0, vittorie_attaccante = 0, vittorie_totali = 0,
        sconfitte_portiere = 0, sconfitte_attaccante = 0, sconfitte_totali = 0
        WHERE id != 9999");

    $stmtS = $conn->query("SELECT id FROM stagioni WHERE is_active = 1 LIMIT 1");
    $activeSeasonId = $stmtS->fetchColumn() ?: 1;

    $stmt    = $conn->prepare("SELECT * FROM partite WHERE stagione_id = ? ORDER BY data ASC, id ASC");
    $stmt->execute([$activeSeasonId]);
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total   = count($matches);

    foreach ($matches as $match) {
        processMatchRec($conn, [
            'squadra1' => [$match['squadra1_portiere'], $match['squadra1_attaccante']],
            'squadra2' => [$match['squadra2_portiere'], $match['squadra2_attaccante']],
            'vincitore' => $match['vincitore']
        ], $match['id']);
    }

    echo json_encode([
        'success' => true,
        'message' => "Ricalcolo completato: $total partite elaborate."
    ]);
}

function getKFactorRec($partite) {
    if ($partite < 10) return 40;
    if ($partite < 20) return 35;
    if ($partite < 30) return 30;
    if ($partite < 40) return 25;
    if ($partite < 50) return 20;
    return 16;
}

function calcolaEloRec($eloGiocatore, $eloPropriaSquadra, $eloSfidanteSquadra, $risultato, $partiteGiocate) {
    $K = getKFactorRec($partiteGiocate);
    $expected = 1 / (1 + pow(10, ($eloSfidanteSquadra - $eloPropriaSquadra) / 400));
    return max(100, min(3000, round($eloGiocatore + $K * ($risultato - $expected))));
}

function updateRunningStateRec($oldElo, $newElo, $oldMatches) {
    global $runningWeightedSum, $runningTotalMatches;
    $runningWeightedSum -= ($oldElo * $oldMatches);
    $runningWeightedSum += ($newElo * ($oldMatches + 1));
    $runningTotalMatches += 1;
}

function getEloFantasmaRec() {
    global $runningWeightedSum, $runningTotalMatches;
    return ($runningTotalMatches == 0) ? 1500 : round($runningWeightedSum / $runningTotalMatches);
}

function updatePlayerRoleRec($conn, $id, $eloField, $newElo, $winField, $loseField, $matchField, $isWin) {
    if ($id == 9999) return;
    static $playersCache = [];
    if (!isset($playersCache[$id])) {
        $stmt = $conn->prepare("SELECT elo_portiere, elo_attaccante, partite_portiere, partite_attaccante FROM giocatori WHERE id = ?");
        $stmt->execute([$id]);
        $playersCache[$id] = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    $oldElo     = (int)$playersCache[$id][$eloField];
    $oldMatches = (int)$playersCache[$id][$matchField];
    updateRunningStateRec($oldElo, $newElo, $oldMatches);
    $playersCache[$id][$eloField]   = $newElo;
    $playersCache[$id][$matchField] = $oldMatches + 1;

    $winAdd  = $isWin ? 1 : 0;
    $loseAdd = $isWin ? 0 : 1;
    $sql = "UPDATE giocatori SET $eloField = ?, $matchField = $matchField + 1,
            partite_totali = partite_totali + 1,
            $winField = $winField + ?, $loseField = $loseField + ?,
            vittorie_totali = vittorie_totali + ?, sconfitte_totali = sconfitte_totali + ?
            WHERE id = ?";
    $conn->prepare($sql)->execute([$newElo, $winAdd, $loseAdd, $winAdd, $loseAdd, $id]);
}

function processMatchRec($conn, $dati, $matchId) {
    $eloF = getEloFantasmaRec();
    $ids  = array_merge($dati['squadra1'], $dati['squadra2']);
    $gs   = [];
    foreach ($ids as $id) {
        if ($id == 9999) {
            $gs[$id] = ['id' => 9999, 'elo_portiere' => $eloF, 'elo_attaccante' => $eloF,
                        'partite_portiere' => 0, 'partite_attaccante' => 0];
        } else {
            $stmt = $conn->prepare("SELECT * FROM giocatori WHERE id = ?");
            $stmt->execute([$id]);
            $gs[$id] = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    $s1p = $gs[$dati['squadra1'][0]]; $s1a = $gs[$dati['squadra1'][1]];
    $s2p = $gs[$dati['squadra2'][0]]; $s2a = $gs[$dati['squadra2'][1]];
    $res1 = $dati['vincitore'] == 1 ? 1 : 0;
    $res2 = $dati['vincitore'] == 2 ? 1 : 0;

    $eloBase_s1p = $s1p['partite_portiere']   > 0 ? $s1p['elo_portiere']   : 1500;
    $eloBase_s1a = $s1a['partite_attaccante'] > 0 ? $s1a['elo_attaccante'] : 1500;
    $eloBase_s2p = $s2p['partite_portiere']   > 0 ? $s2p['elo_portiere']   : 1500;
    $eloBase_s2a = $s2a['partite_attaccante'] > 0 ? $s2a['elo_attaccante'] : 1500;

    $eloMedioSq1 = ($eloBase_s1p + $eloBase_s1a) / 2;
    $eloMedioSq2 = ($eloBase_s2p + $eloBase_s2a) / 2;

    $nElo_s1p = calcolaEloRec($eloBase_s1p, $eloMedioSq1, $eloMedioSq2, $res1, $s1p['partite_portiere']);
    $nElo_s1a = calcolaEloRec($eloBase_s1a, $eloMedioSq1, $eloMedioSq2, $res1, $s1a['partite_attaccante']);
    $nElo_s2p = calcolaEloRec($eloBase_s2p, $eloMedioSq2, $eloMedioSq1, $res2, $s2p['partite_portiere']);
    $nElo_s2a = calcolaEloRec($eloBase_s2a, $eloMedioSq2, $eloMedioSq1, $res2, $s2a['partite_attaccante']);

    updatePlayerRoleRec($conn, $s1p['id'], 'elo_portiere',   $nElo_s1p, 'vittorie_portiere',   'sconfitte_portiere',   'partite_portiere',   $res1);
    updatePlayerRoleRec($conn, $s2p['id'], 'elo_portiere',   $nElo_s2p, 'vittorie_portiere',   'sconfitte_portiere',   'partite_portiere',   $res2);
    updatePlayerRoleRec($conn, $s1a['id'], 'elo_attaccante', $nElo_s1a, 'vittorie_attaccante', 'sconfitte_attaccante', 'partite_attaccante', $res1);
    updatePlayerRoleRec($conn, $s2a['id'], 'elo_attaccante', $nElo_s2a, 'vittorie_attaccante', 'sconfitte_attaccante', 'partite_attaccante', $res2);

    $deltas = [
        's1p' => $nElo_s1p - $eloBase_s1p,
        's1a' => $nElo_s1a - $eloBase_s1a,
        's2p' => $nElo_s2p - $eloBase_s2p,
        's2a' => $nElo_s2a - $eloBase_s2a,
    ];
    $conn->prepare("UPDATE partite SET elo_delta_s1p=?, elo_delta_s1a=?, elo_delta_s2p=?, elo_delta_s2a=? WHERE id=?")
         ->execute([$deltas['s1p'], $deltas['s1a'], $deltas['s2p'], $deltas['s2a'], $matchId]);
}
?>

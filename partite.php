<?php
session_start();
date_default_timezone_set('Europe/Rome');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'database.php';
require_once 'season_pass_engine.php';
require_once 'objectives_engine.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Connessione al database fallita', 'message' => $e->getMessage()]);
    exit();
}

define('GHOST_ID', 9999);

function isAdmin($conn) {
    if (!isset($_SESSION['user_id'])) return false;
    $stmt = $conn->prepare("SELECT is_admin FROM giocatori WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return (bool)$stmt->fetchColumn();
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        handleGetPartite($conn);
        break;
    case 'POST':
        if (!isAdmin($conn)) {
            http_response_code(403);
            echo json_encode(['error' => 'Accesso negato: Solo Admin']);
            exit;
        }
        handleCreatePartita($conn);
        break;
    case 'DELETE':
        if (!isAdmin($conn)) {
            http_response_code(403);
            echo json_encode(['error' => 'Accesso negato: Solo Admin']);
            exit;
        }
        handleDeletePartita($conn);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Metodo non permesso']);
}

function handleGetPartite($conn) {
    try {
        $sql = "SELECT 
                    p.id, 
                    p.stagione_id,
                    p.squadra1_portiere, p.squadra1_attaccante, 
                    p.squadra2_portiere, p.squadra2_attaccante, 
                    p.vincitore, 
                    p.data,
                    p.score_s1, p.score_s2,
                    p.elo_delta_s1p, p.elo_delta_s1a, p.elo_delta_s2p, p.elo_delta_s2a,
                    g1p.nome as squadra1_portiere_nome,
                    g1a.nome as squadra1_attaccante_nome,
                    g2p.nome as squadra2_portiere_nome,
                    g2a.nome as squadra2_attaccante_nome,
                    g1p.avatar_url as avatar_s1p,
                    g1a.avatar_url as avatar_s1a,
                    g2p.avatar_url as avatar_s2p,
                    g2a.avatar_url as avatar_s2a,
                    g1p.active_name_color as color_s1p,
                    g1a.active_name_color as color_s1a,
                    g2p.active_name_color as color_s2p,
                    g2a.active_name_color as color_s2a,
                    g1p.active_name_style as style_s1p,
                    g1a.active_name_style as style_s1a,
                    g2p.active_name_style as style_s2p,
                    g2a.active_name_style as style_s2a,
                    g1p.active_aura as aura_s1p,
                    g1a.active_aura as aura_s1a,
                    g2p.active_aura as aura_s2p,
                    g2a.active_aura as aura_s2a,
                    (SELECT GROUP_CONCAT(CONCAT(lmb.user_id, ':', si.icon) SEPARATOR '|') 
                     FROM live_match_bonuses lmb 
                     JOIN shop_items si ON lmb.item_key = si.key_name 
                     WHERE lmb.match_id = p.id AND lmb.status = 'used') as bonuses_detailed
                FROM partite p
                LEFT JOIN giocatori g1p ON p.squadra1_portiere = g1p.id
                LEFT JOIN giocatori g1a ON p.squadra1_attaccante = g1a.id
                LEFT JOIN giocatori g2p ON p.squadra2_portiere = g2p.id
                LEFT JOIN giocatori g2a ON p.squadra2_attaccante = g2a.id
                ORDER BY p.data DESC";

        $stmt = $conn->prepare($sql);
        $stmt->execute();

        $partite = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $partiteFormatted = [];
        foreach ($partite as $partita) {
            $partiteFormatted[] = [
                'id' => (int)$partita['id'],
                'stagione_id' => $partita['stagione_id'],
                'squadra1_portiere' => (int)$partita['squadra1_portiere'],
                'squadra1_attaccante' => (int)$partita['squadra1_attaccante'],
                'squadra2_portiere' => (int)$partita['squadra2_portiere'],
                'squadra2_attaccante' => (int)$partita['squadra2_attaccante'],
                'vincitore' => (int)$partita['vincitore'],
                'data' => $partita['data'],
                'score_s1' => $partita['score_s1'] !== null ? (int)$partita['score_s1'] : null,
                'score_s2' => $partita['score_s2'] !== null ? (int)$partita['score_s2'] : null,
                'bonuses' => array_filter(array_map(function($b) {
                    $parts = explode(':', $b);
                    return count($parts) === 2 ? ['user_id' => (int)$parts[0], 'icon' => $parts[1]] : null;
                }, explode('|', $partita['bonuses_detailed'] ?? ''))),
                'nomi_giocatori' => [
                    'squadra1_portiere' => $partita['squadra1_portiere_nome'],
                    'squadra1_attaccante' => $partita['squadra1_attaccante_nome'],
                    'squadra2_portiere' => $partita['squadra2_portiere_nome'],
                    'squadra2_attaccante' => $partita['squadra2_attaccante_nome'],
                    'avatar_s1p' => $partita['avatar_s1p'],
                    'avatar_s1a' => $partita['avatar_s1a'],
                    'avatar_s2p' => $partita['avatar_s2p'],
                    'avatar_s2a' => $partita['avatar_s2a'],
                    'color_s1p' => $partita['color_s1p'],
                    'color_s1a' => $partita['color_s1a'],
                    'color_s2p' => $partita['color_s2p'],
                    'color_s2a' => $partita['color_s2a'],
                    'style_s1p' => $partita['style_s1p'],
                    'style_s1a' => $partita['style_s1a'],
                    'style_s2p' => $partita['style_s2p'],
                    'style_s2a' => $partita['style_s2a'],
                    'aura_s1p' => $partita['aura_s1p'],
                    'aura_s1a' => $partita['aura_s1a'],
                    'aura_s2p' => $partita['aura_s2p'],
                    'aura_s2a' => $partita['aura_s2a']
                ],
                'elo_deltas' => [
                    's1p' => (int)$partita['elo_delta_s1p'],
                    's1a' => (int)$partita['elo_delta_s1a'],
                    's2p' => (int)$partita['elo_delta_s2p'],
                    's2a' => (int)$partita['elo_delta_s2a']
                ]
            ];
        }

        echo json_encode($partiteFormatted);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Query failed', 'message' => $e->getMessage()]);
    }
}

function handleCreatePartita($conn) {
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

        if (!isset($data['squadra1']) || !isset($data['squadra2']) || !isset($data['vincitore'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            return;
        }

        if (
            !is_array($data['squadra1']) || count($data['squadra1']) !== 2 ||
            !is_array($data['squadra2']) || count($data['squadra2']) !== 2
        ) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid team format']);
            return;
        }

        if (!in_array($data['vincitore'], [1, 2])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid vincitore']);
            return;
        }

        $allPlayers = array_merge($data['squadra1'], $data['squadra2']);
        if (count(array_unique($allPlayers)) !== 4) {
            http_response_code(400);
            echo json_encode(['error' => 'Players must be unique']);
            return;
        }

        $conn->beginTransaction();

        try {
            $dataCorrente = date('Y-m-d H:i:s');
            
            $stmtStagione = $conn->query("SELECT id FROM stagioni WHERE is_active = 1 LIMIT 1");
            $activeSeasonId = $stmtStagione->fetchColumn() ?: 1;

            $sql = "INSERT INTO partite (squadra1_portiere, squadra1_attaccante, squadra2_portiere, squadra2_attaccante, vincitore, data, score_s1, score_s2, stagione_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $data['squadra1'][0],
                $data['squadra1'][1],
                $data['squadra2'][0],
                $data['squadra2'][1],
                $data['vincitore'],
                $dataCorrente,
                null, // score_s1
                null,  // score_s2
                $activeSeasonId
            ]);

            $newPartitaId = $conn->lastInsertId();

            $deltas = aggiornaEloEStatistiche($conn, $data);
            
            // Update match with deltas
            $sqlUpdate = "UPDATE partite SET elo_delta_s1p=?, elo_delta_s1a=?, elo_delta_s2p=?, elo_delta_s2a=? WHERE id=?";
            $conn->prepare($sqlUpdate)->execute([$deltas['s1p'], $deltas['s1a'], $deltas['s2p'], $deltas['s2a'], $newPartitaId]);

            $pWin = ($data['vincitore'] == 1) ? $data['squadra1'] : $data['squadra2'];
            $pLoss = ($data['vincitore'] == 1) ? $data['squadra2'] : $data['squadra1'];
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

            // Mark bonuses as used
            $conn->exec("UPDATE live_match_bonuses SET status = 'used' WHERE match_id = 1 AND status = 'active'");

            $conn->commit();

            echo json_encode(['success' => true, 'id' => (int)$newPartitaId]);
        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

function handleDeletePartita($conn) {
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

        if (!isset($data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing match ID']);
            return;
        }

        $matchId = (int)$data['id'];

        $sql = "SELECT p.*, 
                       g1p.nome as squadra1_portiere_nome,
                       g1a.nome as squadra1_attaccante_nome,
                       g2p.nome as squadra2_portiere_nome,
                       g2a.nome as squadra2_attaccante_nome
                FROM partite p
                JOIN giocatori g1p ON p.squadra1_portiere = g1p.id
                JOIN giocatori g1a ON p.squadra1_attaccante = g1a.id
                JOIN giocatori g2p ON p.squadra2_portiere = g2p.id
                JOIN giocatori g2a ON p.squadra2_attaccante = g2a.id
                WHERE p.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$matchId]);
        $match = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$match) {
            http_response_code(404);
            echo json_encode(['error' => 'Match not found']);
            return;
        }

        $conn->beginTransaction();

        try {
            reverseEloCalculation($conn, $match);

            $sql = "DELETE FROM partite WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$matchId]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Failed to delete match');
            }

            $conn->commit();

            $matchDate = date('d/m/Y H:i', strtotime($match['data']));
            $squadra1 = $match['squadra1_portiere_nome'] . ' & ' . $match['squadra1_attaccante_nome'];
            $squadra2 = $match['squadra2_portiere_nome'] . ' & ' . $match['squadra2_attaccante_nome'];

            echo json_encode([
                'success' => true,
                'message' => "Partita del {$matchDate} eliminata con successo",
                'deleted_match' => [
                    'id' => (int)$match['id'],
                    'data' => $matchDate,
                    'squadra1' => $squadra1,
                    'squadra2' => $squadra2,
                    'vincitore' => (int)$match['vincitore']
                ]
            ]);
        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

function getKFactorProgressivo($partiteGiocate) {
    if ($partiteGiocate < 10) return 40;
    if ($partiteGiocate < 20) return 35;
    if ($partiteGiocate < 30) return 30;
    if ($partiteGiocate < 40) return 25;
    if ($partiteGiocate < 50) return 20;
    return 16;
}

function reverseEloCalculation($conn, $match) {
    // Delta-based reversal: elo_precedente = elo_attuale - delta_salvato
    // Matematicamente esatto: nessuna dipendenza da K-Factor o Elo storico avversari.
    $risultato1 = $match['vincitore'] == 1 ? 1 : 0;
    $risultato2 = $match['vincitore'] == 2 ? 1 : 0;

    $slots = [
        ['id' => $match['squadra1_portiere'],   'role' => 'portiere',   'delta' => (int)$match['elo_delta_s1p'], 'isWin' => $risultato1],
        ['id' => $match['squadra1_attaccante'], 'role' => 'attaccante', 'delta' => (int)$match['elo_delta_s1a'], 'isWin' => $risultato1],
        ['id' => $match['squadra2_portiere'],   'role' => 'portiere',   'delta' => (int)$match['elo_delta_s2p'], 'isWin' => $risultato2],
        ['id' => $match['squadra2_attaccante'], 'role' => 'attaccante', 'delta' => (int)$match['elo_delta_s2a'], 'isWin' => $risultato2],
    ];

    foreach ($slots as $slot) {
        $playerId = (int)$slot['id'];
        if ($playerId == GHOST_ID || $playerId <= 0) continue;

        $eloField   = 'elo_'       . $slot['role'];
        $matchField = 'partite_'   . $slot['role'];
        $winField   = 'vittorie_'  . $slot['role'];
        $loseField  = 'sconfitte_' . $slot['role'];
        $winDec     = $slot['isWin'] ? 1 : 0;
        $loseDec    = $slot['isWin'] ? 0 : 1;

        $stmtP = $conn->prepare("SELECT $eloField, $matchField FROM giocatori WHERE id = ?");
        $stmtP->execute([$playerId]);
        $row = $stmtP->fetch(PDO::FETCH_ASSOC);
        if (!$row) continue;

        $currentMatches = (int)$row[$matchField];

        if ($currentMatches <= 1) {
            $sql = "UPDATE giocatori SET
                        $eloField = 1500,
                        $matchField = 0,
                        partite_totali   = GREATEST(0, partite_totali   - 1),
                        $winField  = 0,
                        $loseField = 0,
                        vittorie_totali  = GREATEST(0, vittorie_totali  - ?),
                        sconfitte_totali = GREATEST(0, sconfitte_totali - ?)
                    WHERE id = ?";
            $conn->prepare($sql)->execute([$winDec, $loseDec, $playerId]);
        } else {
            $prevElo = max(100, min(3000, $row[$eloField] - $slot['delta']));
            $sql = "UPDATE giocatori SET
                        $eloField = ?,
                        $matchField      = GREATEST(0, $matchField      - 1),
                        partite_totali   = GREATEST(0, partite_totali   - 1),
                        $winField        = GREATEST(0, $winField        - ?),
                        $loseField       = GREATEST(0, $loseField       - ?),
                        vittorie_totali  = GREATEST(0, vittorie_totali  - ?),
                        sconfitte_totali = GREATEST(0, sconfitte_totali - ?)
                    WHERE id = ?";
            $conn->prepare($sql)->execute([$prevElo, $winDec, $loseDec, $winDec, $loseDec, $playerId]);
        }
    }
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

function aggiornaEloEStatistiche($conn, $datiPartita) {
    $eloFantasma = getEloMedioPesatoOverall($conn);
    $giocatori = [];
    $allIds = array_merge($datiPartita['squadra1'], $datiPartita['squadra2']);

    foreach ($allIds as $id) {
        if ($id == GHOST_ID) {
            $giocatori[$id] = [
                'id' => GHOST_ID,
                'elo_portiere' => $eloFantasma,
                'elo_attaccante' => $eloFantasma,
                'partite_portiere' => 0,
                'partite_attaccante' => 0
            ];
        } else {
            $sql = "SELECT * FROM giocatori WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id]);
            $giocatori[$id] = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    $squadra1_portiere = $giocatori[$datiPartita['squadra1'][0]];
    $squadra1_attaccante = $giocatori[$datiPartita['squadra1'][1]];
    $squadra2_portiere = $giocatori[$datiPartita['squadra2'][0]];
    $squadra2_attaccante = $giocatori[$datiPartita['squadra2'][1]];

    // Fetch Active Bonuses
    $stmt = $conn->query("SELECT user_id, item_key FROM live_match_bonuses WHERE match_id = 1 AND status = 'active'");
    $bonuses = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN); 
    // Format: [user_id => ['x2_elo', ...]]

    $getMult = function($id) use ($bonuses) {
        if (isset($bonuses[$id]) && in_array('x2_elo', $bonuses[$id])) return 2;
        return 1;
    };

    $res1 = $datiPartita['vincitore'] == 1 ? 1 : 0;
    $res2 = $datiPartita['vincitore'] == 2 ? 1 : 0;

    // Elo di base per ogni slot (fallback a 1500 se nessuna partita giocata)
    $eloBase_s1p = $squadra1_portiere['partite_portiere']     > 0 ? $squadra1_portiere['elo_portiere']     : 1500;
    $eloBase_s1a = $squadra1_attaccante['partite_attaccante'] > 0 ? $squadra1_attaccante['elo_attaccante'] : 1500;
    $eloBase_s2p = $squadra2_portiere['partite_portiere']     > 0 ? $squadra2_portiere['elo_portiere']     : 1500;
    $eloBase_s2a = $squadra2_attaccante['partite_attaccante'] > 0 ? $squadra2_attaccante['elo_attaccante'] : 1500;

    // Media Elo di ogni squadra (avversario percepito da chi gioca vs di loro)
    $eloMedioSq1 = ($eloBase_s1p + $eloBase_s1a) / 2;
    $eloMedioSq2 = ($eloBase_s2p + $eloBase_s2a) / 2;

    // I giocatori della sq1 giocano contro la media della sq2, e viceversa
    $nuovoElo_portiere1   = calcolaEloProgressivo($eloBase_s1p, $eloMedioSq1, $eloMedioSq2, $res1, $squadra1_portiere['partite_portiere'],   $getMult($squadra1_portiere['id']));
    $nuovoElo_attaccante1 = calcolaEloProgressivo($eloBase_s1a, $eloMedioSq1, $eloMedioSq2, $res1, $squadra1_attaccante['partite_attaccante'], $getMult($squadra1_attaccante['id']));
    $nuovoElo_portiere2   = calcolaEloProgressivo($eloBase_s2p, $eloMedioSq2, $eloMedioSq1, $res2, $squadra2_portiere['partite_portiere'],   $getMult($squadra2_portiere['id']));
    $nuovoElo_attaccante2 = calcolaEloProgressivo($eloBase_s2a, $eloMedioSq2, $eloMedioSq1, $res2, $squadra2_attaccante['partite_attaccante'], $getMult($squadra2_attaccante['id']));

    updatePlayerRole($conn, $squadra1_portiere['id'], 'elo_portiere', $nuovoElo_portiere1, 'vittorie_portiere', 'sconfitte_portiere', 'partite_portiere', $res1);
    updatePlayerRole($conn, $squadra2_portiere['id'], 'elo_portiere', $nuovoElo_portiere2, 'vittorie_portiere', 'sconfitte_portiere', 'partite_portiere', $res2);
    updatePlayerRole($conn, $squadra1_attaccante['id'], 'elo_attaccante', $nuovoElo_attaccante1, 'vittorie_attaccante', 'sconfitte_attaccante', 'partite_attaccante', $res1);
    updatePlayerRole($conn, $squadra2_attaccante['id'], 'elo_attaccante', $nuovoElo_attaccante2, 'vittorie_attaccante', 'sconfitte_attaccante', 'partite_attaccante', $res2);

    $eloBase_s1p = $squadra1_portiere['partite_portiere']     > 0 ? $squadra1_portiere['elo_portiere']     : 1500;
    $eloBase_s1a = $squadra1_attaccante['partite_attaccante'] > 0 ? $squadra1_attaccante['elo_attaccante'] : 1500;
    $eloBase_s2p = $squadra2_portiere['partite_portiere']     > 0 ? $squadra2_portiere['elo_portiere']     : 1500;
    $eloBase_s2a = $squadra2_attaccante['partite_attaccante'] > 0 ? $squadra2_attaccante['elo_attaccante'] : 1500;

    return [
        's1p' => $nuovoElo_portiere1   - $eloBase_s1p,
        's1a' => $nuovoElo_attaccante1 - $eloBase_s1a,
        's2p' => $nuovoElo_portiere2   - $eloBase_s2p,
        's2a' => $nuovoElo_attaccante2 - $eloBase_s2a
    ];
}

function updatePlayerRole($conn, $id, $eloField, $newElo, $winField, $loseField, $matchField, $isWin) {
    if ($id == GHOST_ID) return;
    $winAdd = $isWin ? 1 : 0;
    $loseAdd = $isWin ? 0 : 1;
    $sql = "UPDATE giocatori SET $eloField = ?, $matchField = $matchField + 1, partite_totali = partite_totali + 1, $winField = $winField + ?, $loseField = $loseField + ?, vittorie_totali = vittorie_totali + ?, sconfitte_totali = sconfitte_totali + ? WHERE id = ?";
    $conn->prepare($sql)->execute([$newElo, $winAdd, $loseAdd, $winAdd, $loseAdd, $id]);
}

function calcolaEloProgressivo($eloGiocatore, $eloPropriaSquadra, $eloSfidanteSquadra, $risultato, $partiteGiocate, $multiplier = 1) {
    $K = getKFactorProgressivo($partiteGiocate) * $multiplier;
    $expected = 1 / (1 + pow(10, ($eloSfidanteSquadra - $eloPropriaSquadra) / 400));
    $newElo = round($eloGiocatore + $K * ($risultato - $expected));
    return max(100, min(3000, $newElo));
}
?>
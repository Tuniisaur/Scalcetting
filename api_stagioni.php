<?php
require_once 'session_config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';
    
    if ($action === 'list') {
        try {
            $stmt = $conn->query("SELECT * FROM stagioni ORDER BY id DESC");
            $stagioni = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $stagioni]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
        }
    } elseif ($action === 'leaderboard') {
        $stagione_id = (int)($_GET['stagione_id'] ?? 0);
        if (!$stagione_id) {
            echo json_encode(['error' => 'Missing stagione_id']);
            exit;
        }
        
        try {
            // Check if it's the active season. If so, return players from 'giocatori'
            $stmt = $conn->prepare("SELECT is_active FROM stagioni WHERE id = ?");
            $stmt->execute([$stagione_id]);
            $isActive = $stmt->fetchColumn();
            
            if ($isActive == 1) {
                // Return current players (similar to giocatori.php GET)
                $sql = "SELECT 
                            id, nome, username, avatar_url, crediti,
                            CASE WHEN partite_portiere > 0 THEN elo_portiere ELSE NULL END as display_elo_portiere,
                            CASE WHEN partite_attaccante > 0 THEN elo_attaccante ELSE NULL END as display_elo_attaccante,
                            CASE 
                                WHEN partite_portiere > 0 AND partite_attaccante > 0 THEN GREATEST(elo_portiere, elo_attaccante)
                                WHEN partite_portiere > 0 THEN elo_portiere
                                WHEN partite_attaccante > 0 THEN elo_attaccante
                                ELSE 1500 
                            END as elo_medio,
                            partite_portiere, partite_attaccante, partite_totali,
                            vittorie_portiere, vittorie_attaccante, vittorie_totali,
                            sconfitte_portiere, sconfitte_attaccante, sconfitte_totali
                        FROM giocatori 
                        WHERE id != 9999
                        ORDER BY 
                            CASE 
                                WHEN partite_portiere > 0 AND partite_attaccante > 0 THEN GREATEST(elo_portiere, elo_attaccante)
                                WHEN partite_portiere > 0 THEN elo_portiere
                                WHEN partite_attaccante > 0 THEN elo_attaccante
                                ELSE 1500 
                            END DESC";
                $giocatori = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Return historical stats
                $sql = "SELECT 
                            g.id, g.nome, g.username, g.avatar_url, g.crediti,
                            CASE WHEN s.partite_portiere > 0 THEN s.elo_portiere ELSE NULL END as display_elo_portiere,
                            CASE WHEN s.partite_attaccante > 0 THEN s.elo_attaccante ELSE NULL END as display_elo_attaccante,
                            CASE 
                                WHEN s.partite_portiere > 0 AND s.partite_attaccante > 0 THEN GREATEST(s.elo_portiere, s.elo_attaccante)
                                WHEN s.partite_portiere > 0 THEN s.elo_portiere
                                WHEN s.partite_attaccante > 0 THEN s.elo_attaccante
                                ELSE 1500 
                            END as elo_medio,
                            s.partite_portiere, s.partite_attaccante, s.partite_totali,
                            s.vittorie_portiere, s.vittorie_attaccante, s.vittorie_totali,
                            s.sconfitte_portiere, s.sconfitte_attaccante, s.sconfitte_totali
                        FROM statistiche_stagioni s
                        JOIN giocatori g ON s.giocatore_id = g.id
                        WHERE s.stagione_id = ? AND g.id != 9999
                        ORDER BY 
                            CASE 
                                WHEN s.partite_portiere > 0 AND s.partite_attaccante > 0 THEN GREATEST(s.elo_portiere, s.elo_attaccante)
                                WHEN s.partite_portiere > 0 THEN s.elo_portiere
                                WHEN s.partite_attaccante > 0 THEN s.elo_attaccante
                                ELSE 1500 
                            END DESC";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$stagione_id]);
                $giocatori = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // Cast types
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
            }
            
            echo json_encode(['success' => true, 'data' => $giocatori]);
            
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
        }
    } elseif ($action === 'player_history') {
        $giocatore_id = (int)($_GET['id'] ?? 0);
        if (!$giocatore_id) {
            echo json_encode(['error' => 'Missing giocatore_id']);
            exit;
        }

        try {
            // Fetch past seasons this player participated in
            $sql = "SELECT s.*, 
                           st.id as stagione_id, st.nome, st.data_inizio, st.data_fine
                    FROM statistiche_stagioni s
                    JOIN stagioni st ON s.stagione_id = st.id
                    WHERE s.giocatore_id = ? AND s.partite_totali > 0
                    ORDER BY st.id DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$giocatore_id]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Determine if the player won any of these seasons
            foreach ($history as &$seasonStat) {
                // Calculate Elo Medio for the player in that season
                $playerEloMedio = 1500;
                if ($seasonStat['partite_portiere'] > 0 && $seasonStat['partite_attaccante'] > 0) {
                    $playerEloMedio = max($seasonStat['elo_portiere'], $seasonStat['elo_attaccante']);
                } elseif ($seasonStat['partite_portiere'] > 0) {
                    $playerEloMedio = $seasonStat['elo_portiere'];
                } elseif ($seasonStat['partite_attaccante'] > 0) {
                    $playerEloMedio = $seasonStat['elo_attaccante'];
                }

                // Check if anyone in that season had a higher Elo Medio
                $sqlWinner = "SELECT MAX(
                                CASE 
                                    WHEN partite_portiere > 0 AND partite_attaccante > 0 THEN GREATEST(elo_portiere, elo_attaccante)
                                    WHEN partite_portiere > 0 THEN elo_portiere
                                    WHEN partite_attaccante > 0 THEN elo_attaccante
                                    ELSE 1500 
                                END
                              ) as max_elo
                              FROM statistiche_stagioni
                              WHERE stagione_id = ?";
                $stmtWinner = $conn->prepare($sqlWinner);
                $stmtWinner->execute([$seasonStat['stagione_id']]);
                $maxElo = $stmtWinner->fetchColumn();

                // If player's Elo Medio matches the max Elo for that season, they are a winner
                $seasonStat['is_winner'] = ($playerEloMedio >= $maxElo && $maxElo > 1500) ? 1 : 0;
                $seasonStat['elo_medio'] = $playerEloMedio;

                // Calculate Rank
                $sqlRank = "SELECT COUNT(*) + 1 FROM statistiche_stagioni 
                            WHERE stagione_id = ? AND (
                                CASE 
                                    WHEN partite_portiere > 0 AND partite_attaccante > 0 THEN GREATEST(elo_portiere, elo_attaccante)
                                    WHEN partite_portiere > 0 THEN elo_portiere
                                    WHEN partite_attaccante > 0 THEN elo_attaccante
                                    ELSE 1500 
                                END
                            ) > ?";
                $stmtRank = $conn->prepare($sqlRank);
                $stmtRank->execute([$seasonStat['stagione_id'], $playerEloMedio]);
                $seasonStat['rank'] = (int)$stmtRank->fetchColumn();
            }

            echo json_encode(['success' => true, 'data' => $history]);

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['error' => 'Invalid action']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isAdmin($conn)) {
        http_response_code(403);
        echo json_encode(['error' => 'Access action denied: Solo Admin', 'd' => $_SESSION]);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'end_season') {
        try {
            $conn->beginTransaction();
            
            // 1. Find active season
            $stmt = $conn->query("SELECT id FROM stagioni WHERE is_active = 1 LIMIT 1 FOR UPDATE");
            $oldSeasonId = $stmt->fetchColumn();
            
            if (!$oldSeasonId) {
                $conn->exec("INSERT INTO stagioni (nome, is_active) VALUES ('Stagione 1', 1)");
                $conn->rollBack();
                echo json_encode(['error' => 'Nessuna stagione attiva trovata. Creato default. Riprova.']);
                exit;
            }
            
            // 2. Snapshot players into statistiche_stagioni
            $sql = "INSERT IGNORE INTO statistiche_stagioni 
                    (stagione_id, giocatore_id, elo_portiere, elo_attaccante, partite_portiere, partite_attaccante, vittorie_portiere, vittorie_attaccante, sconfitte_portiere, sconfitte_attaccante, partite_totali, vittorie_totali, sconfitte_totali)
                    SELECT ?, id, elo_portiere, elo_attaccante, partite_portiere, partite_attaccante, vittorie_portiere, vittorie_attaccante, sconfitte_portiere, sconfitte_attaccante, partite_totali, vittorie_totali, sconfitte_totali
                    FROM giocatori
                    WHERE id != 9999 AND partite_totali > 0";
            $stmtInsert = $conn->prepare($sql);
            $stmtInsert->execute([$oldSeasonId]);
            
            // 3. Mark old season as inactive
            $stmtClose = $conn->prepare("UPDATE stagioni SET is_active = 0, data_fine = CURRENT_TIMESTAMP WHERE id = ?");
            $stmtClose->execute([$oldSeasonId]);
            
            // 4. Create new season
            $stmtCount = $conn->query("SELECT COUNT(*) FROM stagioni");
            $seasonNumber = $stmtCount->fetchColumn() + 1;
            $newSeasonName = "Stagione " . $seasonNumber;
            
            $stmtNew = $conn->prepare("INSERT INTO stagioni (nome, is_active) VALUES (?, 1)");
            $stmtNew->execute([$newSeasonName]);
            
            // 5. Reset giocatori (keep crediti, id, ecc)
            $conn->exec("UPDATE giocatori SET 
                            elo_portiere = 1500, elo_attaccante = 1500,
                            partite_portiere = 0, partite_attaccante = 0,
                            vittorie_portiere = 0, vittorie_attaccante = 0,
                            sconfitte_portiere = 0, sconfitte_attaccante = 0,
                            partite_totali = 0, vittorie_totali = 0, sconfitte_totali = 0,
                            xp = 0, level = 1
                         WHERE id != 9999");

            // 6. Reset Season Pass progress
            $conn->exec("DELETE FROM user_objectives");
            $conn->exec("DELETE FROM user_season_claimed");
                         
            $conn->commit();
            
            echo json_encode(['success' => true, 'message' => "Stagione conclusa! Iniziata la $newSeasonName"]);
            
        } catch (PDOException $e) {
            $conn->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['error' => 'Invalid action']);
    }
}
?>

<?php
class Database
{
    private $host = '';
    private $db_name = '';
    private $username = '';
    private $password = '';
    public $conn; // database connection
    private $isLocal = false;

    public function __construct() {
        // Rilevamento ambiente (Locale vs Remoto)
        $this->isLocal = ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_ADDR'] === '127.0.0.1' || $_SERVER['HTTP_HOST'] === 'localhost');
        
        // Se siamo in locale, sovrascriviamo le credenziali
        if ($this->isLocal) {
            $this->host = 'localhost';
            $this->db_name = 'scalcetting_db'; // Cambia se il tuo db locale ha un altro nome
            $this->username = 'root';
            $this->password = '';
        }

        $this->getConnection();
    }

    public function getConnection()
    {
        $this->conn = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]);
        }
        catch (PDOException $exception) {
            // Se in locale fallisce il database, prova con un altro nome comune
            if ($this->isLocal && $this->db_name === 'scalcetting_db' && ($exception->getCode() == 1049 || $exception->getCode() == '42000')) {
                 try {
                     $this->db_name = 'scalcetting';
                     return $this->getConnection();
                 } catch (Exception $e2) {
                     // Se fallisce anche questo, riportiamo l'errore originale
                 }
            }

            error_log("Database connection error: " . $exception->getMessage());
            
            $isApi = isset($_GET['api']) || strpos($_SERVER['REQUEST_URI'], '_api.php') !== false;

            if ($isApi) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(500);
                echo json_encode([
                    'success' => false, 
                    'error' => 'Errore database', 
                    'detail' => $exception->getMessage()
                ]);
            } else {
                echo "<div style='padding:20px; font-family:sans-serif; color:#721c24; background:#f8d7da; border:1px solid #f5c6cb; border-radius:5px;'>";
                echo "<strong>Errore Critico:</strong> Impossibile connettersi al database.<br>";
                echo "<strong>Dettaglio:</strong> " . htmlspecialchars($exception->getMessage()) . "<br>";
                echo "<strong>Host:</strong> " . htmlspecialchars($this->host) . " | <strong>DB:</strong> " . htmlspecialchars($this->db_name);
                echo "</div>";
            }
            exit();
        }
        return $this->conn;
    }

    public function closeConnection()
    {
        $this->conn = null;
    }
}

/**
 * Identifica il leader attuale della classifica generale e il suo ruolo migliore.
 */
function getBountyInfo($conn)
{
    // Il leader è calcolato sulla media ELO degli attaccanti e portieri (classifica Generale)
    $stmt = $conn->prepare("SELECT id, elo_attaccante, elo_portiere FROM giocatori 
                          WHERE id != 9999 AND partite_totali > 0 
                          ORDER BY (elo_attaccante + elo_portiere) / 2 DESC 
                          LIMIT 1");
    $stmt->execute();
    $leader = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$leader)
        return ['leaderId' => 0, 'bestRole' => null];

    $bestRole = ($leader['elo_attaccante'] >= $leader['elo_portiere']) ? 'atk' : 'def';

    return [
        'leaderId' => (int)$leader['id'],
        'bestRole' => $bestRole
    ];
}

/**
 * Assegna le Strisciate per la vittoria, inclusi i 50 extra per la taglia.
 */
function awardMatchCredits($conn, $winners, $losers)
{
    // $winners e $losers sono array [id_portiere, id_attaccante]
    $bounty = getBountyInfo($conn);
    $isBountyWon = false;

    if ($bounty['leaderId'] > 0) {
        // La taglia è vinta se il leader perde giocando nel suo ruolo migliore
        if ($bounty['bestRole'] === 'atk' && $losers[1] === $bounty['leaderId']) {
            $isBountyWon = true;
        }
        elseif ($bounty['bestRole'] === 'def' && $losers[0] === $bounty['leaderId']) {
            $isBountyWon = true;
        }
    }

    $reward = 5 + ($isBountyWon ? 50 : 0);

    foreach ($winners as $pw) {
        if ($pw != 9999 && $pw > 0) {
            $conn->prepare("UPDATE giocatori SET crediti = crediti + ? WHERE id = ?")->execute([$reward, $pw]);
        }
    }

    return $isBountyWon;
}

/**
 * Arricchisce i dati dei giocatori con titoli dinamici (es. Il Bomber).
 * @param array &$giocatori Array di giocatori (reference)
 * @param PDO $conn Connessione al database
 */
function enrichPlayerData(&$giocatori, $conn) {
    if (empty($giocatori)) return;

    // 1. Trova il Bomber (Elo Attaccante più alto)
    $stmtB = $conn->prepare("SELECT id FROM giocatori 
                          WHERE id != 9999 AND partite_attaccante > 0 
                          ORDER BY elo_attaccante DESC 
                          LIMIT 1");
    $stmtB->execute();
    $bomberId = $stmtB->fetchColumn();

    // 2. Trova la Saracinesca (Elo Portiere più alto)
    $stmtS = $conn->prepare("SELECT id FROM giocatori 
                          WHERE id != 9999 AND partite_portiere > 0 
                          ORDER BY elo_portiere DESC 
                          LIMIT 1");
    $stmtS->execute();
    $saracinescaId = $stmtS->fetchColumn();

    // 3. Trova Controcampo (Elo Attaccante più basso)
    $stmtC = $conn->prepare("SELECT id FROM giocatori 
                          WHERE id != 9999 AND partite_attaccante > 0 
                          ORDER BY elo_attaccante ASC 
                          LIMIT 1");
    $stmtC->execute();
    $controcampoId = $stmtC->fetchColumn();

    // 4. Trova Elvis (Elo Portiere più basso)
    $stmtE = $conn->prepare("SELECT id FROM giocatori 
                          WHERE id != 9999 AND partite_portiere > 0 
                          ORDER BY elo_portiere ASC 
                          LIMIT 1");
    $stmtE->execute();
    $elvisId = $stmtE->fetchColumn();

    // Applica titoli ai dati passati solo se non ne hanno già uno equipaggiato dall'inventario
    foreach ($giocatori as &$g) {
        $id = (int)($g['id'] ?? 0);
        if ($id === 0) continue;

        // Se il giocatore ha già un titolo attivo dall'inventario, saltiamo l'assegnazione automatica
        if (!empty($g['active_title'])) {
            continue;
        }

        if ($id === (int)$bomberId) {
            $g['active_title'] = "Il Bomber";
        } elseif ($id === (int)$saracinescaId) {
            $g['active_title'] = "Manuel Neuer";
        } elseif ($id === (int)$controcampoId && $id !== (int)$bomberId) {
            // Solo se non è anche il primo (capita con 1 solo giocatore)
            $g['active_title'] = "Controcampo";
        } elseif ($id === (int)$elvisId && $id !== (int)$saracinescaId) {
             // Solo se non è anche il primo
            $g['active_title'] = "Elvis";
        }
    }
}

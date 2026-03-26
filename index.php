<?php
ob_start();

require_once 'session_config.php';

date_default_timezone_set('Europe/Rome');
require_once 'database.php';
require_once 'betting_engine.php';
require_once 'objectives_engine.php';

define('GHOST_ID', 9999);

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

function applyNameStylePHP($name, $style, $color = '')
{
    if (!$name)
        return "";
    $displayName = $name;
    if ($style === 'arabic') {
        $map = [
            'a' => 'ا', 'b' => 'ب', 'c' => 'ك', 'd' => 'د', 'e' => 'ي', 'f' => 'ف', 'g' => 'ج', 'h' => 'ه', 'i' => 'ي', 'j' => 'ج',
            'k' => 'ك', 'l' => 'ل', 'm' => 'م', 'n' => 'ن', 'o' => 'و', 'p' => 'ب', 'q' => 'ق', 'r' => 'ر', 's' => 'س', 't' => 'т',
            'u' => 'و', 'v' => 'ف', 'w' => 'و', 'x' => 'خ', 'y' => 'ي', 'z' => 'ز',
            'A' => 'ا', 'B' => 'ب', 'C' => 'ك', 'D' => 'د', 'E' => 'ي', 'F' => 'ف', 'G' => 'ج', 'H' => 'ه', 'I' => 'ي', 'J' => 'ج',
            'K' => 'ك', 'L' => 'ل', 'M' => 'م', 'N' => 'ن', 'O' => 'و', 'P' => 'ب', 'Q' => 'ق', 'R' => 'ر', 'S' => 'س', 'T' => 'ت',
            'U' => 'و', 'V' => 'ف', 'W' => 'و', 'X' => 'خ', 'Y' => 'ي', 'Z' => 'ز'
        ];
        $chars = preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY);
        $mapped = array_map(function ($c) use ($map) {
            return $map[$c] ?? $c; }, $chars);
        $displayName = implode('', array_reverse($mapped));
    }
    elseif ($style === 'chinese' || $style === 'name_chinese') {
        $map = [
            'a' => '卂', 'b' => '乃', 'c' => '匚', 'd' => '刀', 'e' => '乇', 'f' => '下', 'g' => '厶', 'h' => '卄', 'i' => '工', 'j' => '丁',
            'k' => '长', 'l' => '乚', 'm' => '爪', 'n' => '冂', 'o' => '口', 'p' => '尸', 'q' => '口', 'r' => '尺', 's' => '丂', 't' => '丅',
            'u' => '凵', 'v' => 'V', 'w' => '山', 'x' => '乂', 'y' => '丫', 'z' => '乙',
            'A' => '卂', 'B' => '乃', 'C' => '匚', 'D' => '刀', 'E' => '乇', 'F' => '下', 'G' => '厶', 'H' => '卄', 'I' => '工', 'J' => '丁',
            'K' => '长', 'L' => '乚', 'M' => '爪', 'N' => '冂', 'O' => '口', 'P' => '尸', 'Q' => '口', 'R' => '尺', 'S' => '丂', 'T' => '丅',
            'U' => '凵', 'V' => 'V', 'W' => '山', 'X' => '乂', 'Y' => '丫', 'Z' => '乙'
        ];
        $chars = preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY);
        $mapped = array_map(function ($c) use ($map) {
            return $map[$c] ?? $c; }, $chars);
        $displayName = implode('', $mapped);
    }
    elseif ($style === 'russian' || $style === 'name_russian') {
        $map = [
            'a' => 'а', 'b' => 'б', 'c' => 'ц', 'd' => 'д', 'e' => 'е', 'f' => 'ф', 'g' => 'г', 'h' => 'х', 'i' => 'и', 'j' => 'й',
            'k' => 'к', 'l' => 'л', 'm' => 'м', 'n' => 'н', 'o' => 'о', 'p' => 'п', 'q' => 'к', 'r' => 'р', 's' => 'с', 't' => 'т',
            'u' => 'у', 'v' => 'в', 'w' => 'ш', 'x' => 'х', 'y' => 'ы', 'z' => 'з',
            'A' => 'А', 'B' => 'Б', 'C' => 'Ц', 'D' => 'Д', 'E' => 'Е', 'F' => 'Ф', 'G' => 'Г', 'H' => 'Х', 'I' => 'И', 'J' => 'Й',
            'K' => 'К', 'L' => 'Л', 'M' => 'М', 'N' => 'Н', 'O' => 'О', 'P' => 'П', 'Q' => 'К', 'R' => 'Р', 'S' => 'С', 'T' => 'Т',
            'U' => 'У', 'V' => 'В', 'W' => 'Ш', 'X' => 'Х', 'Y' => 'Ы', 'Z' => 'З'
        ];
        $chars = preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY);
        $mapped = array_map(function ($c) use ($map) {
            return $map[$c] ?? $c; }, $chars);
        $displayName = implode('', $mapped);
    }

    $colorAttr = $color ? "data-color=\"$color\"" : "";
    $styleAttr = $style ? "data-style=\"$style\"" : "";

    return "<span $colorAttr $styleAttr>$displayName</span>";
}

$isLoggedIn = isset($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? 0;
$userNome = $_SESSION['user_nome'] ?? 'Ospite';

if ($isLoggedIn && isset($_GET['do'])) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        $action = $_GET['do'];

        if ($action === 'sit' || $action === 'sit_guest') {
            $pos = $_GET['pos'] ?? '';
            $map = ['s1p' => 's1_portiere', 's1a' => 's1_attaccante', 's2p' => 's2_portiere', 's2a' => 's2_attaccante'];

            if (isset($map[$pos])) {
                $targetCol = $map[$pos];
                $targetId = ($action === 'sit_guest') ? GHOST_ID : $userId;

                if ($targetId === GHOST_ID) {
                    $stmtCount = $conn->query("SELECT COUNT(*) FROM live_match WHERE (s1_portiere=9999 OR s1_attaccante=9999 OR s2_portiere=9999 OR s2_attaccante=9999) AND id=1");
                    if ($stmtCount->fetchColumn() > 0) {
                        header("Location: index.php?error=Un solo ospite per volta!");
                        exit();
                    }
                }

                $conn->beginTransaction();
                $stmtCheck = $conn->query("SELECT $targetCol FROM live_match WHERE id = 1 FOR UPDATE");
                $occupanteId = $stmtCheck->fetchColumn();

                if (!$occupanteId || $occupanteId == $targetId) {
                    if ($targetId !== GHOST_ID) {

                        $conn->exec("UPDATE live_match SET 
                                     s1_portiere = CASE WHEN s1_portiere = $targetId THEN NULL ELSE s1_portiere END,
                                     s1_attaccante = CASE WHEN s1_attaccante = $targetId THEN NULL ELSE s1_attaccante END,
                                     s2_portiere = CASE WHEN s2_portiere = $targetId THEN NULL ELSE s2_portiere END,
                                     s2_attaccante = CASE WHEN s2_attaccante = $targetId THEN NULL ELSE s2_attaccante END
                                     WHERE id = 1");
                    }
                    $stmtSit = $conn->prepare("UPDATE live_match SET $targetCol = ? WHERE id = 1");
                    $stmtSit->execute([$targetId]);

                    $chk = $conn->query("SELECT s1_portiere, s1_attaccante, s2_portiere, s2_attaccante FROM live_match WHERE id=1")->fetch();
                    if ($chk['s1_portiere'] && $chk['s1_attaccante'] && $chk['s2_portiere'] && $chk['s2_attaccante']) {
                        $conn->exec("UPDATE live_match SET data_inizio_match = NOW() WHERE id = 1 AND data_inizio_match IS NULL");
                    }
                    else {
                        $conn->exec("UPDATE live_match SET data_inizio_match = NULL WHERE id = 1");
                    }

                    $conn->commit();
                    header("Location: index.php");
                    exit();
                }
                else {
                    $conn->rollBack();
                    header("Location: index.php?error=Posto occupato");
                    exit();
                }
            }
        }
        elseif ($action === 'reset') {
            $conn->exec("UPDATE live_match SET s1_portiere=NULL, s1_attaccante=NULL, s2_portiere=NULL, s2_attaccante=NULL, data_inizio_match=NULL WHERE id=1");
            header("Location: index.php");
            exit();
        }
        elseif ($action === 'win') {
            $winningTeam = (int)($_GET['team'] ?? 0);
            $confirm = $_GET['confirm'] ?? 'no';

            if ($winningTeam === 1 || $winningTeam === 2) {

                $matchPre = $conn->query("SELECT * FROM live_match WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
                $playersPre = [(int)$matchPre['s1_portiere'], (int)$matchPre['s1_attaccante'], (int)$matchPre['s2_portiere'], (int)$matchPre['s2_attaccante']];

                if (!in_array($userId, $playersPre) && !isAdmin($conn)) {
                    header("Location: index.php?error=Non sei in partita!");
                    exit();
                }
                if (in_array(0, $playersPre)) {
                    header("Location: index.php?error=Partita incompleta");
                    exit();
                }

                if ($confirm !== 'yes') {
                    $teamName = ($winningTeam == 1) ? "Squadra Blu (1)" : "Squadra Rossa (2)";
                    $teamColor = ($winningTeam == 1) ? "#3b82f6" : "#ef4444";
?>
                    <!DOCTYPE html>
                    <html lang="it" class="light">
                    <head>
                        <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    
    <!-- Anti-Cache Meta Tags -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;700&family=Shojumaru&family=Ruslan+Display&display=swap" rel="stylesheet">

    <title>Conferma Vittoria</title>
                        <script src="https://cdn.tailwindcss.com"></script>
                        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
                        <style>
                            body {
                                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                            }
                            #webapp-bg {
                                position: fixed;
                                inset: 0;
                                width: 100%;
                                height: 100%;
                                border: none;
                                z-index: 0;
                                filter: blur(4px) grayscale(0.5);
                            }
                        </style>
                    </head>
                    <body class="bg-gray-100 min-h-screen flex items-center justify-center">
                        <iframe id="webapp-bg" src="index.php"></iframe>
                        <div class="fixed inset-0 z-50 flex items-center justify-center bg-white/40 backdrop-blur-md">
                            <div class="bg-white p-8 rounded-[2rem] w-[90%] max-w-sm shadow-2xl border border-white transform transition-all scale-100 text-center">
                                <div class="h-20 w-20 bg-yellow-50 text-yellow-600 rounded-3xl flex items-center justify-center mx-auto mb-6 shadow-sm border border-yellow-100">
                                    <span class="material-symbols-outlined text-4xl">help</span>
                                </div>
                                <h3 class="text-2xl font-black text-slate-800 mb-2 tracking-tight">Conferma Vittoria</h3>
                                <p class="text-slate-500 mb-8 font-medium">Confermi la vittoria della squadra <span style="color: <?php echo $teamColor; ?>; font-weight: 800;"><?php echo $teamName; ?></span>?</p>

                                <div class="flex flex-col gap-3">
                                    <a href="index.php?do=win&team=<?php echo $winningTeam; ?>&confirm=yes&deuce=no" class="w-full py-4 rounded-2xl bg-green-500 text-white font-black hover:bg-green-600 transition-all shadow-lg shadow-green-500/20 text-center no-underline text-sm uppercase tracking-widest">Conferma</a>
                                    <a href="index.php?do=win&team=<?php echo $winningTeam; ?>&confirm=yes&deuce=yes" class="w-full py-4 rounded-2xl bg-orange-500 text-white font-black hover:bg-orange-600 transition-all shadow-lg shadow-orange-500/20 text-center no-underline text-sm uppercase tracking-widest">Ai Vantaggi</a>
                                    <a href="index.php" class="w-full py-4 rounded-2xl bg-slate-100 text-slate-500 font-bold hover:bg-slate-200 transition-all text-center no-underline text-sm uppercase tracking-widest">Annulla</a>
                                </div>
                            </div>
                        </div>
                        </div>
                    </body>
                    </html>
                    <?php
                    exit();
                }

                $conn->beginTransaction();

                $match = $conn->query("SELECT * FROM live_match WHERE id = 1 FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
                $players = [(int)$match['s1_portiere'], (int)$match['s1_attaccante'], (int)$match['s2_portiere'], (int)$match['s2_attaccante']];

                if (in_array(0, $players)) {
                    $conn->rollBack();
                    header("Location: index.php?error=Qualcuno si è alzato nel frattempo!");
                    exit();
                }

                $dataCorrente = date('Y-m-d H:i:s');
                $stmtStagione = $conn->query("SELECT id FROM stagioni WHERE is_active = 1 LIMIT 1");
                $activeSeasonId = $stmtStagione->fetchColumn() ?: 1;
                $sql = "INSERT INTO partite (squadra1_portiere, squadra1_attaccante, squadra2_portiere, squadra2_attaccante, vincitore, data, stagione_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$players[0], $players[1], $players[2], $players[3], $winningTeam, $dataCorrente, $activeSeasonId]);

                $matchId = $conn->lastInsertId();
                $deltas = aggiornaEloEStatistiche($conn, [
                    'squadra1' => [$players[0], $players[1]],
                    'squadra2' => [$players[2], $players[3]],
                    'vincitore' => $winningTeam
                ]);

                $sqlUpd = "UPDATE partite SET elo_delta_s1p=?, elo_delta_s1a=?, elo_delta_s2p=?, elo_delta_s2a=? WHERE id=?";
                $conn->prepare($sqlUpd)->execute([$deltas['s1p'], $deltas['s1a'], $deltas['s2p'], $deltas['s2a'], $matchId]);

                $pWin = ($winningTeam == 1) ? [$players[0], $players[1]] : [$players[2], $players[3]];
                $pLoss = ($winningTeam == 1) ? [$players[2], $players[3]] : [$players[0], $players[1]];
                awardMatchCredits($conn, $pWin, $pLoss);

                processBets($conn, $winningTeam);

                $conn->prepare("UPDATE live_match_bonuses SET match_id = ?, status = 'used' WHERE match_id = 1 AND status = 'active'")
                    ->execute([$matchId]);

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

                $conn->exec("UPDATE live_match SET s1_portiere=NULL, s1_attaccante=NULL, s2_portiere=NULL, s2_attaccante=NULL, score_s1=0, score_s2=0, data_inizio_match=NULL WHERE id=1");
                $conn->commit();

                header("Location: index.php?msg=Vittoria registrata!");
                exit();
            }
        }

    }
    catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction())
            $conn->rollBack();
        header("Location: index.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}

function getKFactor($partite)
{
    return ($partite < 10) ? 40 : (($partite < 20) ? 35 : (($partite < 30) ? 30 : (($partite < 40) ? 25 : (($partite < 50) ? 20 : 16))));
}
function calcolaElo($eloGiocatore, $eloPropriaSquadra, $eloSfidanteSquadra, $risultato, $partiteGiocate, $multiplier = 1)
{
    $K = getKFactor($partiteGiocate) * $multiplier;
    $expected = 1 / (1 + pow(10, ($eloSfidanteSquadra - $eloPropriaSquadra) / 400));
    return max(100, min(3000, round($eloGiocatore + $K * ($risultato - $expected))));
}

function getEloMedioPesatoOverall($conn)
{
    $stmt = $conn->query("SELECT elo_portiere, partite_portiere, elo_attaccante, partite_attaccante FROM giocatori WHERE id != " . GHOST_ID . " AND partite_totali > 0");
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $weightedSum = 0;
    $totalMatches = 0;
    foreach ($players as $p) {
        $weightedSum += ($p['elo_portiere'] * $p['partite_portiere']) + ($p['elo_attaccante'] * $p['partite_attaccante']);
        $totalMatches += $p['partite_portiere'] + $p['partite_attaccante'];
    }
    return ($totalMatches == 0) ? 1500 : round($weightedSum / $totalMatches);
}

function aggiornaEloEStatistiche($conn, $dati)
{
    $eloFantasma = getEloMedioPesatoOverall($conn);
    $ids = array_merge($dati['squadra1'], $dati['squadra2']);
    $gs = [];
    foreach ($ids as $id) {
        if ($id == GHOST_ID) {
            $player = ['id' => GHOST_ID, 'elo_portiere' => $eloFantasma, 'elo_attaccante' => $eloFantasma, 'partite_portiere' => 0, 'partite_attaccante' => 0];
        }
        else {
            $stmt = $conn->prepare("SELECT * FROM giocatori WHERE id = ?");
            $stmt->execute([$id]);
            $player = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        $gs[$id] = $player;
    }
    $s1p = $gs[$dati['squadra1'][0]];
    $s1a = $gs[$dati['squadra1'][1]];
    $s2p = $gs[$dati['squadra2'][0]];
    $s2a = $gs[$dati['squadra2'][1]];

    $res1 = $dati['vincitore'] == 1 ? 1 : 0;
    $res2 = $dati['vincitore'] == 2 ? 1 : 0;

    $stmtB = $conn->query("SELECT user_id, item_key FROM live_match_bonuses WHERE match_id = 1 AND status = 'active'");
    $bonuses = $stmtB->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_COLUMN);

    $getMult = function ($id) use ($bonuses) {
        if (isset($bonuses[$id]) && in_array('x2_elo', $bonuses[$id]))
            return 2;
        return 1;
    };

    // Elo di base per ogni slot (fallback a 1500 se nessuna partita giocata nel ruolo)
    $eloBase_s1p = $s1p['elo_portiere'] ?: 1500;
    $eloBase_s1a = $s1a['elo_attaccante'] ?: 1500;
    $eloBase_s2p = $s2p['elo_portiere'] ?: 1500;
    $eloBase_s2a = $s2a['elo_attaccante'] ?: 1500;

    // Media Elo di squadra: portiere (nel suo ruolo) + attaccante (nel suo ruolo)
    $eloMedioSq1 = ($eloBase_s1p + $eloBase_s1a) / 2;
    $eloMedioSq2 = ($eloBase_s2p + $eloBase_s2a) / 2;

    // Ogni giocatore viene aggiornato in base alla media della propria squadra vs media avversaria
    $nElo_s1p = calcolaElo($eloBase_s1p, $eloMedioSq1, $eloMedioSq2, $res1, $s1p['partite_portiere'], $getMult($s1p['id']));
    $nElo_s1a = calcolaElo($eloBase_s1a, $eloMedioSq1, $eloMedioSq2, $res1, $s1a['partite_attaccante'], $getMult($s1a['id']));
    $nElo_s2p = calcolaElo($eloBase_s2p, $eloMedioSq2, $eloMedioSq1, $res2, $s2p['partite_portiere'], $getMult($s2p['id']));
    $nElo_s2a = calcolaElo($eloBase_s2a, $eloMedioSq2, $eloMedioSq1, $res2, $s2a['partite_attaccante'], $getMult($s2a['id']));

    updatePlayerRole($conn, $s1p['id'], 'elo_portiere', $nElo_s1p, 'vittorie_portiere', 'sconfitte_portiere', 'partite_portiere', $res1);
    updatePlayerRole($conn, $s2p['id'], 'elo_portiere', $nElo_s2p, 'vittorie_portiere', 'sconfitte_portiere', 'partite_portiere', $res2);
    updatePlayerRole($conn, $s1a['id'], 'elo_attaccante', $nElo_s1a, 'vittorie_attaccante', 'sconfitte_attaccante', 'partite_attaccante', $res1);
    updatePlayerRole($conn, $s2a['id'], 'elo_attaccante', $nElo_s2a, 'vittorie_attaccante', 'sconfitte_attaccante', 'partite_attaccante', $res2);

    return [
        's1p' => $nElo_s1p - $eloBase_s1p,
        's1a' => $nElo_s1a - $eloBase_s1a,
        's2p' => $nElo_s2p - $eloBase_s2p,
        's2a' => $nElo_s2a - $eloBase_s2a,
    ];
}
function updatePlayerRole($conn, $id, $eloField, $newElo, $winField, $loseField, $matchField, $isWin)
{
    if ($id == GHOST_ID)
        return;
    $winAdd = $isWin ? 1 : 0;
    $loseAdd = $isWin ? 0 : 1;
    $sql = "UPDATE giocatori SET $eloField = ?, $matchField = $matchField + 1, partite_totali = partite_totali + 1, $winField = $winField + ?, $loseField = $loseField + ?, vittorie_totali = vittorie_totali + ?, sconfitte_totali = sconfitte_totali + ? WHERE id = ?";
    $conn->prepare($sql)->execute([$newElo, $winAdd, $loseAdd, $winAdd, $loseAdd, $id]);
}
function isAdmin($conn)
{
    if (!isset($_SESSION['user_id']))
        return false;
    $stmt = $conn->prepare("SELECT is_admin FROM giocatori WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $val = $stmt->fetchColumn();

    return (int)$val === 1;
}

if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $database = new Database();
        $conn = $database->getConnection();
        $conn->exec("INSERT IGNORE INTO live_match (id) VALUES (1)");

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $stmt = $conn->query("SELECT * FROM live_match WHERE id = 1");
            $status = $stmt->fetch(PDO::FETCH_ASSOC);
            $response = [];
            $roles = ['s1_portiere', 's1_attaccante', 's2_portiere', 's2_attaccante'];

            foreach ($roles as $r) {
                if (!empty($status[$r])) {
                    if ($status[$r] == GHOST_ID) {
                        $response[$r] = ['id' => GHOST_ID, 'nome' => 'Ospite'];
                    }
                    else {
                        $stmtN = $conn->prepare("SELECT id, nome FROM giocatori WHERE id = ?");
                        $stmtN->execute([$status[$r]]);
                        $response[$r] = $stmtN->fetch(PDO::FETCH_ASSOC);
                    }
                }
                else {
                    $response[$r] = null;
                }
            }
            echo json_encode($response);
            exit();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$isLoggedIn) {
                echo json_encode(['success' => false, 'error' => 'Login richiesto']);
                exit();
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $action = $input['action'] ?? '';
            $playerId = $userId;

            if ($action === 'sit') {
                $pos = $input['pos'];
                $map = ['s1p' => 's1_portiere', 's1a' => 's1_attaccante', 's2p' => 's2_portiere', 's2a' => 's2_attaccante'];
                if (!isset($map[$pos]))
                    throw new Exception("Posizione errata");
                $targetCol = $map[$pos];

                $targetId = $playerId;
                if (isset($input['player_id']) && (int)$input['player_id'] === GHOST_ID) {
                    $targetId = GHOST_ID;
                }

                $conn->beginTransaction();

                $stmtCheck = $conn->query("SELECT $targetCol FROM live_match WHERE id = 1 FOR UPDATE");
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
                    $conn->exec("UPDATE live_match SET 
                                 s1_portiere = CASE WHEN s1_portiere = $targetId THEN NULL ELSE s1_portiere END,
                                 s1_attaccante = CASE WHEN s1_attaccante = $targetId THEN NULL ELSE s1_attaccante END,
                                 s2_portiere = CASE WHEN s2_portiere = $targetId THEN NULL ELSE s2_portiere END,
                                 s2_attaccante = CASE WHEN s2_attaccante = $targetId THEN NULL ELSE s2_attaccante END
                                 WHERE id = 1");
                }

                $stmtSit = $conn->prepare("UPDATE live_match SET $targetCol = ? WHERE id = 1");
                $stmtSit->execute([$targetId]);

                $chk = $conn->query("SELECT s1_portiere, s1_attaccante, s2_portiere, s2_attaccante FROM live_match WHERE id=1")->fetch();
                if ($chk['s1_portiere'] && $chk['s1_attaccante'] && $chk['s2_portiere'] && $chk['s2_attaccante']) {
                    $conn->exec("UPDATE live_match SET data_inizio_match = NOW() WHERE id = 1 AND data_inizio_match IS NULL");
                }
                else {
                    $conn->exec("UPDATE live_match SET data_inizio_match = NULL WHERE id = 1");
                }

                $conn->commit();
                echo json_encode(['success' => true]);
                exit();
            }

            elseif ($action === 'win') {
                $winningTeam = (int)$input['team'];
                $conn->beginTransaction();
                $match = $conn->query("SELECT * FROM live_match WHERE id = 1 FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
                $players = [(int)$match['s1_portiere'], (int)$match['s1_attaccante'], (int)$match['s2_portiere'], (int)$match['s2_attaccante']];

                if (!in_array($playerId, $players) && !isAdmin($conn)) {
                    $conn->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Non sei in partita!']);
                    exit();
                }
                if (in_array(0, $players)) {
                    $conn->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Partita incompleta!']);
                    exit();
                }

                $stmtStagione = $conn->query("SELECT id FROM stagioni WHERE is_active = 1 LIMIT 1");
                $activeSeasonId = $stmtStagione->fetchColumn() ?: 1;
                $sql = "INSERT INTO partite (squadra1_portiere, squadra1_attaccante, squadra2_portiere, squadra2_attaccante, vincitore, data, stagione_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$players[0], $players[1], $players[2], $players[3], $winningTeam, date('Y-m-d H:i:s'), $activeSeasonId]);

                $matchId = $conn->lastInsertId();
                $deltas = aggiornaEloEStatistiche($conn, ['squadra1' => [$players[0], $players[1]], 'squadra2' => [$players[2], $players[3]], 'vincitore' => $winningTeam]);

                $sqlUpd = "UPDATE partite SET elo_delta_s1p=?, elo_delta_s1a=?, elo_delta_s2p=?, elo_delta_s2a=? WHERE id=?";
                $conn->prepare($sqlUpd)->execute([$deltas['s1p'], $deltas['s1a'], $deltas['s2p'], $deltas['s2a'], $matchId]);

                if (isset($input['deuce']) && $input['deuce'] === 'yes')
                    $_GET['deuce'] = 'yes';

                $pWin = ($winningTeam == 1) ? [$players[0], $players[1]] : [$players[2], $players[3]];
                $pLoss = ($winningTeam == 1) ? [$players[2], $players[3]] : [$players[0], $players[1]];
                awardMatchCredits($conn, $pWin, $pLoss);

                processBets($conn, $winningTeam);

                $conn->prepare("UPDATE live_match_bonuses SET match_id = ?, status = 'used' WHERE match_id = 1 AND status = 'active'")
                    ->execute([$matchId]);

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

                $conn->exec("UPDATE live_match SET s1_portiere=NULL, s1_attaccante=NULL, s2_portiere=NULL, s2_attaccante=NULL WHERE id=1");
                $conn->commit();
                echo json_encode(['success' => true]);
                exit();
            }

            elseif ($action === 'quick_match') {
                if (!isAdmin($conn)) {
                    echo json_encode(['success' => false, 'error' => 'Accesso negato: Solo Admin']);
                    exit();
                }

                $s1p = (int)$input['s1p'];
                $s1a = (int)$input['s1a'];
                $s2p = (int)$input['s2p'];
                $s2a = (int)$input['s2a'];
                $winningTeam = (int)$input['winner'];

                if (!$s1p || !$s1a || !$s2p || !$s2a || !$winningTeam) {
                    echo json_encode(['success' => false, 'error' => 'Dati mancanti']);
                    exit();
                }

                $conn->beginTransaction();

                $stmtStagione = $conn->query("SELECT id FROM stagioni WHERE is_active = 1 LIMIT 1");
                $activeSeasonId = $stmtStagione->fetchColumn() ?: 1;
                $sql = "INSERT INTO partite (squadra1_portiere, squadra1_attaccante, squadra2_portiere, squadra2_attaccante, vincitore, data, score_s1, score_s2, stagione_id) VALUES (?, ?, ?, ?, ?, ?, null, null, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$s1p, $s1a, $s2p, $s2a, $winningTeam, date('Y-m-d H:i:s'), $activeSeasonId]);

                $matchId = $conn->lastInsertId();
                $deltas = aggiornaEloEStatistiche($conn, ['squadra1' => [$s1p, $s1a], 'squadra2' => [$s2p, $s2a], 'vincitore' => $winningTeam]);

                $sqlUpd = "UPDATE partite SET elo_delta_s1p=?, elo_delta_s1a=?, elo_delta_s2p=?, elo_delta_s2a=? WHERE id=?";
                $conn->prepare($sqlUpd)->execute([$deltas['s1p'], $deltas['s1a'], $deltas['s2p'], $deltas['s2a'], $matchId]);

                $pWin = ($winningTeam == 1) ? [$s1p, $s1a] : [$s2p, $s2a];
                $pLoss = ($winningTeam == 1) ? [$s2p, $s2a] : [$s1p, $s1a];
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

                $conn->commit();
                echo json_encode(['success' => true]);
                exit();
            }

            elseif ($action === 'reset') {
                $conn->exec("UPDATE live_match SET s1_portiere=NULL, s1_attaccante=NULL, s2_portiere=NULL, s2_attaccante=NULL, data_inizio_match=NULL WHERE id=1");
                echo json_encode(['success' => true]);
                exit();
            }
        }
    }
    catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction())
            $conn->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
    exit();
}
$database = new Database();
$conn = $database->getConnection();
$currentUserIsAdmin = isAdmin($conn);
?>
<!DOCTYPE html>
<html lang="en"> 
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover" name="viewport"/>

<link rel="stylesheet" href="style.v6.css?v=<?php echo time(); ?>">
<meta http-equiv="Expires" content="0">

<title>Scalcetting Tracker</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Shojumaru&family=Ruslan+Display&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.v6.css?v=<?php echo time(); ?>">
<link rel="icon" type="image/x-icon" href="favicon.ico">
<script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#067ff9",
                        "background-light": "#f5f7f8",
                        "background-dark": "#0f1923",
                    },
                    fontFamily: {
                        "display": ["Lexend", "sans-serif"]
                    },
                    borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
                },
            },
        }
    </script>
<style>

        ::-webkit-scrollbar {
            width: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        .dark ::-webkit-scrollbar-thumb {
            background: #475569;
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes bounce-horizontal {
            0%, 100% { transform: translateY(-50%) translateX(0); }
            50% { transform: translateY(-50%) translateX(-5px); }
        }
        @keyframes bounce-horizontal-reverse {
            0%, 100% { transform: translateY(-50%) translateX(0) scaleX(-1); }
            50% { transform: translateY(-50%) translateX(5px) scaleX(-1); }
        }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        @keyframes scaleIn { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        @keyframes scaleOut { from { transform: scale(1); opacity: 1; } to { transform: scale(0.95); opacity: 0; } }

        .animate-fade-in { animation: fadeIn 0.3s ease-out forwards; }
        .animate-bounce-horizontal { animation: bounce-horizontal 2s ease-in-out infinite; }
        .animate-bounce-horizontal-reverse { animation: bounce-horizontal-reverse 2s ease-in-out infinite; }
        .animate-slide-up { animation: slideUp 0.3s ease-out forwards; }
        .animate-scale-in { animation: scaleIn 0.2s ease-out forwards; }
        .animate-scale-out { animation: scaleOut 0.2s ease-out forwards; }

        #toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 100;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        }
        .toast {
            pointer-events: auto;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        .bonus-icons-live {
            white-space: nowrap;
        }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 min-h-screen flex items-center justify-center font-display antialiased p-0 overflow-x-hidden">
<div id="toast-container"></div>

<div class="flex min-h-screen w-full bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-white transition-colors duration-200 overflow-x-hidden">

    <aside class="hidden md:flex flex-col w-72 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 h-full fixed top-0 left-0 z-40">
        <div class="p-6 flex items-center gap-3">
             <h1 class="text-xl font-bold tracking-tight">Scalcetting<span class="text-primary">Tracker</span></h1>
        </div>

        <nav class="flex-1 px-4 space-y-2 mt-4 overflow-y-auto">
             <button onclick="showPage('home')" class="nav-item-desktop w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all group active" data-target="home">
                 <span class="material-symbols-outlined text-[24px] group-hover:scale-110 transition-transform text-gray-400 group-[.active]:text-primary group-[.active]:fill-current">home</span>
                 <span class="text-gray-500 dark:text-gray-400 group-[.active]:text-gray-900 dark:group-[.active]:text-white font-semibold">Dashboard</span>
             </button>
             <button onclick="showPage('leaderboard')" class="nav-item-desktop w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all group" data-target="leaderboard">
                 <span class="material-symbols-outlined text-[24px] group-hover:scale-110 transition-transform text-gray-400 group-[.active]:text-primary">leaderboard</span>
                 <span class="text-gray-500 dark:text-gray-400 group-[.active]:text-gray-900 dark:group-[.active]:text-white font-semibold">Classifica</span>
             </button>
             <button onclick="showPage('matches')" class="nav-item-desktop w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all group" data-target="matches">
                 <span class="material-symbols-outlined text-[24px] group-hover:scale-110 transition-transform text-gray-400 group-[.active]:text-primary">history</span>
                 <span class="text-gray-500 dark:text-gray-400 group-[.active]:text-gray-900 dark:group-[.active]:text-white font-semibold">Storico Partite</span>
             </button>
             <button onclick="showPage('profile')" class="nav-item-desktop w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all group" data-target="profile">
                 <span class="material-symbols-outlined text-[24px] group-hover:scale-110 transition-transform text-gray-400 group-[.active]:text-primary">person</span>
                 <span class="text-gray-500 dark:text-gray-400 group-[.active]:text-gray-900 dark:group-[.active]:text-white font-semibold">Profilo</span>
             </button>
             <button id="sp-nav-btn-desktop" onclick="showPage('season-pass')" class="nav-item-desktop w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all group relative bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-lg shadow-indigo-500/30 hover:shadow-indigo-500/50 hover:scale-[1.02] border-none" data-target="season-pass">
                 <span class="material-symbols-outlined text-[24px] text-white">confirmation_number</span>
                 <span class="text-white font-bold">Season Pass</span>
             </button>
             <button onclick="openShopModal()" class="nav-item-desktop w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all group mt-2 bg-gradient-to-r from-yellow-500/10 to-orange-500/10 border border-yellow-500/20 hover:border-yellow-500/50">
                 <span class="material-symbols-outlined text-[24px] group-hover:scale-110 transition-transform text-yellow-500">shopping_cart</span>
                 <span class="text-yellow-600 dark:text-yellow-400 font-bold">Negozio</span>
             </button>
        </nav>

        <div class="p-6 border-t border-gray-200 dark:border-gray-700 mt-auto">
             <?php if ($isLoggedIn): ?>
             <div id="signedInUser" class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-700/50 xl:hover:bg-gray-100 xl:dark:hover:bg-gray-700 transition-colors xl:cursor-pointer" onclick="toggleAuthModal()">
                <div class="h-10 w-10 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden ring-2 ring-primary/20 flex-shrink-0 relative">
                    <?php if (!empty($_SESSION['user_aura'])): ?>
                        <div class="absolute inset-[-4px] rounded-full aura-<?php echo $_SESSION['user_aura']; ?> opacity-70 z-0"></div>
                    <?php
    endif; ?>
                    <div class="w-full h-full rounded-full overflow-hidden relative z-10 bg-gray-200 dark:bg-gray-700">
                        <img id="userAvatarImg" src="<?php echo $_SESSION['user_avatar'] ?? ''; ?>" class="w-full h-full object-cover <?php echo empty($_SESSION['user_avatar']) ? 'hidden' : ''; ?>" alt="Avatar">
                        <div id="userInitials" class="h-full w-full flex items-center justify-center bg-gray-400 text-white font-bold <?php echo !empty($_SESSION['user_avatar']) ? 'hidden' : ''; ?>">
                            <?php echo strtoupper(substr($userNome, 0, 1)); ?>
                        </div>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Connesso come</p>
                    <p id="userNameSpan" class="text-sm font-bold text-gray-900 dark:text-white truncate" 
                       data-color="<?php echo $_SESSION['user_name_color'] ?? ''; ?>" 
                       data-style="<?php echo $_SESSION['user_name_style'] ?? ''; ?>">
                        <?php echo applyNameStylePHP($userNome, $_SESSION['user_name_style'] ?? '', $_SESSION['user_name_color'] ?? ''); ?>
                        <span class="text-xs font-normal text-gray-500"><?php echo $currentUserIsAdmin ? '(Admin)' : ''; ?></span>
                    </p>
                </div>
                <span class="material-symbols-outlined text-red-500 hover:text-red-700" onclick="event.stopPropagation(); performLogout();">logout</span>
             </div>
             <?php
else: ?>
             <button id="loginButton" onclick="toggleAuthModal()" class="w-full flex items-center justify-center gap-2 bg-primary xl:hover:bg-blue-600 text-white font-bold py-3 rounded-xl transition-all shadow-lg shadow-blue-500/20 active:scale-95">
                <span class="material-symbols-outlined">login</span>
                <span>Accedi</span>
             </button>
             <?php
endif; ?>
        </div>
    </aside>

    <main class="flex-1 relative h-screen overflow-y-auto no-scrollbar md:ml-72 w-full overflow-x-hidden" id="mainScrollable">
        <div class="max-w-7xl mx-auto w-full md:p-8 pb-32 md:pb-8">

            <div id="home" class="view-section animate-fade-in">

                <header class="md:hidden px-5 pt-4 pb-6">
                    <?php if ($isLoggedIn): ?>
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden ring-2 ring-primary/20 relative">
                                <?php if (!empty($_SESSION['user_aura'])): ?>
                                    <div class="absolute inset-[-4px] rounded-full aura-<?php echo $_SESSION['user_aura']; ?> opacity-70 z-0"></div>
                                <?php
    endif; ?>
                                <div class="w-full h-full rounded-full overflow-hidden relative z-10 bg-gray-200 dark:bg-gray-700">
                                    <img id="welcomeAvatar" src="<?php echo $_SESSION['user_avatar'] ?? ''; ?>" class="w-full h-full object-cover <?php echo empty($_SESSION['user_avatar']) ? 'hidden' : ''; ?>" alt="Avatar">
                                    <div id="welcomeInitials" class="h-full w-full flex items-center justify-center bg-gray-400 text-white font-bold <?php echo !empty($_SESSION['user_avatar']) ? 'hidden' : ''; ?>">
                                        <?php echo strtoupper(substr($userNome, 0, 1)); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Bentornato,</p>
                                <h2 id="welcomeName" class="text-lg font-bold text-gray-900 dark:text-white leading-tight truncate"
                                    data-color="<?php echo $_SESSION['user_name_color'] ?? ''; ?>" 
                                    data-style="<?php echo $_SESSION['user_name_style'] ?? ''; ?>">
                                    <?php echo applyNameStylePHP($userNome, $_SESSION['user_name_style'] ?? '', $_SESSION['user_name_color'] ?? ''); ?>
                                </h2>
                            </div>
                        </div>
                        <button id="sp-nav-btn-mobile" class="p-2.5 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white transition-all active:scale-95 shadow-md shadow-indigo-500/20 border-none relative" onclick="showPage('season-pass')">
                            <span class="material-symbols-outlined text-[22px]">confirmation_number</span>
                        </button>
                    </div>
                    <?php
endif; ?>

                    <?php if ($isLoggedIn): ?>
                    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#067ff9] to-[#045cb5] p-6 text-white shadow-lg shadow-primary/20 mb-4">

                        <div class="absolute top-0 right-0 -mt-4 -mr-4 h-24 w-24 rounded-full bg-white/10 blur-2xl"></div>
                        <div class="absolute bottom-0 left-0 -mb-4 -ml-4 h-16 w-16 rounded-full bg-white/10 blur-xl"></div>

                        <div class="relative z-10 flex flex-col items-center text-center">
                            <div class="mb-1 text-sm font-medium text-white/80 uppercase tracking-wider">ELO</div>
                            <h1 class="text-5xl font-bold tracking-tight mb-2" id="dashboardEloMobile">---</h1>
                            <div class="flex items-center gap-2 w-full justify-center mt-2 px-2">
                                <div class="flex flex-col items-center flex-1">
                                    <span class="text-xs text-white/70">Posizione</span>
                                    <span class="text-lg font-bold" id="dashboardRankMobile">#--</span>
                                </div>
                                <div class="h-8 w-px bg-white/20"></div>
                                <div class="flex flex-col items-center flex-1">
                                    <span class="text-xs text-white/70">Strisciate</span>
                                    <span class="text-lg font-bold" id="dashboardCreditsMobile">---</span>
                                </div>
                                <div class="h-8 w-px bg-white/20"></div>
                                <div class="flex flex-col items-center flex-1">
                                    <span class="text-xs text-white/70">Streak</span>
                                    <span class="text-lg font-bold" id="dashboardStreakMobile">--</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RECENT STATS MOBILE -->
                    <div id="recentStatsWidgetMobile" class="hidden relative overflow-hidden rounded-3xl bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900 border border-gray-200/50 dark:border-gray-700/50 shadow-lg shadow-gray-200/50 dark:shadow-none mb-4 p-5 z-10">
                        <div class="absolute -top-10 -right-10 w-32 h-32 bg-blue-500/10 dark:bg-blue-500/5 rounded-full blur-2xl pointer-events-none"></div>
                        <div class="flex justify-between items-center mb-5 relative z-10">
                            <h3 class="text-[11px] font-black text-gray-400 dark:text-gray-500 flex items-center gap-1.5 uppercase tracking-[0.2em]">
                                <span class="material-symbols-outlined text-[18px] text-blue-500">query_stats</span>
                                Forma
                            </h3>
                            <div id="recentEloDeltaMobile" class="transition-all duration-300">--</div>
                        </div>
                        <div class="relative mt-2 pb-1">
                            <!-- Background line -->
                            <div class="absolute w-[calc(100%-2.5rem)] left-5 top-1/2 -translate-y-1/2 h-1.5 bg-gray-100 dark:bg-gray-800 rounded-full z-0 overflow-hidden">
                                <div class="w-full h-full bg-blue-500/20 dark:bg-blue-500/10 animate-pulse"></div>
                            </div>
                            
                            <div class="flex justify-between items-center relative z-10 px-1" id="recentMatchDotsMobile">
                                <!-- Dots qui -->
                            </div>
                        </div>
                    </div>
                    <?php
else: ?>
                    <div class="relative overflow-hidden rounded-2xl bg-white dark:bg-gray-800 p-8 text-center shadow-lg border border-gray-100 dark:border-gray-700">
                        <div class="h-16 w-16 bg-gray-100 dark:bg-gray-700 text-gray-400 rounded-full flex items-center justify-center mx-auto mb-4">
                            <span class="material-symbols-outlined text-4xl">lock</span>
                        </div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Non sei loggato</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6 px-4">Accedi per visualizzare le tue statistiche, il tuo Elo e la tua posizione in classifica.</p>
                        <button onclick="toggleAuthModal()" class="w-full bg-primary xl:hover:bg-blue-600 text-white font-bold py-3.5 rounded-xl transition-all shadow-lg shadow-blue-500/20 active:scale-95 flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined">login</span>
                            <span>Accedi</span>
                        </button>
                    </div>
                    <?php
endif; ?>
                </header>

                <div class="px-5 mb-8 md:hidden">
                    <div class="bg-white dark:bg-gray-800 rounded-t-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">

                        <div class="p-4 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center relative">
                            <div class="flex items-center gap-3">
                                <h3 class="font-bold text-gray-900 dark:text-gray-100">Live Match</h3>
                                <div id="liveBountyBanner-mobile" class="hidden bg-gradient-to-r from-yellow-400 to-orange-500 rounded-lg px-2 py-1 shadow-sm flex items-center gap-1 animate-pulse">
                                    <span class="material-symbols-outlined text-white text-[12px]">target</span>
                                    <span class="text-white font-black text-[9px] uppercase tracking-widest">Taglia</span>
                                </div>
                            </div>
                            <button onclick="resetLiveTable()" class="text-xs text-red-500 hover:text-red-600 font-medium">Reset</button>
                        </div>
                        <div class="p-4">

                            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-3 mb-3 border border-blue-100 dark:border-blue-800/50">
                                <div class="flex justify-between items-center mb-3">
                                    <div class="text-xs font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wider">Squadra Blu</div>
                                    <div class="flex items-center pr-2" id="live-score-container-s1-mobile" style="display: none;">
                                        <div id="live-score-s1-mobile" class="text-4xl font-black text-blue-600 dark:text-blue-400 w-10 text-center tracking-tighter">0</div>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <div id="live-mobile-s1p" class="flex-1 bg-white dark:bg-gray-800 border-2 border-dashed border-blue-200 dark:border-blue-800 rounded-lg p-3 flex flex-col items-center justify-center xl:cursor-pointer xl:hover:border-blue-400 transition-colors min-h-[80px] relative" onclick="sitDown('s1p')">
                                        <span class="text-2xl mb-1">🥅</span>
                                        <span class="text-[10px] text-gray-400 font-medium role-label">PORTIERE</span>
                                        <span class="text-xs font-bold text-gray-700 dark:text-gray-200 player-name truncate w-full text-center">Libero</span>
                                    </div>
                                    <div id="live-mobile-s1a" class="flex-1 bg-white dark:bg-gray-800 border-2 border-dashed border-blue-200 dark:border-blue-800 rounded-lg p-3 flex flex-col items-center justify-center xl:cursor-pointer xl:hover:border-blue-400 transition-colors min-h-[80px] relative" onclick="sitDown('s1a')">
                                        <span class="text-2xl mb-1">⚽</span>
                                        <span class="text-[10px] text-gray-400 font-medium role-label">ATTACCO</span>
                                        <span class="text-xs font-bold text-gray-700 dark:text-gray-200 player-name truncate w-full text-center">Libero</span>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-red-50 dark:bg-red-900/20 rounded-xl p-3 mb-4 border border-red-100 dark:border-red-800/50">
                                <div class="flex justify-between items-center mb-3">
                                    <div class="text-xs font-bold text-red-600 dark:text-red-400 uppercase tracking-wider">Squadra Rossa</div>
                                    <div class="flex items-center pr-2" id="live-score-container-s2-mobile" style="display: none;">
                                        <div id="live-score-s2-mobile" class="text-4xl font-black text-red-600 dark:text-red-400 w-10 text-center tracking-tighter">0</div>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <div id="live-mobile-s2a" class="flex-1 bg-white dark:bg-gray-800 border-2 border-dashed border-red-200 dark:border-red-800 rounded-lg p-3 flex flex-col items-center justify-center xl:cursor-pointer xl:hover:border-red-400 transition-colors min-h-[80px] relative" onclick="sitDown('s2a')">
                                        <span class="text-2xl mb-1">⚽</span>
                                        <span class="text-[10px] text-gray-400 font-medium role-label">ATTACCO</span>
                                        <span class="text-xs font-bold text-gray-700 dark:text-gray-200 player-name truncate w-full text-center">Libero</span>
                                    </div>
                                    <div id="live-mobile-s2p" class="flex-1 bg-white dark:bg-gray-800 border-2 border-dashed border-red-200 dark:border-red-800 rounded-lg p-3 flex flex-col items-center justify-center xl:cursor-pointer xl:hover:border-red-400 transition-colors min-h-[80px] relative" onclick="sitDown('s2p')">
                                        <span class="text-2xl mb-1">🥅</span>
                                        <span class="text-[10px] text-gray-400 font-medium role-label">PORTIERE</span>
                                        <span class="text-xs font-bold text-gray-700 dark:text-gray-200 player-name truncate w-full text-center">Libero</span>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <?php if ($isLoggedIn): ?>
                    <div id="betting-wrapper-mobile" class="hidden border border-t-0 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 rounded-b-2xl shadow-xl -mt-[1px]">
                        <!-- ACCORDION HEADER -->
                        <div class="w-full flex justify-between items-center p-3 cursor-pointer bg-gray-50/50 dark:bg-gray-800/50 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors rounded-b-2xl" id="betting-header-mobile" onclick="toggleAccordion('mobile')">
                            <span class="font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2 text-sm">
                                <span class="material-symbols-outlined text-yellow-500">monetization_on</span> Scommesse
                            </span>
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-bold text-red-500 animate-pulse" id="betting-timer-mobile"></span>
                                <span class="material-symbols-outlined text-gray-400 transition-transform duration-500" id="chev-mob">expand_more</span>
                            </div>
                        </div>
                        
                        <!-- ACCORDION CONTENT -->
                        <div id="betting-content-mobile" class="overflow-hidden transition-[max-height,opacity] duration-500 ease-in-out max-h-0 opacity-0 bg-white dark:bg-gray-800 border-t border-gray-100 dark:border-gray-700 rounded-b-2xl">
                            <div class="p-4">
                                <div class="flex justify-between items-center mb-4">
                                    <div class="flex items-center gap-2">
                                        <button onclick="openOddsExplanationModal()" class="info-btn text-gray-400 hover:text-blue-500 transition-colors flex items-center justify-center p-1 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/30 gap-1 mt-1">
                                            <span class="material-symbols-outlined text-[18px]">info</span> <span class="text-xs font-semibold">Info Quota</span>
                                        </button>
                                        <div class="hidden items-center gap-1 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400 px-2 py-0.5 rounded text-[10px] font-medium border border-indigo-100 dark:border-indigo-800/30 mt-1" id="oddsDivisorBadge-mobile">
                                            <span class="material-symbols-outlined text-[12px]">public</span> Div. 
                                            <span id="oddsDivisorValue-mobile" class="font-bold">--</span>
                                        </div>
                                    </div>
                                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Strisciate: <span id="user-credits-mobile" class="text-primary font-bold">---</span></span>
                                </div>

                            <div class="mb-4">
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Puntata</label>
                                <input type="number" id="bet-amount-mobile" class="w-full bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg px-3 py-2 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Quantità" value="10">
                            </div>

                            <div class="grid grid-cols-2 gap-3 mb-4">
                                <button onclick="placeBet('winner', '1')" class="bg-blue-50 dark:bg-blue-900/30 hover:bg-blue-100 dark:hover:bg-blue-900/50 border border-blue-200 dark:border-blue-800 rounded-xl p-3 flex flex-col items-center transition-colors">
                                    <span class="text-xs font-bold text-blue-600 dark:text-blue-400">Vince Blu</span>
                                    <span class="text-lg font-bold text-gray-900 dark:text-white" id="odds-team1-mobile">---</span>
                                </button>
                                <button onclick="placeBet('winner', '2')" class="bg-red-50 dark:bg-red-900/30 hover:bg-red-100 dark:hover:bg-red-900/50 border border-red-200 dark:border-red-800 rounded-xl p-3 flex flex-col items-center transition-colors">
                                    <span class="text-xs font-bold text-red-600 dark:text-red-400">Vince Rosso</span>
                                    <span class="text-lg font-bold text-gray-900 dark:text-white" id="odds-team2-mobile">---</span>
                                </button>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <button onclick="placeBet('deuce', 'yes')" class="bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 border border-gray-200 dark:border-gray-600 rounded-xl p-3 flex flex-col items-center transition-colors">
                                    <span class="text-xs font-bold text-gray-500 dark:text-gray-400">Vantaggi? SI</span>
                                    <span class="text-lg font-bold text-gray-900 dark:text-white" id="odds-deuce-yes-mobile">---</span>
                                </button>
                                <button onclick="placeBet('deuce', 'no')" class="bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 border border-gray-200 dark:border-gray-600 rounded-xl p-3 flex flex-col items-center transition-colors">
                                    <span class="text-xs font-bold text-gray-500 dark:text-gray-400">Vantaggi? NO</span>
                                    <span class="text-lg font-bold text-gray-900 dark:text-white" id="odds-deuce-no-mobile">---</span>
                                </button>
                            </div>
                            </div>
                        </div>
                    </div>
                    <?php
endif; ?>
                </div>

                <div class="hidden md:grid grid-cols-12 gap-8 mb-8">

                    <?php if ($isLoggedIn): ?>
                    <div class="col-span-12 grid grid-cols-3 gap-6">
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm relative overflow-hidden group">
                            <div class="absolute right-0 top-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                                <span class="material-symbols-outlined text-[80px] text-primary">trophy</span>
                            </div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">ELO</p>
                            <h2 class="text-4xl font-bold text-gray-900 dark:text-white" id="dashboardEloDesktop">---</h2>
                        </div>
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Posizione</p>
                            <h2 class="text-4xl font-bold text-gray-900 dark:text-white" id="dashboardRankDesktop">#--</h2>
                        </div>
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm relative overflow-hidden group">
                            <div class="absolute right-0 top-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                                <span class="material-symbols-outlined text-[80px] text-yellow-500">monetization_on</span>
                            </div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Strisciate</p>
                            <h2 class="text-4xl font-bold text-yellow-500" id="dashboardCreditsDesktop">---</h2>
                        </div>
                        
                        <!-- RECENT STATS DESKTOP -->
                        <div id="recentStatsWidgetDesktop" class="col-span-3 hidden bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900 p-6 rounded-3xl border border-gray-200/60 dark:border-gray-700/50 shadow-xl shadow-gray-200/40 dark:shadow-none relative overflow-hidden group hover:border-blue-300/50 dark:hover:border-blue-800/50 transition-all duration-500">
                            <div class="absolute -top-16 -right-16 w-48 h-48 bg-blue-500/10 dark:bg-blue-500/5 rounded-full blur-3xl pointer-events-none group-hover:bg-blue-500/15 transition-colors duration-500"></div>
                            <div class="flex justify-between items-center mb-6 relative z-10">
                                <h3 class="text-xs font-black text-gray-400 dark:text-gray-500 uppercase tracking-[0.25em] flex items-center gap-2">
                                    <span class="material-symbols-outlined text-[20px] text-blue-500 drop-shadow-sm">query_stats</span>
                                    Forma
                                </h3>
                                <div id="recentEloDeltaDesktop" class="transition-transform duration-300 hover:scale-105">--</div>
                            </div>
                            <div class="relative px-3 mt-4 pb-2">
                                <!-- Background line -->
                                <div class="absolute w-[calc(100%-3rem)] left-6 top-1/2 -translate-y-1/2 h-1.5 bg-gray-100 dark:bg-gray-800 rounded-full z-0 overflow-hidden">
                                     <div class="w-full h-full bg-blue-500/20 dark:bg-blue-500/10 animate-pulse"></div>
                                </div>
                                
                                <div class="flex justify-between items-center relative z-10" id="recentMatchDotsDesktop">
                                    <!-- Dots qui -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
else: ?>
                    <div class="col-span-12 bg-white dark:bg-gray-800 p-10 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm text-center flex flex-col items-center justify-center min-h-[300px]">
                        <div class="h-20 w-20 bg-gray-100 dark:bg-gray-700/50 rounded-full flex items-center justify-center mb-6 ring-8 ring-gray-50 dark:ring-gray-700/20">
                            <span class="material-symbols-outlined text-4xl text-gray-400 dark:text-gray-500">lock</span>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-3">Accesso Richiesto</h2>
                        <p class="text-gray-500 dark:text-gray-400 mb-8 max-w-md text-lg">Effettua il login per visualizzare le tue statistiche complete, l'andamento del tuo Elo e la tua posizione nella classifica globale.</p>
                        <button onclick="toggleAuthModal()" class="px-8 py-3.5 bg-primary xl:hover:bg-blue-600 text-white font-bold rounded-xl transition-all shadow-lg shadow-blue-500/30 transform xl:hover:-translate-y-1 flex items-center gap-3 text-lg">
                            <span class="material-symbols-outlined">login</span>
                            <span>Effettua il Login</span>
                        </button>
                    </div>
                    <?php
endif; ?>

                    <div class="col-span-12 h-full">
                        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm h-full flex flex-col">
                            <div class="p-4 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center relative">
                                <div class="flex items-center gap-4">
                                    <h3 class="font-bold text-gray-900 dark:text-white">Partita Live</h3>
                                    <div id="liveBountyBanner-desktop" class="hidden bg-gradient-to-r from-yellow-400 to-orange-500 rounded-lg px-2 py-1 shadow-sm flex items-center gap-1 animate-pulse" title="Il capolista sta giocando nel suo ruolo migliore!">
                                        <span class="material-symbols-outlined text-white text-[12px]">target</span>
                                        <span class="text-white font-black text-[9px] uppercase tracking-widest">Taglia Attiva</span>
                                    </div>
                                </div>
                                <button onclick="resetLiveTable()" class="text-xs text-red-500 xl:hover:text-red-700 font-medium px-2 py-1 rounded xl:hover:bg-red-50 dark:xl:hover:bg-red-900/20 transition-colors">Reset</button>
                            </div>
                            <div class="flex-1 p-4 flex flex-col gap-4 overflow-y-auto custom-scrollbar relative">

                                <div class="bg-blue-50 dark:bg-blue-900/10 rounded-xl p-4 border border-blue-100 dark:border-blue-800/30 relative">
                                    <div class="flex justify-between items-center mb-4">
                                        <div class="text-sm font-bold text-blue-600 dark:text-blue-400 uppercase tracking-widest">Squadra Blu</div>
                                        <div class="flex items-center pr-4" id="live-score-container-s1-desktop" style="display: none;">
                                            <div id="live-score-s1-desktop" class="text-6xl font-black text-blue-600 dark:text-blue-400 w-16 text-center tracking-tighter tabular-nums drop-shadow-sm">0</div>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div id="live-desktop-s1p" class="bg-white dark:bg-gray-800 border-2 border-dashed border-blue-200 dark:border-blue-800 rounded-lg p-4 flex flex-col items-center justify-center xl:cursor-pointer xl:hover:border-blue-400 xl:hover:shadow-md transition-all group min-h-[100px] relative" onclick="sitDown('s1p')">
                                            <span class="text-3xl mb-2 group-hover:scale-110 transition-transform">🥅</span>
                                            <span class="text-[10px] text-gray-400 font-medium uppercase tracking-widest mb-1">Portiere</span>
                                            <span class="text-sm font-bold text-gray-800 dark:text-gray-200 player-name text-center">Libero</span>
                                        </div>
                                        <div id="live-desktop-s1a" class="bg-white dark:bg-gray-800 border-2 border-dashed border-blue-200 dark:border-blue-800 rounded-lg p-4 flex flex-col items-center justify-center xl:cursor-pointer xl:hover:border-blue-400 xl:hover:shadow-md transition-all group min-h-[100px] relative" onclick="sitDown('s1a')">
                                            <span class="text-3xl mb-2 group-hover:scale-110 transition-transform">⚽</span>
                                            <span class="text-[10px] text-gray-400 font-medium uppercase tracking-widest mb-1">Attacco</span>
                                            <span class="text-sm font-bold text-gray-800 dark:text-gray-200 player-name text-center">Libero</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-red-50 dark:bg-red-900/10 rounded-xl p-4 border border-red-100 dark:border-red-800/30 relative">
                                    <div class="flex justify-between items-center mb-4">
                                        <div class="text-sm font-bold text-red-600 dark:text-red-400 uppercase tracking-widest">Squadra Rossa</div>
                                        <div class="flex items-center pr-4" id="live-score-container-s2-desktop" style="display: none;">
                                            <div id="live-score-s2-desktop" class="text-6xl font-black text-red-600 dark:text-red-400 w-16 text-center tracking-tighter tabular-nums drop-shadow-sm">0</div>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div id="live-desktop-s2a" class="bg-white dark:bg-gray-800 border-2 border-dashed border-red-200 dark:border-red-800 rounded-lg p-4 flex flex-col items-center justify-center xl:cursor-pointer xl:hover:border-red-400 xl:hover:shadow-md transition-all group min-h-[100px] relative" onclick="sitDown('s2a')">
                                            <span class="text-3xl mb-2 group-hover:scale-110 transition-transform">⚽</span>
                                            <span class="text-[10px] text-gray-400 font-medium uppercase tracking-widest mb-1">Attacco</span>
                                            <span class="text-sm font-bold text-gray-800 dark:text-gray-200 player-name text-center">Libero</span>
                                        </div>
                                        <div id="live-desktop-s2p" class="bg-white dark:bg-gray-800 border-2 border-dashed border-red-200 dark:border-red-800 rounded-lg p-4 flex flex-col items-center justify-center xl:cursor-pointer xl:hover:border-red-400 xl:hover:shadow-md transition-all group min-h-[100px] relative" onclick="sitDown('s2p')">
                                            <span class="text-3xl mb-2 group-hover:scale-110 transition-transform">🥅</span>
                                            <span class="text-[10px] text-gray-400 font-medium uppercase tracking-widest mb-1">Portiere</span>
                                            <span class="text-sm font-bold text-gray-800 dark:text-gray-200 player-name text-center">Libero</span>
                                        </div>
                                    </div>
                                </div>

                            </div>
                            
                            <?php if ($isLoggedIn): ?>
                            <div id="betting-wrapper-desktop" class="hidden border-t border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 rounded-b-2xl">
                                <!-- ACCORDION HEADER -->
                                <div class="w-full flex justify-between items-center p-4 cursor-pointer bg-gray-50/50 dark:bg-gray-800/50 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors" onclick="toggleAccordion('desktop')">
                                    <span class="font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2 text-lg">
                                        <span class="material-symbols-outlined text-yellow-500">monetization_on</span> Scommesse Live
                                    </span>
                                    <div class="flex items-center gap-4">
                                        <span class="font-bold text-red-500 animate-pulse text-lg" id="betting-timer-desktop"></span>
                                        <span class="material-symbols-outlined text-gray-400 transition-transform duration-500" id="chev-desk">expand_more</span>
                                    </div>
                                </div>
                                
                                <!-- ACCORDION CONTENT -->
                                <div id="betting-content-desktop" class="overflow-hidden transition-[max-height,opacity] duration-500 ease-in-out max-h-0 opacity-0 bg-white dark:bg-gray-800 border-t border-gray-100 dark:border-gray-700 rounded-b-2xl">
                                    <div class="p-6">
                                    <div class="flex justify-between items-center mb-6">
                                        <div class="flex items-center gap-3">
                                            <button onclick="openOddsExplanationModal()" class="info-btn text-gray-400 hover:text-blue-500 transition-colors flex items-center gap-2 p-1.5 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/30">
                                                <span class="material-symbols-outlined">info</span> <span class="text-sm font-semibold">Spiegazione Quote</span>
                                            </button>
                                            <div class="hidden items-center gap-1.5 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400 px-3 py-1 rounded-full text-xs font-medium border border-indigo-100 dark:border-indigo-800/30" id="oddsDivisorBadge-desktop">
                                                <span class="material-symbols-outlined text-[14px]">public</span> Divisore:
                                                <span id="oddsDivisorValue-desktop" class="font-bold">--</span>
                                            </div>
                                        </div>
                                        <div class="bg-gray-100 dark:bg-gray-700 px-4 py-2 rounded-lg">
                                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Strisciate:</span>
                                            <span class="text-lg font-bold text-primary ml-2" id="user-credits-desktop">---</span>
                                        </div>
                                    </div>

                                    <div class="flex gap-4 mb-6 items-end">
                                        <div class="flex-1">
                                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">La tua puntata</label>
                                            <div class="relative">
                                                <input type="number" id="bet-amount-desktop" class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-600 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary font-bold text-lg" placeholder="10" value="10">
                                                <div class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-bold">STR</div>
                                            </div>
                                        </div>
                                        <button onclick="setBetAmount(10)" class="px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-sm font-bold text-gray-600 dark:text-gray-300 transition-colors">10</button>
                                        <button onclick="setBetAmount(50)" class="px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-sm font-bold text-gray-600 dark:text-gray-300 transition-colors">50</button>
                                        <button onclick="setBetAmount(100)" class="px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-sm font-bold text-gray-600 dark:text-gray-300 transition-colors">100</button>
                                    </div>

                                    <div class="grid grid-cols-4 gap-4">
                                        <button onclick="placeBet('winner', '1')" class="group relative overflow-hidden bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/40 border border-blue-200 dark:border-blue-800 rounded-xl p-4 transition-all">
                                            <div class="text-xs font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wider mb-2">Vince Blu</div>
                                            <div class="text-3xl font-bold text-gray-900 dark:text-white" id="odds-team1-desktop">---</div>
                                            <div class="absolute bottom-0 right-0 p-2 opacity-10 group-hover:opacity-20">
                                                <span class="material-symbols-outlined text-4xl">inventory_2</span>
                                            </div>
                                        </button>
                                        <button onclick="placeBet('winner', '2')" class="group relative overflow-hidden bg-red-50 dark:bg-red-900/20 hover:bg-red-100 dark:hover:bg-red-900/40 border border-red-200 dark:border-red-800 rounded-xl p-4 transition-all">
                                            <div class="text-xs font-bold text-red-600 dark:text-red-400 uppercase tracking-wider mb-2">Vince Rosso</div>
                                            <div class="text-3xl font-bold text-gray-900 dark:text-white" id="odds-team2-desktop">---</div>
                                            <div class="absolute bottom-0 right-0 p-2 opacity-10 group-hover:opacity-20">
                                                <span class="material-symbols-outlined text-4xl">inventory_2</span>
                                            </div>
                                        </button>
                                        <button onclick="placeBet('deuce', 'yes')" class="group relative overflow-hidden bg-gray-50 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl p-4 transition-all">
                                            <div class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Vantaggi: SI</div>
                                            <div class="text-3xl font-bold text-gray-900 dark:text-white" id="odds-deuce-yes-desktop">---</div>
                                        </button>
                                        <button onclick="placeBet('deuce', 'no')" class="group relative overflow-hidden bg-gray-50 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl p-4 transition-all">
                                            <div class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Vantaggi: NO</div>
                                            <div class="text-3xl font-bold text-gray-900 dark:text-white" id="odds-deuce-no-desktop">---</div>
                                        </button>
                                    </div>
                                    </div>
                                </div>
                            </div>
                            <?php
endif; ?>
                        </div>
                    </div>
                </div>

                    <div id="live-bonuses-widget" class="col-span-12 hidden px-4 md:px-0">
                        <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-2xl border border-indigo-200 dark:border-indigo-800/40 p-3 mb-4 shadow-sm max-w-md mx-auto md:max-w-none">
                            <div class="flex justify-between items-center mb-2 px-1">
                                <h3 class="font-bold text-indigo-900 dark:text-indigo-200 flex items-center gap-1.5 text-sm uppercase tracking-tight">
                                    <span class="material-symbols-outlined text-xl">backpack</span>
                                    Il Mio Inventario
                                </h3>
                                <button onclick="openShopModal()" class="text-[10px] bg-indigo-600 text-white px-2.5 py-1 rounded-lg font-bold xl:hover:bg-indigo-700 transition-all active:scale-95 shadow-sm">
                                    + SHOP
                                </button>
                            </div>

                            <div id="inventory-list" class="flex gap-2 overflow-x-auto p-1 pb-2 custom-scrollbar min-h-[70px]">

                                <div class="text-[10px] text-gray-500 italic w-full text-center py-2">Nessun bonus disponibile</div>
                            </div>
                        </div>
                    </div>

                <div class="px-5 md:px-0">

                    <?php if (isset($_SESSION['match_payout'])): ?>
                    <div id="payout-notification" class="mb-6 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-2xl p-4 flex items-start gap-4 shadow-sm animate-fade-in relative">
                         <div class="h-10 w-10 rounded-full bg-green-100 dark:bg-green-900/40 text-green-600 dark:text-green-400 flex items-center justify-center flex-shrink-0">
                            <span class="material-symbols-outlined">celebration</span>
                         </div>
                         <div class="flex-1">
                             <h4 class="font-bold text-green-900 dark:text-green-100">Vittoria!</h4>
                             <p class="text-sm text-green-700 dark:text-green-300"><?php echo $_SESSION['match_payout']['message']; ?></p>
                         </div>
                         <button onclick="this.parentElement.remove()" class="text-green-400 hover:text-green-600 dark:hover:text-green-200">
                             <span class="material-symbols-outlined">close</span>
                         </button>
                    </div>
                    <?php unset($_SESSION['match_payout']); ?>
                    <?php
endif; ?>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Partite Recenti</h3>
                        <button class="text-sm font-medium text-primary hover:text-primary/80" onclick="showPage('matches')">Vedi Tutte</button>
                    </div>

                    <div class="space-y-3 md:grid md:grid-cols-2 lg:grid-cols-3 md:gap-4 md:space-y-0" id="recentMatchesList">
                        <div class="text-center text-gray-500 py-4 col-span-full">Caricamento partite...</div>
                    </div>
                </div>
            </div>

            <!-- Season Pass View -->
            <section id="season-pass" class="view-section hidden pb-32">
                <div class="px-6 pt-6 flex items-center gap-4 mb-8">
                    <button onclick="showPage('home')" class="h-10 w-10 flex items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 hover:bg-gray-200 transition-colors">
                        <span class="material-symbols-outlined">arrow_back</span>
                    </button>
                    <div>
                        <h1 class="text-3xl font-black text-gray-900 dark:text-white uppercase tracking-tight leading-none">Season Pass</h1>
                        <p class="text-gray-500 dark:text-gray-400 text-[11px] font-medium uppercase tracking-wider mt-1">Livelli & Ricompense</p>
                    </div>
                </div>

                <!-- Progress Card -->
                <div class="px-6 mb-8">
                    <div class="bg-gradient-to-br from-indigo-600 to-purple-700 rounded-3xl p-6 text-white shadow-xl shadow-indigo-500/20 relative overflow-hidden">
                        <div class="absolute -top-10 -right-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
                        <div class="relative z-10">
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-3">
                                    <div class="h-12 w-12 bg-white/20 backdrop-blur-md rounded-2xl flex items-center justify-center border border-white/30">
                                        <span class="material-symbols-outlined text-3xl font-bold">star</span>
                                    </div>
                                    <div>
                                        <p class="text-[10px] uppercase font-black tracking-widest opacity-80">Livello Attuale</p>
                                        <h2 class="text-2xl font-black leading-none" id="sp-current-level">LV ---</h2>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-[10px] uppercase font-black tracking-widest opacity-80">Punti Totali</p>
                                    <h2 class="text-xl font-bold leading-none" id="sp-total-xp">---</h2>
                                </div>
                            </div>
                            
                            <div class="space-y-2">
                                <div class="h-3 w-full bg-white/20 rounded-full overflow-hidden">
                                    <div id="sp-progress-bar" class="h-full bg-white rounded-full transition-all duration-1000 ease-out shadow-[0_0_10px_rgba(255,255,255,0.5)]" style="width: 0%"></div>
                                </div>
                                <div class="flex justify-between text-[11px] font-black uppercase tracking-wider opacity-90">
                                    <span id="sp-xp-label">--- / 500 XP</span>
                                    <span id="sp-next-level-target">LV ---</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab Switcher -->
                <div class="px-6 mb-6">
                    <div class="flex p-1 bg-gray-100 dark:bg-gray-800/50 rounded-2xl">
                        <button onclick="switchSPTab('rewards')" id="sp-tab-rewards" class="flex-1 py-3 text-xs font-black uppercase tracking-widest rounded-xl transition-all bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm">
                            Ricompense
                        </button>
                        <button onclick="switchSPTab('missions')" id="sp-tab-missions" class="flex-1 py-3 text-xs font-black uppercase tracking-widest rounded-xl transition-all text-gray-500 dark:text-gray-400">
                            Missioni
                        </button>
                    </div>
                </div>

                <!-- Reward Track Section -->
                <div id="sp-content-rewards">
                    <div class="px-6">
                        <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest mb-6 px-1 text-center">Percorso Sbloccabili</h3>
                        <div id="sp-reward-track" class="space-y-4 max-w-md mx-auto relative">
                            <!-- Vertical line for track -->
                            <div class="absolute left-6 top-8 bottom-8 w-1 bg-gray-100 dark:bg-gray-800 -z-10"></div>
                            <!-- Rewards injected here -->
                        </div>
                    </div>
                </div>

                <!-- Missions Section -->
                <div id="sp-content-missions" class="hidden px-6">
                    <div class="mb-8">
                        <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">today</span>
                            Missioni Giornaliere
                        </h3>
                        <div id="sp-daily-list" class="space-y-3">
                            <!-- Daily objectives here -->
                        </div>
                    </div>

                    <div>
                        <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">workspace_premium</span>
                            Missioni Stagionali
                        </h3>
                        <div id="sp-seasonal-list" class="space-y-3">
                            <!-- Seasonal objectives here -->
                        </div>
                    </div>
                </div>
            </section>

            <section id="leaderboard" class="view-section hidden px-5 pt-4 md:px-0 md:pt-0 animate-fade-in">
                 <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">Classifica</h2>
                        <select id="leaderboardSeasonSelect" onchange="changeLeaderboardSeason(this.value)" class="bg-gray-100 dark:bg-gray-700 border-0 text-gray-900 dark:text-white rounded-lg text-sm font-bold p-2 cursor-pointer focus:ring-2 focus:ring-blue-500 hidden md:block">
                        </select>
                    </div>

                    <button onclick="openComparisonModal()" class="hidden md:flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-xl font-bold transition-all shadow-sm text-base">
                        <span class="material-symbols-outlined text-lg">compare_arrows</span>
                        <span>Confronta Giocatori</span>
                    </button>
                 </div>
                 
                 <!-- Mobile Dropdown -->
                 <div class="md:hidden w-full mb-4">
                     <select id="leaderboardSeasonSelectMobile" onchange="changeLeaderboardSeason(this.value)" class="w-full bg-gray-100 dark:bg-gray-700 border-0 text-gray-900 dark:text-white rounded-lg text-sm font-bold p-3 cursor-pointer focus:ring-2 focus:ring-blue-500">
                     </select>
                 </div>

                 <div class="flex gap-2 mb-4 overflow-x-auto pb-2 scrollbar-hide">
                     <button onclick="switchLeaderboardTab('generale')" id="tab-generale" class="leaderboard-tab px-4 md:px-6 py-2 md:py-2.5 rounded-xl text-sm md:text-base font-bold transition-all whitespace-nowrap bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 flex-shrink-0 [&.active]:bg-blue-500 [&.active]:text-white">
                         Generale
                     </button>
                     <button onclick="switchLeaderboardTab('attaccanti')" id="tab-attaccanti" class="leaderboard-tab px-4 md:px-6 py-2 md:py-2.5 rounded-xl text-sm md:text-base font-bold transition-all whitespace-nowrap bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 flex-shrink-0 [&.active]:bg-blue-500 [&.active]:text-white">
                         Attaccanti
                     </button>
                     <button onclick="switchLeaderboardTab('portieri')" id="tab-portieri" class="leaderboard-tab px-4 md:px-6 py-2 md:py-2.5 rounded-xl text-sm md:text-base font-bold transition-all whitespace-nowrap bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 flex-shrink-0 [&.active]:bg-blue-500 [&.active]:text-white">
                         Portieri
                     </button>
                 </div>

                 <button onclick="openComparisonModal()" class="md:hidden w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-500 hover:bg-blue-600 text-white rounded-xl font-bold transition-all shadow-sm text-sm mb-6">
                     <span class="material-symbols-outlined text-lg">compare_arrows</span>
                     <span>Confronta Giocatori</span>
                 </button>

                 <div class="flex flex-col gap-8">
                     <div class="w-full flex justify-center items-end gap-2 mb-4 mt-4 md:mt-8">
                         <div id="leaderboardPodium" class="w-full max-w-3xl flex justify-center items-end gap-2">
                         </div>
                     </div>

                     <div class="w-full max-w-4xl mx-auto space-y-2" id="leaderboardList">
                     </div>
                 </div>
            </section>

            <div id="matches" class="view-section hidden px-5 pt-4 md:px-0 md:pt-0 animate-fade-in">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">Storico Partite</h2>
                        <select id="matchesSeasonSelect" onchange="changeMatchesSeason(this.value)" class="bg-gray-100 dark:bg-gray-700 border-0 text-gray-900 dark:text-white rounded-lg text-sm font-bold p-2 cursor-pointer focus:ring-2 focus:ring-blue-500 hidden md:block">
                        </select>
                    </div>
                    <button onclick="openMatchSearchModal()" class="hidden md:flex items-center gap-2 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-xl font-bold transition-all shadow-sm text-sm md:text-base">
                        <span class="material-symbols-outlined text-lg">search</span>
                        <span>Cerca Partite</span>
                    </button>
                </div>
                <!-- Mobile Dropdown -->
                <div class="md:hidden w-full mb-4">
                    <select id="matchesSeasonSelectMobile" onchange="changeMatchesSeason(this.value)" class="w-full bg-gray-100 dark:bg-gray-700 border-0 text-gray-900 dark:text-white rounded-lg text-sm font-bold p-3 cursor-pointer focus:ring-2 focus:ring-blue-500">
                    </select>
                </div>
                <div class="space-y-3 md:grid md:grid-cols-2 xl:grid-cols-3 md:gap-4 md:space-y-0" id="fullMatchList">

                </div>

                <div id="loadMoreContainer" class="mt-8 flex justify-center hidden">
                    <button onclick="loadMoreMatches()" class="px-8 py-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 font-bold rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition-all shadow-sm flex items-center gap-2">
                        <span class="material-symbols-outlined">expand_more</span>
                        <span>Mostra Altre</span>
                    </button>
                </div>

                <div class="md:hidden fixed <?php echo $currentUserIsAdmin ? 'bottom-[160px]' : 'bottom-[90px]'; ?> right-5 z-20 flex flex-col gap-3">
                    <!-- Season Pass / Shop Icon -->
                    <button id="matchesSeasonPassBtn" onclick="showPage('season-pass')" class="p-2.5 rounded-2xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-500 hover:scale-110 active:scale-95 transition-all shadow-lg shadow-indigo-500/10">
                        <span class="material-symbols-outlined text-[26px]">confirmation_number</span>
                    </button>
                    <button onclick="openMatchSearchModal()" class="flex items-center justify-center h-14 w-14 bg-blue-600 text-white rounded-full shadow-lg shadow-blue-600/30 transition-all transform active:scale-95">
                        <span class="material-symbols-outlined text-[28px]">search</span>
                    </button>
                </div>
            </div>

            <div id="profile" class="view-section hidden px-4 pb-20 md:px-0 md:pb-0 animate-fade-in max-w-4xl mx-auto">
                <br>

                <div id="profileContent" class="space-y-6">

                </div>
            </div>

        </div>
    </main>

    <?php if ($currentUserIsAdmin): ?>
    <div id="addMatchBtnContainer" class="fixed bottom-[90px] md:bottom-8 right-5 md:right-8 z-20">
        <button onclick="showAddMatchModal()" class="flex items-center gap-2 bg-primary hover:bg-blue-600 text-white shadow-lg shadow-blue-500/30 rounded-full px-5 py-3.5 transition-all transform active:scale-95">
            <span class="material-symbols-outlined text-[24px]">add</span>
            <span class="font-bold tracking-wide">Aggiungi Partita</span>
        </button>
    </div>
    <?php
endif; ?>

    <nav class="md:hidden fixed bottom-0 left-0 w-full bg-white dark:bg-background-dark border-t border-gray-100 dark:border-gray-800 px-6 py-3 flex justify-between items-center z-50 pb-6 shadow-lg">
        <a href="#" onclick="showPage('home')" class="nav-item flex flex-col items-center gap-1 text-primary group active" data-target="home">
            <span class="material-symbols-outlined text-[26px] xl:group-hover:scale-110 transition-transform text-gray-400 group-[.active]:text-primary group-[.active]:fill-current">home</span>
            <span class="text-[10px] font-medium">Home</span>
        </a>
        <a href="#" onclick="showPage('leaderboard')" class="nav-item flex flex-col items-center gap-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 group transition-colors" data-target="leaderboard">
            <span class="material-symbols-outlined text-[26px] xl:group-hover:scale-110 transition-transform text-gray-400 group-[.active]:text-primary group-[.active]:fill-current">leaderboard</span>
            <span class="text-[10px] font-medium">Classifica</span>
        </a>
        <a href="#" onclick="showPage('matches')" class="nav-item flex flex-col items-center gap-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 group transition-colors" data-target="matches">
            <span class="material-symbols-outlined text-[26px] xl:group-hover:scale-110 transition-transform text-gray-400 group-[.active]:text-primary group-[.active]:fill-current">history</span>
            <span class="text-[10px] font-medium">Storico</span>
        </a>
        <a href="#" onclick="showPage('profile')" class="nav-item flex flex-col items-center gap-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 group transition-colors" data-target="profile">
            <span class="material-symbols-outlined text-[26px] xl:group-hover:scale-110 transition-transform text-gray-400 group-[.active]:text-primary group-[.active]:fill-current">person</span>
            <span class="text-[10px] font-medium">Profilo</span>
        </a>
    </nav>
</div>

    <div id="matchDetailsModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm hidden" onclick="closeMatchDetailsModal(event)">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl w-[90%] max-w-sm shadow-2xl border border-gray-700 transform transition-all scale-100 relative" onclick="event.stopPropagation()">
            <button onclick="closeMatchDetailsModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                <span class="material-symbols-outlined">close</span>
            </button>

            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-1 text-center">Dettagli Partita</h3>
            <p class="text-xs text-gray-500 text-center mb-6">
                <span id="matchDetailsDate">---</span> • <span id="matchDetailsSeason" class="font-bold text-primary tracking-wider uppercase">---</span>
            </p>

            <div id="matchDetailsResult" class="text-center font-bold text-lg py-2 rounded-xl mb-6">

            </div>

            <!-- Bounty Banner -->
            <div id="md-bounty-banner" class="hidden mb-6 bg-gradient-to-r from-yellow-400 to-orange-500 rounded-2xl p-4 shadow-lg shadow-yellow-500/20 relative overflow-hidden group">
                <div class="absolute -right-4 -top-4 opacity-10 group-hover:opacity-20 transition-opacity">
                    <span class="material-symbols-outlined text-[80px] text-white">target</span>
                </div>
                <div class="relative z-10 flex items-center gap-3">
                    <div class="h-10 w-10 bg-white/20 rounded-full flex items-center justify-center text-white">
                        <span class="material-symbols-outlined">payments</span>
                    </div>
                    <div>
                        <h4 class="text-white font-black text-xs uppercase tracking-widest">Taglia Attiva!</h4>
                        <p class="text-white/90 text-[10px] font-bold">Bounty sul capolista in questa partita</p>
                    </div>
                </div>
            </div>

            <div class="space-y-6">

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-blue-500 uppercase">Squadra Blu</span>
                        <span id="matchDetailsScoreBlue" class="text-sm font-bold text-gray-900 dark:text-white"></span>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-3 space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div id="md-s1p-avatar" class="h-10 w-10 rounded-full overflow-hidden border-2 border-white dark:border-gray-700 shrink-0"></div>
                                <div>
                                    <p class="text-xs text-gray-400 font-bold uppercase">POR</p>
                                    <p id="md-s1p-name" class="font-bold text-sm text-gray-900 dark:text-white truncate max-w-[100px]"></p>
                                </div>
                            </div>
                            <span id="md-s1p-delta" class="font-mono font-bold text-sm"></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div id="md-s1a-avatar" class="h-10 w-10 rounded-full overflow-hidden border-2 border-white dark:border-gray-700 shrink-0"></div>
                                <div>
                                    <p class="text-xs text-gray-400 font-bold uppercase">ATT</p>
                                    <p id="md-s1a-name" class="font-bold text-sm text-gray-900 dark:text-white truncate max-w-[100px]"></p>
                                </div>
                            </div>
                            <span id="md-s1a-delta" class="font-mono font-bold text-sm"></span>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-red-500 uppercase">Squadra Rossa</span>
                        <span id="matchDetailsScoreRed" class="text-sm font-bold text-gray-900 dark:text-white"></span>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-3 space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div id="md-s2p-avatar" class="h-10 w-10 rounded-full overflow-hidden border-2 border-white dark:border-gray-700 shrink-0"></div>
                                <div>
                                    <p class="text-xs text-gray-400 font-bold uppercase">POR</p>
                                    <p id="md-s2p-name" class="font-bold text-sm text-gray-900 dark:text-white truncate max-w-[100px]"></p>
                                </div>
                            </div>
                            <span id="md-s2p-delta" class="font-mono font-bold text-sm"></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div id="md-s2a-avatar" class="h-10 w-10 rounded-full overflow-hidden border-2 border-white dark:border-gray-700 shrink-0"></div>
                                <div>
                                    <p class="text-xs text-gray-400 font-bold uppercase">ATT</p>
                                    <p id="md-s2a-name" class="font-bold text-sm text-gray-900 dark:text-white truncate max-w-[100px]"></p>
                                </div>
                            </div>
                            <span id="md-s2a-delta" class="font-mono font-bold text-sm"></span>
                        </div>
                    </div>
                </div>
            </div>

            <div id="matchDetailsBonuses" class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-700 hidden">
                <p class="text-[10px] font-bold text-gray-400 uppercase mb-2">Bonus Utilizzati in questa partita</p>
                <div id="md-bonuses-list" class="flex flex-wrap gap-2">

                </div>
            </div>

            <div id="deleteMatchAction" class="mt-8 pt-6 border-t border-gray-100 dark:border-gray-700 hidden">
                <button onclick="deleteCurrentMatch()" class="w-full py-3 rounded-xl border-2 border-red-500/20 text-red-500 font-bold hover:bg-red-500 hover:text-white transition-all">
                    Elimina Partita & Ripristina Elo
                </button>
            </div>
        </div>
    </div>

    <div id="addMatchModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm hidden">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl w-[90%] max-w-sm shadow-2xl border border-gray-700 transform transition-all scale-100">
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-6 text-center">Nuova Partita</h3>

            <form id="addMatchForm" onsubmit="submitMatch(event)" class="space-y-4">

                <div class="space-y-2">
                    <label class="text-xs font-bold text-blue-500 uppercase tracking-wider">Team Blu (Por / Att)</label>
                    <div class="flex gap-2">
                        <select id="s1p" class="w-1/2 bg-gray-100 dark:bg-gray-700 border-0 text-gray-900 dark:text-white rounded-xl focus:ring-2 focus:ring-blue-500 text-sm p-3">
                            <option value="">POR...</option>
                        </select>
                        <select id="s1a" class="w-1/2 bg-gray-100 dark:bg-gray-700 border-0 text-gray-900 dark:text-white rounded-xl focus:ring-2 focus:ring-blue-500 text-sm p-3">
                            <option value="">ATT...</option>
                        </select>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-xs font-bold text-red-500 uppercase tracking-wider">Team Rosso (Por / Att)</label>
                    <div class="flex gap-2">
                        <select id="s2p" class="w-1/2 bg-gray-100 dark:bg-gray-700 border-0 text-gray-900 dark:text-white rounded-xl focus:ring-2 focus:ring-red-500 text-sm p-3">
                            <option value="">POR...</option>
                        </select>
                        <select id="s2a" class="w-1/2 bg-gray-100 dark:bg-gray-700 border-0 text-gray-900 dark:text-white rounded-xl focus:ring-2 focus:ring-red-500 text-sm p-3">
                            <option value="">ATT...</option>
                        </select>
                    </div>
                </div>

                <div class="pt-2">
                    <label class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider block mb-2">Vincitori</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="cursor-pointer">
                            <input type="radio" name="winningTeam" value="1" class="peer sr-only">
                            <div class="rounded-xl border-2 border-transparent bg-blue-500/10 text-blue-500 py-3 text-center font-bold peer-checked:bg-blue-500 peer-checked:text-white peer-checked:border-blue-500 transition-all">
                                Blu
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="winningTeam" value="2" class="peer sr-only">
                            <div class="rounded-xl border-2 border-transparent bg-red-500/10 text-red-500 py-3 text-center font-bold peer-checked:bg-red-500 peer-checked:text-white peer-checked:border-red-500 transition-all">
                                Rosso
                            </div>
                        </label>
                    </div>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeAddMatchModal()" class="flex-1 py-3.5 rounded-xl text-gray-500 font-bold hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">Annulla</button>
                    <button type="submit" class="flex-1 py-3.5 rounded-xl bg-gray-900 dark:bg-white text-white dark:text-gray-900 font-bold hover:opacity-90 transition-opacity">Registra</button>
                </div>
            </form>
        </div>
    </div>

    <div id="authModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm hidden">
        <div class="bg-white dark:bg-gray-800 p-8 rounded-3xl w-[90%] max-w-sm shadow-2xl border border-gray-700 transform transition-all scale-100 relative">
            <button onclick="toggleAuthModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                <span class="material-symbols-outlined">close</span>
            </button>
            <div class="text-center mb-6">
                <div class="h-16 w-16 bg-primary/10 text-primary rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="material-symbols-outlined text-3xl">lock</span>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white">Bentornato</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Accedi per utilizzare la web app.</p>
            </div>

            <form onsubmit="handleLogin(event)" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Username</label>
                    <input type="text" id="loginUsername" class="w-full bg-gray-50 dark:bg-gray-700 border-gray-200 dark:border-gray-600 text-gray-900 dark:text-white rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent p-3" placeholder="Username">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Password</label>
                    <input type="password" id="loginPassword" class="w-full bg-gray-50 dark:bg-gray-700 border-gray-200 dark:border-gray-600 text-gray-900 dark:text-white rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent p-3" placeholder="••••••••">
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-3.5 rounded-xl bg-primary text-white font-bold hover:bg-blue-600 transition-colors shadow-lg shadow-blue-500/25">
                        Accedi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="app.js?v=<?php echo time(); ?>"></script>
    <script src="shop.js?v=<?php echo time(); ?>"></script>

    <div id="playerDetailsModal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true">

        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity" onclick="closePlayerDetailsModal(event)"></div>

        <div class="relative w-full max-w-lg mx-auto mt-10 md:mt-20 px-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[85vh]">

                <div class="relative p-6 pb-0 text-center shrink-0">
                    <button onclick="closePlayerDetailsModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                        <span class="material-symbols-outlined text-[24px]">close</span>
                    </button>

                    <div class="flex flex-col items-center">
                        <div class="relative mb-3">
                            <div id="pd-aura" class="absolute inset-[-8px] rounded-full opacity-70 z-0"></div>
                            <div class="h-24 w-24 rounded-full p-1 shadow-lg ring-4 ring-gray-100 dark:ring-gray-700 bg-white dark:bg-gray-800 overflow-hidden relative z-10">
                                <div id="pd-avatar-container" class="w-full h-full rounded-full overflow-hidden"></div>
                            </div>
                        </div>
                        <h2 id="pd-name" class="text-2xl font-bold text-gray-900 dark:text-white"></h2>
                        <div id="pd-title" class="player-title mb-1"></div>
                        <a id="pd-username" class="text-primary font-medium text-sm hover:underline cursor-pointer"></a>
                    </div>
                </div>

                <div class="p-6 overflow-y-auto custom-scrollbar space-y-8">
                    
                    <!-- Universal Season Selector -->
                    <div class="bg-indigo-50 dark:bg-indigo-900/10 rounded-2xl p-4 border border-indigo-100 dark:border-indigo-800 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-indigo-500">calendar_month</span>
                            <span class="text-sm font-bold text-indigo-700 dark:text-indigo-300">Statistiche per:</span>
                        </div>
                        <select id="playerModalSeasonSelect" onchange="changePlayerModalSeason(this.value)" class="bg-white dark:bg-gray-800 border border-indigo-200 dark:border-indigo-700 rounded-xl text-sm font-bold px-3 py-1.5 focus:ring-2 focus:ring-indigo-500 text-gray-900 dark:text-white outline-none">
                            <!-- Populated dynamically -->
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">

                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 text-center">
                            <p class="text-xs font-bold text-gray-500 uppercase mb-1">Attaccante</p>
                            <div class="text-xl font-bold text-gray-900 dark:text-white mb-1" id="pd-elo-atk">---</div>
                            <div class="text-xs text-emerald-500 font-bold" id="pd-winrate-atk">--% Vittorie</div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 text-center">
                            <p class="text-xs font-bold text-gray-500 uppercase mb-1">Portiere</p>
                            <div class="text-xl font-bold text-gray-900 dark:text-white mb-1" id="pd-elo-def">---</div>
                            <div class="text-xs text-emerald-500 font-bold" id="pd-winrate-def">--% Vittorie</div>
                        </div>
                    </div>

                    <div class="space-y-3">
                         <h4 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">Streak Partite</h4>
                         <div class="grid grid-cols-3 gap-2 text-center">
                             <div class="p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
                                 <p class="text-[10px] text-gray-400 uppercase">Attuale</p>
                                 <p id="pd-streak-current" class="font-bold text-gray-900 dark:text-white text-lg">---</p>
                             </div>
                             <div class="p-3 bg-green-50 dark:bg-green-900/20 rounded-xl border border-green-100 dark:border-green-800">
                                 <p class="text-[10px] text-green-600 dark:text-green-400 uppercase">Migliore</p>
                                 <p id="pd-streak-best" class="font-bold text-green-600 dark:text-green-400 text-lg">---</p>
                             </div>
                             <div class="p-3 bg-red-50 dark:bg-red-900/20 rounded-xl border border-red-100 dark:border-red-800">
                                 <p class="text-[10px] text-red-600 dark:text-red-400 uppercase">Peggiore</p>
                                 <p id="pd-streak-worst" class="font-bold text-red-600 dark:text-red-400 text-lg">---</p>
                             </div>
                         </div>
                    </div>

                    <div id="pd-season-history-container" class="hidden">
                        <h4 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider mb-4 flex items-center justify-between">
                            <span>Storico Stagioni</span>
                        </h4>
                        <div id="pd-season-history" class="space-y-3">
                            <!-- Season item template -->
                        </div>
                    </div>

                    <div id="pd-advanced-stats-container" class="hidden">
                        <h4 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                            <span class="material-symbols-outlined text-indigo-500 text-[18px]">analytics</span>
                            Analisi Avanzata
                        </h4>
                        <div id="pd-advanced-stats" class="grid grid-cols-2 gap-3">
                            <!-- Injected by app.js -->
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">Andamento Elo</h4>
                        </div>
                        <div class="h-48 w-full bg-gray-50 dark:bg-gray-700/50 rounded-xl p-2">
                             <canvas id="eloHistoryChart"></canvas>
                        </div>
                    </div>

                    <!-- Logout Button (Mobile Alternative) -->
                    <div id="pd-logout-container" class="hidden pt-6 border-t border-gray-100 dark:border-gray-700">
                        <button onclick="performLogout()" class="w-full py-4 bg-red-50 dark:bg-red-900/20 text-red-600 rounded-2xl font-black uppercase tracking-wider flex items-center justify-center gap-2 hover:bg-red-100 transition-all">
                            <span class="material-symbols-outlined">logout</span>
                            Logout
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </div>
    <div id="confirmationModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm hidden">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl w-[90%] max-w-sm shadow-2xl border border-gray-700 transform transition-all scale-100 text-center">
            <div class="h-16 w-16 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-500 rounded-full flex items-center justify-center mx-auto mb-4">
                <span class="material-symbols-outlined text-3xl">help</span>
            </div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2" id="confirmTitle">Conferma</h3>
            <p class="text-gray-500 dark:text-gray-400 mb-6" id="confirmMessage">Sei sicuro?</p>
            <div class="flex gap-3">
                <button onclick="closeConfirmModal(false)" class="flex-1 py-3 rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-bold hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">No</button>
                <button onclick="closeConfirmModal(true)" class="flex-1 py-3 rounded-xl bg-red-500 text-white font-bold hover:bg-red-600 transition-colors" id="confirmYesBtn">Sì</button>
            </div>
        </div>
    </div>
    <div id="deleteMatchModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm hidden">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl w-[90%] max-w-sm shadow-2xl border border-gray-700 transform transition-all scale-100 text-center">
            <div class="h-16 w-16 bg-red-100 dark:bg-red-900/30 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
                <span class="material-symbols-outlined text-3xl">delete</span>
            </div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Elimina Partita</h3>
            <p class="text-gray-500 dark:text-gray-400 mb-6">Sei sicuro di voler eliminare questa partita? Verranno ripristinati i punti Elo.</p>
            <div class="flex gap-3">
                <button onclick="closeDeleteMatchModal()" class="flex-1 py-3 rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-bold hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">Annulla</button>
                <button id="deleteMatchActionBtn" onclick="confirmDeleteMatch()" class="flex-1 py-3 rounded-xl bg-red-500 text-white font-bold hover:bg-red-600 transition-colors">Elimina</button>
            </div>
        </div>
    </div>

    <div id="recalculateEloModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm hidden">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl w-[90%] max-w-sm shadow-2xl border border-gray-700 transform transition-all scale-100 text-center">
            <div class="h-16 w-16 bg-purple-100 dark:bg-purple-900/30 text-purple-500 rounded-full flex items-center justify-center mx-auto mb-4">
                <span class="material-symbols-outlined text-3xl">autorenew</span>
            </div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Ricalcola Elo</h3>
            <p class="text-gray-500 dark:text-gray-400 mb-6">Azzera tutti gli Elo a 1500 e rielabora tutte le partite in ordine cronologico. Può richiedere qualche secondo.</p>
            <div class="flex gap-3">
                <button onclick="closeModal('recalculateEloModal')" class="flex-1 py-3 rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-bold hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">Annulla</button>
                <button id="recalculateEloConfirmBtn" onclick="executeRecalculateElo()" class="flex-1 py-3 rounded-xl bg-purple-500 text-white font-bold hover:bg-purple-600 transition-colors">Ricalcola</button>
            </div>
        </div>
    </div>

    <div id="createPlayerModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm hidden">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl w-[90%] max-w-sm shadow-2xl border border-gray-700 transform transition-all scale-100 text-center relative">
            <button onclick="closeModal('createPlayerModal')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                <span class="material-symbols-outlined">close</span>
            </button>
            <div class="h-16 w-16 bg-green-100 dark:bg-green-900/30 text-green-500 rounded-full flex items-center justify-center mx-auto mb-4">
                <span class="material-symbols-outlined text-3xl">person_add</span>
            </div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Crea Giocatore / Utente</h3>
            <p class="text-gray-500 dark:text-gray-400 text-sm mb-6">Inserisci i dati del nuovo giocatore. Username e password permettono l'accesso.</p>
            <form id="createPlayerForm" onsubmit="performCreatePlayer(event)" class="space-y-4 text-left">
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Nome Giocatore *</label>
                    <input type="text" id="newPlayerName" required class="w-full bg-gray-50 dark:bg-gray-700 border-0 text-gray-900 dark:text-white rounded-xl focus:ring-2 focus:ring-blue-500 p-3" placeholder="Es. Mario Rossi">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Username</label>
                    <input type="text" id="newPlayerUsername" class="w-full bg-gray-50 dark:bg-gray-700 border-0 text-gray-900 dark:text-white rounded-xl focus:ring-2 focus:ring-blue-500 p-3" placeholder="Es. mariolino">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Password</label>
                    <input type="text" id="newPlayerPassword" class="w-full bg-gray-50 dark:bg-gray-700 border-0 text-gray-900 dark:text-white rounded-xl focus:ring-2 focus:ring-blue-500 p-3" placeholder="Default: 1234">
                </div>
                <div class="flex items-center gap-2 mt-4 mb-2">
                    <input type="checkbox" id="newPlayerAdmin" class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500 bg-gray-50 dark:bg-gray-700 dark:border-gray-600">
                    <label for="newPlayerAdmin" class="text-sm font-bold text-gray-700 dark:text-gray-300">Permessi di Amministratore</label>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeModal('createPlayerModal')" class="flex-1 py-3 rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-bold hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">Annulla</button>
                    <button type="submit" id="createPlayerSubmitBtn" class="flex-1 py-3 rounded-xl bg-green-500 text-white font-bold hover:bg-green-600 transition-colors shadow-lg shadow-green-500/30">Crea Giocatore</button>
                </div>
            </form>
        </div>
    </div>

    <div id="errorModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm hidden">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl w-[90%] max-w-sm shadow-2xl border border-gray-700 transform transition-all scale-100 text-center">
            <div class="h-16 w-16 bg-red-100 dark:bg-red-900/30 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
                <span class="material-symbols-outlined text-3xl">error</span>
            </div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2" id="errorTitle">Errore</h3>
            <p class="text-gray-500 dark:text-gray-400 mb-6" id="errorMessage">Si è verificato un errore.</p>
            <button onclick="closeErrorModal()" class="w-full py-3 rounded-xl bg-gray-900 dark:bg-white text-white dark:text-gray-900 font-bold hover:opacity-90 transition-opacity">OK</button>
        </div>
    </div>

    <div id="oddsExplanationModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm hidden">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl w-[90%] max-w-lg shadow-2xl border border-gray-700 transform transition-all scale-100 relative max-h-[90vh] flex flex-col">
            <button onclick="closeOddsExplanationModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                <span class="material-symbols-outlined">close</span>
            </button>
            <div class="text-center mb-6 shrink-0 relative">
                <div class="h-16 w-16 bg-blue-100 dark:bg-blue-900/30 text-blue-500 rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="material-symbols-outlined text-3xl">calculate</span>
                </div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">Analisi Quota</h3>
                
                <div class="mt-3 inline-flex items-center gap-1.5 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400 px-3 py-1 rounded-full text-xs font-medium border border-indigo-100 dark:border-indigo-800/30">
                    <span class="material-symbols-outlined text-[14px]">public</span>
                    Divisore Gravitazionale: <span id="oddsExpDivisor" class="font-bold">--</span>
                </div>
            </div>

            <div class="overflow-y-auto custom-scrollbar pr-2 pb-2">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Colonna Blue -->
                    <div class="bg-blue-50/50 dark:bg-blue-900/10 rounded-2xl p-4 border border-blue-100 dark:border-blue-800/30 flex flex-col">
                        <div class="text-center border-b border-blue-200 dark:border-blue-800/50 pb-3 mb-3">
                            <h4 class="font-bold text-blue-600 dark:text-blue-400 uppercase tracking-widest text-xs">Squadra Blu</h4>
                            <div class="text-2xl font-black text-gray-900 dark:text-gray-100 mt-1" id="oddsExpTotalBlue">---</div>
                            <div class="text-[10px] text-gray-500 dark:text-gray-400">Elo Totale Stimato</div>
                        </div>
                        <div class="space-y-3 text-sm flex-1" id="oddsExpDetailsBlue">
                            <!-- Popolato dinamicamente -->
                        </div>
                    </div>

                    <!-- Colonna Red -->
                    <div class="bg-red-50/50 dark:bg-red-900/10 rounded-2xl p-4 border border-red-100 dark:border-red-800/30 flex flex-col">
                        <div class="text-center border-b border-red-200 dark:border-red-800/50 pb-3 mb-3">
                            <h4 class="font-bold text-red-600 dark:text-red-400 uppercase tracking-widest text-xs">Squadra Rossa</h4>
                            <div class="text-2xl font-black text-gray-900 dark:text-gray-100 mt-1" id="oddsExpTotalRed">---</div>
                            <div class="text-[10px] text-gray-500 dark:text-gray-400">Elo Totale Stimato</div>
                        </div>
                        <div class="space-y-3 text-sm flex-1" id="oddsExpDetailsRed">
                            <!-- Popolato dinamicamente -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="comparisonModal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true">

        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity" onclick="closeComparisonModal()"></div>

        <div class="relative w-full max-w-4xl mx-auto mt-10 md:mt-20 px-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[85vh]">

                <div class="relative p-6 pb-4 border-b border-gray-200 dark:border-gray-700 shrink-0">
                    <button onclick="closeComparisonModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                        <span class="material-symbols-outlined text-[24px]">close</span>
                    </button>
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white">Confronta Giocatori</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Seleziona due giocatori per confrontare le loro statistiche</p>
                </div>

                <div class="p-6 border-b border-gray-200 dark:border-gray-700 shrink-0">
                    <div class="mb-4">
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Stagione</label>
                        <select id="compareSeason" onchange="updateComparison()" class="w-full bg-gray-50 dark:bg-gray-700 border-0 text-gray-900 dark:text-white rounded-xl focus:ring-2 focus:ring-blue-500 p-3">
                            <!-- Populated dynamically -->
                        </select>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Giocatore 1</label>
                            <select id="comparePlayer1" onchange="updateComparison()" class="w-full bg-gray-50 dark:bg-gray-700 border-0 text-gray-900 dark:text-white rounded-xl focus:ring-2 focus:ring-blue-500 p-3">
                                <option value="">Seleziona...</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Giocatore 2</label>
                            <select id="comparePlayer2" onchange="updateComparison()" class="w-full bg-gray-50 dark:bg-gray-700 border-0 text-gray-900 dark:text-white rounded-xl focus:ring-2 focus:ring-blue-500 p-3">
                                <option value="">Seleziona...</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div id="comparisonContent" class="p-6 overflow-y-auto custom-scrollbar flex-1">
                    <div class="text-center text-gray-500 dark:text-gray-400 py-12">
                        Seleziona due giocatori per visualizzare il confronto
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="matchSearchModal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true">

        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity" onclick="closeMatchSearchModal()"></div>

        <div class="relative w-full max-w-4xl mx-auto mt-10 md:mt-20 px-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl overflow-hidden flex flex-col" style="height: 600px; max-height: 85vh;">

                <div class="relative p-6 pb-4 border-b border-gray-200 dark:border-gray-700 shrink-0">
                    <button onclick="closeMatchSearchModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
                        <span class="material-symbols-outlined text-[24px]">close</span>
                    </button>
                    <div class="flex items-center justify-between">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white">🤖 Assistente Ricerca Partite</h3>
                        <select id="searchSeason" class="bg-gray-50 dark:bg-gray-700 border-0 rounded-lg text-xs font-bold px-2 py-1 focus:ring-2 focus:ring-blue-500 mr-8">
                            <option value="0">Tutte le stagioni</option>
                            <!-- Populated dynamically -->
                        </select>
                    </div>
                </div>

                <div id="chatMessages" class="flex-1 p-6 overflow-y-auto custom-scrollbar space-y-4">

                    <div class="flex gap-3">
                        <div class="h-8 w-8 rounded-full bg-blue-500 flex items-center justify-center flex-shrink-0">
                            <span class="text-white text-sm">🤖</span>
                        </div>
                        <div class="flex-1">
                            <div class="bg-gray-100 dark:bg-gray-700 rounded-2xl rounded-tl-none p-4">
                                <p class="text-gray-900 dark:text-white text-sm">
                                    Ciao! Sono il tuo assistente per la ricerca delle partite. Puoi chiedermi cose come:
                                </p>
                                <ul class="mt-2 text-xs text-gray-600 dark:text-gray-400 space-y-1">
                                    <li>• "partite dove X ha vinto contro Y"</li>
                                    <li>• "vittorie di X come attaccante"</li>
                                    <li>• "ultime 10 partite"</li>
                                    <li>• "scontri diretti X Y"</li>
                                    <li>• "X Y vs Z W
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-4 border-t border-gray-200 dark:border-gray-700 shrink-0">
                    <form onsubmit="submitSearchQuery(event)" class="flex gap-2">
                        <input 
                            type="text" 
                            id="searchQueryInput" 
                            placeholder="Scrivi la tua richiesta..." 
                            class="flex-1 bg-gray-50 dark:bg-gray-700 border-0 text-gray-900 dark:text-white rounded-xl focus:ring-2 focus:ring-blue-500 p-3 text-sm"
                            autocomplete="off"
                        >
                        <button type="submit" class="px-6 py-3 bg-blue-500 hover:bg-blue-600 text-white rounded-xl font-bold transition-all shadow-sm">
                            <span class="material-symbols-outlined">send</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>

        let bettingPollInterval = null;
        let countdownInterval = null;
        let remainingSeconds = 0;
        const BETTING_API = 'betting_api.php';

        function startBettingPoll() {
            fetchOdds(); 
            if (bettingPollInterval) clearInterval(bettingPollInterval);
            bettingPollInterval = setInterval(fetchOdds, 5000); 

            if (countdownInterval) clearInterval(countdownInterval);
            countdownInterval = setInterval(() => {
                if (remainingSeconds > 0) {
                    remainingSeconds--;
                    updateTimerDisplay(remainingSeconds);
                } else if (remainingSeconds === 0) {
                   updateTimerDisplay(0);
                   disableBettingUI();
                }
            }, 1000);
        }

        async function fetchOdds() {
            try {
                const res = await fetch(`${BETTING_API}?action=odds`);
                const data = await res.json();

                if (data.success && data.status === 'active') {

                    document.getElementById('betting-wrapper-mobile')?.classList.remove('hidden');
                    const bettingDesktop = document.getElementById('betting-wrapper-desktop');
                    if (bettingDesktop) {
                        if (window.innerWidth >= 768) {
                            bettingDesktop.classList.remove('hidden');
                        } else {
                            bettingDesktop.classList.add('hidden');
                        }
                    }

                    updateBettingUI(data.odds, data.user_credits);
                    if (data.breakdown) {
                        window.currentOddsBreakdown = data.breakdown;
                        if (data.breakdown.divisor) {
                            setText('oddsDivisorValue-mobile', data.breakdown.divisor);
                            setText('oddsDivisorValue-desktop', data.breakdown.divisor);
                            document.getElementById('oddsDivisorBadge-mobile')?.classList.remove('hidden');
                            document.getElementById('oddsDivisorBadge-desktop')?.classList.remove('hidden');
                            document.getElementById('oddsDivisorBadge-mobile')?.classList.add('flex');
                            document.getElementById('oddsDivisorBadge-desktop')?.classList.add('flex');
                        }
                    }

                    if (data.timer) {
                        if (data.timer.status === 'open') {
                            if (remainingSeconds === 0) {
                                // Auto-open the accordion when betting transitions from closed to open
                                openAccordion('mobile');
                                openAccordion('desktop');
                            }
                            remainingSeconds = data.timer.remaining_seconds;
                            updateTimerDisplay(remainingSeconds);
                            enableBettingUI();
                        } else {
                            if (remainingSeconds > 0) {
                                // Auto-close when betting transitions from open to closed
                                closeAccordion('mobile');
                                closeAccordion('desktop');
                            }
                            remainingSeconds = 0;
                            updateTimerDisplay(0);
                            disableBettingUI();
                        }
                    }
                } else {

                     document.getElementById('betting-wrapper-mobile')?.classList.add('hidden');
                     document.getElementById('betting-wrapper-desktop')?.classList.add('hidden');
                }
            } catch (e) {
                console.error("Betting poll error", e);
            }
        }

        function updateBettingUI(odds, credits) {

            setText('odds-team1-mobile', odds.team1);
            setText('odds-team2-mobile', odds.team2);
            setText('odds-deuce-yes-mobile', odds.deuce_yes);
            setText('odds-deuce-no-mobile', odds.deuce_no);
            setText('user-credits-mobile', credits);

            setText('odds-team1-desktop', odds.team1);
            setText('odds-team2-desktop', odds.team2);
            setText('odds-deuce-yes-desktop', odds.deuce_yes);
            setText('odds-deuce-no-desktop', odds.deuce_no);
            setText('user-credits-desktop', credits);
        }

        function setText(id, text) {
            const el = document.getElementById(id);
            if(el) el.innerText = text;
        }

        function setBetAmount(amt) {
            const el = document.getElementById('bet-amount-desktop');
            if(el) el.value = amt;

        }

        async function placeBet(type, value) {

            if (remainingSeconds <= 0) {
                 showToast("Tempo scaduto!", "error");
                 return;
            }

            let amount = 0;
            if (window.innerWidth < 768) {
                amount = document.getElementById('bet-amount-mobile').value;
            } else {
                amount = document.getElementById('bet-amount-desktop').value;
            }

            if (!amount || amount <= 0) {
                showToast("Inserisci un importo valido", "error");
                return;
            }

            try {
                const res = await fetch(`${BETTING_API}?action=place_bet`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ type, value, amount })
                });
                const data = await res.json();

                if (data.success) {
                    showToast(data.message, "success");
                    fetchOdds(); 
                } else {
                    showToast(data.error, "error");
                }
            } catch (e) {
                showToast("Errore di connessione", "error");
            }
        }

        function updateTimerDisplay(seconds) {
            const text = seconds > 0 ? `Chiude in: ${seconds}s` : "Scommesse Chiuse";
            const colorClass = seconds > 10 ? 'text-red-500' : 'text-red-600'; 

            const mob = document.getElementById('betting-timer-mobile');
            const dsk = document.getElementById('betting-timer-desktop');

            if (mob) { mob.innerText = text; mob.className = `text-xs font-bold ${seconds > 0 ? 'text-red-500 animate-pulse' : 'text-gray-400'}`; }
            if (dsk) { dsk.innerText = text; dsk.className = `font-bold text-xl ${seconds > 0 ? 'text-red-500 animate-pulse' : 'text-gray-400'}`; }
        }

        function disableBettingUI() {
            const btns = document.querySelectorAll('#betting-content-mobile button:not(.info-btn), #betting-content-desktop button:not(.info-btn)');
            btns.forEach(b => {
                b.disabled = true;
                b.classList.add('opacity-50', 'cursor-not-allowed');
            });
        }

        function enableBettingUI() {
            const btns = document.querySelectorAll('#betting-content-mobile button:not(.info-btn), #betting-content-desktop button:not(.info-btn)');
            btns.forEach(b => {
                b.disabled = false;
                b.classList.remove('opacity-50', 'cursor-not-allowed');
            });
        }

        // --- ACCORDION LOGIC ---
        function toggleAccordion(type) {
            const content = document.getElementById(`betting-content-${type}`);
            const chev = document.getElementById(`chev-${type === 'mobile' ? 'mob' : 'desk'}`);
            if (!content || !chev) return;

            if (content.classList.contains('max-h-0')) {
                openAccordion(type);
            } else {
                closeAccordion(type);
            }
        }

        function openAccordion(type) {
            const content = document.getElementById(`betting-content-${type}`);
            const chev = document.getElementById(`chev-${type === 'mobile' ? 'mob' : 'desk'}`);
            const headerMobile = document.getElementById('betting-header-mobile');
            if (!content || !chev) return;

            content.classList.remove('max-h-0', 'opacity-0');
            content.classList.add('max-h-[1000px]', 'opacity-100'); // Arbitrary large max-height
            chev.classList.add('rotate-180');
            if (type === 'mobile' && headerMobile) headerMobile.classList.remove('rounded-b-2xl');
        }

        function closeAccordion(type) {
            const content = document.getElementById(`betting-content-${type}`);
            const chev = document.getElementById(`chev-${type === 'mobile' ? 'mob' : 'desk'}`);
            const headerMobile = document.getElementById('betting-header-mobile');
            if (!content || !chev) return;

            content.classList.remove('max-h-[1000px]', 'opacity-100');
            content.classList.add('max-h-0', 'opacity-0');
            chev.classList.remove('rotate-180');
            if (type === 'mobile' && headerMobile) {
                // Return bottom rounding after transition ends
                setTimeout(() => headerMobile.classList.add('rounded-b-2xl'), 500);
            }
        }

        window.currentLeaderboardTab = localStorage.getItem('leaderboardTab') || 'generale';

        function switchLeaderboardTab(tab) {
            window.currentLeaderboardTab = tab;
            localStorage.setItem('leaderboardTab', tab);

            document.querySelectorAll('.leaderboard-tab').forEach(btn => {
                btn.classList.remove('active');
            });

            const activeTab = document.getElementById(`tab-${tab}`);
            if (activeTab) activeTab.classList.add('active');

            if (window.renderLeaderboard) {
                window.renderLeaderboard();
            }
        }

        <?php if ($isLoggedIn): ?>
        document.addEventListener('DOMContentLoaded', () => {
             startBettingPoll();
        });
        <?php
endif; ?>

    </script>

<div id="shopModal" class="fixed inset-0 z-[60] hidden" aria-labelledby="shop-title" role="dialog" aria-modal="true">

    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity opacity-0" id="shopOverlay"></div>

    <div class="absolute inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center">
            <div class="relative transform overflow-hidden rounded-3xl bg-white dark:bg-gray-800 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" id="shopPanel">

                <div class="bg-gradient-to-r from-yellow-500 to-orange-500 px-6 py-4 flex justify-between items-center shrink-0">
                    <h3 class="text-xl font-black text-white flex items-center gap-2" id="shop-title">
                        <span class="material-symbols-outlined">storefront</span>
                        Negozio
                    </h3>
                    <div class="bg-white/20 px-3 py-1.5 rounded-xl text-white font-bold text-sm flex items-center gap-1.5 shadow-inner">
                        <span class="material-symbols-outlined text-[18px]">monetization_on</span>
                        <span id="shop-user-credits">---</span>
                    </div>
                </div>

                <!-- NEW TAB NAVIGATION -->
                <div class="flex border-b border-gray-100 dark:border-gray-700/50 bg-gray-50/50 dark:bg-gray-900/20 px-4 shrink-0">
                    <button onclick="switchShopTab('bonus')" id="shop-tab-bonus" class="shop-tab-btn active">Bonus</button>
                    <button onclick="switchShopTab('aesthetic')" id="shop-tab-aesthetic" class="shop-tab-btn">Estetica</button>
                </div>

                <div class="p-4 sm:p-6 overflow-hidden flex flex-col flex-1 min-h-0">
                    <div id="shop-items-grid" class="flex flex-col gap-3 overflow-y-auto pr-2 pb-4 shop-scroll custom-scrollbar max-h-[60vh]">
                        <!-- Items rendered by JS -->
                        <div class="col-span-full py-20 text-center">
                            <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-yellow-500 mx-auto"></div>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-700/50 px-6 py-4 flex justify-end">
                    <button type="button" onclick="closeModal('shopModal')" class="px-5 py-2.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 font-bold rounded-xl hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors shadow-sm">
                        Chiudi
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="shopPurchaseModal" class="fixed inset-0 z-[70] hidden" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity opacity-0" id="shopPurchaseOverlay"></div>
    <div class="absolute inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center">
            <div class="relative transform overflow-hidden rounded-3xl bg-white dark:bg-gray-800 text-left shadow-2xl transition-all sm:w-full sm:max-w-sm opacity-0 scale-95" id="shopPurchasePanel">
                <div class="p-6 text-center">
                    <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-yellow-100 dark:bg-yellow-900/30 mb-6">
                        <span class="material-symbols-outlined text-4xl text-yellow-600 dark:text-yellow-400" id="shopConfirmIcon">shopping_cart</span>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2" id="shopConfirmTitle">Conferma Acquisto</h3>
                    <p class="text-gray-500 dark:text-gray-400 text-sm mb-6">
                        Vuoi davvero acquistare <strong id="shopConfirmName" class="text-gray-900 dark:text-white">---</strong> per <strong id="shopConfirmCost" class="text-yellow-600 dark:text-yellow-400">---</strong> Strisciate?
                    </p>
                    <div class="grid grid-cols-2 gap-3">
                        <button onclick="closeModal('shopPurchaseModal')" class="w-full py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-bold hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                            Annulla
                        </button>
                        <button id="shopConfirmBtn" class="w-full py-3 bg-gradient-to-r from-yellow-500 to-orange-500 text-white rounded-xl font-bold hover:shadow-lg hover:shadow-yellow-500/30 transition-all active:scale-95">
                            Acquista
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SHOP ITEM DETAIL MODAL -->
<div id="sd-modal" class="fixed inset-0 z-[75] hidden" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm transition-opacity opacity-0" id="sd-overlay" onclick="closeShopDetailModal()"></div>
    <div class="absolute inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center">
            <div class="relative transform overflow-hidden rounded-3xl bg-white dark:bg-gray-800 text-left shadow-2xl transition-all w-full max-w-sm opacity-0 scale-95" id="sd-panel">
                <!-- Modal Header -->
                <div class="p-6 pb-0 flex justify-between items-start">
                    <div class="h-14 w-14 rounded-2xl flex items-center justify-center text-3xl shadow-sm" id="sd-icon-container">
                        <span class="material-symbols-outlined" id="sd-icon"></span>
                    </div>
                    <button onclick="closeShopDetailModal()" class="h-10 w-10 flex items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 hover:bg-gray-200 transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <div class="p-6 pt-4">
                    <h3 class="text-2xl font-black text-gray-900 dark:text-white uppercase tracking-tight mb-1" id="sd-name"></h3>
                    <p class="text-gray-500 dark:text-gray-400 text-sm leading-relaxed mb-6" id="sd-desc"></p>

                    <!-- Preview Section -->
                    <div id="sd-preview-container" class="hidden mb-6">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3 block">Anteprima Effetto</label>
                        <div class="bg-gray-50 dark:bg-gray-900/50 rounded-2xl p-6 border border-gray-100 dark:border-gray-700/50 flex flex-col items-center justify-center overflow-hidden relative min-h-[140px]">
                            <div id="sd-preview-content" class="flex flex-col items-center gap-3"></div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <button id="sd-buy-btn" class="w-full py-4 bg-gradient-to-r from-yellow-500 to-orange-500 text-white rounded-2xl font-black uppercase tracking-wider shadow-lg shadow-yellow-500/20 hover:shadow-yellow-500/40 transition-all active:scale-95 flex items-center justify-center gap-3">
                            <span class="material-symbols-outlined">monetization_on</span>
                            <span id="sd-cost">---</span>
                        </button>
                        <p class="text-[10px] text-center text-gray-400 italic">Clicca il tasto sopra per confermare l'acquisto</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="shopUseModal" class="fixed inset-0 z-[70] hidden" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity opacity-0" id="shopUseOverlay"></div>
    <div class="absolute inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center">
            <div class="relative transform overflow-hidden rounded-3xl bg-white dark:bg-gray-800 text-left shadow-2xl transition-all sm:w-full sm:max-w-sm opacity-0 scale-95" id="shopUsePanel">
                <div class="p-6 text-center">
                    <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/30 mb-6 font-bold text-4xl text-indigo-600 dark:text-indigo-400">
                        <span class="material-symbols-outlined text-4xl" id="shopUseIcon">rocket_launch</span>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2" id="shopUseTitle">Attiva Bonus</h3>
                    <p class="text-gray-500 dark:text-gray-400 text-sm mb-6">
                        Vuoi attivare <strong id="shopUseName" class="text-gray-900 dark:text-white">---</strong> adesso?
                    </p>
                    <div class="grid grid-cols-2 gap-3">
                        <button onclick="closeModal('shopUseModal')" class="w-full py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-bold hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                            Annulla
                        </button>
                        <button id="shopUseConfirmBtn" class="w-full py-3 bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-xl font-bold hover:shadow-lg hover:shadow-indigo-500/30 transition-all active:scale-95">
                            Attiva
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>

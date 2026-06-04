<?php
/**
 * NEW SEASON SETUP SCRIPT
 * Esegui questo script UNA SOLA VOLTA per avviare la nuova stagione.
 * Cancella le vecchie ricompense, inserisce quelle nuove e verifica
 * che tutti gli item estetici esistano nella tabella shop_items.
 *
 * ACCESSO: Solo admin — rimuovi questo file dopo l'uso!
 */
require_once 'session_config.php';
require_once 'database.php';

// ── Protezione admin ──────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die("Accesso negato. Fai login come admin.");
}

$db   = new Database();
$conn = $db->getConnection();

$stmt = $conn->prepare("SELECT is_admin FROM giocatori WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$isAdmin = (int)$stmt->fetchColumn();
if ($isAdmin !== 1) {
    http_response_code(403);
    die("Accesso negato. Solo gli admin possono eseguire questo script.");
}

// ─────────────────────────────────────────────────────────────────
// STEP 1 — Assicura che tutti gli item estetici esistano in shop_items
// ─────────────────────────────────────────────────────────────────
// Questi item vengono aggiunti all'inventario del giocatore al riscatto.
// Usiamo is_exclusive = 1 così non appaiono nello shop pubblico.
// Formato: [key_name, name, item_type, cost, description]
$shopItems = [
    // Colori nome
    ['color_#ef4444', 'Rosso Fuoco',        'aesthetic', 0, 'Nome color rosso brillante'],
    ['color_#f97316', 'Arancione Vivido',   'aesthetic', 0, 'Nome color arancione intenso'],
    ['color_#fbbf24', 'Giallo Dorato',      'aesthetic', 0, 'Nome color giallo dorato'],
    ['color_#22c55e', 'Verde Neon',         'aesthetic', 0, 'Nome color verde neon'],
    ['color_#3b82f6', 'Azzurro Cielo',      'aesthetic', 0, 'Nome color azzurro cielo'],
    ['color_#8b5cf6', 'Viola Cosmico',      'aesthetic', 0, 'Nome color viola cosmico'],
    ['color_#ec4899', 'Rosa Glamour',       'aesthetic', 0, 'Nome color rosa glamour'],
    ['color_#06b6d4', 'Ciano Ghiaccio',     'aesthetic', 0, 'Nome color ciano ghiaccio'],
    ['color_#f8fafc', 'Bianco Ghiaccio',    'aesthetic', 0, 'Nome color bianco puro'],
    ['color_#94a3b8', 'Grigio Platino',     'aesthetic', 0, 'Nome color grigio platino'],
    ['color_#dc2626', 'Rosso Scarlatto',    'aesthetic', 0, 'Nome color rosso scarlatto'],
    ['color_#d97706', 'Ambra Bruciata',     'aesthetic', 0, 'Nome color ambra bruciata'],
    ['color_#059669', 'Verde Smeraldo',     'aesthetic', 0, 'Nome color verde smeraldo'],
    ['color_#1d4ed8', 'Blu Reale',          'aesthetic', 0, 'Nome color blu reale'],
    ['color_#a855f7', 'Lilla Mistico',      'aesthetic', 0, 'Nome color lilla mistico'],
    ['color_#4f46e5', 'Indaco Profondo',    'aesthetic', 0, 'Nome color indaco profondo'],
    ['color_#0891b2', 'Turchese Marino',    'aesthetic', 0, 'Nome color turchese marino'],
    ['color_#f43f5e', 'Corallo Infuocato',  'aesthetic', 0, 'Nome color corallo infuocato'],
    // Nuove Aure esclusive Season Pass
    ['aura_ice',    'Aura Ghiaccio',  'aesthetic', 0, 'Cristalli di ghiaccio orbitanti — Season Pass esclusiva'],
    ['aura_toxic',  'Aura Tossica',   'aesthetic', 0, 'Vapori radioattivi verdi — Season Pass esclusiva'],
    ['aura_galaxy', 'Aura Galattica', 'aesthetic', 0, 'Stelle e nebulose rotanti — Season Pass esclusiva'],
    ['aura_blood',  'Aura Sangue',    'aesthetic', 0, 'Gocce cremisi che cadono — Season Pass esclusiva'],
];

$stmtShop = $conn->prepare("
    INSERT IGNORE INTO shop_items (key_name, name, item_type, cost, description, is_exclusive)
    VALUES (?, ?, ?, ?, ?, 1)
");

// ─────────────────────────────────────────────────────────────────
// STEP 2 — Definisci le 20 ricompense della nuova stagione
// ─────────────────────────────────────────────────────────────────
// Formato: [livello, nome_display, tipo, descrizione, icona_material, item_key, importo_crediti]
// tipo:     'aesthetic' | 'bonus' | 'credit'
// item_key: chiave di shop_items (per aesthetic/bonus) o null (per credit)
// importo:  solo per tipo 'credit', altrimenti null
$nuoveRicompense = [
    [1,  '🔴 Rosso Fuoco',          'aesthetic', 'Il tuo nome brucia di rosso vivo',                   'palette',      'color_#ef4444',  null],
    [2,  '🟠 Arancione Vivido',     'aesthetic', 'Colore sfacciato per chi non passa inosservato',      'palette',      'color_#f97316',  null],
    [3,  '🟡 Giallo Dorato',        'aesthetic', 'Brilla come l\'oro sul tabellone',                    'palette',      'color_#fbbf24',  null],
    [4,  '🟢 Verde Neon',           'aesthetic', 'Fluorescente e letale come un contropiede',           'palette',      'color_#22c55e',  null],
    [5,  '❄️ Aura Ghiaccio',        'aesthetic', 'Cristalli esagonali di ghiaccio ti orbitano intorno', 'ac_unit',      'aura_ice',       null],
    [6,  '💜 Viola Cosmico',        'aesthetic', 'Il colore del mistero e del dominio',                 'palette',      'color_#8b5cf6',  null],
    [7,  '🩷 Rosa Glamour',         'aesthetic', 'Per chi gioca con stile e vince con classe',          'palette',      'color_#ec4899',  null],
    [8,  '🩵 Ciano Ghiaccio',       'aesthetic', 'Freddo come la determinazione di un campione',        'palette',      'color_#06b6d4',  null],
    [9,  '🤍 Bianco Ghiaccio',      'aesthetic', 'Eleganza assoluta — solo i migliori osano',           'palette',      'color_#f8fafc',  null],
    [10, '☣️ Aura Tossica',         'aesthetic', 'Vapori radioattivi verdi che salgono minacciosi',     'science',      'aura_toxic',     null],
    [11, '❤️‍🔥 Rosso Scarlatto',   'aesthetic', 'Più scuro, più profondo, più dominante',              'palette',      'color_#dc2626',  null],
    [12, '🟤 Ambra Bruciata',       'aesthetic', 'Caldo e irresistibile come un gol all\'ultimo',       'palette',      'color_#d97706',  null],
    [13, '💚 Verde Smeraldo',       'aesthetic', 'Il verde dei campioni del mondo',                     'palette',      'color_#059669',  null],
    [14, '🌌 Aura Galattica',       'aesthetic', 'Stelle e nebulose cosmiche ti avvolgono',             'auto_awesome', 'aura_galaxy',    null],
    [15, '⚽ Palla Matta',          'bonus',     'Gol imprevedibili — il caos diventa arte',            'sports_soccer','palla_matta',    null],
    [16, '🟣 Lilla Mistico',        'aesthetic', 'Tra viola e rosa — enigmatico e inafferrabile',       'palette',      'color_#a855f7',  null],
    [17, '🔮 Indaco Profondo',      'aesthetic', 'Il colore dell\'intuizione e dei riflessi fulminei',  'palette',      'color_#4f46e5',  null],
    [18, '🌊 Turchese Marino',      'aesthetic', 'La profondità dell\'oceano nel tuo nome',             'palette',      'color_#0891b2',  null],
    [19, '🩸 Aura Sangue',          'aesthetic', 'Gocce cremisi che cadono — per i veri predatori',     'water_drop',   'aura_blood',     null],
    [20, '⚡ x2 ELO — Gran Finale', 'bonus',     'ELO raddoppiato — il premio dei Leggendari',          'bolt',         'x2_elo',         null],
];

// ─────────────────────────────────────────────────────────────────
// ESECUZIONE
// ─────────────────────────────────────────────────────────────────
$log    = [];
$errors = [];

try {
    $conn->beginTransaction();

    // 0. Auto-migrazione: garantisce la presenza delle colonne description e icon
    $stmtCols = $conn->query("SHOW COLUMNS FROM season_pass_rewards");
    $columns = $stmtCols->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('description', $columns)) {
        $conn->exec("ALTER TABLE season_pass_rewards ADD COLUMN description VARCHAR(255) DEFAULT NULL");
        $log[] = "🔧 Colonna 'description' mancante creata automaticamente.";
    }
    if (!in_array('icon', $columns)) {
        $conn->exec("ALTER TABLE season_pass_rewards ADD COLUMN icon VARCHAR(100) DEFAULT NULL");
        $log[] = "🔧 Colonna 'icon' mancante creata automaticamente.";
    }

    // 1. Garantisce che gli shop_items estetici esistano
    $insertedItems = 0;
    foreach ($shopItems as $item) {
        $stmtShop->execute($item);
        if ($stmtShop->rowCount() > 0) $insertedItems++;
    }
    $log[] = "✅ Shop items verificati — {$insertedItems} nuovi item aggiunti.";

    // 2. Svuota le claim della stagione precedente
    $conn->exec("DELETE FROM user_season_claimed");
    $log[] = "✅ Claim della stagione precedente resettati.";

    // 3. Rimuove le vecchie ricompense
    $conn->exec("DELETE FROM season_pass_rewards");
    $log[] = "✅ Vecchie ricompense rimosse.";

    // 4. Azzera XP e livello di tutti i giocatori (escluso NPC id=9999)
    $conn->exec("UPDATE giocatori SET xp = 0, level = 1 WHERE id != 9999");
    $log[] = "✅ XP e livelli resettati a LV 1 per tutti i giocatori.";

    // 5. Inserisce le 20 nuove ricompense
    $stmtReward = $conn->prepare("
        INSERT INTO season_pass_rewards
            (level, reward_name, reward_type, description, icon, item_key, amount)
        VALUES
            (?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($nuoveRicompense as $r) {
        $stmtReward->execute($r);
        $tipo = strtoupper($r[2]);
        $log[] = "✅ LV {$r[0]} — {$r[1]} <span style='opacity:.6'>({$tipo})</span> aggiunto.";
    }

    $conn->commit();
    $log[] = "<strong style='color:#22c55e;font-size:1.3em'>🎉 NUOVA STAGIONE AVVIATA CON SUCCESSO! (20 ricompense)</strong>";

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    $errors[] = "❌ ERRORE — rollback eseguito: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Nuova Stagione – Scalcetting</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            background: #0a0a0a;
            color: #e2e8f0;
            padding: 2rem;
            max-width: 860px;
            margin: 0 auto;
        }
        h1 { color: #f97316; border-bottom: 2px solid #f97316; padding-bottom: .6rem; margin-bottom: 1.5rem; }
        .log  {
            background: #111827;
            border-left: 4px solid #10b981;
            padding: .7rem 1rem;
            margin: .25rem 0;
            border-radius: 4px;
            font-size: .9em;
        }
        .log:last-of-type { margin-bottom: 1.5rem; }
        .err  {
            background: #1f0a0a;
            border-left: 4px solid #ef4444;
            padding: .7rem 1rem;
            margin: .25rem 0;
            border-radius: 4px;
        }
        .warn {
            color: #fbbf24;
            margin-top: 1.5rem;
            border: 1px dashed #fbbf24;
            padding: 1rem;
            border-radius: 8px;
            font-size: .95em;
        }
        a { color: #6366f1; text-decoration: none; font-size: 1.05em; }
        a:hover { text-decoration: underline; }
        code { background: #1e293b; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>⚽ Scalcetting — Avvio Nuova Stagione</h1>
    <?php foreach ($log as $l): ?>
        <div class="log"><?= $l ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $e): ?>
        <div class="err"><?= $e ?></div>
    <?php endforeach; ?>
    <p class="warn">
        ⚠️ <strong>IMPORTANTE:</strong> Elimina o rinomina questo file (<code>new_season.php</code>) subito dopo l'uso!<br>
        Non lasciarlo accessibile in produzione.
    </p>
    <p style="margin-top:1.5rem"><a href="index.php">← Torna all'app</a></p>
</body>
</html>

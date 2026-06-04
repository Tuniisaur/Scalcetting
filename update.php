<?php
/**
2:  * DATABASE SCHEMA MIGRATION / UPDATE SCRIPT
3:  * Esegui questo script nel browser per aggiungere le colonne mancanti 
4:  * ('description' e 'icon') alla tabella 'season_pass_rewards'.
5:  *
6:  * ACCESSO: Solo admin.
7:  */
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
// ESECUZIONE MIGRAZIONE
// ─────────────────────────────────────────────────────────────────
$log = [];
$errors = [];

try {
    // 1. Verifica se le colonne esistono già
    $stmtCols = $conn->query("SHOW COLUMNS FROM season_pass_rewards");
    $columns = $stmtCols->fetchAll(PDO::FETCH_COLUMN);

    $hasDescription = in_array('description', $columns);
    $hasIcon = in_array('icon', $columns);

    if ($hasDescription && $hasIcon) {
        $log[] = "ℹ️ Le colonne <code>description</code> e <code>icon</code> esistono già nella tabella <code>season_pass_rewards</code>. Nessuna modifica necessaria.";
    } else {
        $conn->beginTransaction();

        if (!$hasDescription) {
            $conn->exec("ALTER TABLE season_pass_rewards ADD COLUMN description VARCHAR(255) DEFAULT NULL");
            $log[] = "✅ Colonna <code>description</code> aggiunta con successo alla tabella <code>season_pass_rewards</code>.";
        }

        if (!$hasIcon) {
            $conn->exec("ALTER TABLE season_pass_rewards ADD COLUMN icon VARCHAR(100) DEFAULT NULL");
            $log[] = "✅ Colonna <code>icon</code> aggiunta con successo alla tabella <code>season_pass_rewards</code>.";
        }

        $conn->commit();
        $log[] = "<strong style='color:#22c55e;font-size:1.1em'>🎉 Database aggiornato con successo!</strong>";
    }

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $errors[] = "❌ ERRORE durante l'aggiornamento: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Aggiornamento Database – Scalcetting</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            background: #080b14;
            color: #e2e8f0;
            padding: 2rem;
            max-width: 700px;
            margin: 0 auto;
        }
        h1 { 
            color: #6366f1; 
            border-bottom: 2px solid #312e81; 
            padding-bottom: .6rem; 
            margin-bottom: 1.5rem; 
            font-size: 1.6rem;
        }
        .log  {
            background: #111827;
            border-left: 4px solid #6366f1;
            padding: .7rem 1rem;
            margin: .5rem 0;
            border-radius: 4px;
            font-size: .9em;
        }
        .err  {
            background: #1f0a0a;
            border-left: 4px solid #ef4444;
            padding: .7rem 1rem;
            margin: .5rem 0;
            border-radius: 4px;
        }
        .warn {
            color: #fbbf24;
            margin-top: 2rem;
            border: 1px dashed #fbbf24;
            padding: 1rem;
            border-radius: 8px;
            font-size: .9em;
            background: rgba(251, 191, 36, 0.05);
        }
        a { color: #6366f1; text-decoration: none; font-size: 1em; font-weight: bold; }
        a:hover { text-decoration: underline; }
        code { background: #1e293b; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>⚙️ Scalcetting — Aggiornamento Database</h1>
    
    <?php if (empty($log) && empty($errors)): ?>
        <div class="log">Nessuna operazione eseguita.</div>
    <?php endif; ?>

    <?php foreach ($log as $l): ?>
        <div class="log"><?= $l ?></div>
    <?php endforeach; ?>

    <?php foreach ($errors as $e): ?>
        <div class="err"><?= $e ?></div>
    <?php endforeach; ?>

    <p class="warn">
        ⚠️ <strong>IMPORTANTE:</strong> Rimuovi o rinomina questo file (<code>update_db.php</code>) dopo l'uso per motivi di sicurezza.
    </p>
    
    <p style="margin-top:1.5rem">
        <a href="new_season.php">Vai a New Season Setup →</a> | 
        <a href="index.php">← Torna all'App</a>
    </p>
</body>
</html>

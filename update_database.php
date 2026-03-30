<?php
require_once 'database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    echo "<h1>Database Update: goal_log</h1>";

    // 1. Update live_match
    $sql1 = "ALTER TABLE live_match ADD COLUMN goal_log TEXT";
    try {
        $conn->exec($sql1);
        echo "<p style='color: green;'>[OK] Colonna 'goal_log' aggiunta a 'live_match'</p>";
    } catch (PDOException $e) {
        if ($e->getCode() == '42S21') { // Column already exists
            echo "<p style='color: orange;'>[INFO] La colonna 'goal_log' esiste già in 'live_match'</p>";
        } else {
            echo "<p style='color: red;'>[ERRORE] live_match: " . $e->getMessage() . "</p>";
        }
    }

    // 2. Update partite
    $sql2 = "ALTER TABLE partite ADD COLUMN goal_log TEXT";
    try {
        $conn->exec($sql2);
        echo "<p style='color: green;'>[OK] Colonna 'goal_log' aggiunta a 'partite'</p>";
    } catch (PDOException $e) {
        if ($e->getCode() == '42S21') {
            echo "<p style='color: orange;'>[INFO] La colonna 'goal_log' esiste già in 'partite'</p>";
        } else {
            echo "<p style='color: red;'>[ERRORE] partite: " . $e->getMessage() . "</p>";
        }
    }

    echo "<br><a href='index.php'>Torna alla Dashboard</a>";

} catch (Exception $e) {
    echo "Errore di connessione: " . $e->getMessage();
}
?>

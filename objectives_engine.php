<?php
/**
 * Objectives Engine
 * Tracks Daily and Seasonal objectives progress.
 */

require_once 'season_pass_engine.php';

function checkMatchObjectives($conn, $playerId, $isWin, $role = null)
{
    if ($playerId == 9999 || $playerId <= 0)
        return;

    // 1. Daily Reset Check — MUST happen before fetching objectives
    checkDailyReset($conn, $playerId);

    // 2. Identify relevant objectives (fetch AFTER reset so daily ones are fresh)
    $stmt = $conn->prepare("SELECT o.*, uo.current_value, uo.completed 
                            FROM objectives o 
                            LEFT JOIN user_objectives uo ON o.id = uo.objective_id AND uo.user_id = ?");
    $stmt->execute([$playerId]);
    $objectives = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($objectives as $obj) {
        if ($obj['completed'])
            continue;

        $shouldIncrement = false;
        $key = $obj['key_name'];

        // Daily Play
        if ($key === 'daily_play_1' || $key === 'daily_play_3') {
            $shouldIncrement = true;
        }
        // Daily Win
        elseif ($key === 'daily_win_1' && $isWin) {
            $shouldIncrement = true;
        }
        // Seasonal Win (General)
        elseif (strpos($key, 'season_win_') !== false && strpos($key, '_atk') === false && strpos($key, '_gk') === false && $isWin) {
            $shouldIncrement = true;
        }
        // Role-based Play
        elseif ($role === 'attaccante' && strpos($key, 'season_play_atk_') !== false) {
            $shouldIncrement = true;
        }
        elseif ($role === 'portiere' && strpos($key, 'season_play_gk_') !== false) {
            $shouldIncrement = true;
        }
        // Role-based Win
        elseif ($isWin) {
            if ($role === 'attaccante' && strpos($key, 'season_win_atk_') !== false) {
                $shouldIncrement = true;
            }
            elseif ($role === 'portiere' && strpos($key, 'season_win_gk_') !== false) {
                $shouldIncrement = true;
            }
        }

        if ($shouldIncrement) {
            updateObjectiveProgress($conn, $playerId, $obj);
        }
    }
}

/**
 * Tracks betting objectives (Place and Win)
 */
function checkBetObjectives($conn, $playerId, $type)
{
    if ($playerId == 9999 || $playerId <= 0)
        return;

    $stmt = $conn->prepare("SELECT o.*, uo.current_value, uo.completed 
                            FROM objectives o 
                            LEFT JOIN user_objectives uo ON o.id = uo.objective_id AND uo.user_id = ?
                            WHERE o.key_name LIKE 'season_bet_%'");
    $stmt->execute([$playerId]);
    $objectives = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($objectives as $obj) {
        if ($obj['completed'])
            continue;

        $shouldIncrement = false;
        $key = $obj['key_name'];

        if ($type === 'place' && strpos($key, 'season_bet_win_') === false) {
            $shouldIncrement = true;
        }
        elseif ($type === 'win' && strpos($key, 'season_bet_win_') !== false) {
            $shouldIncrement = true;
        }

        if ($shouldIncrement) {
            updateObjectiveProgress($conn, $playerId, $obj);
        }
    }
}

function checkDailyReset($conn, $playerId)
{
    $today = date('Y-m-d');

    // Check if user has any daily objective not yet reset today
    $stmt = $conn->prepare("SELECT COUNT(*) FROM user_objectives uo 
                            JOIN objectives o ON uo.objective_id = o.id 
                            WHERE uo.user_id = ? AND o.type = 'daily' 
                            AND (uo.last_updated IS NULL OR uo.last_updated < ?)");
    $stmt->execute([$playerId, $today]);

    if ($stmt->fetchColumn() > 0) {
        // Delete rows entirely so they come back as 0/target (not completed) on next fetch
        $stmtReset = $conn->prepare("DELETE uo FROM user_objectives uo 
                                    JOIN objectives o ON uo.objective_id = o.id 
                                    WHERE uo.user_id = ? AND o.type = 'daily'");
        $stmtReset->execute([$playerId]);
    }
}

function updateObjectiveProgress($conn, $playerId, $obj)
{
    $today = date('Y-m-d');
    $objectiveId = $obj['id'];
    $newValue = ($obj['current_value'] ?? 0) + 1;
    $completed = ($newValue >= $obj['target_value']) ? 1 : 0;

    $stmt = $conn->prepare("INSERT INTO user_objectives (user_id, objective_id, current_value, completed, last_updated) 
                            VALUES (?, ?, ?, ?, ?) 
                            ON DUPLICATE KEY UPDATE 
                            current_value = VALUES(current_value), 
                            completed = VALUES(completed), 
                            last_updated = VALUES(last_updated)");
    $stmt->execute([$playerId, $objectiveId, $newValue, $completed, $today]);

    // If just completed, award XP
    if ($completed && !($obj['completed'] ?? 0)) {
        awardXP($conn, $playerId, $obj['xp_reward']);
    }
}

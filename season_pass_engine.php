<?php
/**
 * Season Pass Engine
 * Handles XP awarding, level ups, and reward distribution.
 */

function awardXP($conn, $playerId, $amount) {
    if ($playerId == 9999 || $playerId <= 0) return null;

    // 1. Fetch current progress
    $stmt = $conn->prepare("SELECT xp, level FROM giocatori WHERE id = ?");
    $stmt->execute([$playerId]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$player) return null;

    $newXP = $player['xp'] + $amount;
    $currentLevel = $player['level'];
    
    // Level formula: 500 XP per level
    $xpPerLevel = 500;
    $newLevel = floor($newXP / $xpPerLevel) + 1;
    
    $leveledUp = ($newLevel > $currentLevel);
    
    // 2. Update player XP and Level
    $stmtUpdate = $conn->prepare("UPDATE giocatori SET xp = ?, level = ? WHERE id = ?");
    $stmtUpdate->execute([$newXP, $newLevel, $playerId]);

    // 3. Level up info
    // Automatic reward granting has been removed in favor of manual claiming.
    $grantedRewards = []; // Legacy field, kept for compatibility if needed elsewhere


    return [
        'playerId' => $playerId,
        'oldLevel' => $currentLevel,
        'newLevel' => $newLevel,
        'xpAdded' => $amount,
        'totalXP' => $newXP,
        'leveledUp' => $leveledUp,
        'rewards' => $grantedRewards
    ];
}

function claimReward($conn, $userId, $level) {
    // 1. Verify level
    $stmt = $conn->prepare("SELECT level FROM giocatori WHERE id = ?");
    $stmt->execute([$userId]);
    $currentLevel = $stmt->fetchColumn();
    
    if ($currentLevel < $level) {
        return ['success' => false, 'error' => 'Livello non ancora raggiunto'];
    }
    
    // 2. Verify not already claimed
    $stmtCheck = $conn->prepare("SELECT 1 FROM user_season_claimed WHERE user_id = ? AND level = ?");
    $stmtCheck->execute([$userId, $level]);
    if ($stmtCheck->fetch()) {
        return ['success' => false, 'error' => 'Premio già riscattato'];
    }
    
    // 3. Grant Reward
    $reward = grantLevelReward($conn, $userId, $level);
    if (!$reward) {
        return ['success' => false, 'error' => 'Nessun premio configurato per questo livello'];
    }
    
    // 4. Mark as claimed
    $stmtMark = $conn->prepare("INSERT INTO user_season_claimed (user_id, level) VALUES (?, ?)");
    $stmtMark->execute([$userId, $level]);
    
    return ['success' => true, 'reward' => $reward];
}

function grantLevelReward($conn, $playerId, $level) {
    $stmt = $conn->prepare("SELECT * FROM season_pass_rewards WHERE level = ?");
    $stmt->execute([$level]);
    $reward = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reward) return null;

    switch ($reward['reward_type']) {
        case 'credit':
            $conn->prepare("UPDATE giocatori SET crediti = crediti + ? WHERE id = ?")
                 ->execute([$reward['amount'], $playerId]);
            break;
            
        case 'aesthetic':
        case 'bonus':
            // Find item_id from shop_items
            $stmtItem = $conn->prepare("SELECT id FROM shop_items WHERE key_name = ?");
            $stmtItem->execute([$reward['item_key']]);
            $itemId = $stmtItem->fetchColumn();
            
            if ($itemId) {
                // Add to inventory
                $stmtInv = $conn->prepare("INSERT INTO user_inventory (user_id, item_id, quantity) 
                                         VALUES (?, ?, 1) 
                                         ON DUPLICATE KEY UPDATE quantity = quantity + 1");
                $stmtInv->execute([$playerId, $itemId]);
            }
            break;
    }

    return $reward;
}

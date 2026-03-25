<?php
function processBets($conn, $winningTeam) {
    $isDeuce = (isset($_GET['deuce']) && $_GET['deuce'] === 'yes') ? 'yes' : 'no';
    
    $stmt = $conn->query("SELECT * FROM scommesse WHERE status = 'pending'");
    $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $userWinnings = [];

    foreach ($bets as $bet) {
        $won = false;
        if ($bet['bet_type'] === 'winner') {
            if ($bet['bet_value'] == $winningTeam) $won = true;
        } elseif ($bet['bet_type'] === 'deuce') {
            if ($bet['bet_value'] === $isDeuce) $won = true;
        }
        
        if ($won) {
            $payout = $bet['amount'] * $bet['quota'];
            $conn->prepare("UPDATE giocatori SET crediti = crediti + ? WHERE id = ?")->execute([$payout, $bet['user_id']]);
            $conn->prepare("UPDATE scommesse SET status = 'won' WHERE id = ?")->execute([$bet['id']]);
            
            require_once 'objectives_engine.php';
            checkBetObjectives($conn, $bet['user_id'], 'win');
            
            if (!isset($userWinnings[$bet['user_id']])) $userWinnings[$bet['user_id']] = 0;
            $userWinnings[$bet['user_id']] += $payout;
        } else {
            $conn->prepare("UPDATE scommesse SET status = 'lost' WHERE id = ?")->execute([$bet['id']]);
        }
    }

    // Set Session Message for current user if they won
    if (isset($_SESSION['user_id']) && isset($userWinnings[$_SESSION['user_id']])) {
        $wonAmount = $userWinnings[$_SESSION['user_id']];
        $_SESSION['match_payout'] = [
            'amount' => $wonAmount,
            'message' => "Complimenti! Hai vinto $wonAmount crediti con le tue scommesse!"
        ];
    }
}
?>

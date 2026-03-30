<?php
function processBets($conn, $winningTeam, $s1 = 0, $s2 = 0, $goalLog = '') {
    $isDeuce = ($s1 >= 7 && $s2 >= 7) ? 'yes' : 'no';
    
    $stmt = $conn->query("SELECT * FROM scommesse WHERE status = 'pending'");
    $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $userWinnings = [];
    $logArr = ($goalLog !== '') ? explode(',', $goalLog) : [];

    foreach ($bets as $bet) {
        $won = false;
        if ($bet['bet_type'] === 'winner') {
            if ($bet['bet_value'] == $winningTeam) $won = true;
        } elseif ($bet['bet_type'] === 'deuce') {
            if ($bet['bet_value'] === $isDeuce) $won = true;
        } elseif ($bet['bet_type'] === 'handicap') {
            // value format: "s1_-1.5" or "s2_+0.5"
            $parts = explode('_', $bet['bet_value']);
            if (count($parts) === 2) {
                $team = $parts[0]; // s1 or s2
                $hVal = (float)$parts[1]; // -1.5, +0.5 etc
                if ($team === 's1') {
                    if (($s1 + $hVal) > $s2) $won = true;
                } else {
                    if (($s2 + $hVal) > $s1) $won = true;
                }
            }
        } elseif ($bet['bet_type'] === 'over_under') {
            // value format: "10.5_over" or "12.5_under"
            $parts = explode('_', $bet['bet_value']);
            if (count($parts) === 2) {
                $threshold = (float)$parts[0];
                $type = $parts[1]; // over or under
                $total = $s1 + $s2;
                if ($type === 'over') {
                    if ($total > $threshold) $won = true;
                } else {
                    if ($total < $threshold) $won = true;
                }
            }
        } elseif ($bet['bet_type'] === 'deuce_winner') {
            // Only valid if match reached 7-7
            if ($s1 >= 7 && $s2 >= 7) {
                if ($bet['bet_value'] == $winningTeam) $won = true;
            }
        } elseif ($bet['bet_type'] === 'deuce_parity') {
            // Only valid if reached deuce
            if ($s1 >= 7 && $s2 >= 7) {
                $parts = explode('_', $bet['bet_value']);
                if (count($parts) === 2) {
                    $threshold = (float)$parts[0];
                    $type = $parts[1]; // over or under
                    $parityCount = min($s1, $s2) - 7;
                    if ($type === 'over') {
                        if ($parityCount > $threshold) $won = true;
                    } else {
                        if ($parityCount < $threshold) $won = true;
                    }
                }
            }
        } elseif ($bet['bet_type'] === 'winning_margin') {
            // value: "1", "2", ... "5"
            $marginVal = (int)$bet['bet_value'];
            if (abs($s1 - $s2) === $marginVal) $won = true;
        } elseif ($bet['bet_type'] === 'winning_diff') {
            // value: "1_yes", "2p_no" etc
            $parts = explode('_', $bet['bet_value']);
            if (count($parts) === 2) {
                $threshold = $parts[0]; // 1, 2p, 3p
                $isYes = $parts[1] === 'yes';
                $diff = abs($s1 - $s2);
                $conditionMet = false;
                if ($threshold === '1') $conditionMet = ($diff === 1);
                elseif ($threshold === '2p') $conditionMet = ($diff >= 2);
                elseif ($threshold === '3p') $conditionMet = ($diff >= 3);
                
                if ($isYes && $conditionMet) $won = true;
                elseif (!$isYes && !$conditionMet) $won = true;
            }
        } elseif ($bet['bet_type'] === 'team_over_under') {
            // value: "s1_4.5_over", "s2_5.5_under"
            $parts = explode('_', $bet['bet_value']);
            if (count($parts) === 3) {
                $team = $parts[0]; // s1 or s2
                $threshold = (float)$parts[1];
                $type = $parts[2]; // over or under
                $score = ($team === 's1') ? $s1 : $s2;
                if ($type === 'over') {
                    if ($score > $threshold) $won = true;
                } else {
                    if ($score < $threshold) $won = true;
                }
            }
        } elseif ($bet['bet_type'] === 'exact_score') {
            // value: "8-3", "10-9" etc
            if ($bet['bet_value'] === "{$s1}-{$s2}") $won = true;
        } elseif ($bet['bet_type'] === 'combo') {
            // value format: "s1_o145", "s2_vN", "s1_m2"
            $parts = explode('_', $bet['bet_value']);
            if (count($parts) === 2) {
                $team = $parts[0]; // s1 or s2
                $cond = $parts[1]; // o145, vY, m1 etc
                
                $isWinnerMatch = ($team === 's1' && $s1 > $s2) || ($team === 's2' && $s2 > $s1);
                if ($isWinnerMatch) {
                    if ($cond[0] === 'o' || $cond[0] === 'u') {
                        $type = $cond[0];
                        $threshold = (float)substr($cond, 1) / 10;
                        $total = $s1 + $s2;
                        if ($type === 'o') { if ($total > $threshold) $won = true; }
                        else { if ($total < $threshold) $won = true; }
                    } elseif ($cond === 'vY') {
                        if ($s1 >= 7 && $s2 >= 7) $won = true;
                    } elseif ($cond === 'vN') {
                        if ($s1 < 7 || $s2 < 7) $won = true;
                    } elseif ($cond === 'm1') {
                        if (abs($s1 - $s2) === 1) $won = true;
                    } elseif ($cond === 'm2') {
                        if (abs($s1 - $s2) === 2) $won = true;
                    } elseif ($cond === 'm3') {
                        if (abs($s1 - $s2) >= 3) $won = true;
                    }
                }
            }
        } elseif ($bet['bet_type'] === 'btts_threshold') {
            // value: "5_yes", "7_no"
            $parts = explode('_', $bet['bet_value']);
            if (count($parts) === 2) {
                $threshold = (int)$parts[0];
                $isYes = $parts[1] === 'yes';
                $conditionMet = (min($s1, $s2) >= $threshold);
                if ($isYes && $conditionMet) $won = true;
                elseif (!$isYes && !$conditionMet) $won = true;
            }
        } elseif ($bet['bet_type'] === 'cappotto_yn') {
            $isYes = $bet['bet_value'] === 'yes';
            $conditionMet = ($s1 === 8 && $s2 === 0) || ($s1 === 0 && $s2 === 8);
            if ($isYes && $conditionMet) $won = true;
            elseif (!$isYes && !$conditionMet) $won = true;
        } elseif ($bet['bet_type'] === 'consecutive3_yn') {
            $isYes = $bet['bet_value'] === 'yes'; // Yes = No Team scores 3+ consecutive
            $hasStreak3 = false;
            if (count($logArr) >= 3) {
                for ($i = 0; $i <= count($logArr)-3; $i++) {
                    if ($logArr[$i] == $logArr[$i+1] && $logArr[$i+1] == $logArr[$i+2]) {
                        $hasStreak3 = true; break;
                    }
                }
            }
            if ($isYes && !$hasStreak3) $won = true;
            elseif (!$isYes && $hasStreak3) $won = true;
        } elseif ($bet['bet_type'] === 'fgoal_win_yn') {
            $isYes = $bet['bet_value'] === 'yes';
            $firstScorer = count($logArr) > 0 ? (int)$logArr[0] : 0;
            $conditionMet = ($firstScorer > 0 && $firstScorer == $winningTeam);
            if ($isYes && $conditionMet) $won = true;
            elseif (!$isYes && !$conditionMet && $firstScorer > 0) $won = true;
        } elseif ($bet['bet_type'] === 'killer_pt_yn') {
            $isYes = $bet['bet_value'] === 'yes';
            $isKillerPt = (max($s1, $s2) >= 8 && abs($s1 - $s2) == 1) || ($s1 == 10 || $s2 == 10);
            // Formally, let's say KP is reaching 8-7, 7-8, 9-8, 8-9, or 10-9/9-10
            $conditionMet = $isKillerPt;
            if ($isYes && $conditionMet) $won = true;
            elseif (!$isYes && !$conditionMet) $won = true;
        } elseif ($bet['bet_type'] === 'ribaltone_yn') {
            $isYes = $bet['bet_value'] === 'yes';
            $maxDeficitS1 = 0; $maxDeficitS2 = 0;
            $curS1 = 0; $curS2 = 0;
            foreach($logArr as $sc) {
                if ($sc == 1) $curS1++; else $curS2++;
                $maxDeficitS1 = max($maxDeficitS1, $curS2 - $curS1);
                $maxDeficitS2 = max($maxDeficitS2, $curS1 - $curS2);
            }
            $conditionMet = ($winningTeam == 1 && $maxDeficitS1 >= 4) || ($winningTeam == 2 && $maxDeficitS2 >= 4);
            if ($isYes && $conditionMet) $won = true;
            elseif (!$isYes && !$conditionMet) $won = true;
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

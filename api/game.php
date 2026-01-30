<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');
require_once 'db.php';

$input = json_decode(file_get_contents("php://input"), true);
$username = $input['username'] ?? null;
$token    = $input['token'] ?? null;

/* ================= LOGIN ================= */
if ($username) {

    // Check if user already exists
    $stmt = $pdo->prepare("SELECT player_id FROM players WHERE username=?");
    $stmt->execute([$username]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$player) {
        // Insert new player
        $stmt = $pdo->prepare("INSERT INTO players(username,last_active) VALUES(?,NOW())");
        $stmt->execute([$username]);
        $player_id = $pdo->lastInsertId();
    } else {
        $player_id = $player['player_id'];
    }

    $token = bin2hex(random_bytes(8));
    $stmt = $pdo->prepare("UPDATE players SET token=?, last_active=NOW() WHERE player_id=?");
    $stmt->execute([$token, $player_id]);

    // ---------------- GET ACTIVE PLAYERS ----------------
    $players = $pdo->query("SELECT player_id, username FROM players ORDER BY player_id ASC LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);

    // ---------------- LOAD BOARD ----------------
    $board = $pdo->query("SELECT * FROM board WHERE id=1")->fetch(PDO::FETCH_ASSOC);

    // Treat empty JSON arrays as empty (even if not technically empty in PHP)
    $needsShuffle = true;
    if($board){
        $deckArr = json_decode($board['deck'], true);
        $p1Hand = json_decode($board['player1_hand'], true);
        $p2Hand = json_decode($board['player2_hand'], true);
        if(!empty($deckArr) || !empty($p1Hand) || !empty($p2Hand)){
            $needsShuffle = false;
        }
    }

    if (!$board || $needsShuffle) {
        // Shuffle deck
        $deck = $pdo->query("SELECT card_id FROM cards ORDER BY RAND()")->fetchAll(PDO::FETCH_COLUMN);
        $table = array_splice($deck, 0, 4);
        $p1_hand = array_splice($deck, 0, 6);
        $p2_hand = array_splice($deck, 0, 6);

        if (!$board) {
            // Insert new board row
            $pdo->prepare("
                INSERT INTO board(id, deck, table_pile, player1_hand, player2_hand,
                player1_captured, player2_captured, player1_xeri, player2_xeri,
                current_turn, round_number, status)
                VALUES(1,?,?,?,?,?,'[]','[]','[]','[]',1,1,'waiting')
            ")->execute([
                json_encode($deck),
                json_encode($table),
                json_encode($p1_hand),
                json_encode($p2_hand),
                json_encode($p2_hand)
            ]);
        } else {
            // Update existing board
            $pdo->prepare("
                UPDATE board SET
                    deck=?,
                    table_pile=?,
                    player1_hand=?,
                    player2_hand=?,
                    player1_captured='[]',
                    player2_captured='[]',
                    player1_xeri='[]',
                    player2_xeri='[]',
                    current_turn=1,
                    round_number=1,
                    status='waiting'
                WHERE id=1
            ")->execute([
                json_encode($deck),
                json_encode($table),
                json_encode($p1_hand),
                json_encode($p2_hand)
            ]);
        }
        // reload board
        $board = $pdo->query("SELECT * FROM board WHERE id=1")->fetch(PDO::FETCH_ASSOC);
    }

    // ---------------- ACTIVATE BOARD IF 2 PLAYERS ----------------
    $players = $pdo->query("SELECT player_id, username FROM players ORDER BY player_id ASC LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);
    if(count($players) == 2 && $board['status'] === 'waiting'){
        $pdo->prepare("UPDATE board SET status='active', current_turn=1 WHERE id=1")->execute();
        $board['status'] = 'active';
        $board['current_turn'] = 1;
    }

    echo json_encode([
        'player_id' => $player_id,
        'token' => $token,
        'username' => $username,
        'players' => $players
    ]);
    exit;
}

/* ================= GAME STATE ================= */
if ($token) {
    $stmt = $pdo->prepare("SELECT * FROM board WHERE id=1");
    $stmt->execute();
    $board = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$board) {
        echo json_encode(['error' => 'Board not found']);
        exit;
    }

    $deck      = json_decode($board['deck'], true) ?? [];
    $p1capt    = json_decode($board['player1_captured'], true) ?? [];
    $p2capt    = json_decode($board['player2_captured'], true) ?? [];
    $xeri_p1   = json_decode($board['player1_xeri'], true) ?? [];
    $xeri_p2   = json_decode($board['player2_xeri'], true) ?? [];

    /* ========= SCORE HELPERS ========= */
    function rank($c){ return ($c-1)%13+1; }
    function suit($c){
        if($c<=13) return 'spades';
        if($c<=26) return 'hearts';
        if($c<=39) return 'diamonds';
        return 'clubs';
    }

    function calcScore($captured, $xeri){
        $score = 0;
        foreach($xeri as $c){
            $score += (rank($c) === 11 ? 20 : 10);
        }
        foreach($captured as $c){
            if(in_array($c,$xeri,true)) continue;
            $r = rank($c);
            $s = suit($c);
            if(in_array($r,[11,12,13])) $score += 1;
            elseif($r === 10 && $s !== 'diamonds') $score += 1;
            elseif($r === 2 && $s === 'spades') $score += 1;
            elseif($r === 10 && $s === 'diamonds') $score += 1;
        }
        return $score;
    }

    /* ========= FINAL SCORE (ONLY IF GAME ENDED) ========= */
    $final_score = null;
    $winner = null;

    if ($board['status'] === 'finished') {
        $s1 = calcScore($p1capt, $xeri_p1);
        $s2 = calcScore($p2capt, $xeri_p2);

        $final_score = [
            'player1' => $s1,
            'player2' => $s2
        ];

        if ($s1 > $s2) $winner = $players[0]['username'] ?? 'Player 1';
        elseif ($s2 > $s1) $winner = $players[1]['username'] ?? 'Player 2';
        else $winner = 'Draw';
    }

    // Get active players with usernames
    $players = $pdo->query("SELECT player_id, username FROM players ORDER BY player_id ASC LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'current_turn'     => (int)$board['current_turn'],
        'round'            => (int)$board['round_number'],
        'deck_count'       => count($deck),
        'player1_hand'     => json_decode($board['player1_hand'], true),
        'player2_hand'     => json_decode($board['player2_hand'], true),
        'player1_captured' => $p1capt,
        'player2_captured' => $p2capt,
        'table_pile'       => json_decode($board['table_pile'], true),
        'status'           => $board['status'],
        'final_score'      => $final_score,
        'winner'           => $winner,
        'players'          => $players
    ]);
    exit;
}

echo json_encode(['error' => 'Invalid request']);

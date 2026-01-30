<?php
// Ορίζουμε ότι όλα τα responses θα είναι JSON
header('Content-Type: application/json');
require_once 'db.php';// Σύνδεση με βάση δεδομένων

// Λήψη JSON input: token, κάρτα και action (πχ reset_game ή leave)
$input  = json_decode(file_get_contents("php://input"), true);
$token  = $input['token'] ?? null;
$card   = $input['card'] ?? null;
$action = $input['action'] ?? null;

// Έλεγχος αν δόθηκε token
if (!$token) {
    echo json_encode(['error'=>'Missing token']);
    exit;
}

// Εντοπισμός παίκτη με βάση το token
$stmt = $pdo->prepare("SELECT player_id FROM players WHERE token=?");
$stmt->execute([$token]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$player) {
    echo json_encode(['error'=>'Invalid token']);
    exit;
}
$pid = (int)$player['player_id'];

// Προσδιορισμός αριθμού παίκτη (player1 ή player2) ανάλογα με το player_id
$playerNum = ($pid % 2 === 1) ? 1 : 2;

// Ενημέρωση last_active για να φαίνεται ότι ο παίκτης είναι ενεργός
$pdo->prepare("UPDATE players SET last_active=NOW() WHERE player_id=?")->execute([$pid]);

// Φόρτωση board από βάση
$board = $pdo->query("SELECT * FROM board WHERE id=1")->fetch(PDO::FETCH_ASSOC);
if (!$board) {
    echo json_encode(['error'=>'Board missing']);
    exit;
}

// RESET GAME: τυχαία διανομή καρτών, καθαρισμός capture/xeri, ενεργοποίηση παιχνιδιού
if ($action === 'reset_game') {
    $deck = $pdo->query("SELECT card_id FROM cards ORDER BY RAND()")->fetchAll(PDO::FETCH_COLUMN);
    $table = array_splice($deck, 0, 4);
    $p1    = array_splice($deck, 0, 6);
    $p2    = array_splice($deck, 0, 6);

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
            status='active'
        WHERE id=1
    ")->execute([json_encode($deck),json_encode($table),json_encode($p1),json_encode($p2)]);

    echo json_encode(['status'=>'ok']);
    exit;
}

// LEAVE GAME: ο παίκτης φεύγει, αν ήταν ενεργό το παιχνίδι, ο άλλος κερδίζει
if ($action === 'leave') {
    $pdo->prepare("DELETE FROM players WHERE player_id=?")->execute([$pid]);
    if($board && $board['status']==='active'){
        $winner = $playerNum===1?'player2':'player1';
        $pdo->prepare("UPDATE board SET status='finished' WHERE id=1")->execute();
    }
    echo json_encode(['status'=>'ok']);
    exit;
}

// ---------------- HELPERS ----------------
function rank($c){ return ($c-1)%13+1; }
function suit($c){
    if($c<=13) return 'spades';
    if($c<=26) return 'hearts';
    if($c<=39) return 'diamonds';
    return 'clubs';
}

// Ορισμός μεταβλητών ανάλογα με τον παίκτη
$handKey = 'player'.$playerNum.'_hand';
$capKey  = 'player'.$playerNum.'_captured';
$xeriKey = 'player'.$playerNum.'_xeri';
$otherHandKey = ($playerNum === 1) ? 'player2_hand' : 'player1_hand';

// Φόρτωση χεριού, τραπέζης, captured, xeri, deck
$hand  = json_decode($board[$handKey], true) ?? [];
$otherHand  = json_decode($board[$otherHandKey], true) ?? [];
$cap   = json_decode($board[$capKey], true) ?? [];
$table = json_decode($board['table_pile'], true) ?? [];
$deck  = json_decode($board['deck'], true) ?? [];
$xeri  = json_decode($board[$xeriKey], true) ?? [];

// Έλεγχος αν είναι η σειρά του παίκτη
if((int)$board['current_turn']!==$playerNum){
    echo json_encode(['error'=>'Not your turn']);
    exit;
}

// Έλεγχος αν η κάρτα υπάρχει στο χέρι και αφαίρεση
$idx = array_search($card,$hand,true);
if($idx===false){
    echo json_encode(['error'=>'Card not in hand']);
    exit;
}
array_splice($hand,$idx,1);

// Λογική capture και ξερής
$top = end($table);
$capturedNow = false;
$isXeri = false;

if($top && (rank($top)===rank($card) || rank($card)===11)){
    $capturedNow=true;
    if(count($table)===1 || rank($card)===11){
        $isXeri=true;
        $xeri[]=$card;
    }
    $cap=array_merge($cap,$table,[$card]);
    $table=[];
}else{
    $table[]=$card;
}

// Αποθήκευση της κίνησης στη βάση
$pdo->prepare("UPDATE board SET $handKey=?, $capKey=?, table_pile=?, $xeriKey=? WHERE id=1")
    ->execute([json_encode($hand),json_encode($cap),json_encode($table),json_encode($xeri)]);

// Ανάκτηση νέου γύρου από deck
if(empty($hand) && empty($otherHand) && count($deck)>0){
    $new1 = array_splice($deck,0,min(6,count($deck)));
    $new2 = array_splice($deck,0,min(6,count($deck)));
    $pdo->prepare("UPDATE board SET player1_hand=?, player2_hand=?, deck=?, round_number=round_number+1 WHERE id=1")
        ->execute([json_encode($new1),json_encode($new2),json_encode($deck)]);
}

// Επόμενη σειρά παίκτη
$nextTurn = ($playerNum === 1) ? 2 : 1;
$pdo->prepare("UPDATE board SET current_turn=? WHERE id=1")->execute([$nextTurn]);

// Φόρτωση τελικής κατάστασης board
$stmt = $pdo->prepare("SELECT * FROM board WHERE id=1");
$stmt->execute();
$board = $stmt->fetch(PDO::FETCH_ASSOC);

// Έλεγχος αν τελείωσε το παιχνίδι
$deck = json_decode($board['deck'], true) ?? [];
$p1_hand = json_decode($board['player1_hand'], true) ?? [];
$p2_hand = json_decode($board['player2_hand'], true) ?? [];

if (empty($deck) && empty($p1_hand) && empty($p2_hand)) {

    $p1cap = json_decode($board['player1_captured'], true) ?? [];
    $p2cap = json_decode($board['player2_captured'], true) ?? [];
    $x1 = json_decode($board['player1_xeri'], true) ?? [];
    $x2 = json_decode($board['player2_xeri'], true) ?? [];

    function finalScore($captured,$xeri){
        $score = 0;
        foreach($xeri as $c){
            $score += (rank($c)===11?20:10);
        }
        foreach($captured as $c){
            if(in_array($c,$xeri,true)) continue;
            $r = rank($c); $s = suit($c);
            if(in_array($r,[11,12,13])) $score+=1;
            elseif($r===10 && $s!=='diamonds') $score+=1;
            elseif($r===10 && $s==='diamonds') $score+=1;
            elseif($r===2 && $s==='spades') $score+=1;
        }
        return $score;
    }

    $s1 = finalScore($p1cap,$x1);
    $s2 = finalScore($p2cap,$x2);

    if(count($p1cap)>count($p2cap)) $s1+=3;
    elseif(count($p2cap)>count($p1cap)) $s2+=3;

    if($s1 > $s2) $winner='player1';
    elseif($s2 > $s1) $winner='player2';
    else $winner='draw';

    $pdo->prepare("UPDATE board SET status='finished' WHERE id=1")->execute();

    // Αφαίρεση παικτών μετά το τέλος
    $pdo->query("DELETE FROM players");

    echo json_encode([
        'status'=>'finished',
        'winner'=>$winner,
        'player1_score'=>$s1,
        'player2_score'=>$s2
    ]);
    exit;
}

// Επιστροφή αποτελέσματος κίνησης
echo json_encode([
    'status'=>'ok',
    'captured'=>$capturedNow,
    'xeri'=>$isXeri
]);

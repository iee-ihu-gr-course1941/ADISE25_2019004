<?php
// Ορίζουμε ότι όλα τα responses θα είναι JSON
header('Content-Type: application/json');
require_once 'db.php';// Φορτώνει το db.php για το $pdo

try {
    $input = json_decode(file_get_contents("php://input"), true);
    $username = trim($input['username'] ?? '');
 // Έλεγχος αν δόθηκε username
    if (!$username) {
        echo json_encode(['error'=>'Username required']);
        exit;
    }

    // Έλεγχος αν ο παίκτης υπάρχει ήδη στη βάση
    $stmt = $pdo->prepare("SELECT * FROM players WHERE username=?");
    $stmt->execute([$username]);
    $player = $stmt->fetch();

    if (!$player) {
       // Αν δεν υπάρχει ο παίκτης, ελέγχουμε πόσοι παίκτες ήδη υπάρχουν
        $count = $pdo->query("SELECT COUNT(*) FROM players")->fetchColumn();
        if ($count >= 2) {// Αν υπάρχουν ήδη 2 παίκτες, δεν μπορεί να μπει άλλος
            echo json_encode(['error'=>'Game full']);
            exit;
        }
        // Δημιουργία νέου παίκτη
        $player_id = $count + 1;
        $token = md5(uniqid());// Μοναδικό token για auth

        $stmt = $pdo->prepare("INSERT INTO players (player_id, username, token) VALUES (?, ?, ?)");
        $stmt->execute([$player_id, $username, $token]);
    } else {// Αν υπάρχει ήδη ο παίκτης, κρατάμε τα υπάρχοντα στοιχεία
        $player_id = $player['player_id'];
        $token = $player['token'];
    }

    // Αν έχει μπει ο δεύτερος παίκτης, αρχικοποίηση board
    $count = $pdo->query("SELECT COUNT(*) FROM players")->fetchColumn();
    $status = $pdo->query("SELECT status FROM board WHERE id=1")->fetchColumn();

    if ($count == 2 && $status !== 'active') {// Παίρνουμε όλες τις κάρτες τυχαία
        $cards = $pdo->query("SELECT card_id FROM cards ORDER BY RAND()")->fetchAll(PDO::FETCH_COLUMN);

        // single table pile: first 4 cards
        $table_pile = array_slice($cards, 0, 4);
        $player1_hand = array_slice($cards, 4, 6);
        $player2_hand = array_slice($cards, 10, 6);

        // Ενημέρωση του board στη βάση
        $stmt = $pdo->prepare("UPDATE board SET 
            table_pile=?,
            player1_hand=?,
            player2_hand=?,
            player1_captured='[]',
            player2_captured='[]',
            status='active',
            current_turn=1
            WHERE id=1
        ");
        $stmt->execute([
            json_encode($table_pile),
            json_encode($player1_hand),
            json_encode($player2_hand)
        ]);
    }

    // Επιστροφή JSON με στοιχεία του παίκτη
    echo json_encode(['player_id'=>$player_id,'username'=>$username,'token'=>$token]);
 // Αν υπάρχει σφάλμα PHP ή DB, επιστρέφουμε JSON με error
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error'=>'Exception','message'=>$e->getMessage()]);
}

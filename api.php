<?php
/*
══════════════════════════════════════════
  WEDDING RSVP DATABASE
  Upload this file + the HTML to your server
  Database file is created automatically
══════════════════════════════════════════
*/

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── DATABASE FILE PATH ──
// Change this if you want the file elsewhere
 $dbFile = __DIR__ . '/wedding_rsvp.db';

// ── CREATE DATABASE + TABLE ──
 $db = new SQLite3($dbFile);
 $db->exec('CREATE TABLE IF NOT EXISTS rsvps (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    phone TEXT DEFAULT "",
    status TEXT NOT NULL,
    guests INTEGER DEFAULT 1,
    dietary TEXT DEFAULT "None",
    message TEXT DEFAULT "",
    ref TEXT DEFAULT "",
    created_at TEXT DEFAULT (datetime("now"))
)');

// ── ROUTES ──
 $method = $_SERVER['REQUEST_METHOD'];
 $path = isset($_GET['action']) ? $_GET['action'] : '';

switch ($method) {

    // GET all RSVPs
    case 'GET':
        $result = $db->query('SELECT * FROM rsvps ORDER BY created_at DESC');
        $rows = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $row;
        }

        // Also return stats
        $total = count($rows);
        $attending = 0;
        $declined = 0;
        $totalGuests = 0;
        foreach ($rows as $r) {
            if ($r['status'] === 'Attending') $attending++;
            if ($r['status'] === 'Declined') $declined++;
            $totalGuests += intval($r['guests']);
        }

        echo json_encode([
            'success' => true,
            'data' => $rows,
            'stats' => [
                'total' => $total,
                'attending' => $attending,
                'declined' => $declined,
                'guests' => $totalGuests
            ]
        ]);
        break;

    // POST new RSVP
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || empty($input['name']) || empty($input['email'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Name and email are required']);
            break;
        }

        $stmt = $db->prepare('INSERT INTO rsvps (name, email, phone, status, guests, dietary, message, ref) VALUES (:name, :email, :phone, :status, :guests, :dietary, :message, :ref)');
        $stmt->bindValue(':name', trim($input['name']), SQLITE3_TEXT);
        $stmt->bindValue(':email', trim($input['email']), SQLITE3_TEXT);
        $stmt->bindValue(':phone', isset($input['phone']) ? trim($input['phone']) : '', SQLITE3_TEXT);
        $stmt->bindValue(':status', isset($input['status']) ? $input['status'] : 'Pending', SQLITE3_TEXT);
        $stmt->bindValue(':guests', isset($input['guests']) ? intval($input['guests']) : 1, SQLITE3_INTEGER);
        $stmt->bindValue(':dietary', isset($input['dietary']) ? $input['dietary'] : 'None', SQLITE3_TEXT);
        $stmt->bindValue(':message', isset($input['message']) ? trim($input['message']) : '', SQLITE3_TEXT);
        $stmt->bindValue(':ref', isset($input['ref']) ? trim($input['ref']) : '', SQLITE3_TEXT);

        if ($stmt->execute()) {
            $newId = $db->lastInsertRowID();
            $result = $db->querySingle('SELECT * FROM rsvps WHERE id = ' . intval($newId), true);
            echo json_encode(['success' => true, 'data' => $result]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database write failed']);
        }
        break;

    // DELETE all
    case 'DELETE':
        $action = isset($_GET['confirm']) ? $_GET['confirm'] : '';
        if ($action === 'yes') {
            $db->exec('DELETE FROM rsvps');
            echo json_encode(['success' => true, 'message' => 'All data cleared']);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Add ?confirm=yes to confirm deletion']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

 $db->close();
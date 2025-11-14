<?php
require_once '../config/app.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth->requireAuth();
header('Content-Type: application/json');

try {
    $pdo = getDBConnection();

    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $st = $pdo->prepare("SELECT i.*, c.name as category_name FROM catalog_items i LEFT JOIN catalog_categories c ON c.id=i.category_id WHERE i.id=? AND i.is_active=1");
        $st->execute([$id]);
        $item = $st->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success'=>true,'data'=>$item]);
        exit;
    }

    $type = $_GET['type'] ?? null;
    $cat = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
    $q = trim($_GET['q'] ?? '');

    $sql = "SELECT id, name, item_type, unit, cost_price, sell_price FROM catalog_items WHERE is_active=1";
    $params = [];
    if ($type && in_array($type,['product','service'])) { $sql .= " AND item_type=?"; $params[]=$type; }
    if ($cat) { $sql .= " AND category_id=?"; $params[]=$cat; }
    if ($q !== '') { $sql .= " AND name LIKE ?"; $params[]='%'.$q.'%'; }
    $sql .= " ORDER BY name";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $items = $st->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success'=>true,'data'=>$items]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}



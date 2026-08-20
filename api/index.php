<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Credentials: true');

// CORS
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    header("Access-Control-Allow-Origin: $origin");
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Max-Age: 86400');
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/router.php';
require_once __DIR__ . '/seed.php';
require_once __DIR__ . '/catalog.php';
require_once __DIR__ . '/forum.php';

// Seed demo data
Seed::seedIfEmpty();

$router = new Router('/api');

// ─── Health ───────────────────────────────────────────────────────────────────

$router->get('/health', function() {
    Response::json(['status' => 'ok', 'timestamp' => date('c')]);
});

// ─── Auth ─────────────────────────────────────────────────────────────────────

$router->post('/auth/login', function() {
    $body = Request::getJson();
    $login = trim($body['login'] ?? '');
    $password = $body['password'] ?? '';

    if (!$login || !$password) {
        Response::error('Укажите логин и пароль', 400);
        return;
    }

    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT * FROM users WHERE login = ? OR email = ? LIMIT 1");
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();

    if (!$user || !Auth::verifyPassword($password, $user['password_hash'])) {
        Response::error('Неверный логин или пароль', 401);
        return;
    }

    $token = Auth::signToken([
        'sub' => $user['id'],
        'login' => $user['login'],
        'role' => $user['role']
    ]);

    Auth::setCookie($token);
    Response::json([
        'id' => (int)$user['id'],
        'login' => $user['login'],
        'email' => $user['email'],
        'role' => $user['role'],
        'createdAt' => $user['created_at']
    ]);
});

$router->post('/auth/register', function() {
    $body = Request::getJson();
    $token = trim($body['token'] ?? '');
    $login = trim($body['login'] ?? '');
    $email = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';

    if (!$token || !$login || !$email || !$password) {
        Response::error('Заполните все поля', 400);
        return;
    }

    $db = Database::getInstance();

    // Validate invite token
    $stmt = $db->prepare("SELECT * FROM invite_tokens WHERE token = ? LIMIT 1");
    $stmt->execute([$token]);
    $invite = $stmt->fetch();

    if (!$invite) {
        Response::error('Недействительная пригласительная ссылка', 400);
        return;
    }
    if ($invite['used']) {
        Response::error('Пригласительная ссылка уже использована', 400);
        return;
    }
    if ($invite['expires_at'] && strtotime($invite['expires_at']) < time()) {
        Response::error('Срок действия ссылки истёк', 400);
        return;
    }

    // Check uniqueness
    $stmt = $db->prepare("SELECT id FROM users WHERE login = ? OR email = ? LIMIT 1");
    $stmt->execute([$login, $email]);
    if ($stmt->fetch()) {
        Response::error('Логин или email уже занят', 400);
        return;
    }

    // Create user
    $passwordHash = Auth::hashPassword($password);
    $stmt = $db->prepare("
        INSERT INTO users (login, email, password_hash, role)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$login, $email, $passwordHash, $invite['role']]);
    $userId = $db->lastInsertId();

    // Mark invite as used
    $stmt = $db->prepare("
        UPDATE invite_tokens
        SET used = 1, used_by_id = ?, used_at = datetime('now')
        WHERE id = ?
    ");
    $stmt->execute([$userId, $invite['id']]);

    // Fetch created user
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    $jwt = Auth::signToken([
        'sub' => $user['id'],
        'login' => $user['login'],
        'role' => $user['role']
    ]);

    Auth::setCookie($jwt);
    Response::json([
        'id' => (int)$user['id'],
        'login' => $user['login'],
        'email' => $user['email'],
        'role' => $user['role'],
        'createdAt' => $user['created_at']
    ], 201);
});

$router->post('/auth/logout', function() {
    Auth::clearCookie();
    Response::noContent();
});

$router->get('/auth/me', function() {
    $user = Auth::requireAuth();
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user['sub']]);
    $userData = $stmt->fetch();

    if (!$userData) {
        Response::error('Пользователь не найден', 404);
        return;
    }

    Response::json([
        'id' => (int)$userData['id'],
        'login' => $userData['login'],
        'email' => $userData['email'],
        'role' => $userData['role'],
        'createdAt' => $userData['created_at']
    ]);
});

$router->patch('/auth/me', function() {
    $user = Auth::requireAuth();

    if ($user['role'] === 'admin') {
        Response::error('Администраторы не могут менять роль через демо-переключатель', 403);
        return;
    }

    $body = Request::getJson();
    $role = $body['role'] ?? '';
    $allowedRoles = ['dealer', 'collector'];

    if (!in_array($role, $allowedRoles)) {
        Response::error('Недостаточно прав для назначения этой роли', 403);
        return;
    }

    $db = Database::getInstance();
    $stmt = $db->prepare("
        UPDATE users
        SET role = ?, updated_at = datetime('now')
        WHERE id = ?
    ");
    $stmt->execute([$role, $user['sub']]);

    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user['sub']]);
    $updated = $stmt->fetch();

    $newToken = Auth::signToken([
        'sub' => $updated['id'],
        'login' => $updated['login'],
        'role' => $updated['role']
    ]);

    Auth::setCookie($newToken);
    Response::json([
        'id' => (int)$updated['id'],
        'login' => $updated['login'],
        'email' => $updated['email'],
        'role' => $updated['role'],
        'createdAt' => $updated['created_at']
    ]);
});

// ─── Catalog ──────────────────────────────────────────────────────────────────

$router->get('/catalog/themes', function() {
    Response::json(CatalogData::getThemes());
});

$router->get('/catalog/themes/:id', function($params) {
    $themes = CatalogData::getThemes();
    $theme = null;
    foreach ($themes as $t) {
        if ($t['id'] === $params['id'] || $t['slug'] === $params['id']) {
            $theme = $t;
            break;
        }
    }

    if (!$theme) {
        Response::error('Тематика не найдена', 404);
        return;
    }

    Response::json($theme);
});

$router->get('/catalog/themes/:id/groups', function($params) {
    $themes = CatalogData::getThemes();
    $theme = null;
    foreach ($themes as $t) {
        if ($t['id'] === $params['id'] || $t['slug'] === $params['id']) {
            $theme = $t;
            break;
        }
    }

    if (!$theme) {
        Response::error('Тематика не найдена', 404);
        return;
    }

    $groups = array_filter(CatalogData::getGroups(), fn($g) => $g['themeId'] === $theme['id']);
    Response::json(array_values($groups));
});

$router->get('/catalog/groups/:id', function($params) {
    $groups = CatalogData::getGroups();
    $group = null;
    foreach ($groups as $g) {
        if ($g['id'] === $params['id']) {
            $group = $g;
            break;
        }
    }

    if (!$group) {
        Response::error('Группа не найдена', 404);
        return;
    }

    Response::json($group);
});

// Helper function to enrich lots with bid data
function enrichLots(array $lots): array {
    if (empty($lots)) {
        return [];
    }

    $db = Database::getInstance();
    $lotIds = array_column($lots, 'id');
    $placeholders = str_repeat('?,', count($lotIds) - 1) . '?';

    // Get bids
    $stmt = $db->prepare("SELECT lot_id, MAX(amount) as max_amount, COUNT(*) as count FROM bids WHERE lot_id IN ($placeholders) GROUP BY lot_id");
    $stmt->execute($lotIds);
    $bidData = [];
    while ($row = $stmt->fetch()) {
        $bidData[$row['lot_id']] = [
            'currentBid' => (int)$row['max_amount'],
            'extraBids' => (int)$row['count']
        ];
    }

    // Get sales
    $stmt = $db->prepare("SELECT lot_id FROM lot_sales WHERE lot_id IN ($placeholders)");
    $stmt->execute($lotIds);
    $soldLots = [];
    while ($row = $stmt->fetch()) {
        $soldLots[] = $row['lot_id'];
    }

    return array_map(function($lot) use ($bidData, $soldLots) {
        $enriched = $lot;
        if (isset($bidData[$lot['id']])) {
            $enriched['currentBid'] = $bidData[$lot['id']]['currentBid'];
            $enriched['bidsCount'] += $bidData[$lot['id']]['extraBids'];
        } else {
            $enriched['currentBid'] = null;
        }
        if (in_array($lot['id'], $soldLots)) {
            $enriched['status'] = 'sold';
        }
        return $enriched;
    }, $lots);
}

$router->get('/lots', function() {
    $section = Request::query('section');
    $themeId = Request::query('themeId');
    $groupId = Request::query('groupId');

    $lots = CatalogData::getLots();

    if ($section) {
        $lots = array_filter($lots, fn($l) => $l['sectionType'] === $section);
    }
    if ($themeId) {
        $lots = array_filter($lots, fn($l) => $l['themeId'] === $themeId);
    }
    if ($groupId) {
        $lots = array_filter($lots, fn($l) => $l['groupId'] === $groupId);
    }

    $lots = array_values($lots);
    $enriched = enrichLots($lots);

    Response::json($enriched);
});

$router->get('/lots/:id', function($params) {
    $lots = CatalogData::getLots();
    $lot = null;
    foreach ($lots as $l) {
        if ($l['id'] === $params['id']) {
            $lot = $l;
            break;
        }
    }

    if (!$lot) {
        Response::error('Лот не найден', 404);
        return;
    }

    $enriched = enrichLots([$lot]);
    Response::json($enriched[0]);
});

$router->get('/lots/:id/bids', function($params) {
    $lots = CatalogData::getLots();
    $lotExists = false;
    foreach ($lots as $l) {
        if ($l['id'] === $params['id']) {
            $lotExists = true;
            break;
        }
    }

    if (!$lotExists) {
        Response::error('Лот не найден', 404);
        return;
    }

    $db = Database::getInstance();
    $stmt = $db->prepare("
        SELECT b.id, b.amount, b.created_at, b.user_id, u.login as user_login
        FROM bids b
        JOIN users u ON b.user_id = u.id
        WHERE b.lot_id = ?
        ORDER BY b.amount DESC, b.created_at DESC
    ");
    $stmt->execute([$params['id']]);

    $bids = [];
    while ($row = $stmt->fetch()) {
        $login = $row['user_login'];
        $masked = strlen($login) > 2
            ? $login[0] . '***' . $login[strlen($login) - 1]
            : $login[0] . '***';

        $bids[] = [
            'id' => (int)$row['id'],
            'amount' => (int)$row['amount'],
            'createdAt' => $row['created_at'],
            'userId' => (int)$row['user_id'],
            'userLabel' => $masked
        ];
    }

    Response::json($bids);
});

$router->post('/lots/:id/bid', function($params) {
    $user = Auth::requireAuth();

    $lots = CatalogData::getLots();
    $lot = null;
    foreach ($lots as $l) {
        if ($l['id'] === $params['id']) {
            $lot = $l;
            break;
        }
    }

    if (!$lot) {
        Response::error('Лот не найден', 404);
        return;
    }

    if ($lot['format'] !== 'auction') {
        Response::error('Лот не является аукционным', 400);
        return;
    }

    if ($user['role'] === 'collector') {
        Response::error('Коллекционеры не могут делать ставки в этом разделе', 403);
        return;
    }

    $body = Request::getJson();
    $amount = (int)($body['amount'] ?? 0);

    if ($amount <= 0) {
        Response::error('Некорректная сумма ставки', 400);
        return;
    }

    $db = Database::getInstance();

    // Transaction with lock
    $db->beginTransaction();

    try {
        // Check if sold
        $stmt = $db->prepare("SELECT id FROM lot_sales WHERE lot_id = ? LIMIT 1");
        $stmt->execute([$lot['id']]);
        if ($stmt->fetch()) {
            $db->rollBack();
            Response::error('Лот уже продан', 409);
            return;
        }

        // Get current max bid
        $stmt = $db->prepare("SELECT MAX(amount) as max_amount, COUNT(*) as cnt FROM bids WHERE lot_id = ?");
        $stmt->execute([$lot['id']]);
        $agg = $stmt->fetch();
        $currentBid = $agg['max_amount'] ? (int)$agg['max_amount'] : null;
        $extraBids = (int)$agg['cnt'];

        // Calculate min next bid
        $minNextBid = $currentBid !== null ? (int)ceil($currentBid * 1.05) : ($lot['bidMin'] ?? 1);

        if ($amount < $minNextBid) {
            $db->rollBack();
            Response::error("Минимальная ставка — {$minNextBid} ₽", 400);
            return;
        }

        // Check blitz price
        $isBlitz = isset($lot['bidMax']) && $amount >= $lot['bidMax'];
        $effectiveAmount = $isBlitz ? $lot['bidMax'] : $amount;

        // Insert bid
        $stmt = $db->prepare("INSERT INTO bids (lot_id, user_id, amount) VALUES (?, ?, ?)");
        $stmt->execute([$lot['id'], $user['sub'], $effectiveAmount]);
        $bidId = $db->lastInsertId();

        // If blitz, mark as sold
        if ($isBlitz) {
            $stmt = $db->prepare("INSERT INTO lot_sales (lot_id, buyer_id, final_price, sold_via) VALUES (?, ?, ?, 'blitz')");
            $stmt->execute([$lot['id'], $user['sub'], $lot['bidMax']]);
        }

        $db->commit();

        // Fetch created bid
        $stmt = $db->prepare("SELECT * FROM bids WHERE id = ?");
        $stmt->execute([$bidId]);
        $bid = $stmt->fetch();

        $enrichedLot = $lot;
        $enrichedLot['currentBid'] = $effectiveAmount;
        $enrichedLot['bidsCount'] = $lot['bidsCount'] + $extraBids + 1;
        if ($isBlitz) {
            $enrichedLot['status'] = 'sold';
        }

        Response::json([
            'bid' => [
                'id' => (int)$bid['id'],
                'lotId' => $bid['lot_id'],
                'userId' => (int)$bid['user_id'],
                'amount' => (int)$bid['amount'],
                'createdAt' => $bid['created_at']
            ],
            'leader' => [
                'userId' => $user['sub'],
                'amount' => $effectiveAmount
            ],
            'sold' => $isBlitz,
            'lot' => $enrichedLot
        ], 201);

    } catch (Exception $e) {
        $db->rollBack();
        error_log('Bid error: ' . $e->getMessage());
        Response::error('Ошибка сервера', 500);
    }
});

$router->get('/catalog/news', function() {
    Response::json(CatalogData::getNews());
});

$router->get('/activity', function() {
    Response::json(CatalogData::getActivities());
});

// ─── Cart ─────────────────────────────────────────────────────────────────────

$router->get('/cart', function() {
    $user = Auth::requireAuth();
    $db = Database::getInstance();

    $stmt = $db->prepare("SELECT * FROM cart_items WHERE user_id = ? ORDER BY added_at");
    $stmt->execute([$user['sub']]);

    $items = [];
    $lots = CatalogData::getLots();
    $lotsById = [];
    foreach ($lots as $l) {
        $lotsById[$l['id']] = $l;
    }

    while ($row = $stmt->fetch()) {
        if (isset($lotsById[$row['lot_id']])) {
            $items[] = [
                'id' => (int)$row['id'],
                'userId' => (int)$row['user_id'],
                'lotId' => $row['lot_id'],
                'addedAt' => $row['added_at'],
                'lot' => $lotsById[$row['lot_id']]
            ];
        }
    }

    Response::json($items);
});

$router->post('/cart', function() {
    $user = Auth::requireAuth();
    $body = Request::getJson();
    $lotId = $body['lotId'] ?? '';

    if (!$lotId) {
        Response::error('lotId required', 400);
        return;
    }

    $lots = CatalogData::getLots();
    $lot = null;
    foreach ($lots as $l) {
        if ($l['id'] === $lotId) {
            $lot = $l;
            break;
        }
    }

    if (!$lot) {
        Response::error('Лот не найден', 404);
        return;
    }

    $db = Database::getInstance();

    // Check if already in cart
    $stmt = $db->prepare("SELECT id FROM cart_items WHERE user_id = ? AND lot_id = ? LIMIT 1");
    $stmt->execute([$user['sub'], $lotId]);
    if ($stmt->fetch()) {
        Response::error('Лот уже в корзине', 409);
        return;
    }

    // Add to cart
    $stmt = $db->prepare("INSERT INTO cart_items (user_id, lot_id) VALUES (?, ?)");
    $stmt->execute([$user['sub'], $lotId]);
    $itemId = $db->lastInsertId();

    $stmt = $db->prepare("SELECT * FROM cart_items WHERE id = ?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();

    Response::json([
        'id' => (int)$item['id'],
        'userId' => (int)$item['user_id'],
        'lotId' => $item['lot_id'],
        'addedAt' => $item['added_at'],
        'lot' => $lot
    ], 201);
});

$router->delete('/cart/:lotId', function($params) {
    $user = Auth::requireAuth();
    $db = Database::getInstance();

    $stmt = $db->prepare("DELETE FROM cart_items WHERE user_id = ? AND lot_id = ?");
    $stmt->execute([$user['sub'], $params['lotId']]);

    Response::noContent();
});

$router->delete('/cart', function() {
    $user = Auth::requireAuth();
    $db = Database::getInstance();

    $stmt = $db->prepare("DELETE FROM cart_items WHERE user_id = ?");
    $stmt->execute([$user['sub']]);

    Response::noContent();
});

// ─── Stickers ─────────────────────────────────────────────────────────────────

$router->get('/stickers', function() {
    $db = Database::getInstance();

    $stmt = $db->query("
        SELECT s.*, u.login as user_login
        FROM stickers s
        JOIN users u ON s.user_id = u.id
        ORDER BY s.created_at DESC
    ");

    $stickers = [];
    while ($row = $stmt->fetch()) {
        $stickers[] = [
            'id' => (int)$row['id'],
            'userId' => (int)$row['user_id'],
            'userLogin' => $row['user_login'],
            'text' => $row['text'],
            'budget' => (int)$row['budget'],
            'imageUrl' => $row['image_url'],
            'createdAt' => $row['created_at']
        ];
    }

    Response::json($stickers);
});

$router->post('/stickers', function() {
    $user = Auth::requireAuth();
    $body = Request::getJson();

    $text = trim($body['text'] ?? '');
    $budget = (int)($body['budget'] ?? 0);
    $imageUrl = trim($body['imageUrl'] ?? '');

    if (!$text || $budget <= 0 || !$imageUrl) {
        Response::error('Заполните все поля', 400);
        return;
    }

    $db = Database::getInstance();
    $stmt = $db->prepare("INSERT INTO stickers (user_id, text, budget, image_url) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user['sub'], $text, $budget, $imageUrl]);
    $stickerId = $db->lastInsertId();

    $stmt = $db->prepare("
        SELECT s.*, u.login as user_login
        FROM stickers s
        JOIN users u ON s.user_id = u.id
        WHERE s.id = ?
    ");
    $stmt->execute([$stickerId]);
    $sticker = $stmt->fetch();

    Response::json([
        'id' => (int)$sticker['id'],
        'userId' => (int)$sticker['user_id'],
        'userLogin' => $sticker['user_login'],
        'text' => $sticker['text'],
        'budget' => (int)$sticker['budget'],
        'imageUrl' => $sticker['image_url'],
        'createdAt' => $sticker['created_at']
    ], 201);
});

$router->delete('/stickers/:id', function($params) {
    $user = Auth::requireAuth();
    $db = Database::getInstance();

    // Check ownership or admin
    $stmt = $db->prepare("SELECT user_id FROM stickers WHERE id = ?");
    $stmt->execute([$params['id']]);
    $sticker = $stmt->fetch();

    if (!$sticker) {
        Response::error('Стикер не найден', 404);
        return;
    }

    if ($sticker['user_id'] != $user['sub'] && $user['role'] !== 'admin') {
        Response::error('Недостаточно прав', 403);
        return;
    }

    $stmt = $db->prepare("DELETE FROM stickers WHERE id = ?");
    $stmt->execute([$params['id']]);

    Response::noContent();
});

// ─── Invites ──────────────────────────────────────────────────────────────────

$router->get('/invites', function() {
    Auth::requireAdmin();
    $db = Database::getInstance();

    $stmt = $db->query("
        SELECT
            i.*,
            creator.login as creator_login,
            used_by.login as used_by_login
        FROM invite_tokens i
        LEFT JOIN users creator ON i.created_by_id = creator.id
        LEFT JOIN users used_by ON i.used_by_id = used_by.id
        ORDER BY i.created_at DESC
    ");

    $invites = [];
    while ($row = $stmt->fetch()) {
        $invites[] = [
            'id' => (int)$row['id'],
            'token' => $row['token'],
            'role' => $row['role'],
            'label' => $row['label'],
            'used' => (bool)$row['used'],
            'usedById' => $row['used_by_id'] ? (int)$row['used_by_id'] : null,
            'usedByLogin' => $row['used_by_login'],
            'usedAt' => $row['used_at'],
            'createdById' => $row['created_by_id'] ? (int)$row['created_by_id'] : null,
            'creatorLogin' => $row['creator_login'],
            'expiresAt' => $row['expires_at'],
            'createdAt' => $row['created_at']
        ];
    }

    Response::json($invites);
});

$router->post('/invites', function() {
    $adminUser = Auth::requireAdmin();
    $body = Request::getJson();

    $role = $body['role'] ?? '';
    $label = trim($body['label'] ?? '');

    if (!in_array($role, ['dealer', 'collector']) || !$label) {
        Response::error('Некорректные данные', 400);
        return;
    }

    $token = bin2hex(random_bytes(16));

    $db = Database::getInstance();
    $stmt = $db->prepare("INSERT INTO invite_tokens (token, role, label, created_by_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$token, $role, $label, $adminUser['sub']]);
    $inviteId = $db->lastInsertId();

    $stmt = $db->prepare("
        SELECT
            i.*,
            creator.login as creator_login
        FROM invite_tokens i
        LEFT JOIN users creator ON i.created_by_id = creator.id
        WHERE i.id = ?
    ");
    $stmt->execute([$inviteId]);
    $invite = $stmt->fetch();

    Response::json([
        'id' => (int)$invite['id'],
        'token' => $invite['token'],
        'role' => $invite['role'],
        'label' => $invite['label'],
        'used' => (bool)$invite['used'],
        'usedById' => null,
        'usedByLogin' => null,
        'usedAt' => null,
        'createdById' => (int)$invite['created_by_id'],
        'creatorLogin' => $invite['creator_login'],
        'expiresAt' => $invite['expires_at'],
        'createdAt' => $invite['created_at']
    ], 201);
});

$router->delete('/invites/:id', function($params) {
    Auth::requireAdmin();
    $db = Database::getInstance();

    $stmt = $db->prepare("DELETE FROM invite_tokens WHERE id = ?");
    $stmt->execute([$params['id']]);

    Response::noContent();
});

// ─── Admin Users ──────────────────────────────────────────────────────────────

$router->get('/admin/users', function() {
    Auth::requireAdmin();
    $db = Database::getInstance();

    $stmt = $db->query("SELECT id, login, email, role, created_at, updated_at FROM users ORDER BY created_at DESC");

    $users = [];
    while ($row = $stmt->fetch()) {
        $users[] = [
            'id' => (int)$row['id'],
            'login' => $row['login'],
            'email' => $row['email'],
            'role' => $row['role'],
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at']
        ];
    }

    Response::json($users);
});

$router->patch('/admin/users/:id', function($params) {
    Auth::requireAdmin();
    $body = Request::getJson();
    $role = $body['role'] ?? '';

    if (!in_array($role, ['admin', 'dealer', 'collector'])) {
        Response::error('Некорректная роль', 400);
        return;
    }

    $db = Database::getInstance();
    $stmt = $db->prepare("UPDATE users SET role = ?, updated_at = datetime('now') WHERE id = ?");
    $stmt->execute([$role, $params['id']]);

    $stmt = $db->prepare("SELECT id, login, email, role, created_at, updated_at FROM users WHERE id = ?");
    $stmt->execute([$params['id']]);
    $user = $stmt->fetch();

    if (!$user) {
        Response::error('Пользователь не найден', 404);
        return;
    }

    Response::json([
        'id' => (int)$user['id'],
        'login' => $user['login'],
        'email' => $user['email'],
        'role' => $user['role'],
        'createdAt' => $user['created_at'],
        'updatedAt' => $user['updated_at']
    ]);
});

$router->delete('/admin/users/:id', function($params) {
    $adminUser = Auth::requireAdmin();

    // Prevent self-deletion
    if ($adminUser['sub'] == $params['id']) {
        Response::error('Нельзя удалить свой аккаунт', 400);
        return;
    }

    $db = Database::getInstance();
    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$params['id']]);

    Response::noContent();
});

// ─── Forum ────────────────────────────────────────────────────────────────────

$router->get('/forum/categories', function() {
    $user = Auth::optionalAuth();
    $categories = ForumAPI::getCategories();

    // Filter by access
    $accessible = array_filter($categories, fn($cat) => ForumAPI::canAccessCategory($cat, $user));

    Response::json(array_values($accessible));
});

$router->get('/forum/categories/:id', function($params) {
    $user = Auth::optionalAuth();
    $category = ForumAPI::getCategoryById($params['id']);

    if (!$category) {
        Response::error('Категория не найдена', 404);
        return;
    }

    if (!ForumAPI::canAccessCategory($category, $user)) {
        Response::error('Доступ запрещён', 403);
        return;
    }

    Response::json($category);
});

$router->get('/forum/categories/:id/threads', function($params) {
    $user = Auth::optionalAuth();
    $category = ForumAPI::getCategoryById($params['id']);

    if (!$category) {
        Response::error('Категория не найдена', 404);
        return;
    }

    if (!ForumAPI::canAccessCategory($category, $user)) {
        Response::error('Доступ запрещён', 403);
        return;
    }

    $db = Database::getInstance();
    $stmt = $db->prepare("
        SELECT * FROM forum_threads
        WHERE category_id = ?
        ORDER BY is_pinned DESC, updated_at DESC
    ");
    $stmt->execute([$params['id']]);

    $threads = [];
    while ($row = $stmt->fetch()) {
        $threads[] = $row;
    }

    $enriched = ForumAPI::enrichThreads($threads, $user ? $user['sub'] : null);
    Response::json($enriched);
});

$router->post('/forum/categories/:id/threads', function($params) {
    $user = Auth::requireAuth();
    $category = ForumAPI::getCategoryById($params['id']);

    if (!$category) {
        Response::error('Категория не найдена', 404);
        return;
    }

    if (!ForumAPI::canPostInCategory($category, $user)) {
        Response::error('Нет прав для создания темы', 403);
        return;
    }

    $body = Request::getJson();
    $title = trim($body['title'] ?? '');
    $bodyText = trim($body['body'] ?? '');

    if (!$title || !$bodyText) {
        Response::error('Заполните заголовок и текст', 400);
        return;
    }

    $db = Database::getInstance();
    $db->beginTransaction();

    try {
        // Create thread
        $stmt = $db->prepare("
            INSERT INTO forum_threads (category_id, title, author_id)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$params['id'], $title, $user['sub']]);
        $threadId = $db->lastInsertId();

        // Create OP post
        $stmt = $db->prepare("
            INSERT INTO forum_posts (thread_id, author_id, body, is_op)
            VALUES (?, ?, ?, 1)
        ");
        $stmt->execute([$threadId, $user['sub'], $bodyText]);

        $db->commit();

        // Fetch created thread
        $stmt = $db->prepare("SELECT * FROM forum_threads WHERE id = ?");
        $stmt->execute([$threadId]);
        $thread = $stmt->fetch();

        $enriched = ForumAPI::enrichThreads([$thread], $user['sub']);
        Response::json($enriched[0], 201);

    } catch (Exception $e) {
        $db->rollBack();
        error_log('Forum thread creation error: ' . $e->getMessage());
        Response::error('Ошибка сервера', 500);
    }
});

$router->get('/forum/threads/:id', function($params) {
    $user = Auth::optionalAuth();
    $db = Database::getInstance();

    $stmt = $db->prepare("SELECT * FROM forum_threads WHERE id = ?");
    $stmt->execute([$params['id']]);
    $thread = $stmt->fetch();

    if (!$thread) {
        Response::error('Тема не найдена', 404);
        return;
    }

    $category = ForumAPI::getCategoryById($thread['category_id']);
    if (!$category || !ForumAPI::canAccessCategory($category, $user)) {
        Response::error('Доступ запрещён', 403);
        return;
    }

    // Increment views
    $stmt = $db->prepare("UPDATE forum_threads SET views = views + 1 WHERE id = ?");
    $stmt->execute([$params['id']]);

    $enriched = ForumAPI::enrichThreads([$thread], $user ? $user['sub'] : null);
    Response::json($enriched[0]);
});

$router->get('/forum/threads/:id/posts', function($params) {
    $user = Auth::optionalAuth();
    $db = Database::getInstance();

    $stmt = $db->prepare("SELECT * FROM forum_threads WHERE id = ?");
    $stmt->execute([$params['id']]);
    $thread = $stmt->fetch();

    if (!$thread) {
        Response::error('Тема не найдена', 404);
        return;
    }

    $category = ForumAPI::getCategoryById($thread['category_id']);
    if (!$category || !ForumAPI::canAccessCategory($category, $user)) {
        Response::error('Доступ запрещён', 403);
        return;
    }

    // Get posts
    $stmt = $db->prepare("SELECT * FROM forum_posts WHERE thread_id = ? ORDER BY created_at ASC");
    $stmt->execute([$params['id']]);

    $posts = [];
    while ($row = $stmt->fetch()) {
        $posts[] = $row;
    }

    // Update seen count
    if ($user) {
        $stmt = $db->prepare("
            INSERT INTO thread_seen (thread_id, user_id, post_count, updated_at)
            VALUES (?, ?, ?, datetime('now'))
            ON CONFLICT(thread_id, user_id)
            DO UPDATE SET post_count = ?, updated_at = datetime('now')
        ");
        $postCount = count($posts);
        $stmt->execute([$params['id'], $user['sub'], $postCount, $postCount]);
    }

    $enriched = ForumAPI::enrichPosts($posts, $user ? $user['sub'] : null);
    Response::json($enriched);
});

$router->post('/forum/threads/:id/posts', function($params) {
    $user = Auth::requireAuth();
    $db = Database::getInstance();

    $stmt = $db->prepare("SELECT * FROM forum_threads WHERE id = ?");
    $stmt->execute([$params['id']]);
    $thread = $stmt->fetch();

    if (!$thread) {
        Response::error('Тема не найдена', 404);
        return;
    }

    if ($thread['is_locked'] && $user['role'] !== 'admin') {
        Response::error('Тема закрыта для обсуждения', 403);
        return;
    }

    $category = ForumAPI::getCategoryById($thread['category_id']);
    if (!$category || !ForumAPI::canPostInCategory($category, $user)) {
        Response::error('Нет прав для ответа', 403);
        return;
    }

    $body = Request::getJson();
    $bodyText = trim($body['body'] ?? '');
    $quotedPostId = $body['quotedPostId'] ?? null;

    if (!$bodyText) {
        Response::error('Текст сообщения не может быть пустым', 400);
        return;
    }

    // Create post
    $stmt = $db->prepare("
        INSERT INTO forum_posts (thread_id, author_id, body, quoted_post_id)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$params['id'], $user['sub'], $bodyText, $quotedPostId]);
    $postId = $db->lastInsertId();

    // Update thread timestamp
    $stmt = $db->prepare("UPDATE forum_threads SET updated_at = datetime('now') WHERE id = ?");
    $stmt->execute([$params['id']]);

    // Fetch created post
    $stmt = $db->prepare("SELECT * FROM forum_posts WHERE id = ?");
    $stmt->execute([$postId]);
    $post = $stmt->fetch();

    $enriched = ForumAPI::enrichPosts([$post], $user['sub']);
    Response::json($enriched[0], 201);
});

$router->patch('/forum/posts/:id', function($params) {
    $user = Auth::requireAuth();
    $db = Database::getInstance();

    $stmt = $db->prepare("SELECT * FROM forum_posts WHERE id = ?");
    $stmt->execute([$params['id']]);
    $post = $stmt->fetch();

    if (!$post) {
        Response::error('Сообщение не найдено', 404);
        return;
    }

    // Check ownership or admin
    if ($post['author_id'] != $user['sub'] && $user['role'] !== 'admin') {
        Response::error('Недостаточно прав', 403);
        return;
    }

    $body = Request::getJson();
    $bodyText = trim($body['body'] ?? '');

    if (!$bodyText) {
        Response::error('Текст не может быть пустым', 400);
        return;
    }

    $stmt = $db->prepare("
        UPDATE forum_posts
        SET body = ?, edited_at = datetime('now')
        WHERE id = ?
    ");
    $stmt->execute([$bodyText, $params['id']]);

    $stmt = $db->prepare("SELECT * FROM forum_posts WHERE id = ?");
    $stmt->execute([$params['id']]);
    $updated = $stmt->fetch();

    $enriched = ForumAPI::enrichPosts([$updated], $user['sub']);
    Response::json($enriched[0]);
});

$router->delete('/forum/posts/:id', function($params) {
    $user = Auth::requireAuth();
    $db = Database::getInstance();

    $stmt = $db->prepare("SELECT * FROM forum_posts WHERE id = ?");
    $stmt->execute([$params['id']]);
    $post = $stmt->fetch();

    if (!$post) {
        Response::error('Сообщение не найдено', 404);
        return;
    }

    // Check ownership or admin
    if ($post['author_id'] != $user['sub'] && $user['role'] !== 'admin') {
        Response::error('Недостаточно прав', 403);
        return;
    }

    // Can't delete OP
    if ($post['is_op']) {
        Response::error('Нельзя удалить первое сообщение темы', 400);
        return;
    }

    $stmt = $db->prepare("DELETE FROM forum_posts WHERE id = ?");
    $stmt->execute([$params['id']]);

    Response::noContent();
});

$router->post('/forum/posts/:id/like', function($params) {
    $user = Auth::requireAuth();
    $db = Database::getInstance();

    $stmt = $db->prepare("SELECT id FROM forum_posts WHERE id = ?");
    $stmt->execute([$params['id']]);
    if (!$stmt->fetch()) {
        Response::error('Сообщение не найдено', 404);
        return;
    }

    // Toggle like
    $stmt = $db->prepare("SELECT 1 FROM post_likes WHERE post_id = ? AND user_id = ?");
    $stmt->execute([$params['id'], $user['sub']]);

    if ($stmt->fetch()) {
        // Unlike
        $stmt = $db->prepare("DELETE FROM post_likes WHERE post_id = ? AND user_id = ?");
        $stmt->execute([$params['id'], $user['sub']]);
    } else {
        // Like
        $stmt = $db->prepare("INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)");
        $stmt->execute([$params['id'], $user['sub']]);
    }

    Response::noContent();
});

$router->post('/forum/threads/:id/bookmark', function($params) {
    $user = Auth::requireAuth();
    $db = Database::getInstance();

    $stmt = $db->prepare("SELECT id FROM forum_threads WHERE id = ?");
    $stmt->execute([$params['id']]);
    if (!$stmt->fetch()) {
        Response::error('Тема не найдена', 404);
        return;
    }

    // Toggle bookmark
    $stmt = $db->prepare("SELECT 1 FROM thread_bookmarks WHERE thread_id = ? AND user_id = ?");
    $stmt->execute([$params['id'], $user['sub']]);

    if ($stmt->fetch()) {
        // Remove bookmark
        $stmt = $db->prepare("DELETE FROM thread_bookmarks WHERE thread_id = ? AND user_id = ?");
        $stmt->execute([$params['id'], $user['sub']]);
    } else {
        // Add bookmark
        $stmt = $db->prepare("INSERT INTO thread_bookmarks (thread_id, user_id) VALUES (?, ?)");
        $stmt->execute([$params['id'], $user['sub']]);
    }

    Response::noContent();
});

$router->patch('/forum/threads/:id', function($params) {
    $user = Auth::requireAdmin();
    $body = Request::getJson();
    $db = Database::getInstance();

    $updates = [];
    $values = [];

    if (isset($body['isPinned'])) {
        $updates[] = 'is_pinned = ?';
        $values[] = $body['isPinned'] ? 1 : 0;
    }

    if (isset($body['isLocked'])) {
        $updates[] = 'is_locked = ?';
        $values[] = $body['isLocked'] ? 1 : 0;
    }

    if (empty($updates)) {
        Response::error('Нечего обновлять', 400);
        return;
    }

    $values[] = $params['id'];
    $sql = "UPDATE forum_threads SET " . implode(', ', $updates) . " WHERE id = ?";

    $stmt = $db->prepare($sql);
    $stmt->execute($values);

    $stmt = $db->prepare("SELECT * FROM forum_threads WHERE id = ?");
    $stmt->execute([$params['id']]);
    $thread = $stmt->fetch();

    if (!$thread) {
        Response::error('Тема не найдена', 404);
        return;
    }

    $enriched = ForumAPI::enrichThreads([$thread], $user['sub']);
    Response::json($enriched[0]);
});

$router->delete('/forum/threads/:id', function($params) {
    Auth::requireAdmin();
    $db = Database::getInstance();

    $stmt = $db->prepare("DELETE FROM forum_threads WHERE id = ?");
    $stmt->execute([$params['id']]);

    Response::noContent();
});

// Run router
$router->run();

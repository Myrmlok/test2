<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/router.php';

class ForumAPI {
    private static $categories = [
        ['id' => 'c-general', 'title' => 'Общий чат', 'description' => 'Знакомства, вопросы о клубе, общение участников', 'icon' => 'message-square', 'accessRoles' => [], 'isReadOnly' => false],
        ['id' => 'c-expertise', 'title' => 'Экспертиза и атрибуция', 'description' => 'Определение монет, помощь с атрибуцией, экспертные оценки', 'icon' => 'scan-search', 'accessRoles' => [], 'isReadOnly' => false],
        ['id' => 'c-deals', 'title' => 'Сделки и переговоры', 'description' => 'Обсуждение сделок, поиск партнёров. Только для дилеров', 'icon' => 'scale', 'accessRoles' => ['dealer', 'admin'], 'isReadOnly' => false],
        ['id' => 'c-numizmatika', 'title' => 'Нумизматика', 'description' => 'История монет, редкости, литература, каталоги и исследования', 'icon' => 'book-open', 'accessRoles' => [], 'isReadOnly' => false],
        ['id' => 'c-tech', 'title' => 'Хранение и реставрация', 'description' => 'Чистка, консервация, капсулы, сейфы, советы по хранению', 'icon' => 'shield', 'accessRoles' => [], 'isReadOnly' => false],
        ['id' => 'c-announce', 'title' => 'Объявления', 'description' => 'Официальные объявления администрации клуба', 'icon' => 'bell', 'accessRoles' => [], 'isReadOnly' => true],
    ];

    public static function getCategories(): array {
        return self::$categories;
    }

    public static function getCategoryById(string $id): ?array {
        foreach (self::$categories as $cat) {
            if ($cat['id'] === $id) {
                return $cat;
            }
        }
        return null;
    }

    public static function canAccessCategory(array $category, ?array $user): bool {
        if (empty($category['accessRoles'])) {
            return true;
        }
        if (!$user) {
            return false;
        }
        return in_array($user['role'], $category['accessRoles']);
    }

    public static function canPostInCategory(array $category, ?array $user): bool {
        if ($category['isReadOnly'] && (!$user || $user['role'] !== 'admin')) {
            return false;
        }
        return self::canAccessCategory($category, $user);
    }

    public static function enrichThreads(array $threads, ?int $userId): array {
        if (empty($threads)) {
            return [];
        }

        $db = Database::getInstance();
        $threadIds = array_column($threads, 'id');
        $placeholders = str_repeat('?,', count($threadIds) - 1) . '?';

        // Get authors
        $authorIds = array_unique(array_column($threads, 'author_id'));
        $authPlaceholders = str_repeat('?,', count($authorIds) - 1) . '?';
        $stmt = $db->prepare("SELECT id, login, role FROM users WHERE id IN ($authPlaceholders)");
        $stmt->execute($authorIds);
        $authorMap = [];
        while ($row = $stmt->fetch()) {
            $authorMap[$row['id']] = $row;
        }

        // Post counts
        $stmt = $db->prepare("SELECT thread_id, COUNT(*) as count FROM forum_posts WHERE thread_id IN ($placeholders) GROUP BY thread_id");
        $stmt->execute($threadIds);
        $postCountMap = [];
        while ($row = $stmt->fetch()) {
            $postCountMap[$row['thread_id']] = (int)$row['count'];
        }

        // Last posts
        $stmt = $db->prepare("
            SELECT fp.thread_id, fp.author_id, fp.created_at, u.login, u.role
            FROM forum_posts fp
            JOIN users u ON fp.author_id = u.id
            WHERE fp.thread_id IN ($placeholders) AND fp.is_op = 0
            ORDER BY fp.created_at DESC
        ");
        $stmt->execute($threadIds);
        $lastPostMap = [];
        while ($row = $stmt->fetch()) {
            if (!isset($lastPostMap[$row['thread_id']])) {
                $lastPostMap[$row['thread_id']] = [
                    'authorLogin' => $row['login'],
                    'authorRole' => $row['role'],
                    'createdAt' => $row['created_at']
                ];
            }
        }

        // Bookmarks
        $bookmarked = [];
        if ($userId) {
            $stmt = $db->prepare("SELECT thread_id FROM thread_bookmarks WHERE thread_id IN ($placeholders) AND user_id = ?");
            $stmt->execute(array_merge($threadIds, [$userId]));
            while ($row = $stmt->fetch()) {
                $bookmarked[] = $row['thread_id'];
            }
        }

        // Seen
        $seenMap = [];
        if ($userId) {
            $stmt = $db->prepare("SELECT thread_id, post_count FROM thread_seen WHERE thread_id IN ($placeholders) AND user_id = ?");
            $stmt->execute(array_merge($threadIds, [$userId]));
            while ($row = $stmt->fetch()) {
                $seenMap[$row['thread_id']] = (int)$row['post_count'];
            }
        }

        return array_map(function($t) use ($authorMap, $postCountMap, $lastPostMap, $bookmarked, $seenMap) {
            $author = $authorMap[$t['author_id']] ?? null;
            $totalPosts = $postCountMap[$t['id']] ?? 0;
            $replyCount = max(0, $totalPosts - 1);
            $lastPost = $lastPostMap[$t['id']] ?? null;
            $seenCount = $seenMap[$t['id']] ?? 0;

            return [
                'id' => (int)$t['id'],
                'categoryId' => $t['category_id'],
                'title' => $t['title'],
                'authorId' => (int)$t['author_id'],
                'authorLogin' => $author ? $author['login'] : null,
                'authorRole' => $author ? $author['role'] : null,
                'isPinned' => (bool)$t['is_pinned'],
                'isLocked' => (bool)$t['is_locked'],
                'views' => (int)$t['views'],
                'replyCount' => $replyCount,
                'lastPost' => $lastPost,
                'isBookmarked' => in_array($t['id'], $bookmarked),
                'hasUnread' => $totalPosts > $seenCount,
                'createdAt' => $t['created_at'],
                'updatedAt' => $t['updated_at']
            ];
        }, $threads);
    }

    public static function enrichPosts(array $posts, ?int $userId): array {
        if (empty($posts)) {
            return [];
        }

        $db = Database::getInstance();
        $postIds = array_column($posts, 'id');
        $placeholders = str_repeat('?,', count($postIds) - 1) . '?';

        // Get authors
        $authorIds = array_unique(array_column($posts, 'author_id'));
        $authPlaceholders = str_repeat('?,', count($authorIds) - 1) . '?';
        $stmt = $db->prepare("SELECT id, login, role FROM users WHERE id IN ($authPlaceholders)");
        $stmt->execute($authorIds);
        $authorMap = [];
        while ($row = $stmt->fetch()) {
            $authorMap[$row['id']] = $row;
        }

        // Like counts
        $stmt = $db->prepare("SELECT post_id, COUNT(*) as count FROM post_likes WHERE post_id IN ($placeholders) GROUP BY post_id");
        $stmt->execute($postIds);
        $likeCountMap = [];
        while ($row = $stmt->fetch()) {
            $likeCountMap[$row['post_id']] = (int)$row['count'];
        }

        // User likes
        $userLikes = [];
        if ($userId) {
            $stmt = $db->prepare("SELECT post_id FROM post_likes WHERE post_id IN ($placeholders) AND user_id = ?");
            $stmt->execute(array_merge($postIds, [$userId]));
            while ($row = $stmt->fetch()) {
                $userLikes[] = $row['post_id'];
            }
        }

        // Quoted posts
        $quotedIds = array_filter(array_column($posts, 'quoted_post_id'));
        $quotedMap = [];
        if (!empty($quotedIds)) {
            $quotedPlaceholders = str_repeat('?,', count($quotedIds) - 1) . '?';
            $stmt = $db->prepare("
                SELECT fp.id, fp.body, fp.author_id, fp.created_at, u.login, u.role
                FROM forum_posts fp
                JOIN users u ON fp.author_id = u.id
                WHERE fp.id IN ($quotedPlaceholders)
            ");
            $stmt->execute($quotedIds);
            while ($row = $stmt->fetch()) {
                $quotedMap[$row['id']] = [
                    'id' => (int)$row['id'],
                    'body' => $row['body'],
                    'authorId' => (int)$row['author_id'],
                    'authorLogin' => $row['login'],
                    'authorRole' => $row['role'],
                    'createdAt' => $row['created_at']
                ];
            }
        }

        return array_map(function($p) use ($authorMap, $likeCountMap, $userLikes, $quotedMap) {
            $author = $authorMap[$p['author_id']] ?? null;
            $quotedPost = $p['quoted_post_id'] ? ($quotedMap[$p['quoted_post_id']] ?? null) : null;

            return [
                'id' => (int)$p['id'],
                'threadId' => (int)$p['thread_id'],
                'authorId' => (int)$p['author_id'],
                'authorLogin' => $author ? $author['login'] : null,
                'authorRole' => $author ? $author['role'] : null,
                'body' => $p['body'],
                'isOp' => (bool)$p['is_op'],
                'quotedPost' => $quotedPost,
                'likesCount' => $likeCountMap[$p['id']] ?? 0,
                'isLikedByUser' => in_array($p['id'], $userLikes),
                'editedAt' => $p['edited_at'],
                'createdAt' => $p['created_at']
            ];
        }, $posts);
    }
}

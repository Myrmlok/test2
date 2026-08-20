<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';

class Seed {
    public static function seedIfEmpty(): void {
        $db = Database::getInstance();

        // Check if users exist
        $result = $db->query("SELECT COUNT(*) as count FROM users");
        $row = $result->fetch();

        if ($row['count'] > 0) {
            return; // Already seeded
        }

        // Create demo users
        $db->exec("
            INSERT INTO users (login, email, password_hash, role) VALUES
            ('admin', 'admin@4bor.club', '" . Auth::hashPassword('admin123') . "', 'admin'),
            ('dealer_ivanov', 'dealer1@example.com', '" . Auth::hashPassword('123') . "', 'dealer'),
            ('collector_petrov', 'collector1@example.com', '" . Auth::hashPassword('123') . "', 'collector'),
            ('dealer_sidorov', 'dealer2@example.com', '" . Auth::hashPassword('123') . "', 'dealer'),
            ('collector_kuznetsov', 'collector2@example.com', '" . Auth::hashPassword('123') . "', 'collector');
        ");

        // Create invite tokens
        $db->exec("
            INSERT INTO invite_tokens (token, role, label, created_by_id) VALUES
            ('dealer-demo-2024', 'dealer', 'Демо-инвайт для дилеров', 1),
            ('collector-demo-2024', 'collector', 'Демо-инвайт для коллекционеров', 1);
        ");

        // Create demo forum threads
        $db->exec("
            INSERT INTO forum_threads (category_id, title, author_id, is_pinned, views) VALUES
            ('c-general', 'Добро пожаловать в клуб!', 1, 1, 150),
            ('c-expertise', 'Помогите определить монету', 2, 0, 45),
            ('c-deals', 'Партнёр для совместной закупки', 2, 0, 20),
            ('c-numizmatika', 'Редкие варианты копеек Петра I', 3, 0, 78);
        ");

        // Create demo forum posts
        $db->exec("
            INSERT INTO forum_posts (thread_id, author_id, body, is_op) VALUES
            (1, 1, 'Рады приветствовать всех участников нашего клуба!', 1),
            (1, 2, 'Спасибо за приглашение!', 0),
            (2, 2, 'Нашел монету, похожа на средневековую, помогите определить', 1),
            (2, 3, 'Скиньте фото четче, сложно сказать', 0),
            (3, 2, 'Ищу партнёра для закупки лота на аукционе', 1),
            (4, 3, 'Интересная тема про варианты чеканки', 1);
        ");

        error_log('Database seeded with demo data');
    }
}

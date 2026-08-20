<?php
require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            try {
                self::$instance = new PDO('sqlite:' . DB_PATH);
                self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

                // Enable foreign keys
                self::$instance->exec('PRAGMA foreign_keys = ON');

                // Initialize schema if needed
                self::initSchema();
            } catch (PDOException $e) {
                error_log('Database connection failed: ' . $e->getMessage());
                throw $e;
            }
        }
        return self::$instance;
    }

    private static function initSchema(): void {
        $db = self::$instance;

        // Check if tables exist
        $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
        if ($result->fetch()) {
            return; // Schema already exists
        }

        // Create tables
        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                login TEXT NOT NULL UNIQUE,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'collector',
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT NOT NULL DEFAULT (datetime('now'))
            );

            CREATE TABLE IF NOT EXISTS invite_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                token TEXT NOT NULL UNIQUE,
                role TEXT NOT NULL,
                label TEXT NOT NULL,
                used INTEGER NOT NULL DEFAULT 0,
                used_by_id INTEGER,
                used_at TEXT,
                created_by_id INTEGER,
                expires_at TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (used_by_id) REFERENCES users(id),
                FOREIGN KEY (created_by_id) REFERENCES users(id)
            );

            CREATE TABLE IF NOT EXISTS forum_threads (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id TEXT NOT NULL,
                title TEXT NOT NULL,
                author_id INTEGER NOT NULL,
                is_pinned INTEGER NOT NULL DEFAULT 0,
                is_locked INTEGER NOT NULL DEFAULT 0,
                views INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (author_id) REFERENCES users(id)
            );

            CREATE TABLE IF NOT EXISTS forum_posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                thread_id INTEGER NOT NULL,
                author_id INTEGER NOT NULL,
                body TEXT NOT NULL,
                quoted_post_id INTEGER,
                is_op INTEGER NOT NULL DEFAULT 0,
                edited_at TEXT,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (thread_id) REFERENCES forum_threads(id) ON DELETE CASCADE,
                FOREIGN KEY (author_id) REFERENCES users(id)
            );

            CREATE TABLE IF NOT EXISTS post_likes (
                post_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                PRIMARY KEY (post_id, user_id),
                FOREIGN KEY (post_id) REFERENCES forum_posts(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS thread_bookmarks (
                thread_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                PRIMARY KEY (thread_id, user_id),
                FOREIGN KEY (thread_id) REFERENCES forum_threads(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS thread_seen (
                thread_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                post_count INTEGER NOT NULL DEFAULT 0,
                updated_at TEXT NOT NULL DEFAULT (datetime('now')),
                PRIMARY KEY (thread_id, user_id),
                FOREIGN KEY (thread_id) REFERENCES forum_threads(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS cart_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                lot_id TEXT NOT NULL,
                added_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS bids (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lot_id TEXT NOT NULL,
                user_id INTEGER NOT NULL,
                amount INTEGER NOT NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS lot_sales (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lot_id TEXT NOT NULL UNIQUE,
                buyer_id INTEGER NOT NULL,
                final_price INTEGER NOT NULL,
                sold_via TEXT NOT NULL,
                sold_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (buyer_id) REFERENCES users(id)
            );

            CREATE TABLE IF NOT EXISTS stickers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                text TEXT NOT NULL,
                budget INTEGER NOT NULL,
                image_url TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE INDEX idx_forum_threads_category ON forum_threads(category_id);
            CREATE INDEX idx_forum_posts_thread ON forum_posts(thread_id);
            CREATE INDEX idx_bids_lot ON bids(lot_id);
            CREATE INDEX idx_cart_user ON cart_items(user_id);
        ");
    }
}

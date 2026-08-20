<?php
// Catalog data (static, mirrors Node.js API)

class CatalogData {
    public static function getThemes(): array {
        return [
            ['id' => '1', 'slug' => 'medieval-coins', 'name' => 'Средневековые монеты', 'imageUrl' => '/images/theme-medieval.jpg'],
            ['id' => '2', 'slug' => 'udely', 'name' => 'Уделы', 'imageUrl' => '/images/theme-udels.jpg'],
            ['id' => '3', 'slug' => 'metaloplastika', 'name' => 'Металлопластика', 'imageUrl' => '/images/theme-metal.jpg'],
            ['id' => '4', 'slug' => 'russian-empire', 'name' => 'Российская Империя', 'imageUrl' => '/images/theme-empire.jpg'],
            ['id' => '5', 'slug' => 'vostok', 'name' => 'Восток', 'imageUrl' => '/images/theme-east.jpg'],
        ];
    }

    public static function getGroups(): array {
        $groups = [];
        foreach (self::getThemes() as $theme) {
            for ($i = 1; $i <= 4; $i++) {
                $groups[] = [
                    'id' => "{$theme['id']}-g{$i}",
                    'themeId' => $theme['id'],
                    'name' => "Группа 0{$i}"
                ];
            }
        }
        return $groups;
    }

    public static function getLots(): array {
        return [
            ['id' => 'l1', 'title' => 'Денга Ивана Грозного', 'description' => 'Отличное состояние, четкий чекан.', 'bidMin' => 1500, 'bidMax' => 3000, 'bidsCount' => 4, 'format' => 'auction', 'status' => 'active', 'imageUrl' => '/images/theme-medieval.jpg', 'themeId' => '1', 'groupId' => '1-g1', 'sectionType' => 'auction', 'createdAt' => '2024-05-01T10:00:00Z'],
            ['id' => 'l2', 'title' => 'Полушка Василия Дмитриевича', 'description' => 'Редкая разновидность, темная патина.', 'price' => 12000, 'bidsCount' => 0, 'format' => 'fixed', 'status' => 'active', 'imageUrl' => '/images/theme-udels.jpg', 'themeId' => '2', 'groupId' => '2-g1', 'sectionType' => 'exclusive', 'createdAt' => '2024-05-02T11:00:00Z'],
            ['id' => 'l3', 'title' => 'Крест энколпион', 'description' => 'Створка энколпиона XII века, бронза.', 'bidMin' => 5000, 'bidMax' => 8500, 'bidsCount' => 2, 'format' => 'auction', 'status' => 'active', 'imageUrl' => '/images/theme-metal.jpg', 'themeId' => '3', 'groupId' => '3-g2', 'sectionType' => 'liquidation', 'createdAt' => '2024-05-03T12:00:00Z'],
            ['id' => 'l4', 'title' => '1 Рубль 1898 года', 'description' => 'Серебро, портрет Николая II.', 'price' => 8500, 'bidsCount' => 0, 'format' => 'fixed', 'status' => 'active', 'imageUrl' => '/images/theme-empire.jpg', 'themeId' => '4', 'groupId' => '4-g3', 'sectionType' => 'exclusive', 'createdAt' => '2024-05-04T13:00:00Z'],
            ['id' => 'l5', 'title' => 'Дирхем Золотой Орды', 'description' => 'Тохтамыш, Сарай ал-Джедид.', 'bidMin' => 800, 'bidMax' => 1200, 'bidsCount' => 5, 'format' => 'auction', 'status' => 'active', 'imageUrl' => '/images/theme-east.jpg', 'themeId' => '5', 'groupId' => '5-g4', 'sectionType' => 'auction', 'createdAt' => '2024-05-05T14:00:00Z'],
            ['id' => 'l6', 'title' => 'Копейка Петра I', 'description' => 'Чешуя серебро.', 'bidMin' => 300, 'bidMax' => 500, 'bidsCount' => 1, 'format' => 'auction', 'status' => 'active', 'imageUrl' => '/images/theme-medieval.jpg', 'themeId' => '1', 'groupId' => '1-g2', 'sectionType' => 'auction', 'createdAt' => '2024-05-06T15:00:00Z'],
            ['id' => 'l7', 'title' => '5 копеек 1726 года', 'description' => 'Крестовик, медь.', 'price' => 45000, 'bidsCount' => 0, 'format' => 'fixed', 'status' => 'active', 'imageUrl' => '/images/theme-empire.jpg', 'themeId' => '4', 'groupId' => '4-g1', 'sectionType' => 'exclusive', 'createdAt' => '2024-05-07T16:00:00Z'],
            ['id' => 'l8', 'title' => 'Иконка Святой Николай', 'description' => 'Медная пластика XIX век.', 'bidMin' => 1500, 'bidMax' => 2000, 'bidsCount' => 0, 'format' => 'auction', 'status' => 'active', 'imageUrl' => '/images/theme-metal.jpg', 'themeId' => '3', 'groupId' => '3-g1', 'sectionType' => 'auction', 'createdAt' => '2024-05-08T17:00:00Z'],
            ['id' => 'l9', 'title' => 'Удельная денга', 'description' => 'Неопознанная удельщина.', 'price' => 5000, 'bidsCount' => 0, 'format' => 'fixed', 'status' => 'active', 'imageUrl' => '/images/theme-udels.jpg', 'themeId' => '2', 'groupId' => '2-g2', 'sectionType' => 'liquidation', 'createdAt' => '2024-05-09T18:00:00Z'],
            ['id' => 'l10', 'title' => 'Серебряный дирхем', 'description' => 'Саманиды, X век.', 'bidMin' => 2000, 'bidMax' => 4000, 'bidsCount' => 3, 'format' => 'auction', 'status' => 'active', 'imageUrl' => '/images/theme-east.jpg', 'themeId' => '5', 'groupId' => '5-g1', 'sectionType' => 'auction', 'createdAt' => '2024-05-10T19:00:00Z'],
            ['id' => 'l11', 'title' => 'Новгородка', 'description' => 'Новгород Великий.', 'price' => 3500, 'bidsCount' => 0, 'format' => 'fixed', 'status' => 'active', 'imageUrl' => '/images/theme-medieval.jpg', 'themeId' => '1', 'groupId' => '1-g3', 'sectionType' => 'liquidation', 'createdAt' => '2024-05-11T20:00:00Z'],
            ['id' => 'l12', 'title' => 'Полтина 1762 года', 'description' => 'Петр III, редкая.', 'price' => 150000, 'bidsCount' => 0, 'format' => 'fixed', 'status' => 'active', 'imageUrl' => '/images/theme-empire.jpg', 'themeId' => '4', 'groupId' => '4-g2', 'sectionType' => 'exclusive', 'createdAt' => '2024-05-12T21:00:00Z'],
        ];
    }

    public static function getNews(): array {
        return [
            ['id' => 'n1', 'date' => '2024-05-10T00:00:00Z', 'title' => 'Итоги весеннего аукциона', 'imageUrl' => '/images/news-1.jpg'],
            ['id' => 'n2', 'date' => '2024-05-08T00:00:00Z', 'title' => 'Новые поступления в разделе Империя', 'imageUrl' => '/images/news-2.jpg'],
            ['id' => 'n3', 'date' => '2024-05-05T00:00:00Z', 'title' => 'Правила работы закрытого раздела', 'imageUrl' => '/images/news-3.jpg'],
            ['id' => 'n4', 'date' => '2024-05-01T00:00:00Z', 'title' => 'Открытие новых разделов Металлопластики', 'imageUrl' => '/images/news-4.jpg'],
        ];
    }

    public static function getActivities(): array {
        return [
            ['id' => 'a1', 'text' => 'Дилер D*** сделал ставку на 5 копеек', 'timeAgo' => '2 мин назад'],
            ['id' => 'a2', 'text' => 'Коллекционер K*** зарегистрировался', 'timeAgo' => '15 мин назад'],
            ['id' => 'a3', 'text' => 'Новый лот в разделе Ликвидация', 'timeAgo' => '1 час назад'],
            ['id' => 'a4', 'text' => 'Дилер A*** купил лот по блиц-цене', 'timeAgo' => '2 часа назад'],
            ['id' => 'a5', 'text' => 'Добавлен новый стикер', 'timeAgo' => '3 часа назад'],
        ];
    }
}

# Yandex Webmaster Core

> Библиотека и каталог skills для работы с Яндекс.Вебмастером

Этот пакет содержит WebmasterClient — общий API-клиент для всех skills Яндекс.Вебмастера:
- OAuth авторизация
- Запросы к API Вебмастера
- Проверка безопасности (.gitignore)
- Экспорт в CSV/Markdown

## Skills на основе этого пакета

| Skill | Описание | Репозиторий |
|-------|----------|-------------|
| yandex-webmaster-queries | Поисковые запросы с SEO-метриками | [github.com/prikotov/yandex-webmaster-queries](https://github.com/prikotov/yandex-webmaster-queries) |

## Установка

Skills совместимы с различными AI-агентами. Примеры ниже даны для OpenCode — для других инструментов смотрите их документацию по установке skills.

### 1. Установите core

```bash
git clone https://github.com/prikotov/yandex-webmaster-core.git .opencode/skills/yandex-webmaster-core
```

### 2. Создайте OAuth-приложение Яндекс

1. Перейдите на https://oauth.yandex.ru/client/new
2. Заполните:
   - **Название**: `Webmaster Stats`
   - **Платформа**: Веб-сервисы
   - **Redirect URI**: `https://oauth.yandex.ru/verification_code`
   - **Доступы**: 
     - `webmaster:hostinfo`
     - `webmaster:verify`
3. Скопируйте:
   - **ClientID** → `client_id`
   - **Client secret** → `client_secret`

### 3. Создайте конфигурацию

```bash
cp .opencode/skills/yandex-webmaster-core/webmaster_config.example.json ./webmaster_config.json
```

Заполните:
```json
{
    "client_id": "ваш_client_id",
    "client_secret": "ваш_client_secret",
    "host_id": null
}
```

`host_id` можно оставить null — скрипт выберет первый сайт из списка, или укажите конкретный ID (например, `https:site.ru:443`).

### 4. Установите нужные skills

```bash
git clone https://github.com/prikotov/yandex-webmaster-queries.git .opencode/skills/yandex-webmaster-queries
```

## Структура

```
your-project/
├── webmaster_config.json          # Общий конфиг (создаётся вручную в корне проекта)
├── yandex_webmaster_token.json    # Создаётся автоматически при первом запуске
├── webmaster_reports/             # Создаётся автоматически при запуске отчёта
│   └── YYYY-MM-DD/                # Папка с отчётами за день
└── .opencode/skills/
    ├── yandex-webmaster-core/     # Библиотека
    └── yandex-webmaster-queries/  # Поисковые запросы
```

## Безопасность

WebmasterClient автоматически защищает конфиденциальные данные от случайной публикации в git. При первом запуске он проверяет `.gitignore` и добавляет недостающие записи.

Защищаемые файлы:
- `webmaster_config.json` — OAuth-данные приложения
- `yandex_webmaster_token.json` — токен авторизации
- `webmaster_reports/` — папка с отчётами

## Создание нового skill

1. Создайте репозиторий `yandex-webmaster-XXX`
2. Подключите WebmasterClient:
```php
<?php
require_once __DIR__ . '/../yandex-webmaster-core/WebmasterClient.php';

WebmasterClient::checkGitignore();
$config = WebmasterClient::loadConfig();

$client = new WebmasterClient(
    $config['client_id'],
    $config['client_secret'],
    $config['host_id'] ?? null
);

// Ваш код...
```

### Пример использования WebmasterClient API

```php
<?php
require_once __DIR__ . '/../yandex-webmaster-core/WebmasterClient.php';

// 1. Проверка .gitignore и загрузка конфига
WebmasterClient::checkGitignore();
$config = WebmasterClient::loadConfig();

// 2. Создание клиента
$client = new WebmasterClient(
    $config['client_id'],
    $config['client_secret'],
    $config['host_id'] ?? null
);

// 3. Запрос популярных запросов
$data = $client->getPopularQueries('2026-01-01', '2026-02-28', 100);

// 4. Преобразование ответа
$rows = [];
foreach ($data['queries'] ?? [] as $item) {
    $indicators = $item['indicators'] ?? [];
    $shows = (int)($indicators['TOTAL_SHOWS'] ?? 0);
    $clicks = (int)($indicators['TOTAL_CLICKS'] ?? 0);
    
    $rows[] = [
        'query' => $item['query_text'],
        'shows' => $shows,
        'clicks' => $clicks,
        'position' => round($indicators['AVG_SHOW_POSITION'] ?? 0, 1)
    ];
}

// 5. Сохранение отчёта
$reportDir = WebmasterClient::createReportDir();
WebmasterClient::saveCsv($rows, "$reportDir/queries.csv");
WebmasterClient::saveMarkdown($rows, "$reportDir/queries.md", 'Поисковые запросы', '2026-01-01', '2026-02-28');
```

## Требования

- PHP 7.4+
- Расширение cURL

---

## Пример работы в AI-агенте

**Пользователь:**
> Проанализируй поисковые запросы сайта за последний месяц. Найди запросы с высоким потенциалом для SEO-оптимизации.

**Агент (рассуждения):**
> Загружаю skill для анализа поисковых запросов из Яндекс.Вебмастера. Запрашиваю данные за последние 30 дней. Сортирую по показам, анализирую CTR и позиции...

**Агент (ответ):**

---

**Анализ поисковых запросов (03.02 - 05.03.2026)**

**Запросы с высоким потенциалом**

| Запрос | Показы | Клики | CTR | Позиция | Рекомендация |
|--------|--------|-------|-----|---------|--------------|
| fingpt | 290 | 5 | 1.72% | 8.3 | Улучшить сниппет |
| kilo code | 231 | 7 | 3.03% | 8.7 | Потенциал топ-5 |
| glm-5 нейросеть | 164 | 2 | 1.22% | 10.3 | Оптимизировать страницу |

**Выводы**

*Быстрые победы (позиции 5-15):*
- `kilo code` — хороший CTR 3.03%, близок к топу
- `fingpt` — много показов, но низкий CTR → улучшить title/description

*Скрытый потенциал:*
- `glm 5 нейросеть` — 255 показов, позиция 9.9 → нужна точечная оптимизация

---

> Постановка задач, архитектура, ревью — [Dmitry Prikotov](https://prikotov.pro/), реализация — GLM-5 в [OpenCode](https://opencode.ai)

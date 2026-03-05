# Yandex Webmaster Core

> Библиотека и skills для работы с Яндекс.Вебмастером

## Возможности

- OAuth авторизация
- Получение популярных поисковых запросов
- Показы, клики, CTR, позиции

## Установка

### 1. Создайте OAuth-приложение

1. https://oauth.yandex.ru/client/new
2. Платформа: **Веб-сервисы**
3. Redirect URI: `https://oauth.yandex.ru/verification_code`
4. Доступы:
   - `webmaster:hostinfo`
   - `webmaster:verify`

### 2. Создайте конфигурацию

```bash
cp .opencode/skills/yandex-webmaster-core/webmaster_config.example.json ./webmaster_config.json
```

Заполните `client_id`, `client_secret`. `host_id` можно оставить null — скрипт выберет первый сайт.

### 3. Запустите

```bash
php .opencode/skills/yandex-webmaster-queries/queries.php -l 20
```

При первом запуске откроется ссылка для авторизации.

## Структура

```
your-project/
├── webmaster_config.json          # Конфиг
├── yandex_webmaster_token.json    # Токен (автоматически)
├── webmaster_reports/             # Отчёты
└── .opencode/skills/
    ├── yandex-webmaster-core/     # Библиотека
    └── yandex-webmaster-queries/  # Поисковые запросы
```

## SEO-анализ

- **Высокие показы + низкий CTR** → улучшить title/description
- **Позиции 5-15** → потенциал для топ-5
- **Позиции 10-20** → скрытый потенциал роста

---
name: yandex-webmaster-core
description: Библиотека и каталог skills для Яндекс.Вебмастера
license: MIT
compatibility: opencode
---

## Доступные skills

### yandex-webmaster-queries
Поисковые запросы с SEO-метриками:
- Текст запроса
- Показы, клики, CTR
- Средняя позиция показа и клика

---

## API Вебмастера

Документация: https://yandex.ru/dev/webmaster/doc/dg/reference/

### Требуемые права OAuth:
- `webmaster:hostinfo` — информация о сайте
- `webmaster:verify` — статус индексации

### Основные эндпоинты:
- `/user` — ID пользователя
- `/user/{user-id}/hosts` — список сайтов
- `/user/{user-id}/hosts/{host-id}/search-queries/popular` — популярные запросы

Подробности установки в README.md

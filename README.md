# SIM Cards API

Небольшой backend‑сервис на Laravel для управления контрактами, SIM‑картами и группами SIM‑карт.

## Упрощения в архитектуре

В проекте намеренно не использованы дополнительные слои архитектуры, такие как:

- DTO
- Service layer
- Actions / UseCases

Для тестового задания бизнес-логика остаётся в контроллерах и job, чтобы избежать избыточной абстракции и сохранить код компактным.

В production-проекте логика обычно была бы вынесена в отдельные слои:
- DTO для входных данных
- Actions / UseCases для бизнес-операций
- Service слой для переиспользуемой логики

## Стек
PHP, Laravel, PostgreSQL, Laravel Queue, spatie/laravel-permission, PHPUnit.

## Сущности
Contract, User, SimCard, SimGroup, BulkGroupTask.

## Логика
- контракт имеет SIM‑карты и группы SIM‑карт
- client привязан к одному контракту
- admin не привязан к контракту
- SIM‑карта может входить в несколько групп

## Массовые операции
Добавление SIM‑карт в группу выполняется асинхронно через queue job `AttachSimCardsToGroupJob`.  
Статус операции хранится в `bulk_group_tasks`.

Статусы:
pending, processing, completed, failed.

## Тестовый режим
Аутентификация упрощена. Пользователь передаётся через заголовок:

X-Test-User-Id

Middleware `ResolveTestUser` подставляет пользователя в request.

## Основные таблицы
contracts, users, sim_cards, sim_groups, sim_card_group, bulk_group_tasks.

## Тесты

php artisan test

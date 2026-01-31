SHELL := /bin/bash

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build app

migrate:
	docker compose exec app php artisan migrate

test:
	docker compose exec app php artisan test

worker-high:
	docker compose exec -d app php artisan queue:work --queue=notifications-high

worker-normal:
	docker compose exec -d app php artisan queue:work --queue=notifications-normal

worker-low:
	docker compose exec -d app php artisan queue:work --queue=notifications-low

worker-dead:
	docker compose exec -d app php artisan queue:work --queue=notifications-dead

scheduler:
	docker compose exec -d app php artisan schedule:work

queue-monitor:
	docker compose exec app php artisan queue:monitor notifications-high,notifications-normal,notifications-low

logs:
	docker compose logs --tail=200 app

reverb:
	docker compose exec -d app php artisan reverb:start

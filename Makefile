SHELL := /bin/bash
DC_DEV = docker-compose -f docker-compose.yml

# WordPress management via docker-compose
run:
	$(DC_DEV) up
	
start:
	$(DC_DEV) up -d

stop:
	$(DC_DEV) stop

# One liner to get a shell inside the WordPress container.
shell: start
	docker-compose exec wordpress /bin/bash -c "cd /var/www/html/wp-content/plugins/phpcompat/; /bin/bash"

test: start
	docker-compose exec wordpress /bin/bash -c "/var/www/html/wp-content/plugins/phpcompat/bin/test.sh"
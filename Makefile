SHELL := /bin/bash

# WordPress management via docker-compose
start:
	docker-compose -f docker-compose.yml up -d

stop:
	docker-compose -f docker-compose.yml stop

# One liner to get a shell inside the WordPress container.
shell:
	docker-compose exec wordpress /bin/bash -c "cd /var/www/html/wp-content/plugins/phpcompat/; /bin/bash"

# Vars
stack_name=doghelp
app_container_id = $(shell docker ps --filter name="$(stack_name)_php" -q)
db_container_id = $(shell docker ps --filter name="$(stack_name)_db" -q)
prod_host=doghelp.barlito.fr
image_name=barlito/doghelp
worker_image_name=barlito/doghelp-worker
worker_services=worker
# /login is public; / redirects to /admin behind the Discord auth
smoke_path=/login

# Config paths
config_cs_fixer=vendor/barlito/utils/config/.php-cs-fixer.dist.php
config_phpcs=vendor/barlito/utils/config/phpcs.xml.dist
config_phpmd=vendor/barlito/utils/config/phpmd.xml

# Include all make rules from submodule
include make/entrypoint.mk

# Overrides deploy.ci: the project has no Hautelook fixtures
deploy.ci:
	make docker.deploy.ci
	castor barlito:castor:wait-php-container
	make composer.command args="config -g github-oauth.github.com $(github_token)"
	make composer.install
	castor barlito:castor:wait-db-container
	make doctrine.migrate.ci

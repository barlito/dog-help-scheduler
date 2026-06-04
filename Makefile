# Vars
stack_name=doghelp
app_container_id = $(shell docker ps --filter name="$(stack_name)_php" -q)
db_container_id = $(shell docker ps --filter name="$(stack_name)_db" -q)
prod_host=doghelp.barlito.fr
backup_path=/srv/doghelp/backups

# Config paths
config_cs_fixer=vendor/barlito/utils/config/.php-cs-fixer.dist.php
config_phpcs=vendor/barlito/utils/config/phpcs.xml.dist
config_phpmd=vendor/barlito/utils/config/phpmd.xml

# Include all make rules from submodule
include make/entrypoint.mk

### Overrides — submodule rules adaptées au projet

# Override deploy.prod : on chaîne db.backup avant la migration et smoke.test après.
# Le backup est skippé proprement si la DB n'existe pas encore (1er deploy).
deploy.prod:
	make docker.deploy.prod
	castor barlito:castor:wait-php-container
	castor barlito:castor:wait-db-container
	make db.backup
	make doctrine.migrate
	make smoke.test

# Dump pg_dump dans le volume host /srv/doghelp/backups (monté dans le container db).
# Format -F c (custom, compressé). Skippé silencieusement si la DB n'est pas démarrée
# (cas 1er deploy).
db.backup:
	@cid="$$(docker ps --filter name='$(stack_name)_db' -q | head -1)"; \
	if [ -n "$$cid" ]; then \
		ts="$$(date +%Y%m%d-%H%M%S)"; \
		echo "🗄  Backup DB → $(backup_path)/doghelp-$$ts.dump (container $$cid)"; \
		docker exec -t "$$cid" sh -c "pg_dump -U doghelp -F c -d doghelp -f /backups/doghelp-$$ts.dump"; \
	else \
		echo "ℹ  DB container introuvable, backup skippé (1er deploy ?)"; \
	fi

# Smoke test : curl GET /login → fail si non-2xx. Garde-fou post-deploy/update.
# (/login est public ; / redirige vers /admin protégé par l'auth Discord.)
smoke.test:
	@echo "🩺 Smoke test https://$(prod_host)/login..."
	@curl -fsS -o /dev/null -w "  HTTP %{http_code}\n" https://$(prod_host)/login || (echo "❌ Smoke test KO" && exit 1)
	@echo "✓ Smoke test OK"

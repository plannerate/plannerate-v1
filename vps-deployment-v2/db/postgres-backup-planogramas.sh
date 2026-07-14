#!/bin/bash
# postgres-backup-planogramas.sh
#
# Tier RÁPIDO: só as tabelas de planograma + hierarquia filha (TABLES_INCLUDED),
# que mudam o tempo todo. Pensado pra rodar a cada 30min via cron.
#
# Roda como usuário OS "postgres" (peer auth — sem login/senha no script; o
# usuário "postgres" já é superuser e conecta em qualquer banco). Percorre
# TODOS os bancos automaticamente (landlord + cada tenant, um por cliente) —
# quando um cliente novo chegar, o próprio script descobre o banco novo sem
# precisar editar nada aqui.
#
#	#########################################################################
#	# RESTORE								#
#	#########################################################################
#	# Baixar o pacote do dia/hora desejado do DO Spaces, depois:
#	# tar xzf <timestamp>.tar.gz -C /tmp/restore
#	# pg_restore --dbname "<database>" --verbose --disable-triggers /tmp/restore/<tabela>.tar
#
#	# USAGE:
#	# postgres-backup-planogramas.sh
#	# (crontab -u postgres, a cada 30min)

set -uo pipefail

################################################################################
# CONFIGURATION
################################################################################

declare -A DIRECTORIES
declare -A FILES

DIRECTORIES[dest]="/opt/backups/postgres-planogramas"
DIRECTORIES[logs]="/var/log/plannerate"

FILES[logs]="${DIRECTORIES[logs]}/postgres-backup-planogramas.log"
FILES[lock]="/tmp/postgres-backup-planogramas.lock"

# Bancos descobertos automaticamente pelo dono (exclui postgres/template0/1 e
# qualquer banco de outro dono, ex.: bancos de teste avulsos como
# tenant_production_test_vector, que pertence ao role "postgres").
DB_OWNERS=("plannerate_prod" "plannerate_staging")

# Tabelas do tier rápido — mudam o tempo todo, valem backup frequente.
TABLES_INCLUDED=(
    "planograms" "gondolas" "sections" "shelves" "segments" "layers"
    "planogram_gondola_slot_overrides" "planogram_product_rules"
    "planogram_rejected_products" "planogram_subtemplates"
    "planogram_template_slots" "planogram_templates"
    "planogram_generation_runs" "gondola_analyses" "adjacency_rules"
    "shelf_level_preferences"
)

RETENTION_COUNT=48   # 48 x 30min = 24h de histórico (local e no Spaces)

# DigitalOcean Spaces — credenciais num arquivo separado, nunca hardcoded aqui.
# Formato esperado (uma linha por variável): ver spaces-credentials.env.example
SPACES_CREDENTIALS_FILE="/etc/plannerate/spaces-backup-credentials.env"
SPACES_PREFIX="db-backups/planogramas"

SoftwareBackup="$(command -v pg_dump)"
SoftwarePsql="$(command -v psql)"
SoftwareAws="$(command -v aws)"
SoftwareTar="$(command -v tar)"




################################################################################
# PREREQUISITES
################################################################################

[ ! -d "${DIRECTORIES[logs]}" ] && mkdir -p "${DIRECTORIES[logs]}"
if [ ! -d "${DIRECTORIES[dest]}" ]; then
	mkdir -p "${DIRECTORIES[dest]}" &> /dev/null
	if [ $? -gt 0 ]; then
		echo "IMPOSSIBLE TO CREATE THE DESTINATION DIRECTORY: ${DIRECTORIES[dest]} , ABORTING THE BACKUP NOW..." | tee -a "${FILES[logs]}"
		exit 1
	fi
fi

for cmd_name in SoftwareBackup SoftwarePsql SoftwareAws SoftwareTar; do
	if [ -z "${!cmd_name}" ]; then
		echo "REQUIRED COMMAND MISSING (${cmd_name}), ABORTING THE BACKUP NOW..." | tee -a "${FILES[logs]}"
		exit 1
	fi
done

if [ ! -f "${SPACES_CREDENTIALS_FILE}" ]; then
	echo "SPACES CREDENTIALS FILE NOT FOUND: ${SPACES_CREDENTIALS_FILE}, ABORTING THE BACKUP NOW..." | tee -a "${FILES[logs]}"
	exit 1
fi
# shellcheck disable=SC1090
source "${SPACES_CREDENTIALS_FILE}"
for var_name in SPACES_ENDPOINT SPACES_REGION SPACES_BUCKET SPACES_ACCESS_KEY_ID SPACES_SECRET_ACCESS_KEY; do
	if [ -z "${!var_name:-}" ]; then
		echo "MISSING ${var_name} IN ${SPACES_CREDENTIALS_FILE}, ABORTING THE BACKUP NOW..." | tee -a "${FILES[logs]}"
		exit 1
	fi
done
export AWS_ACCESS_KEY_ID="${SPACES_ACCESS_KEY_ID}"
export AWS_SECRET_ACCESS_KEY="${SPACES_SECRET_ACCESS_KEY}"
export AWS_DEFAULT_REGION="${SPACES_REGION}"

# ALREADY RUNNING? (flock em vez do pidof do script original — mais confiável)
exec 9>"${FILES[lock]}"
if ! flock -n 9; then
	echo -e "\nTHE PLANOGRAMAS BACKUP IS ALREADY RUNNING \nABORTING NOW... \n" | tee -a "${FILES[logs]}"
	exit 1
fi




################################################################################
# WORKING
################################################################################

echo | tee -a "${FILES[logs]}"
echo "==================================================================" | tee -a "${FILES[logs]}"
echo "planogramas $(date +%Y/%m/%d\ %X)" | tee -a "${FILES[logs]}"
echo "==================================================================" | tee -a "${FILES[logs]}"
Start=$(date +%s)

# DESCOBRE OS BANCOS (landlord + cada tenant, de cada ambiente configurado em DB_OWNERS)
# Argumento opcional $1: restringe a rodada a um único banco (teste manual ou
# reprocessamento pontual de um tenant), em vez de todos os descobertos.
owners_sql=$(printf "'%s'," "${DB_OWNERS[@]}")
owners_sql="${owners_sql%,}"
ALL_OWNED_DATABASES=($($SoftwarePsql -d postgres -tAc "SELECT d.datname FROM pg_database d JOIN pg_roles r ON d.datdba = r.oid WHERE r.rolname IN (${owners_sql}) AND NOT d.datistemplate ORDER BY d.datname;" 2>> "${FILES[logs]}"))

if [ -n "${1:-}" ]; then
	DATABASES=()
	for candidate in "${ALL_OWNED_DATABASES[@]}"; do
		[ "$candidate" = "$1" ] && DATABASES+=("$candidate")
	done
	if [ "${#DATABASES[@]}" = "0" ]; then
		echo "$(date +%Y/%m/%d\ %X) - Banco '$1' não encontrado entre os donos (${DB_OWNERS[*]}), ABORTANDO" | tee -a "${FILES[logs]}"
		exit 1
	fi
else
	DATABASES=("${ALL_OWNED_DATABASES[@]}")
fi

if [ "${#DATABASES[@]}" = "0" ]; then
	echo "$(date +%Y/%m/%d\ %X) - NENHUM BANCO ENCONTRADO PARA OS DONOS (${DB_OWNERS[*]}), ABORTANDO" | tee -a "${FILES[logs]}"
	exit 1
fi
echo "$(date +%Y/%m/%d\ %X) - Bancos encontrados: ${DATABASES[*]}" | tee -a "${FILES[logs]}"

DateTime="$(date +%Y-%m-%d-%H-%M-%S)"
overall_fail=0

for Database in "${DATABASES[@]}"; do
	printf "%-120.*s" 100 "$(date +%Y/%m/%d\ %X) - [$Database] Iniciando"; echo | tee -a "${FILES[logs]}"

	# LISTA REAL DE TABELAS DO BANCO, INTERSECTADA COM TABLES_INCLUDED
	# (bancos diferentes podem não ter todas as tabelas — ex.: landlord não
	# tem "gondolas"; a intersecção evita erro de "tabela não existe")
	EXISTING_TABLES=($($SoftwarePsql -d "$Database" -tAc "SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname='public';" 2>> "${FILES[logs]}"))

	TABLES_TO_BACKUP=()
	for wanted in "${TABLES_INCLUDED[@]}"; do
		for existing in "${EXISTING_TABLES[@]}"; do
			if [ "$wanted" = "$existing" ]; then
				TABLES_TO_BACKUP+=("$wanted")
				break
			fi
		done
	done

	if [ "${#TABLES_TO_BACKUP[@]}" = "0" ]; then
		echo "$(date +%Y/%m/%d\ %X) - [$Database] Nenhuma tabela do tier rápido encontrada, pulando (normal pro banco landlord)" | tee -a "${FILES[logs]}"
		continue
	fi

	RunDir="${DIRECTORIES[dest]}/${Database}/tmp-${DateTime}"
	mkdir -p "$RunDir"

	db_fail=0
	for Table in "${TABLES_TO_BACKUP[@]}"; do
		printf "%-120.*s" 100 "$(date +%Y/%m/%d\ %X) - [$Database] Backup tabela $Table"
		$SoftwareBackup -d "$Database" -t "\"public\".\"$Table\"" -F c -b -f "$RunDir/$Table.tar" 2>> "${FILES[logs]}"
		if [ $? = "0" ]; then
			echo "[ OK    ]" | tee -a "${FILES[logs]}"
		else
			echo "[ ERROR ]" | tee -a "${FILES[logs]}"
			db_fail=1
			overall_fail=1
		fi
	done

	# EMPACOTA NUM ÚNICO ARQUIVO (reduz nº de objetos no Spaces e no disco local)
	Bundle="${DIRECTORIES[dest]}/${Database}/${DateTime}.tar.gz"
	$SoftwareTar -C "$RunDir" -czf "$Bundle" . 2>> "${FILES[logs]}"
	rm -rf "$RunDir"

	if [ ! -f "$Bundle" ]; then
		echo "$(date +%Y/%m/%d\ %X) - [$Database] FALHA ao empacotar o backup, pulando upload" | tee -a "${FILES[logs]}"
		overall_fail=1
		continue
	fi

	# UPLOAD
	remote_key="${SPACES_PREFIX}/${Database}/${DateTime}.tar.gz"
	if $SoftwareAws --endpoint-url "${SPACES_ENDPOINT}" s3 cp "$Bundle" "s3://${SPACES_BUCKET}/${remote_key}" --only-show-errors 2>> "${FILES[logs]}"; then
		echo "$(date +%Y/%m/%d\ %X) - [$Database] Enviado: s3://${SPACES_BUCKET}/${remote_key}" | tee -a "${FILES[logs]}"
	else
		echo "$(date +%Y/%m/%d\ %X) - [$Database] FALHA no upload pro Spaces" | tee -a "${FILES[logs]}"
		overall_fail=1
	fi

	# RETENÇÃO LOCAL — mantém só os RETENTION_COUNT mais recentes
	ls -1t "${DIRECTORIES[dest]}/${Database}"/*.tar.gz 2>/dev/null | tail -n +$((RETENTION_COUNT + 1)) | xargs -r rm -f

	# RETENÇÃO REMOTA — idem, no Spaces
	remote_list=$($SoftwareAws --endpoint-url "${SPACES_ENDPOINT}" s3 ls "s3://${SPACES_BUCKET}/${SPACES_PREFIX}/${Database}/" 2>> "${FILES[logs]}" | awk '{print $4}' | sort -r)
	echo "$remote_list" | tail -n +$((RETENTION_COUNT + 1)) | while read -r old_key; do
		[ -z "$old_key" ] && continue
		$SoftwareAws --endpoint-url "${SPACES_ENDPOINT}" s3 rm "s3://${SPACES_BUCKET}/${SPACES_PREFIX}/${Database}/${old_key}" --only-show-errors 2>> "${FILES[logs]}"
	done

	if [ "$db_fail" = "1" ]; then
		echo "$(date +%Y/%m/%d\ %X) - [$Database] Concluído COM ERROS em alguma tabela" | tee -a "${FILES[logs]}"
	else
		echo "$(date +%Y/%m/%d\ %X) - [$Database] Concluído OK" | tee -a "${FILES[logs]}"
	fi
done

End=$(date +%s)
Time=$((End - Start))
TimeFormated=$(TZ=UTC0 printf '%(%H:%M:%S)T\n' "$Time")
echo "$(date +%Y/%m/%d\ %X) - Rodada completa em $TimeFormated (bancos: ${#DATABASES[@]})" | tee -a "${FILES[logs]}"

exit "$overall_fail"

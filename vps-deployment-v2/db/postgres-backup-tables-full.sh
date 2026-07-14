#!/bin/bash
# postgres-backup-tables-full.sh

# Version 1.2 - Elias Beranbé Turchiello - 07/07/2023

	#########################################################################
	# RESTORE								#
	#########################################################################
	# RESTORE ALL TABLES
	# for x in $(ls -1); do
	#	name=$(echo $x | cut -d'.' -f1);
	#	printf "%-100s" "$(date +%d/%m/%Y-%X) - Deleting now - $x";
	#	psql -U audiencymusic -d audiency_music -c 'DROP TABLE public.'"\"$name\" CASCADE;" &> /dev/null;
	#	printf "%-100s" "-> Restauring again $x ...";
	#	pg_restore --dbname "audiency_music" --verbose $x 2> /dev/null;
	#	echo "[ DONE ]" ;
	# done

	# RESTORE A SINGLE TABLE
	# psql -U audiencymusic -d audiency_music -c 'DROP TABLE public."nome_da_tabela" CASCADE;'
	# pg_restore --dbname "audiency_music" --verbose  --disable-triggers  arquivo_backup_da_tabela.tar
	
	# USAGE:
	# postgres-backup-tables-full.sh










################################################################################
# CONFIGURATION
################################################################################

declare -A DIRECTORIES
declare -A FILES


DIRECTORIES[dest]="/media/sdb1/Postgres-backup-tables"
DIRECTORIES[logs]="$HOME/Logs/Postgres-backup-tables"


FILES[logs]="${DIRECTORIES[logs]}/postgres-backup-tables-full.log"


Database="nome-do-banco-de-dados"
SoftwareBackup="$(which pg_dump)"
SoftwarePsql="$(which psql)"










################################################################################
# PREREQUISITES
################################################################################

# DIRECTORIES
[ ! -d ${DIRECTORIES[logs]} ] && mkdir -p ${DIRECTORIES[logs]}
if [ ! -d ${DIRECTORIES[dest]} ]; then
	mkdir -p ${DIRECTORIES[dest]} &> /dev/null
	if [ $? -gt 0 ]; then
		echo "IMPOSSIBLE TO CREATE THE DESTINATION DIRECTORY: ${DIRECTORIES[dest]} , ABORTING THE BACKUP NOW..." | tee -a ${FILES[logs]}
		exit 1
	fi
fi



# SOFTWARE
if [ -z $SoftwareBackup ]; then
	echo "\"pg_dump\" COMMAND NOT FOUND IN THE SYSTEM, ABORTING THE BACKUP NOW..." | tee -a ${FILES[logs]}
	exit 1
fi
if [ -z $SoftwarePsql ]; then
	echo "\"pgsql\" COMMAND NOT FOUND IN THE SYSTEM, ABORTING THE BACKUP NOW..." | tee -a ${FILES[logs]}
	exit 1
fi



# DATABASES LIST
if [ ${#Database} -eq 0 ]; then
	echo -e "\nANY DATABASE CONFIGURED \nPLEASE CONFIGURE THE SCRIPT! \nABORTING NOW... \n" | tee -a ${FILES[logs]}
	exit 1
fi



# ALREADY RUNNING?
# NOTE - The pidof always sum 1 more, I don't know why
if [ $(pidof -x $(basename $0) | wc -w) -ge 3 ]; then
	echo -e "\nTHE FULL BACKUP IS ALREADY RUNNING \nABORTING NOW... \n" | tee -a ${FILES[logs]}
	exit 1
fi










################################################################################
# WORKING
################################################################################

# LOGGING
echo | tee -a ${FILES[logs]}
echo "==================================================================" | tee -a ${FILES[logs]}
echo "$Database $(date +%Y/%m/%d\ %X)" | tee -a ${FILES[logs]}
echo "==================================================================" | tee -a ${FILES[logs]}
Start=$(date +%s)



# LIST ALL TABLES FROM THIS DATABASE
TABLES_LIST=($($SoftwarePsql -d $Database -c "SELECT schemaname, tablename FROM pg_catalog.pg_tables WHERE schemaname != 'pg_catalog' AND schemaname != 'information_schema' ORDER BY tablename ASC;" 2> /dev/null | sed '1,2 d' | sed '$ d' | sed '$ d' | sed 's/^ \+//'  | tr -d \ ))



# EMPTY?
if [ "${#TABLES_LIST[@]}" = "0" ]; then
	printf "%-120.*s" 100 "$(date +%Y/%m/%d\ %X) - Database $Database is empty, any table found, aborting now..." | tee -a ${FILES[logs]}
	echo | tee -a ${FILES[logs]}
	exit 1
fi



# BACKUP DIRECTORY NAME
DateTime="$(date +%Y-%m-%d-%H-%M-%S)"
ThisDirectory="${DIRECTORIES[dest]}/F-$DateTime-$Database"



# DESTINATION
printf "%-120.*s" 100 "$(date +%Y/%m/%d\ %X) - Creating the directory for $Database" | tee -a ${FILES[logs]}
if [ ! -d $ThisDirectory ]; then
	mkdir -p $ThisDirectory
	echo "[ OK    ]" | tee -a ${FILES[logs]}
else
	echo "[ ERROR ]" | tee -a ${FILES[logs]}
	exit 1
fi



# BACKUP TABLES
for y in ${TABLES_LIST[@]}; do
	# PREPARING
	Schema="$(echo $y | cut -d'|' -f1)"
	Table="$(echo $y | cut -d'|' -f2)"


	# LOGS
	printf "%-120.*s" 100 "$(date +%Y/%m/%d\ %X) - Backup now $Schema $Table" | tee -a ${FILES[logs]}


	# BACKUP
	$SoftwareBackup -d "$Database" -t ''\"$Schema\".\"$Table\"'' -F c -b -v -f $ThisDirectory/$Table.tar 2> /dev/null
	if [ $? = "0" ]; then
		echo "[ OK    ]" | tee -a ${FILES[logs]}
	else
		echo "[ ERROR ]" | tee -a ${FILES[logs]}
	fi
done
Size=$(du -sh $ThisDirectory | awk '{print $1}')
End=$(date +%s)
Time=$((End - Start))
TimeFormated=$(TZ=UTC0 printf '%(%H:%M:%S)T\n' $Time)
printf "%-120.*s" 100 "$(date +%Y/%m/%d\ %X) - All done in $TimeFormated. Backup size: $Size" | tee -a ${FILES[logs]}
echo "[ DONE  ]" | tee -a ${FILES[logs]}

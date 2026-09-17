#!/usr/bin/env bash
# Usage: bash import-cours-vps.sh [--dry-run]
# Run as the usual deployment user, from any working directory.
set -Eeuo pipefail

DRY_RUN=0
case "${1:-}" in
    '') ;;
    --dry-run) DRY_RUN=1 ;;
    -h|--help)
        printf '%s\n' \
            'Usage : bash import-cours-vps.sh [--dry-run]' \
            'Sans option : verification, simulation, puis import conservateur.' \
            '--dry-run : simulation seulement, aucune ligne conservee.' \
            'PHP_BIN peut designer un autre executable PHP (ex. /usr/bin/php8.3).' \
            'A lancer avec le compte habituel de deploiement du projet.'
        exit 0
        ;;
    *) printf 'Option inconnue : %s\n' "$1" >&2; exit 2 ;;
esac
if (( $# > 1 )); then
    printf '%s\n' 'Trop de parametres. Utiliser --help.' >&2
    exit 2
fi

PROJECT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
cd -- "$PROJECT_DIR"
PHP_BIN="${PHP_BIN:-php}"
LOG_FILE=''
PHASE='verification'

on_error() {
    local status=$?
    trap - ERR
    printf '\nArret pendant : %s (code %s).\n' "$PHASE" "$status" >&2
    if [[ -n "$LOG_FILE" ]]; then
        printf 'Journal : %s\n' "$LOG_FILE" >&2
    fi
    printf '%s\n' 'Ne pas lancer les seeders generaux pour contourner cette erreur.' >&2
    exit "$status"
}
trap on_error ERR
trap 'printf "\nExecution interrompue. Consulter le journal avant de relancer.\n" >&2; exit 130' INT
trap 'printf "\nExecution interrompue. Consulter le journal avant de relancer.\n" >&2; exit 143' TERM

fail() { printf 'Erreur : %s\n' "$*" >&2; return 1; }

for executable in "$PHP_BIN" flock tee mktemp; do
    command -v "$executable" >/dev/null 2>&1 || fail "Executable absent : $executable"
done

for file in artisan .env vendor/autoload.php \
    app/Console/Commands/ImportCours.php \
    app/Services/TheoryCourseCatalog.php \
    app/Services/DirectVideoUrl.php \
    database/seeders/CoursJsonSeeder.php \
    public/cours.json public/cours_theorique/liens_cours_theorique.json; do
    [[ -r "$file" ]] || fail "Fichier absent ou illisible : $PROJECT_DIR/$file"
done
[[ -d database/data/cours-quizzes ]] || fail 'Deployer aussi database/data/cours-quizzes.'
[[ -d storage && -w storage ]] || fail 'storage doit etre accessible en ecriture au compte de deploiement.'
[[ -d bootstrap/cache && -w bootstrap/cache ]] || fail 'bootstrap/cache doit etre accessible en ecriture.'

# Lock only this wrapper. The seeder also holds its own independent lock.
# Do not remove the lock file: deleting it could allow parallel processes.
mkdir -p -- storage/app/private
exec 9>>storage/app/private/cours-vps-script.lock
flock -n 9 || fail 'Un autre lancement de ce script est deja en cours.'

# Restrict only the journal permissions; do not change Laravel file permissions.
LOG_FILE="$(umask 077; mktemp "$PROJECT_DIR/storage/app/private/cours-import-XXXXXXXX.log")"
log() { printf '%s\n' "$*" | tee -a "$LOG_FILE"; }
run() { "$@" 2>&1 | tee -a "$LOG_FILE"; }

log "Projet : $PROJECT_DIR"
log "Journal : $LOG_FILE"
log 'Import des cours uniquement. Donnees existantes et Digital conserves.'
log 'Le .env et la configuration Laravel actuellement chargee restent en place.'

run "$PHP_BIN" -r '
if (PHP_VERSION_ID < 80200) {
    fwrite(STDERR, "PHP 8.2 ou plus recent est necessaire.\n");
    exit(1);
}
echo "PHP ".PHP_VERSION."\n";
'

# An authoritative Composer class map may not know newly deployed classes.
# Refresh it only if needed; never install/update dependencies or run hooks.
if ! "$PHP_BIN" -r '
require "vendor/autoload.php";
exit(class_exists("App\\Console\\Commands\\ImportCours")
    && class_exists("App\\Services\\TheoryCourseCatalog")
    && class_exists("App\\Services\\DirectVideoUrl")
    && class_exists("Database\\Seeders\\CoursJsonSeeder") ? 0 : 1);
' >>"$LOG_FILE" 2>&1; then
    command -v composer >/dev/null 2>&1 || fail 'Autoload incomplet : executer composer dump-autoload --optimize --no-scripts, puis relancer.'
    log 'Actualisation de l’autoload Composer sans installation ni scripts.'
    run composer dump-autoload --optimize --no-scripts --no-interaction
fi

PHASE='simulation'
log 'Simulation : validation des fichiers et import dans une transaction annulee.'
log 'Sur MySQL, la simulation peut consommer des numeros auto-incrementes.'
run "$PHP_BIN" artisan cours:import --dry-run --no-interaction --no-ansi

if (( DRY_RUN )); then
    log 'Simulation terminee. Aucune ligne importee conservee.'
    exit 0
fi

PHASE='import'
log 'Simulation reussie. Application de l’import conservateur.'
run "$PHP_BIN" artisan cours:import --force --no-interaction --no-ansi

PHASE='termine'
log 'Import termine. Les absences de sources eventuelles figurent dans le bilan ci-dessus.'
log 'Administration : https://elite.tfs237.com/admin'
log 'Test HTML : https://elite.tfs237.com/cours_theorique/audiovisuel__module1__lecon1.html'
log 'Le script ne verifie pas la configuration HTTP du VPS ; ouvrir ces liens pour tester.'

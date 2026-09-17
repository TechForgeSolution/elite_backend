#!/usr/bin/env bash
# Correct only known Drive video URLs. No migrations or full import.
set -Eeuo pipefail
DRY_RUN=0
case "${1:-}" in
    '') ;;
    --dry-run) DRY_RUN=1 ;;
    -h|--help)
        printf '%s\n' 'Usage : bash correct-video-links-vps.sh [--dry-run]' \
            'Simulation puis correction des liens video Drive du catalogue.' \
            'Les anciennes URL sont sauvegardees dans storage/app/private.'
        exit 0 ;;
    *) printf 'Option inconnue : %s\n' "$1" >&2; exit 2 ;;
esac
(( $# <= 1 )) || { printf 'Trop de parametres.\n' >&2; exit 2; }
PROJECT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
cd -- "$PROJECT_DIR"
PHP_BIN="${PHP_BIN:-php}"
LOG_FILE=''
trap 'status=$?; printf "Correction interrompue (code %s). Journal : %s\n" "$status" "$LOG_FILE" >&2; exit "$status"' ERR
trap 'exit 130' INT
trap 'exit 143' TERM
fail() { printf 'Erreur : %s\n' "$*" >&2; return 1; }
for executable in "$PHP_BIN" flock tee mktemp; do
    command -v "$executable" >/dev/null 2>&1 || fail "Executable absent : $executable"
done
for file in artisan .env vendor/autoload.php public/cours.json \
    app/Services/DirectVideoUrl.php app/Console/Commands/CorrectCoursVideos.php \
    database/seeders/CoursVideoLinksSeeder.php; do
    [[ -r "$file" ]] || fail "Fichier absent ou illisible : $file"
done
[[ -d storage && -w storage && -d bootstrap/cache && -w bootstrap/cache ]] || fail 'Verifier les droits du compte de deploiement sur storage et bootstrap/cache.'
mkdir -p -- storage/app/private
# Same wrapper lock as the full import. The seeder also shares its PHP lock.
exec 9>>storage/app/private/cours-vps-script.lock
flock -n 9 || fail 'Un import/correcteur est deja en cours.'
LOG_FILE="$(umask 077; mktemp "$PROJECT_DIR/storage/app/private/video-correction-XXXXXXXX.log")"
run() { "$@" 2>&1 | tee -a "$LOG_FILE"; }
run "$PHP_BIN" -r 'if (PHP_VERSION_ID < 80200) { fwrite(STDERR, "PHP 8.2 minimum.\n"); exit(1); }'
if ! "$PHP_BIN" -r '
require "vendor/autoload.php";
exit(class_exists("App\\Console\\Commands\\CorrectCoursVideos")
    && class_exists("App\\Services\\DirectVideoUrl")
    && class_exists("Database\\Seeders\\CoursVideoLinksSeeder") ? 0 : 1);
' >>"$LOG_FILE" 2>&1; then
    command -v composer >/dev/null 2>&1 || fail 'Actualiser l’autoload avec composer dump-autoload --optimize --no-scripts.'
    run composer dump-autoload --optimize --no-scripts --no-interaction
fi
run "$PHP_BIN" artisan cours:correct-videos --dry-run --no-interaction --no-ansi
if (( DRY_RUN )); then
    printf 'Simulation terminee. Journal : %s\n' "$LOG_FILE"
    exit 0
fi
run "$PHP_BIN" artisan cours:correct-videos --force --no-interaction --no-ansi
printf 'Correction terminee. Journal : %s\n' "$LOG_FILE"
printf '%s\n' 'Deployer aussi la modification mobile du lecteur puis recharger les lecons.' \
    'La lecture reste soumise aux permissions et quotas Google Drive.'

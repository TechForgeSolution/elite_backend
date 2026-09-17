#!/usr/bin/env bash
# Wrapper smoke tests with fake PHP/flock: never connects to a database.
set -Eeuo pipefail
REPO="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd -P)"
SCRIPT_NAME="${1:-import-cours-vps.sh}"
case "$SCRIPT_NAME" in import-cours-vps.sh|correct-video-links-vps.sh) ;; *) exit 2 ;; esac
FIXTURE="$(mktemp -d "${TMPDIR:-/tmp}/elite-import-test-XXXXXXXX")"
mkdir -p "$FIXTURE/bin" "$FIXTURE/project/storage" "$FIXTURE/project/bootstrap/cache" \
    "$FIXTURE/project/vendor" "$FIXTURE/project/app/Console/Commands" \
    "$FIXTURE/project/app/Services" "$FIXTURE/project/database/seeders" \
    "$FIXTURE/project/database/data/cours-quizzes" "$FIXTURE/project/public/cours_theorique"
cp "$REPO/$SCRIPT_NAME" "$FIXTURE/project/$SCRIPT_NAME"
for file in artisan .env vendor/autoload.php app/Console/Commands/ImportCours.php \
    app/Services/TheoryCourseCatalog.php app/Services/DirectVideoUrl.php database/seeders/CoursJsonSeeder.php \
    app/Console/Commands/CorrectCoursVideos.php database/seeders/CoursVideoLinksSeeder.php \
    public/cours.json public/cours_theorique/liens_cours_theorique.json; do
    touch "$FIXTURE/project/$file"
done
cat > "$FIXTURE/bin/php" <<'MOCK'
#!/usr/bin/env bash
set -eu
if [[ "${1:-}" == '-r' ]]; then exit 0; fi
printf '%s\n' "$*" >> "$TEST_CALLS"
if [[ "$*" == *--dry-run* && "${TEST_FAILURE:-}" == simulation ]]; then exit 31; fi
if [[ "$*" == *--force* && "${TEST_FAILURE:-}" == import ]]; then exit 32; fi
printf '%s\n' 'Import mock OK'
MOCK
cat > "$FIXTURE/bin/flock" <<'MOCK'
#!/usr/bin/env bash
if [[ "${TEST_FAILURE:-}" == lock ]]; then exit 1; fi
exit 0
MOCK
chmod +x "$FIXTURE/bin/php" "$FIXTURE/bin/flock"
export PATH="$FIXTURE/bin:$PATH" PHP_BIN="$FIXTURE/bin/php" TEST_CALLS="$FIXTURE/calls"
export TEST_FAILURE=''
SCRIPT="$FIXTURE/project/$SCRIPT_NAME"
assert_calls() {
    local expected=$1 actual
    actual="$(wc -l < "$TEST_CALLS")"
    [[ "$actual" -eq "$expected" ]] || { printf 'FAIL: %s calls, expected %s\n' "$actual" "$expected"; exit 1; }
}
run_success() { bash "$SCRIPT" "$@" > "$FIXTURE/output" 2>&1; }
run_failure() {
    if bash "$SCRIPT" "$@" > "$FIXTURE/output" 2>&1; then
        printf '%s\n' 'FAIL: expected nonzero exit'; exit 1
    fi
}

# Successful import runs simulation first, then force, even from another cwd.
: > "$TEST_CALLS"
run_success
assert_calls 2
[[ "$(head -n 1 "$TEST_CALLS")" == *--dry-run* ]]
[[ "$(tail -n 1 "$TEST_CALLS")" == *--force* ]]

# Simulation-only never executes the real import.
: > "$TEST_CALLS"
run_success --dry-run
assert_calls 1
[[ "$(head -n 1 "$TEST_CALLS")" == *--dry-run* ]]

# A failed simulation must stop before force.
: > "$TEST_CALLS"
export TEST_FAILURE=simulation
run_failure
assert_calls 1

# Import failure must propagate instead of printing successful completion.
: > "$TEST_CALLS"
export TEST_FAILURE=import
run_failure
assert_calls 2
if grep -Eq '^(Import termine|Correction terminee)\.' "$FIXTURE/output"; then exit 1; fi

# Concurrent run refused before any artisan command.
: > "$TEST_CALLS"
export TEST_FAILURE=lock
run_failure
assert_calls 0

# Missing environment and invalid arguments never reach artisan.
export TEST_FAILURE=''
mv "$FIXTURE/project/.env" "$FIXTURE/project/.env.fixture"
run_failure
assert_calls 0
run_failure --unknown
assert_calls 0

printf '%s\n' '7 wrapper scenarios passed; no real PHP or database used.'
printf 'Temporary fixture retained at: %s\n' "$FIXTURE"

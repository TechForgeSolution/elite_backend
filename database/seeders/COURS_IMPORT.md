# Import CFPAM : remplacement du catalogue

Le seeder `CoursJsonSeeder` lit exclusivement les sources livrées dans
`public/` :

- `public/index-cours.json` : filières, modules, leçons, vidéos et liens Drive des quiz ;
- `public/CFPAM_HTML_EXPORT/` : première partie théorique HTML ;
- `database/data/cours-quizzes/` (facultatif) : cache des JSON de quiz déjà téléchargés.

Il remplace toutes les filières, packs, modules, leçons, quiz, questions et
réponses qui ne relèvent pas de la filière **DIGITAL** (sans distinction de
majuscule/minuscule). DIGITAL et ses données ne sont jamais supprimés. Les
filières importées sont enregistrées en majuscules.

Avant toute suppression, tous les JSON de quiz sont lus et validés. Les JSON
absents du cache sont téléchargés depuis Google Drive avec l'identifiant fourni
par `index-cours.json`. Une erreur de réseau ou un JSON invalide annule donc
l'opération sans modifier la base.

Les URL de vidéos d'explication et pratiques sont directement construites à
partir des fichiers Drive. Les HTML trouvés sont servis sous
`/CFPAM_HTML_EXPORT/...`; une leçon référencée sans HTML correspondant est
quand même créée, avec `url_web` vide, et est signalée dans le bilan.

## Exécution sur le VPS

Déployer au minimum `public/index-cours.json`, tout le dossier
`public/CFPAM_HTML_EXPORT`, `database/seeders/CoursJsonSeeder.php`,
`app/Services/TheoryCourseCatalog.php`, `app/Services/DirectVideoUrl.php` et
`app/Console/Commands/ImportCours.php`.

Conserver une sauvegarde récente de la base avant lancement, car c'est un
remplacement intentionnel du catalogue hors DIGITAL.

Simulation (les téléchargements et validations ont lieu, puis la transaction
est annulée) :

```sh
php artisan cours:import --dry-run
```

Import réel :

```sh
php artisan cours:import --force
```

La commande classique est également possible :

```sh
php artisan db:seed --class=CoursJsonSeeder --force
```

Ne pas lancer `db:seed` sans `--class` sur le VPS : cela lancerait d'autres
seeders sans rapport avec cet import.

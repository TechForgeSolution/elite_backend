# Import de production sans écraser les données

Depuis la racine du backend sur le VPS, après déploiement des fichiers :

```sh
bash import-cours-vps.sh
```

Le script vérifie PHP et les fichiers, actualise l'autoload Composer seulement
si nécessaire, puis exécute une simulation avant l'import réel. Il s'arrête à
la première erreur, bloque les lancements simultanés et conserve un journal
privé `storage/app/private/cours-import-XXXXXXXX.log` (permissions 600).
Utiliser le compte habituel de déploiement ayant accès à `storage` et
`bootstrap/cache`. Il ne modifie ni `.env`, ni migrations, ni services,
et ne lance aucun seeder général. Aucune dépendance n'est installée.

Pour une simulation uniquement : `bash import-cours-vps.sh --dry-run`.
Il fonctionne aussi depuis un autre dossier avec son chemin absolu.
Pour choisir PHP : `PHP_BIN=/usr/bin/php8.3 bash import-cours-vps.sh`.
La configuration Laravel en cache reste celle du VPS. Le script n'effectue
pas de sauvegarde de la base : conserver la sauvegarde récente indiquée plus bas.

Cette commande importe les cours et quiz manquants, avec les URL HTML
`https://elite.tfs237.com/cours_theorique/...`. Elle travaille hors ligne :
les 81 JSON de quiz sont livrés dans `database/data/cours-quizzes`.
Elle ne lance ni DatabaseSeeder, ni DigitalCourseSeeder, ni une migration.

## Fichiers à déployer

- `import-cours-vps.sh` à la racine du backend (fins de ligne LF)
- `app/Console/Commands/ImportCours.php`
- `app/Services/TheoryCourseCatalog.php`
- `app/Services/DirectVideoUrl.php`
- `database/seeders/CoursJsonSeeder.php`
- `database/seeders/CoursTheoriqueLinksSeeder.php`
- `database/data/cours-quizzes/` : 81 fichiers JSON, hors du dossier public
- `public/cours.json`
- `public/cours_theorique/` : les 359 HTML et `liens_cours_theorique.json`

Conserver le .env du VPS et ses identifiants de base. Laravel doit déjà être
installé et les migrations du projet appliquées. Le serveur web doit utiliser
`public` comme racine ; PHP doit pouvoir écrire dans `storage`.
Si le schéma nécessaire manque, l'import s'arrête avec une indication explicite.
Sur MySQL, les tables modifiées doivent être InnoDB pour assurer le rollback.

Les copies `public/Downloads_Cours` et l'archive ZIP ne sont pas nécessaires
au déploiement. Le script Python de téléchargement ne sert pas à cet import.

## Procédure

Conserver une sauvegarde récente de la base du VPS avant le déploiement.
Une simulation facultative vérifie les fichiers, les quiz, les correspondances
et exécute les insertions dans une transaction qui est ensuite annulée :

```sh
php artisan cours:import --dry-run
```

Aucune ligne n'est conservée par cette simulation. Sur MySQL, les identifiants
auto-incrémentés consommés peuvent laisser des trous : c'est normal.
Le verrou de l'import est créé dans storage, y compris en simulation.

Pour appliquer :

```sh
php artisan cours:import --force
```

`--force` autorise l'exécution en production ; il n'autorise aucun écrasement.
Le bilan indique les éléments créés, conservés et les sources manquantes.
Une erreur annule les insertions de cette exécution. Une relance conserve les
données importées précédemment. Un verrou interdit deux imports simultanés
avec ce seeder sur le même serveur et le même dossier storage.

La commande classique reste disponible et utilise aussi le mode ajout :

```sh
php artisan db:seed --class=CoursJsonSeeder --force
```

Préférer `cours:import` : il refuse de contacter Drive si un JSON local manque.
Le seeder classique garde un téléchargement de secours pour le développement.

## Protection des données

Les catégories/packs sont identifiés par slug ; les modules et leçons par
parent et numéro d'ordre. Les correspondances ambiguës provoquent une erreur
et l'annulation de l'import, jamais un choix arbitraire.

Tout enregistrement existant est conservé avec ses valeurs, son ID et ses
timestamps : prix, activation, descriptions, vidéos et URL personnalisées.
Seuls les éléments absents sont créés. Les modules existants peuvent donc
recevoir des leçons manquantes, sans réécriture de leurs leçons actuelles.

Si un module possède déjà un quiz, ce quiz entier est conservé, même si son
ordre diffère de 1. Aucune question ni réponse n'est supprimée ou réécrite.
Le mélange A–D a lieu uniquement lors de la création d'un nouveau quiz ; la
bonne réponse reste associée au même texte et les lettres des explications
structurées sont adaptées. Les explications libres restent telles quelles.

Le catalogue ne contient pas le parcours Digital. Les slugs digital,
developpement-web-et-app et marketing-digital sont explicitement protégés :
leur présence dans une future source bloque cet import.
Les utilisateurs, soldes, achats, résultats, progressions et paramètres ne
sont jamais modifiés par cet import.

Les URL existantes différentes du manifeste sont conservées et leurs IDs
figurent dans `preserved_different_theory_urls`. Il faut les examiner si l'on
souhaite ensuite les remplacer : cet import ne prend pas cette décision.

Le seeder `CoursTheoriqueLinksSeeder` est désormais également conservateur :
il complète seulement les URL vides de leçons déjà présentes. Il ne remplace
plus les URL non vides. Ne pas l'utiliser à la place de l'import complet.

Ne pas lancer `migrate:fresh`, `migrate:refresh` ou `db:seed` sans `--class`
sur cette base : DatabaseSeeder appelle d'autres seeders, dont celui de Digital,
qui ne sont pas couverts par ces protections.

## Contrôle après import

Ouvrir l'administration sur https://elite.tfs237.com/admin et tester un cours :
https://elite.tfs237.com/cours_theorique/audiovisuel__module1__lecon1.html

Le HTML doit s'afficher comme une page, avec HTTP 200 et Content-Type text/html.
Dans le mobile, recharger le catalogue puis ouvrir une théorie et un quiz.
Les nouvelles URL sont directement utilisées par la WebView existante.
Les autorisations d'accès et les achats existants restent applicables.

## Limites connues des sources

Le catalogue contient 14 filières, 82 modules, 420 leçons, 359 HTML et 81 quiz.
Il manque le HTML de Audiovisuel M1 L4 et celui des 30 leçons Restauration et
des 30 leçons Secrétariat. Réseau & Maintenance M2 n'a pas de quiz.
Les vidéos restent sur Drive et leur accessibilité dépend de leur partage.

Les nouvelles importations enregistrent les URL de téléchargement vidéo direct
avec confirmation, et non plus les URL `/preview`. Pour corriger les leçons déjà en base,
utiliser le correcteur distinct décrit dans [VIDEOS_CORRECTION.md](VIDEOS_CORRECTION.md).
L'import conservateur ne remplace pas les anciens liens existants.

Les nouveaux packs gardent les valeurs de l'import initial : niveau BEPC,
prix par défaut de la base, durées non renseignées. Les prix et durées existants
sont préservés. L'import n'invente pas de politique commerciale ni de liens
entre profils et packs. Tester via l'admin ne nécessite pas ces associations.

Validation automatisée : protection de Digital et des données liées aux
apprenants, simulation, annulation en cas d'ambiguïté, import complet hors ligne,
préservation des bonnes réponses, stabilité intégrale à la deuxième exécution.

```sh
php artisan test --compact --filter=CoursJsonSeederTest
```

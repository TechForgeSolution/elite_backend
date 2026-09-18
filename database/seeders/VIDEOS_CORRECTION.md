# Correction des vidéos Drive

Après déploiement du code, depuis la racine du backend sur le VPS :

```sh
bash correct-video-links-vps.sh
```

Simulation seule :

```sh
bash correct-video-links-vps.sh --dry-run
```

Le script simule puis applique la correction. Il utilise le verrou du script
d'import, s'arrête en cas d'erreur et ne lance ni migration ni import complet.
Le seeder partage également le verrou PHP de CoursJsonSeeder.

## Fichiers à déployer sur le VPS

- `correct-video-links-vps.sh`
- `app/Console/Commands/CorrectCoursVideos.php`
- `app/Services/DirectVideoUrl.php`
- `database/seeders/CoursVideoLinksSeeder.php`
- `public/cours.json`

Pour les prochains imports, déployer également le `CoursJsonSeeder.php` et
le `import-cours-vps.sh` mis à jour. Les nouveaux cours auront directement
les bons liens vidéo.

Commande Artisan équivalente :

```sh
php artisan cours:correct-videos --force
```

Ou le seeder seul, sans simulation préalable automatique :

```sh
php artisan db:seed --class=CoursVideoLinksSeeder --force
```

## Périmètre exact

Seuls `url_video_explication`, `url_video_pratique` et `url_video` des leçons
du catalogue sont candidats. La filière, le module et le numéro de leçon
identifient la ligne. Le lien actuel doit viser le même identifiant Drive
que le fichier source correspondant. Les liens personnalisés, les autres
fichiers Drive et les champs vides sont préservés. Les leçons absentes sont
comptées dans le bilan, sans être créées. Les correspondances ambiguës bloquent
l'opération et annulent les modifications. Digital est protégé.

Le correcteur ne touche ni aux HTML, ni aux quiz, ni aux résultats, achats,
progressions ou timestamps. Une relance ne réécrit pas les URL déjà corrigées.
Avant les changements, il enregistre les anciennes et nouvelles valeurs dans
`storage/app/private/video-links-<date>-<identifiant>.json` (permissions 600).
Ce fichier concerne uniquement les liens ; ce n'est pas une sauvegarde complète
de la base. S'il reste après une erreur, comparer les valeurs en base avant
de l'utiliser pour une restauration ciblée.

Le format utilisé est :

```text
https://drive.usercontent.google.com/download?id=ID_DRIVE&export=download&confirm=t
```

Il n'est pas nécessaire que l'URL se termine par `.mp4` : c'est la réponse
`Content-Type: video/mp4` qui indique le fichier média. Les URL directes
d'un hébergement MP4 présentes dans une future source sont aussi acceptées
pour les nouveaux cours.

## Modification mobile indispensable

L'écran de leçon utilisait une WebView qui naviguait vers le lien reçu.
Il utilise désormais une page locale minimale contenant `<video controls>` :
le fichier MP4 est lu dans le lecteur intégré, avec les commandes vidéo,
sans charger la page ni le lecteur Google Drive. Les intégrations YouTube
restent compatibles et l'affichage des théories HTML est inchangé.

Déployer aussi la mise à jour de l'application contenant :

- `elite_mobile/src/screens/courses/LessonScreen.tsx`
- `elite_mobile/src/utils/lessonVideoSource.js`

Aucune nouvelle dépendance mobile n'a été ajoutée. Mettre à jour l'application
par son processus habituel puis recharger la leçon. Exécuter seulement le
script VPS ne change pas le lecteur d'une application déjà installée.

## Vérifications et limites

Les deux URL échantillonnées (explication de 23 Mo et pratique de 929 Mo)
renvoient `video/mp4` avec `Accept-Ranges: bytes` sur le lien direct corrigé.
Le lien standard sans confirmation de la grosse vidéo renvoyait du HTML.
Ce constat ne valide pas toutes les vidéos : les fichiers doivent rester
accessibles sans connexion Google, avec téléchargement autorisé. Drive peut
imposer des quotas ou des confirmations supplémentaires ; les liens ne sont
pas un hébergement vidéo garanti. Le correcteur ne télécharge pas les vidéos
sur le VPS et ne contourne aucune permission.

Documentation Google sur le téléchargement des fichiers :
https://developers.google.com/workspace/drive/api/guides/manage-downloads

Tester sur Android/iOS une vidéo explicative et une vidéo pratique, notamment
une grosse vidéo : démarrage, pause, déplacement dans la durée, plein écran.
La lecture sur appareil n'a pas été exécutée dans l'environnement de développement.

Dans l'administration, le bouton de test ouvre désormais une page interne avec
un lecteur HTML5. Ouvrir l'URL Drive elle-même déclenche un téléchargement car
Drive répond avec `Content-Disposition: attachment`; ce comportement est normal.
Le lecteur admin et le lecteur mobile demandent `preload="metadata"`, puis le
navigateur charge les plages d'octets nécessaires à la lecture. Ils ne créent
pas volontairement une copie complète avant le démarrage. Le volume réellement
transféré dépend du tampon du système, des déplacements dans la vidéo et du cache.

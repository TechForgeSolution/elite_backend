<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Test vidéo — {{ $lesson->titre }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: #040d24; color: #fff; font-family: Arial, sans-serif; display: grid; place-items: center; }
        main { width: min(1100px, 100%); padding: 24px; }
        h1 { margin: 0 0 6px; font-size: clamp(20px, 3vw, 32px); }
        p { color: #b9c9df; margin: 0 0 18px; }
        video { display: block; width: 100%; max-height: 75vh; background: #000; border-radius: 14px; }
        .notice { margin-top: 12px; font-size: 13px; }
    </style>
</head>
<body>
<main>
    <h1>{{ $lesson->titre }}</h1>
    <p>Vidéo {{ $part === 'pratique' ? 'pratique' : "d'explication" }}</p>
    <video controls autoplay playsinline preload="metadata" crossorigin="anonymous">
        <source src="{{ $url }}" type="video/mp4">
        Votre navigateur ne prend pas en charge la lecture vidéo HTML5.
    </video>
    <p class="notice">La lecture utilise le streaming progressif : seules les portions nécessaires sont chargées.</p>
</main>
</body>
</html>

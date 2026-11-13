<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vérification WhatsApp — Elite 2.0</title>
  <style>
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #F4F6FA; color: #172033; }
    .card { width: 100%; max-width: 440px; padding: 32px; border-radius: 22px; background: #fff; box-shadow: 0 16px 48px rgba(23,32,51,.08); }
    h1 { margin: 0 0 8px; font-size: 24px; }
    p { color: #667085; line-height: 1.5; }
    label { display: block; margin: 22px 0 7px; color: #344054; font-size: 13px; font-weight: 600; }
    input { width: 100%; padding: 14px; border: 1px solid #D0D5DD; border-radius: 12px; font-size: 22px; letter-spacing: 8px; text-align: center; }
    button { width: 100%; padding: 14px; margin-top: 16px; border: 0; border-radius: 12px; color: #fff; background: #172F64; font-size: 15px; font-weight: 700; cursor: pointer; }
    .secondary { color: #344054; background: #F2F4F7; }
    .alert { padding: 12px; border-radius: 10px; color: #B42318; background: #FEF3F2; font-size: 14px; }
    .status { color: #067647; font-size: 14px; }
  </style>
</head>
<body>
<main class="card">
  <h1>Vérifiez votre WhatsApp</h1>
  <p>Un code à 6 chiffres a été envoyé au <strong>+237 {{ $telephone }}</strong>. Il ne reste qu’une étape !</p>

  @if($errors->any())
    <div class="alert" role="alert">{{ $errors->first() }}</div>
  @endif
  @if(session('status'))
    <p class="status">{{ session('status') }}</p>
  @endif

  <form method="POST" action="{{ route('web.verify-whatsapp.submit') }}">
    @csrf
    <label for="code">Code de vérification</label>
    <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required autofocus>
    <button type="submit">Vérifier et terminer</button>
  </form>

  <form method="POST" action="{{ route('web.resend-whatsapp') }}">
    @csrf
    <button class="secondary" type="submit">Renvoyer le code sur WhatsApp</button>
  </form>
</main>
</body>
</html>

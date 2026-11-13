<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inscription — Elite 2.0</title>
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
      padding: 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      color: #172033; background: #F4F6FA;
    }
    .card { width: 100%; max-width: 480px; padding: 32px; border-radius: 22px; background: #fff; box-shadow: 0 16px 48px rgba(23,32,51,.08); }
    .logo { text-align: center; margin-bottom: 22px; }
    .logo h1 { margin: 0; font-size: 26px; }
    .logo p { margin: 5px 0 0; color: #667085; font-size: 14px; }
    .referral-banner { display: flex; gap: 10px; padding: 12px; margin-bottom: 16px; border-radius: 12px; background: #ECFDF5; color: #065F46; font-size: 13px; line-height: 1.5; }
    .progress { height: 5px; margin: 22px 0 10px; overflow: hidden; border-radius: 99px; background: #E5E7EB; }
    .progress-fill { width: 12.5%; height: 100%; background: #314B83; transition: width .2s ease; }
    .progress-message { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 20px; color: #667085; font-size: 13px; }
    .progress-message strong { color: #314B83; white-space: nowrap; }
    .step { display: none; }
    .step.active { display: block; }
    .step-title { margin: 0 0 18px; font-size: 20px; }
    .form-group { margin-bottom: 16px; }
    label { display: block; margin-bottom: 6px; color: #344054; font-size: 13px; font-weight: 600; }
    input, select {
      width: 100%; padding: 13px 15px; border: 1px solid #D0D5DD; border-radius: 12px;
      outline: none; color: #172033; background: #fff; font-size: 16px;
    }
    input:focus, select:focus { border-color: #314B83; box-shadow: 0 0 0 3px rgba(49,75,131,.1); }
    input[readonly] { background: #F9FAFB; }
    .actions { display: flex; gap: 10px; margin-top: 20px; }
    .button {
      flex: 1; padding: 14px 16px; border: 0; border-radius: 12px; cursor: pointer;
      font-size: 15px; font-weight: 700;
    }
    .primary { color: #fff; background: #172F64; }
    .secondary { flex: 0 0 auto; color: #344054; background: #F2F4F7; }
    .terms { margin: 18px 0 0; color: #667085; text-align: center; font-size: 12px; line-height: 1.5; }
    .alert { padding: 12px; margin-bottom: 14px; border-radius: 10px; color: #B42318; background: #FEF3F2; font-size: 14px; }
    @media (max-width: 480px) { .card { padding: 26px 20px; } }
  </style>
</head>
<body>
<main class="card">
  <header class="logo">
    <h1>Elite 2.0</h1>
    <p>Votre plateforme de formation professionnelle</p>
  </header>

  @if($referralCode && $parrainName)
    <div class="referral-banner">
      <span aria-hidden="true">🎉</span>
      <span>Vous avez été invité(e) par <strong>{{ $parrainName }}</strong>.</span>
    </div>
  @endif

  @if($errors->any())
    <div class="alert" role="alert">{{ $errors->first() }}</div>
  @endif

  <div class="progress" aria-hidden="true"><div class="progress-fill" id="progressFill"></div></div>
  <div class="progress-message">
    <span id="progressMessage">Une question à la fois, vous avancez bien.</span>
    <strong id="stepCount">1/8</strong>
  </div>

  <form id="registerForm" action="{{ route('web.register.submit') }}" method="POST">
    @csrf
    <section class="step active">
      <h2 class="step-title">Comment vous appelez-vous ?</h2>
      <div class="form-group">
        <label for="prenom">Prénom</label>
        <input id="prenom" name="prenom" value="{{ old('prenom') }}" placeholder="Ex. : Jean" required autocomplete="given-name">
      </div>
    </section>
    <section class="step">
      <h2 class="step-title">Quel est votre nom ?</h2>
      <div class="form-group">
        <label for="nom">Nom</label>
        <input id="nom" name="nom" value="{{ old('nom') }}" placeholder="Ex. : Dupont" required autocomplete="family-name">
      </div>
    </section>
    <section class="step">
      <h2 class="step-title">Votre numéro WhatsApp</h2>
      <div class="form-group">
        <label for="telephone">Numéro WhatsApp (+237)</label>
        <input id="telephone" name="telephone" type="tel" inputmode="tel" value="{{ old('telephone') }}" placeholder="6XXXXXXXX" required autocomplete="tel" maxlength="13">
      </div>
    </section>
    <section class="step">
      <h2 class="step-title">Votre niveau d’études</h2>
      <div class="form-group">
        <label for="dernier_diplome">Dernier diplôme</label>
        <select id="dernier_diplome" name="dernier_diplome" required>
          <option value="">Sélectionner...</option>
          @foreach(['BEPC', 'Probatoire', 'BAC', 'BTS', 'Licence', 'Master', 'Doctorat', 'Autre'] as $diploma)
            <option value="{{ $diploma }}" @selected(old('dernier_diplome') === $diploma)>{{ $diploma }}</option>
          @endforeach
        </select>
      </div>
    </section>
    <section class="step">
      <h2 class="step-title">Où habitez-vous ?</h2>
      <div class="form-group">
        <label for="ville">Ville</label>
        <input id="ville" name="ville" value="{{ old('ville') }}" placeholder="Ex. : Douala" required autocomplete="address-level2">
      </div>
    </section>
    <section class="step">
      <h2 class="step-title">Choisissez un mot de passe</h2>
      <div class="form-group">
        <label for="password">Mot de passe</label>
        <input id="password" name="password" type="password" minlength="6" placeholder="Au moins 6 caractères" required autocomplete="new-password">
      </div>
    </section>
    <section class="step">
      <h2 class="step-title">Confirmez votre mot de passe</h2>
      <div class="form-group">
        <label for="password_confirmation">Confirmer le mot de passe</label>
        <input id="password_confirmation" name="password_confirmation" type="password" minlength="6" placeholder="Répétez le mot de passe" required autocomplete="new-password">
      </div>
    </section>
    <section class="step">
      <h2 class="step-title">C’est presque fini !</h2>
      <div class="form-group">
        <label for="referral_code">Code de parrainage / centre partenaire</label>
        <input id="referral_code" name="referral_code" value="{{ old('referral_code', $referralCode) }}" placeholder="Facultatif" {{ $referralCode ? 'readonly' : '' }}>
      </div>
      <p class="terms">Vous recevrez ensuite un code sur WhatsApp pour vérifier votre numéro.</p>
    </section>
    <div class="actions">
      <button type="button" class="button secondary" id="backBtn" hidden>Retour</button>
      <button type="button" class="button primary" id="nextBtn">Continuer</button>
      <button type="submit" class="button primary" id="submitBtn" hidden>Recevoir mon code WhatsApp</button>
    </div>
  </form>

  <p class="terms">Inscription par numéro WhatsApp uniquement. Aucun compte n’est activé avant vérification.</p>
</main>
<script>
  const steps = [...document.querySelectorAll('.step')];
  const form = document.getElementById('registerForm');
  let currentStep = 0;

  function showStep(index) {
    currentStep = index;
    steps.forEach((step, stepIndex) => {
      step.classList.toggle('active', stepIndex === index);
      step.querySelectorAll('input, select').forEach(input => {
        input.required = stepIndex === index && input.name !== 'referral_code';
      });
    });
    document.getElementById('stepCount').textContent = `${index + 1}/${steps.length}`;
    document.getElementById('progressFill').style.width = `${((index + 1) / steps.length) * 100}%`;
    document.getElementById('progressMessage').textContent = index >= 5
      ? 'C’est presque fini, encore quelques instants.'
      : 'Une question à la fois, vous avancez bien.';
    document.getElementById('backBtn').hidden = index === 0;
    document.getElementById('nextBtn').hidden = index === steps.length - 1;
    document.getElementById('submitBtn').hidden = index !== steps.length - 1;
  }

  document.getElementById('nextBtn').addEventListener('click', () => {
    const input = steps[currentStep].querySelector('input, select');
    if (input && !input.reportValidity()) return;
    if (currentStep === 6 && document.getElementById('password').value !== document.getElementById('password_confirmation').value) {
      const confirmation = document.getElementById('password_confirmation');
      confirmation.setCustomValidity('Les mots de passe ne correspondent pas.');
      confirmation.reportValidity();
      confirmation.setCustomValidity('');
      return;
    }
    showStep(currentStep + 1);
  });
  document.getElementById('backBtn').addEventListener('click', () => showStep(currentStep - 1));
  form.addEventListener('submit', event => {
    const password = document.getElementById('password').value;
    const confirmation = document.getElementById('password_confirmation').value;
    if (password !== confirmation) {
      event.preventDefault();
      const input = document.getElementById('password_confirmation');
      input.setCustomValidity('Les mots de passe ne correspondent pas.');
      input.reportValidity();
      input.setCustomValidity('');
    }
  });
  showStep(0);
</script>
</body>
</html>

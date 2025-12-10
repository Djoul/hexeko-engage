<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Custom Registration</title>
  <script src="https://cdn.jsdelivr.net/npm/amazon-cognito-identity-js@6.3.3/dist/amazon-cognito-identity.min.js"></script>
  @vite('resources/css/app.css')
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
  <div class="w-full max-w-xl bg-white rounded-2xl shadow-2xl p-10 space-y-10 border border-gray-100">
    <div class="space-y-2 border-b border-gray-900/10 pb-8">
      <h1 class="text-3xl font-bold text-center text-indigo-700">Bienvenue {{ $invitedUser->first_name }} {{ $invitedUser->last_name }} !</h1>
      <p class="text-center text-gray-600">Vous êtes invité à rejoindre <span class="font-semibold text-indigo-600">{{ $invitedUser->financer->name }}</span></p>
    </div>

    <div id="choice" class="flex flex-col gap-4">
      <button onclick="showNewUserForm()" class="w-full py-2 px-4 rounded-md bg-indigo-600 text-white font-semibold shadow-sm hover:bg-indigo-700 transition">Créer un nouvel utilisateur</button>
      <button onclick="showExistingUserForm()" class="w-full py-2 px-4 rounded-md bg-gray-200 text-indigo-700 font-semibold shadow-sm hover:bg-gray-300 transition">Continuer avec un compte existant</button>
    </div>

    <!-- Nouvel utilisateur -->
    <form id="newUserForm" style="display:none;" onsubmit="submitNewUser(event)" class="space-y-8">
      <div class="border-b border-gray-900/10 pb-8">
        <h2 class="text-lg font-semibold text-gray-900">Créer un nouveau compte</h2>
        <div class="mt-6 grid grid-cols-1 gap-x-6 gap-y-6 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <label for="newEmail" class="block text-sm font-medium text-gray-900">Email</label>
            <input type="email" id="newEmail" placeholder="Email" value="{{$invitedUser->email}}" required class="input-tw mt-2" />
          </div>
          <div class="sm:col-span-2">
            <label for="newPhone" class="block text-sm font-medium text-gray-900">Téléphone</label>
            <input type="text" id="newPhone" placeholder="Téléphone" required class="input-tw mt-2" value="{{ $invitedUser->phone ?? '' }}" />
          </div>
          <div>
            <label for="newPassword" class="block text-sm font-medium text-gray-900">Mot de passe</label>
            <input type="password" id="newPassword" placeholder="Mot de passe" required class="input-tw mt-2" />
          </div>
          <div>
            <label for="newConfirm" class="block text-sm font-medium text-gray-900">Confirmer mot de passe</label>
            <input type="password" id="newConfirm" placeholder="Confirmer mot de passe" required class="input-tw mt-2" />
          </div>
        </div>
      </div>
      <div class="flex gap-2 justify-between">
        <button type="button" onclick="backToChoice()" class="rounded bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Retour</button>
        <button type="submit" class="rounded bg-indigo-600 px-2 py-1 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Créer mon compte</button>
      </div>
    </form>

    <!-- Formulaire de vérification du code -->
    <form id="verifyCodeForm" style="display:none;" onsubmit="submitVerificationCode(event)" class="space-y-8">
      <div class="border-b border-gray-900/10 pb-8">
        <h2 class="text-lg font-semibold text-gray-900">Vérification de l'email</h2>
        <p class="mt-1 text-sm text-gray-600">Un code de vérification a été envoyé à votre adresse email. Veuillez le saisir ci-dessous :</p>
        <input type="text" id="verificationCode" placeholder="Code de vérification" required class="input-tw mt-6" />
        <div class="flex flex-col items-end mt-4 gap-2">
          <button type="button" onclick="resendVerificationCode()" class="rounded bg-gray-100 px-2 py-1 text-xs font-semibold text-indigo-700 shadow-sm hover:bg-indigo-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Renvoyer le code</button>
          <span id="resendCodeMsg" class="text-xs text-green-600 hidden"></span>
        </div>
      </div>
      <div class="flex gap-2 justify-between">
        <button type="button" onclick="backToChoice()" class="rounded bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Retour</button>
        <button type="submit" class="rounded bg-indigo-600 px-2 py-1 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Vérifier mon email</button>
      </div>
    </form>

    <div id="successMessage" style="display:none;" class="text-center space-y-2 pt-6">
      <h2 class="text-lg font-bold text-green-600">Email vérifié !</h2>
      <p class="text-gray-700">Votre compte a été confirmé.<br>Vous pouvez maintenant installer l'application et vous connecter.</p>
    </div>

    <!-- Utilisateur existant -->
    <form id="existingUserForm" style="display:none;" onsubmit="submitExistingUser(event)" class="space-y-8">
      <div class="border-b border-gray-900/10 pb-8">
        <h2 class="text-lg font-semibold text-gray-900">Connexion avec compte existant</h2>
        <div class="mt-6 grid grid-cols-1 gap-x-6 gap-y-6 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <label for="existingEmail" class="block text-sm font-medium text-gray-900">Email</label>
            <input type="email" id="existingEmail" placeholder="Email" required class="input-tw mt-2" />
          </div>
          <div class="sm:col-span-2">
            <label for="existingPassword" class="block text-sm font-medium text-gray-900">Mot de passe</label>
            <input type="password" id="existingPassword" placeholder="Mot de passe" required class="input-tw mt-2" />
          </div>
        </div>
      </div>
      <div class="flex gap-2 justify-between">
        <button type="button" onclick="backToChoice()" class="rounded bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Retour</button>
        <button type="submit" class="rounded bg-indigo-600 px-2 py-1 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Continuer</button>
      </div>
    </form>
  </div>

  <script>

    const userPool = new AmazonCognitoIdentity.CognitoUserPool({
      UserPoolId: '{{ config('services.cognito.user_pool_id') }}',
      ClientId: '3b82s449rdulkrs0sh9q3rif8p',

    });

    function showNewUserForm() {
      document.getElementById('choice').style.display = 'none';
      document.getElementById('newUserForm').style.display = 'block';
    }

    function showExistingUserForm() {
      document.getElementById('choice').style.display = 'none';
      document.getElementById('existingUserForm').style.display = 'block';
    }

    async function submitNewUser(e) {
      e.preventDefault();
      const email = document.getElementById('newEmail').value;
      const phone = document.getElementById('newPhone').value;
      const password = document.getElementById('newPassword').value;
      const confirm = document.getElementById('newConfirm').value;

      if (password !== confirm) {
        alert("Les mots de passe ne correspondent pas.");
        return;
      }

      const attributeList = [
        new AmazonCognitoIdentity.CognitoUserAttribute({ Name: "email", Value: email }),
        new AmazonCognitoIdentity.CognitoUserAttribute({ Name: "phone_number", Value: phone }),
        new AmazonCognitoIdentity.CognitoUserAttribute({ Name: "given_name", Value: "{{ $invitedUser->first_name }}" }),
        new AmazonCognitoIdentity.CognitoUserAttribute({ Name: "name", Value: "{{ $invitedUser->last_name }}" }),
        new AmazonCognitoIdentity.CognitoUserAttribute({ Name: "custom:invited_user_id", Value: "{{ $invitedUser->id }}" }),
        new AmazonCognitoIdentity.CognitoUserAttribute({ Name: "custom:financer_id", Value: "{{ $invitedUser->financer_id }}" })
      ];

      userPool.signUp(email, password, attributeList, null, function(err, result) {
        if (err) {
          alert(err.message || JSON.stringify(err));
          return;
        }

        // Display the code verification form
        document.getElementById('newUserForm').style.display = 'none';
        document.getElementById('verifyCodeForm').style.display = 'block';
        // Pre-fill the email for verification
        window._pendingVerificationEmail = email;
      });
    }

    function submitVerificationCode(e) {
      e.preventDefault();
      const code = document.getElementById('verificationCode').value;
      const email = window._pendingVerificationEmail;
      const userData = {
        Username: email,
        Pool: userPool
      };
      const cognitoUser = new AmazonCognitoIdentity.CognitoUser(userData);
      cognitoUser.confirmRegistration(code, true, function(err, result) {
        console.log("err", err)
        console.log("result", result)
        if (err) {
          alert(err.message || JSON.stringify(err));
          return;
        }
        document.getElementById('verifyCodeForm').style.display = 'none';
        document.getElementById('successMessage').style.display = 'block';
      });
    }

    function resendVerificationCode() {
      const email = window._pendingVerificationEmail;
      const userData = {
        Username: email,
        Pool: userPool
      };
      const cognitoUser = new AmazonCognitoIdentity.CognitoUser(userData);
      cognitoUser.resendConfirmationCode(function(err, result) {
        const msg = document.getElementById('resendCodeMsg');
        if (err) {
          msg.textContent = err.message || 'Erreur lors de l\'envoi du code';
          msg.className = 'text-xs text-red-600';
          msg.classList.remove('hidden');
        } else {
          msg.textContent = 'Un nouveau code a été envoyé à votre email.';
          msg.className = 'text-xs text-green-600';
          msg.classList.remove('hidden');
        }
      });
    }

    async function submitExistingUser(e) {
      e.preventDefault();
      const email = document.getElementById('existingEmail').value;
      const password = document.getElementById('existingPassword').value;
      const userData = {
        Username: email,
        Pool: userPool
      };
      const cognitoUser = new AmazonCognitoIdentity.CognitoUser(userData);
      cognitoUser.authenticateUser(new AmazonCognitoIdentity.AuthenticationDetails({
        Username: email,
        Password: password
      }), {
        onSuccess: function (result) {
          // Call the Laravel /merge-user API here with the necessary info
          alert('Connexion réussie.');
        },
        onFailure: function (err) {
          alert(err.message || JSON.stringify(err));
        }
      });
    }

    function backToChoice() {
      document.getElementById('newUserForm').style.display = 'none';
      document.getElementById('existingUserForm').style.display = 'none';
      document.getElementById('verifyCodeForm').style.display = 'none';
      document.getElementById('choice').style.display = 'flex';
    }
  </script>

  <style>
    .input-tw {
      @apply w-full px-4 py-2 border border-gray-300 rounded-md bg-white text-gray-900 placeholder:text-gray-400 outline outline-1 -outline-offset-1 outline-gray-300 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 transition;
    }
  </style>
</body>
</html>

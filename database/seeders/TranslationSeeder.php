<?php

namespace Database\Seeders;

use App\Enums\Languages;
use App\Enums\OrigineInterfaces;
use App\Models\TranslationKey;
use App\Models\TranslationValue;
use DB;
use Illuminate\Database\Seeder;

/*
 * will be removed in the future
 * @deprecated
 * */
class TranslationSeeder extends Seeder
{
    public function run(): void
    {

        DB::table('translation_keys')->truncate();
        DB::table('translation_values')->truncate();

        $translations = [
            Languages::ENGLISH => [
                'auth' => [
                    'signIn' => 'Sign in',
                    'send' => 'Send',
                    'forgotPassword' => 'Forgot password',
                    'verification' => 'Verification',
                    'newPassword' => 'New password',
                    'codeSent' => 'An authentication code has been sent to {{phone_number}}',
                    'checkIdentity' => 'Verify your identity',
                    'setEmail' => 'Enter your email address to recover your password.',
                    'login' => 'Login',
                    'title' => 'Centralize your benefits, optimize your daily life.',
                    'forgotPasswordQuestion' => 'Forgot password?',
                    'passwordUpdated' => 'Password successfully updated',
                    'loginNewPassword' => 'You can now login with your new password.',
                    'addNewPassword' => 'Add new password',
                    'passwordCharacters' => 'At least 8 characters, with upper and lower case letters.',
                    'verificationCode' => 'Verification code',
                    'confirmPassword' => 'Confirm password',
                ],
                'account' => [
                    'notifByEmail' => 'Email notifications',
                    'notifByPush' => 'Phone notifications',
                    'terms' => 'Terms and conditions',
                    'security' => 'Security',
                    'notifications' => 'Notifications',
                    'language' => 'Language',
                    'changeSettings' => 'Modify email and phone notification settings and manage yourself.',
                    'settings' => 'Settings',
                    'fr' => 'French',
                    'en' => 'English',
                    'password' => 'Password',
                    'localAuth' => 'Biometric authentication',
                    'activateFaceID' => 'Activate FaceID or TouchID',
                    'firstname' => 'First name',
                    'lastname' => 'Last name',
                    'email' => 'Email',
                    'informations' => 'Information',
                    'personalInformations' => 'Personal information',
                    'FAQ' => 'FAQ',
                    'contact' => 'Contact',
                    'languages' => [
                        'fr' => 'Français (French)',
                        'en' => 'English',
                    ],
                    'helloFriends' => [
                        'fr' => 'Bonjour, mon ami !',
                        'en' => 'Hello, my friend!',
                    ],
                    'selectLanguage' => 'Select your language to customize your experience.',
                ],
                'purchase' => [
                    'favorites' => 'FAVORITES',
                    'modules' => 'Modules',
                ],
                'tabs' => [
                    'home' => 'Home',
                    'account' => 'Account',
                    'purchase' => 'Purchasing power',
                    'wellness' => 'Wellness',
                    'enterprise' => 'Company',
                ],
                'communications' => [
                    'InternalCommunication\S' => 'Internal communications',
                    'tags' => 'Tags',
                    'share' => 'Share',
                    'like' => 'Like',
                ],
                'update' => [
                    'updateAvailable' => 'Update available',
                    'restart' => 'A new update is available. Please restart the application to install it.',
                ],
                'home' => [
                    'welcome' => 'Hello, {{name}}.',
                    'HRTools' => 'HR tools and benefits at your fingertips.',
                ],
                'modules' => [
                    'pin' => 'Pin',
                    'description' => 'Description',
                ],
            ],
            Languages::FRENCH => [
                'auth' => [
                    'signIn' => 'Se connecter',
                    'send' => 'Envoyer',
                    'forgotPassword' => 'Mot de passe oublié',
                    'verification' => 'Vérification',
                    'newPassword' => 'Nouveau mot de passe',
                    'codeSent' => "Un code d'authentification a été envoyé à {{phone_number}}",
                    'checkIdentity' => 'Vérifiez votre identité',
                    'setEmail' => 'Saisissez votre adresse e-mail pour récupérer votre mot de passe.',
                    'login' => 'Connexion',
                    'title' => 'Centralisez  vos avantages, optimisez votre quotidien.',
                    'forgotPasswordQuestion' => 'Mot de passe oublié ?',
                    'passwordUpdated' => 'Mot de passe modifié avec succès',
                    'loginNewPassword' => 'Vous pouvez maintenant vous connecter avec votre nouveau mot de passe.',
                    'addNewPassword' => 'Ajouter un nouveau mot de passe',
                    'passwordCharacters' => 'Au moins 8 caractères, avec des lettres majuscules et minuscules.',
                    'verificationCode' => 'Code de verification',
                    'confirmPassword' => 'Confirmer le mot de passe',
                ],
                'account' => [
                    'notifByEmail' => 'Notifications par e-mail',
                    'notifByPush' => 'Notifications par téléphone',
                    'terms' => 'Conditions générales',
                    'security' => 'Sécurité',
                    'notifications' => 'Notifications',
                    'language' => 'Langue',
                    'changeSettings' => 'Modifiez les paramètres de notification par e-mail et par téléphone et gérez-vous-même.',
                    'settings' => 'Paramètres',
                    'fr' => 'Français',
                    'en' => 'Anglais',
                    'password' => 'Mot de passe',
                    'localAuth' => 'Reconnaissance digitale',
                    'activateFaceID' => 'Activez FaceID ou TouchID',
                    'firstname' => 'Prénom',
                    'lastname' => 'Nom',
                    'email' => 'Email',
                    'informations' => 'Informations',
                    'personalInformations' => 'Informations personnelles',
                    'FAQ' => 'FAQ',
                    'contact' => 'Contact',
                    'languages' => [
                        'fr' => 'Français (French)',
                        'en' => 'English',
                    ],
                    'helloFriends' => [
                        'fr' => 'Bonjour, mon ami !',
                        'en' => 'Hello, my friend!',
                    ],
                    'selectLanguage' => 'Sélectionnez votre langue pour personnaliser votre expérience.',
                ],
                'purchase' => [
                    'favorites' => 'FAVORIS',
                    'modules' => 'Modules',
                ],
                'tabs' => [
                    'home' => 'Accueil',
                    'account' => 'Compte',
                    'purchase' => "Pouvoir d'achat",
                    'wellness' => 'Bien-être',
                    'enterprise' => 'Entreprise',
                ],
                'communications' => [
                    'InternalCommunication\S' => 'Communications internes',
                    'tags' => 'Tags',
                    'share' => 'Partager',
                    'like' => 'Like',
                ],
                'update' => [
                    'updateAvailable' => 'Mise à jour disponible',
                    'restart' => "Une nouvelle mise à jour est disponible. Veuillez redémarrer l'application pour l'installer.",
                ],
                'home' => [
                    'welcome' => 'Bonjour, {{name}}.',
                    'HRTools' => 'Des outils RH et avantages à portée de main.',
                ],
                'modules' => [
                    'pin' => 'Epingler',
                    'description' => 'Description',
                ],
            ],
            Languages::FRENCH_BELGIUM => [
                'auth' => [
                    'signIn' => 'Se Logguer',
                ],
            ],
        ];

        foreach ($translations as $locale => $groups) {
            foreach ($groups as $group => $keys) {
                foreach ($keys as $key => $value) {
                    if (is_array($value)) {
                        $this->processNestedArray($value, $group, $key, $locale);

                        continue;
                    }

                    $translationKey = TranslationKey::firstOrCreate([
                        'group' => $group,
                        'key' => $key,
                        'interface_origin' => OrigineInterfaces::WEB_FINANCER,
                    ]);

                    TranslationValue::firstOrCreate([
                        'translation_key_id' => $translationKey->id,
                        'locale' => $locale,
                        'value' => $value,
                    ]);
                }

            }
        }
    }

    private function processNestedArray(
        array $array,
        string $group,
        string $parentKey,
        string $locale,
        string $prefix = ''
    ): void {
        foreach ($array as $key => $value) {
            $currentKey = $prefix !== '' && $prefix !== '0' ? $prefix.'.'.$key : $parentKey.'.'.$key;

            if (is_array($value)) {
                $this->processNestedArray($value, $group, $key, $locale, $currentKey);

                continue;
            }

            $translationKey = TranslationKey::firstOrCreate([
                'group' => $group,
                'key' => $currentKey,
            ]);

            TranslationValue::firstOrCreate([
                'translation_key_id' => $translationKey->id,
                'locale' => $locale,
                'value' => $value,
            ]);
        }
    }
}

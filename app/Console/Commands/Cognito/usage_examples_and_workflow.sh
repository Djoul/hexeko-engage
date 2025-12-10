#!/bin/bash

# ═══════════════════════════════════════════════════════════════
#                    GUIDE D'UTILISATION COGNITO
# ═══════════════════════════════════════════════════════════════

echo "🚀 Installation et configuration"
echo "================================"

# 1. Installer les dépendances
# composer require aws/aws-sdk-php

# 2. Créer la migration
# php artisan make:migration add_cognito_id_to_users_table
# php artisan migrate

# 3. Configurer les variables d'environnement dans .env
cat << EOF
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=eu-west-1
AWS_COGNITO_USER_POOL_ID=eu-west-1_xxxxxxxxx
AWS_COGNITO_CLIENT_ID=your_client_id
AWS_COGNITO_CLIENT_SECRET=your_client_secret
EOF

echo ""
echo "📊 WORKFLOW COMPLET DE SYNCHRONISATION"
echo "======================================"

echo ""
echo "1️⃣  ÉTAT INITIAL - Vérifier la situation actuelle"
echo "------------------------------------------------"
echo "# Voir les statistiques générales"
echo "php artisan cognito:stats"
echo ""
echo "# Vérifier l'état de synchronisation"
echo "php artisan cognito:check-sync"
echo ""
echo "# Version détaillée avec export CSV"
echo "php artisan cognito:check-sync --detailed --export=sync_status.csv"

echo ""
echo "2️⃣  SYNCHRONISATION INITIALE - Première synchronisation"
echo "--------------------------------------------------------"
echo "# Mode test pour voir ce qui va se passer (RECOMMANDÉ)"
echo "php artisan cognito:sync-all-users --dry-run --verbose"
echo ""
echo "# Synchronisation réelle de tous les utilisateurs"
echo "php artisan cognito:sync-all-users"
echo ""
echo "# Ou seulement les utilisateurs sans cognito_id"
echo "php artisan cognito:sync-all-users --only-missing"
echo ""
echo "# Pour de gros volumes avec pause entre les lots"
echo "php artisan cognito:sync-all-users --batch-size=25 --delay=2 --verbose"

echo ""
echo "3️⃣  MAINTENANCE - Synchronisation incrémentale"
echo "------------------------------------------------"
echo "# Synchroniser seulement les nouveaux utilisateurs"
echo "php artisan cognito:sync-all-users --only-missing --batch-size=10"
echo ""
echo "# Valider que les IDs existants sont corrects"
echo "php artisan cognito:validate-ids --limit=50"
echo ""
echo "# Corriger automatiquement les IDs invalides"
echo "php artisan cognito:validate-ids --fix"

echo ""
echo "4️⃣  SURVEILLANCE - Contrôles réguliers"
echo "----------------------------------------"
echo "# Statistiques quotidiennes"
echo "php artisan cognito:stats"
echo ""
echo "# Vérification hebdomadaire"
echo "php artisan cognito:check-sync --detailed"
echo ""
echo "# Validation mensuelle des IDs"
echo "php artisan cognito:validate-ids --limit=100"

echo ""
echo "📋 EXEMPLES D'UTILISATION PAR CAS D'USAGE"
echo "=========================================="

echo ""
echo "🆕 Nouveau projet Laravel avec utilisateurs existants"
echo "------------------------------------------------------"
echo "1. php artisan cognito:check-sync                     # État initial"
echo "2. php artisan cognito:sync-all-users --dry-run       # Test"
echo "3. php artisan cognito:sync-all-users                 # Synchronisation complète"
echo "4. php artisan cognito:stats                          # Vérification finale"

echo ""
echo "📈 Maintenance quotidienne (automatisée)"
echo "------------------------------------------"
echo "# À ajouter dans le Kernel.php ou crontab :"
echo "# php artisan cognito:sync-all-users --only-missing --batch-size=20 >/dev/null 2>&1"

echo ""
echo "🔧 Résolution de problèmes"
echo "---------------------------"
echo "# Problème : Des utilisateurs ont des cognito_id incorrects"
echo "php artisan cognito:validate-ids --fix"
echo ""
echo "# Problème : Synchronisation en échec"
echo "php artisan cognito:sync-all-users --only-missing --batch-size=5 --delay=3 --verbose"
echo ""
echo "# Problème : Besoin d'un audit complet"
echo "php artisan cognito:check-sync --detailed --export=audit_$(date +%Y%m%d).csv"

echo ""
echo "🔄 UTILISATION VIA API REST"
echo "============================"

echo ""
echo "Synchroniser tous les utilisateurs :"
echo "curl -X POST http://your-app.com/api/cognito/users/synchronize/all \\"
echo "     -H 'Content-Type: application/json' \\"
echo "     -H 'Authorization: Bearer YOUR_TOKEN'"

echo ""
echo "Synchroniser des utilisateurs spécifiques :"
echo "curl -X POST http://your-app.com/api/cognito/users/synchronize \\"
echo "     -H 'Content-Type: application/json' \\"
echo "     -d '{\"user_ids\": [1, 2, 3, 4, 5]}'"

echo ""
echo "Synchroniser seulement les utilisateurs sans cognito_id :"
echo "curl -X POST http://your-app.com/api/cognito/users/synchronize/missing"

echo ""
echo "📝 UTILISATION PROGRAMMATIQUE"
echo "=============================="

cat << 'PHP'
<?php
// Dans un contrôleur ou service

use App\Services\CognitoUserService;
use App\Models\User;

class UserSyncService
{
    public function __construct(private CognitoUserService $cognitoService)
    {}

    // Synchroniser de nouveaux utilisateurs
    public function syncNewUsers()
    {
        $newUsers = User::where('created_at', '>=', now()->subDays(1))
                       ->whereNull('cognito_id')
                       ->get();

        if ($newUsers->isNotEmpty()) {
            return $this->cognitoService->synchronizeUsers($newUsers->toArray());
        }

        return ['summary' => ['total_processed' => 0]];
    }

    // Synchroniser par département
    public function syncDepartment($department)
    {
        $users = User::where('department', $department)
                    ->whereNull('cognito_id')
                    ->get();

        return $this->cognitoService->synchronizeUsers($users->toArray());
    }

    // Synchroniser un utilisateur spécifique
    public function syncUser($userId)
    {
        $user = User::findOrFail($userId);
        return $user->syncWithCognito();
    }
}
PHP

echo ""
echo "🎯 BONNES PRATIQUES"
echo "==================="

echo ""
echo "✅ À FAIRE :"
echo "-----------"
echo "• Toujours tester avec --dry-run avant une synchronisation complète"
echo "• Utiliser des lots raisonnables (20-50 utilisateurs) pour éviter les timeouts"
echo "• Ajouter des délais entre les lots pour respecter les limites AWS"
echo "• Monitorer les logs Laravel pour détecter les erreurs"
echo "• Faire des sauvegardes de la DB avant les grosses synchronisations"
echo "• Valider régulièrement les cognito_id avec validate-ids"
echo "• Utiliser --only-missing pour les synchronisations incrémentales"

echo ""
echo "❌ À ÉVITER :"
echo "-------------"
echo "• Synchroniser tous les utilisateurs trop fréquemment"
echo "• Utiliser des lots trop grands (>100) qui peuvent causer des timeouts"
echo "• Ignorer les erreurs dans les logs"
echo "• Oublier de configurer les bonnes permissions IAM"
echo "• Lancer plusieurs synchronisations en parallèle"

echo ""
echo "⚠️  LIMITATIONS AWS À CONNAÎTRE :"
echo "---------------------------------"
echo "• AdminCreateUser: 50 requêtes par seconde"
echo "• AdminGetUser: 50 requêtes par seconde"
echo "• ListUsers: 60 requêtes par minute"
echo "• Quota par défaut : 40,000 utilisateurs par User Pool"
echo ""
echo "💡 Conseil : Utilisez --delay=1 ou plus pour respecter ces limites"

echo ""
echo "📊 MONITORING ET ALERTES"
echo "========================"

echo ""
echo "Commandes à monitorer en production :"
echo "-------------------------------------"
echo "# Nombre d'utilisateurs non synchronisés"
echo "php artisan cognito:check-sync | grep 'Non synchronisés'"
echo ""
echo "# Pourcentage de synchronisation"
echo "php artisan cognito:stats | grep 'Pourcentage'"
echo ""
echo "# Détection d'anomalies (IDs invalides)"
echo "php artisan cognito:validate-ids --limit=10"

echo ""
echo "🔧 TROUBLESHOOTING COURANT"
echo "=========================="

echo ""
echo "Erreur 'UserNotFoundException' :"
echo "• L'utilisateur n'existe pas dans Cognito (normal lors de la création)"
echo ""
echo "Erreur 'InvalidParameterException' :"
echo "• Vérifiez le format des attributs (email valide, etc.)"
echo "• Vérifiez les contraintes de mot de passe"
echo ""
echo "Erreur 'LimitExceededException' :"
echo "• Réduisez la taille des lots avec --batch-size"
echo "• Augmentez le délai avec --delay"
echo ""
echo "Erreur 'UnauthorizedOperation' :"
echo "• Vérifiez les permissions IAM de votre utilisateur AWS"
echo "• Vérifiez la configuration dans le .env"

echo ""
echo "🚀 DÉPLOIEMENT EN PRODUCTION"
echo "============================"

echo ""
echo "1. Test sur l'environnement de staging :"
echo "   php artisan cognito:sync-all-users --dry-run"
echo ""
echo "2. Synchronisation progressive en production :"
echo "   php artisan cognito:sync-all-users --only-missing --batch-size=10 --delay=2"
echo ""
echo "3. Validation post-déploiement :"
echo "   php artisan cognito:stats"
echo "   php artisan cognito:validate-ids --limit=20"
echo ""
echo "4. Mise en place de la surveillance :"
echo "   # Cron job quotidien"
echo "   0 2 * * * cd /path/to/your/app && php artisan cognito:sync-all-users --only-missing >/dev/null 2>&1"
echo "   # Cron job hebdomadaire de validation"
echo "   0 3 * * 0 cd /path/to/your/app && php artisan cognito:validate-ids --fix >/dev/null 2>&1"

echo ""
echo "✨ RÉSUMÉ DES COMMANDES DISPONIBLES"
echo "=================================="
echo "cognito:sync-all-users     - Synchronise tous les utilisateurs avec Cognito"
echo "cognito:check-sync         - Vérifie l'état de la synchronisation"
echo "cognito:validate-ids       - Valide les cognito_id existants"
echo "cognito:stats              - Affiche les statistiques détaillées"
echo ""
echo "Options principales :"
echo "--dry-run                  - Mode simulation sans modification"
echo "--only-missing             - Traite seulement les utilisateurs sans cognito_id"
echo "--batch-size=N             - Taille des lots (défaut: 50)"
echo "--delay=N                  - Délai en secondes entre les lots"
echo "--verbose                  - Affichage détaillé"
echo "--fix                      - Correction automatique (validate-ids)"

echo ""
echo "🎉 CONFIGURATION TERMINÉE !"
echo "==========================="
echo "Votre système de synchronisation Cognito est prêt à l'emploi."
echo "Commencez par : php artisan cognito:check-sync"
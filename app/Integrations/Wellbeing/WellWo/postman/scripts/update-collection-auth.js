#!/usr/bin/env node

// Script pour ajouter l'authentification à la collection Postman WellWo
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const collectionPath = path.join(__dirname, '..', 'WellWo-API.postman_collection.json');
const collection = JSON.parse(fs.readFileSync(collectionPath, 'utf8'));

// Créer la requête de login
const loginRequest = {
  "name": "Authentication",
  "item": [
    {
      "name": "Login",
      "event": [
        {
          "listen": "test",
          "script": {
            "exec": [
              "// Vérifier que le login est réussi",
              "pm.test('Login successful', () => {",
              "    pm.response.to.have.status(200);",
              "    const jsonData = pm.response.json();",
              "    pm.expect(jsonData).to.have.property('authentication_result');",
              "});",
              "",
              "// Extraire et sauvegarder le token",
              "if (pm.response.code === 200) {",
              "    const jsonData = pm.response.json();",
              "    const token = jsonData.authentication_result.AccessToken;",
              "    ",
              "    // Sauvegarder dans l'environnement",
              "    pm.environment.set('auth_token', token);",
              "    ",
              "    // Sauvegarder aussi dans les variables de collection",
              "    pm.collectionVariables.set('auth_token', token);",
              "    ",
              "    // Extraire les infos du token",
              "    const expiresIn = jsonData.authentication_result.ExpiresIn;",
              "    const tokenType = jsonData.authentication_result.TokenType;",
              "    ",
              "    console.log('✅ Authentication successful');",
              "    console.log('Token Type:', tokenType);",
              "    console.log('Expires in:', expiresIn, 'seconds');",
              "    ",
              "    // Sauvegarder l'heure d'expiration",
              "    const expiryTime = Date.now() + (expiresIn * 1000);",
              "    pm.environment.set('token_expiry', expiryTime);",
              "} else {",
              "    console.error('❌ Login failed');",
              "}"
            ],
            "type": "text/javascript"
          }
        },
        {
          "listen": "prerequest",
          "script": {
            "exec": [
              "// Vérifier si on a déjà un token valide",
              "const token = pm.environment.get('auth_token');",
              "const tokenExpiry = pm.environment.get('token_expiry');",
              "",
              "if (token && tokenExpiry && Date.now() < tokenExpiry) {",
              "    console.log('ℹ️ Using existing valid token');",
              "    // Skip cette requête si on a déjà un token valide",
              "    // Malheureusement on ne peut pas skip dans Postman, donc on continue",
              "} else {",
              "    console.log('🔐 Requesting new authentication token');",
              "}"
            ],
            "type": "text/javascript"
          }
        }
      ],
      "request": {
        "method": "POST",
        "header": [
          {
            "key": "Content-Type",
            "value": "application/json"
          }
        ],
        "body": {
          "mode": "raw",
          "raw": JSON.stringify({
            "email": "{{email}}",
            "password": "{{password}}"
          }, null, 2)
        },
        "url": {
          "raw": "{{base_url}}/api/v1/login",
          "host": ["{{base_url}}"],
          "path": ["api", "v1", "login"]
        },
        "description": "Authentification via Cognito pour obtenir un token Bearer JWT"
      }
    }
  ],
  "description": "Authentification requise pour accéder aux endpoints WellWo"
};

// Mettre à jour le script de pre-request global pour vérifier le token
const globalPreRequestScript = [
  "// WellWo Collection Pre-request Script",
  "",
  "// Vérifier si on a un token valide",
  "const token = pm.environment.get('auth_token');",
  "const tokenExpiry = pm.environment.get('token_expiry');",
  "",
  "if (!token || !tokenExpiry || Date.now() >= tokenExpiry) {",
  "    console.warn('⚠️ Token expired or missing. Please run the Login request first.');",
  "}",
  "",
  "// Set default language if not specified",
  "if (!pm.request.url.query.has('lang')) {",
  "    pm.request.url.query.add({",
  "        key: 'lang',",
  "        value: pm.environment.get('default_lang') || 'es'",
  "    });",
  "}",
  "",
  "// Log WellWo API call for debugging",
  "console.log('WellWo API Call:', pm.request.method, pm.request.url.toString());"
];

// Mettre à jour le script de pre-request global
if (collection.event && collection.event.length > 0) {
  const preRequestEvent = collection.event.find(e => e.listen === 'prerequest');
  if (preRequestEvent && preRequestEvent.script) {
    preRequestEvent.script.exec = globalPreRequestScript;
  }
}

// Ajouter la requête de login au début de la collection
if (!collection.item.find(item => item.name === 'Authentication')) {
  collection.item.unshift(loginRequest);
}

// Sauvegarder la collection mise à jour
fs.writeFileSync(collectionPath, JSON.stringify(collection, null, 2));
console.log('✅ Collection mise à jour avec l\'authentification');
console.log('📝 N\'oubliez pas de configurer les variables d\'environnement:');
console.log('   - email: beneficiary1.user@hexeko.com');
console.log('   - password: P@ssw0rd');
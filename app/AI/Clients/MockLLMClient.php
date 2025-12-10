<?php

namespace App\AI\Clients;

use App\AI\Contracts\LLMClientInterface;
use App\AI\DTOs\LLMResponse;

/**
 * Un client LLM factice pour le développement et les tests
 * Retourne des réponses prédéfinies sans appeler d'API externe
 */
class MockLLMClient implements LLMClientInterface
{
    /**
     * Simule l'envoi d'un prompt à une API LLM
     *
     * @param  array<string, string>  $prompt  Le prompt à envoyer
     * @param  array<string, mixed>  $params  Paramètres additionnels
     * @return LLMResponse La réponse structurée
     */
    public function sendPrompt(array $prompt, array $params = []): LLMResponse
    {
        // Génère une réponse factice basée sur le prompt
        $promptText = $prompt['user_input'] ?? json_encode($prompt);
        $content = "Voici une réponse générée par le client LLM factice en réponse à votre prompt : \n\n";
        $content .= '"'.$promptText."\"\n\n";
        $content .= "Ce texte est généré localement sans appeler d'API externe. ";
        $content .= 'Utilisez ce client pour le développement et les tests uniquement.';

        // Simule un délai pour rendre le comportement plus réaliste
        usleep(500000); // 500ms

        // Ensure $promptText is a string
        $promptTextStr = is_string($promptText) ? $promptText : '';

        return new LLMResponse(
            $content,
            strlen($promptTextStr) + strlen($content),
            null
        );
    }

    /**
     * Retourne le nom du moteur LLM
     *
     * @return string Le nom du moteur
     */
    public function getName(): string
    {
        return 'mock';
    }
}

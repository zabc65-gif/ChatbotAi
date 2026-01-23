<?php
/**
 * Interface AIServiceInterface
 * Contrat pour tous les services IA
 */

interface AIServiceInterface
{
    /**
     * Envoie une requête à l'API IA
     *
     * @param array $messages Historique de la conversation
     * @return array Réponse avec 'content', 'tokens_used', 'success'
     */
    public function sendRequest(array $messages): array;

    /**
     * Vérifie si le service est disponible
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Retourne le nom du service
     *
     * @return string
     */
    public function getName(): string;
}

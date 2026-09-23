<?php
/**
 * EmailValidator.php
 *
 * Avant : le même pattern regex était copié dans traitement.php ET send.php.
 * Problème : si on doit corriger une règle de validation, il faut penser à
 * la changer PARTOUT, et on oublie toujours un endroit.
 *
 * Solution : une seule classe, un seul endroit où vit la règle de validation.
 * Tout le reste du projet appelle EmailValidator::isValid($email) au lieu
 * d'avoir son propre regex.
 */
class EmailValidator
{
    // Le pattern vit ici une seule fois, dans une constante de la classe.
    private const PATTERN = "/^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/";

    /**
     * Vérifie qu'un email a un format valide (syntaxe uniquement,
     * ne vérifie PAS que le domaine existe réellement - ça viendra
     * plus tard avec une validation avancée séparée).
     *
     * "static" = on appelle EmailValidator::isValid(...) directement,
     * sans avoir besoin de faire new EmailValidator() avant.
     */
    public static function isValid(string $email): bool
    {
        // trim() d'abord : on ne veut pas qu'un espace collé fasse échouer
        // une adresse par ailleurs correcte.
        $email = trim($email);

        // preg_match retourne 1 (trouvé) ou 0 (pas trouvé) ; on le compare
        // explicitement à 1 pour renvoyer un vrai booléen (true/false).
        return preg_match(self::PATTERN, $email) === 1;
    }

    /**
     * Vérifie si un email existe déjà dans un fichier donné
     * (utilisé pour empêcher les doublons à l'ajout, Partie 3).
     */
    public static function existeDansFichier(string $email, string $cheminFichier): bool
    {
        if (!file_exists($cheminFichier)) {
            return false; // pas de fichier -> forcément pas de doublon
        }

        $lignes = file($cheminFichier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        // in_array() cherche une valeur dans un tableau et retourne true/false.
        // true en 3e argument = comparaison stricte (respecte la casse et le type).
        return in_array(trim($email), $lignes, true);
    }
}

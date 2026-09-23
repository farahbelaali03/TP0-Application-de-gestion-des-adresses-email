<?php
/**
 * env.php
 *
 * Charge les variables du fichier .env dans $_ENV, pour qu'on puisse
 * les lire avec $_ENV['SMTP_HOST'] etc., sans jamais écrire de mot de
 * passe directement dans le code PHP.
 */
function chargerEnv(string $cheminFichier): void
{
    if (!file_exists($cheminFichier)) {
        return; // pas de .env -> on ne fait rien (utile en environnement de test)
    }

    // file() encore une fois : une ligne du .env = une case du tableau.
    $lignes = file($cheminFichier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lignes as $ligne) {
        $ligne = trim($ligne);

        // On ignore les commentaires (lignes qui commencent par #).
        if ($ligne === '' || str_starts_with($ligne, '#')) {
            continue;
        }

        // explode avec un 3e argument (2) : on coupe seulement au PREMIER "=" trouvé,
        // au cas où la valeur elle-même contiendrait un "=".
        $parties = explode('=', $ligne, 2);

        if (count($parties) === 2) {
            $cle = trim($parties[0]);
            $valeur = trim($parties[1]);
            $_ENV[$cle] = $valeur;
        }
    }
}

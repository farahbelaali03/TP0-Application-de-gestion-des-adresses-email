<?php
/**
 * traitement.php
 *
 * Les mêmes étapes que partie1.php, mais transformées en FONCTIONS
 * pour pouvoir être appelées depuis une page web (index.php) ET
 * depuis un script en ligne de commande si on veut.
 *
 * Pourquoi des fonctions et pas juste le script d'avant ?
 * - le script d'avant travaillait toujours sur "Emails.txt" en dur.
 * - ici, une fonction reçoit le CHEMIN du fichier en paramètre,
 *   donc elle marche avec n'importe quel fichier uploadé.
 */

/**
 * Lit un fichier d'emails et sépare valides / invalides.
 *
 * @param string $cheminFichier chemin vers le fichier à lire
 * @return array ['valides' => [...], 'invalides' => [...]]
 */
function separerValidesInvalides(string $cheminFichier): array
{
    // Avant : le pattern regex était recopié ici. Maintenant, une seule
    // source de vérité : EmailValidator::isValid().
    require_once __DIR__ . "/EmailValidator.php";

    $lignes = file($cheminFichier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    $valides   = [];
    $invalides = [];

    foreach ($lignes as $ligne) {
        $email = trim($ligne);
        if ($email === "") {
            continue;
        }

        if (EmailValidator::isValid($email)) {
            $valides[] = $email;
        } else {
            $invalides[] = $email;
        }
    }

    // On retourne les deux tableaux dans UN SEUL tableau associatif,
    // pour que la fonction puisse renvoyer "deux résultats" à la fois.
    return [
        'valides'   => $valides,
        'invalides' => $invalides,
    ];
}

/**
 * Enlève les doublons et trie une liste d'emails.
 */
function nettoyerEtTrier(array $emails): array
{
    $sansDoublons = array_unique($emails);
    sort($sansDoublons); // trie ET réindexe (0,1,2...)
    return $sansDoublons;
}

/**
 * Regroupe une liste d'emails par domaine.
 * Retourne un tableau associatif : ['gmail.com' => [...], 'ensa.ac.ma' => [...]]
 */
function grouperParDomaine(array $emails): array
{
    $parDomaine = [];

    foreach ($emails as $email) {
        $morceaux = explode("@", $email);
        $domaine  = $morceaux[1];

        if (!isset($parDomaine[$domaine])) {
            $parDomaine[$domaine] = [];
        }
        $parDomaine[$domaine][] = $email;
    }

    return $parDomaine;
}

/**
 * Fonction "chef d'orchestre" : fait tout le traitement d'un coup
 * et écrit tous les fichiers de sortie dans $dossierSortie.
 *
 * @return array un résumé (compteurs) à afficher à l'utilisateur
 */
function traiterFichierEmails(string $cheminFichier, string $dossierSortie): array
{
    // On supprime d'abord tous les anciens fichiers "*.txt" de domaine
    // générés par un traitement PRECEDENT (sauf Emails.txt, qui est le fichier
    // source qu'on vient de recevoir, pas un fichier généré).
    // Sans ça, un domaine présent hier mais absent du nouveau fichier
    // laisserait un fichier .txt obsolète trainer indéfiniment.
    $anciensFichiers = glob($dossierSortie . "/*.txt");
    foreach ($anciensFichiers as $ancien) {
        if (basename($ancien) !== "Emails.txt") {
            unlink($ancien); // unlink() = supprimer un fichier
        }
    }

    $resultat = separerValidesInvalides($cheminFichier);

    // On écrit les invalides
    file_put_contents(
        $dossierSortie . "/Emailinvalide.txt",
        implode("\n", $resultat['invalides'])
    );

    // On nettoie et on écrit les valides triés
    $propres = nettoyerEtTrier($resultat['valides']);
    file_put_contents(
        $dossierSortie . "/EmailsT.txt",
        implode("\n", $propres)
    );

    // On sépare par domaine et on écrit un fichier par domaine
    $parDomaine = grouperParDomaine($propres);
    $fichiersDomaines = [];

    foreach ($parDomaine as $domaine => $listeEmails) {
        // basename() nettoie le nom pour éviter des caractères dangereux
        // dans le nom de fichier (sécurité qu'on approfondira au LAB 7).
        $nomFichier = basename($domaine) . ".txt";
        file_put_contents($dossierSortie . "/" . $nomFichier, implode("\n", $listeEmails));
        $fichiersDomaines[] = $nomFichier;
    }

    return [
        'nb_valides'    => count($resultat['valides']),
        'nb_invalides'  => count($resultat['invalides']),
        'nb_uniques'    => count($propres),
        'nb_domaines'   => count($parDomaine),
        'fichiers'      => array_merge(
            ['Emailinvalide.txt', 'EmailsT.txt'],
            $fichiersDomaines
        ),
    ];
}
<?php
$dossierStockage = __DIR__ . "/storage";

// On récupère le nom demandé dans l'URL (?fichier=...).
// Si rien n'est fourni, $nomDemande sera une chaîne vide par défaut.
$nomDemande = $_GET['fichier'] ?? '';

// basename() garde SEULEMENT le nom du fichier, en supprimant tout chemin de dossier.
// Exemple : basename("../../etc/passwd") = "passwd" -> on ne peut plus sortir de storage/.
$nomSecurise = basename($nomDemande);

$cheminComplet = $dossierStockage . "/" . $nomSecurise;

// On vérifie que le fichier existe réellement dans NOTRE dossier avant de le servir.
if ($nomSecurise === '' || !file_exists($cheminComplet)) {
    http_response_code(404);
    die("Fichier introuvable.");
}

// Ces en-têtes disent au navigateur : "ceci est un fichier à télécharger",
// pas une page à afficher.
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $nomSecurise . '"');
header('Content-Length: ' . filesize($cheminComplet));

// readfile() envoie le contenu du fichier directement au navigateur.
readfile($cheminComplet);
exit;

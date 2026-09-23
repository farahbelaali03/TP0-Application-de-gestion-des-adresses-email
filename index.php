<?php
require_once "traitement.php";
require_once "EmailValidator.php";
require_once "env.php";
require_once "vendor/autoload.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

chargerEnv(__DIR__ . "/.env");

$dossierStockage = __DIR__ . "/storage";
if (!is_dir($dossierStockage)) {
    mkdir($dossierStockage, 0755, true);
}

$resumeTraitement = null;
$erreurTraitement  = null;
$resultatEnvoi     = null;
$erreurEnvoi       = null;
$resultatAjout     = null;
$erreurAjout       = null;

$action = $_POST['action'] ?? null;

// ===========================================================
// PARTIE 1 + 2a : upload et traitement du fichier
// ===========================================================
if ($action === 'traiter') {
    if (isset($_FILES['fichier_emails']) && $_FILES['fichier_emails']['error'] === UPLOAD_ERR_OK) {
        $cheminFinal = $dossierStockage . "/Emails.txt";

        if (move_uploaded_file($_FILES['fichier_emails']['tmp_name'], $cheminFinal)) {
            $resumeTraitement = traiterFichierEmails($cheminFinal, $dossierStockage);
        } else {
            $erreurTraitement = "Le fichier n'a pas pu être déplacé sur le serveur.";
        }
    } else {
        $erreurTraitement = "Aucun fichier valide n'a été envoyé.";
    }
}

// ===========================================================
// PARTIE 2b : envoi de fichiers / message par email
// ===========================================================
if ($action === 'envoyer') {
    $fichiersChoisis = $_POST['fichiers'] ?? [];
    $destinataire    = trim($_POST['destinataire'] ?? '');
    $objet           = trim($_POST['objet'] ?? '');
    $corps           = trim($_POST['message'] ?? '');

    if (!EmailValidator::isValid($destinataire)) {
        $erreurEnvoi = "L'adresse du destinataire n'est pas valide.";
    } elseif ($objet === '' || $corps === '') {
        $erreurEnvoi = "L'objet et le message ne peuvent pas être vides.";
    } else {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $_ENV['SMTP_HOST'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['SMTP_USER'];
            $mail->Password   = $_ENV['SMTP_PASS'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int) $_ENV['SMTP_PORT'];

            $mail->setFrom($_ENV['SMTP_FROM'], 'Gestion des emails');
            $mail->addAddress($destinataire);
            $mail->Subject = $objet;
            $mail->Body    = $corps;

            foreach ($fichiersChoisis as $nomFichier) {
                $nomSecurise = basename($nomFichier);
                $chemin = $dossierStockage . "/" . $nomSecurise;
                if (file_exists($chemin)) {
                    $mail->addAttachment($chemin);
                }
            }

            if (isset($_FILES['piece_jointe']) && $_FILES['piece_jointe']['error'] === UPLOAD_ERR_OK) {
                $mail->addAttachment(
                    $_FILES['piece_jointe']['tmp_name'],
                    $_FILES['piece_jointe']['name']
                );
            }

            $mail->send();
            $resultatEnvoi = "Email envoyé avec succès à " . htmlspecialchars($destinataire);

        } catch (Exception $e) {
            $erreurEnvoi = "Échec de l'envoi : " . htmlspecialchars($mail->ErrorInfo);
        }
    }
}

// ===========================================================
// PARTIE 3 : ajout d'une nouvelle adresse
// ===========================================================
if ($action === 'ajouter') {
    $nouvelEmail   = trim($_POST['nouvel_email'] ?? '');
    $fichierEmails = $dossierStockage . "/EmailsT.txt";

    if (!EmailValidator::isValid($nouvelEmail)) {
        $erreurAjout = "Cette adresse n'a pas un format email valide.";
    } elseif (EmailValidator::existeDansFichier($nouvelEmail, $fichierEmails)) {
        $erreurAjout = "Cette adresse existe déjà dans la liste.";
    } else {
        file_put_contents($fichierEmails, $nouvelEmail . PHP_EOL, FILE_APPEND);
        $resultatAjout = "Adresse ajoutée avec succès : " . htmlspecialchars($nouvelEmail);
    }
}

$fichiersDisponibles = glob($dossierStockage . "/*.txt");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestion des adresses email</title>
    <style>
        /* ---------------------------------------------------------
           Variables : on définit les couleurs UNE SEULE FOIS ici.
           Tout le CSS en dessous les réutilise avec var(--nom).
           Pour changer le thème entier, on ne modifie que ces lignes.
        --------------------------------------------------------- */
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --bg: #f4f5fb;
            --card-bg: #ffffff;
            --text: #1f2937;
            --text-muted: #6b7280;
            --border: #e5e7eb;
            --success-bg: #ecfdf5;
            --success-text: #047857;
            --error-bg: #fef2f2;
            --error-text: #b91c1c;
            --radius: 10px;
        }

        * { box-sizing: border-box; }

        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            padding-bottom: 60px;
        }

        header {
            background: var(--primary);
            color: white;
            padding: 28px 20px;
            text-align: center;
        }

        header h1 { margin: 0 0 4px 0; font-size: 1.6rem; }
        header p  { margin: 0; opacity: 0.9; font-size: 0.95rem; }

        /* Barre de navigation "collante" : reste visible même en scrollant,
           position: sticky la fixe une fois qu'on arrive à top: 0. */
        nav {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            justify-content: center;
            gap: 8px;
            background: var(--card-bg);
            border-bottom: 1px solid var(--border);
            padding: 12px;
            flex-wrap: wrap;
        }

        nav a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 8px 14px;
            border-radius: 999px;
            transition: background 0.15s;
        }
        nav a:hover { background: #eef2ff; }

        main {
            max-width: 680px;
            margin: 30px auto;
            padding: 0 16px;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* Chaque section devient une "carte" : fond blanc, coins arrondis,
           légère ombre pour la détacher du fond gris de la page. */
        section {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            scroll-margin-top: 80px; /* évite que la nav sticky cache le haut de section au clic */
        }

        section h2 {
            margin-top: 0;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            font-size: 0.85rem;
            font-weight: 700;
        }

        label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            margin: 14px 0 6px;
            color: var(--text-muted);
        }

        input[type="text"],
        input[type="email"],
        input[type="file"],
        textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
        }

        input:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79,70,229,0.15);
        }

        textarea { resize: vertical; }

        button {
            margin-top: 16px;
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s;
        }
        button:hover { background: var(--primary-dark); }

        .checkbox-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin: 10px 0;
        }
        .checkbox-list label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 400;
            color: var(--text);
            margin: 0;
        }

        .alert {
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 14px;
        }
        .alert-success { background: var(--success-bg); color: var(--success-text); }
        .alert-error   { background: var(--error-bg); color: var(--error-text); }

        .stats {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 14px 0;
            padding: 0;
            list-style: none;
        }
        .stats li {
            background: #f9fafb;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.85rem;
            flex: 1 1 120px;
        }
        .stats strong { display: block; font-size: 1.3rem; color: var(--primary); }

        .file-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding: 0;
            list-style: none;
            margin: 10px 0 0;
        }
        .file-list a {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            background: #f9fafb;
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            text-decoration: none;
            font-size: 0.9rem;
            width: fit-content;
        }
        .file-list a:hover { border-color: var(--primary); color: var(--primary); }

        .hint {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 6px;
        }
    </style>
</head>
<body>

    <header>
        <h1>Application de gestion des adresses email</h1>
    </header>

    <nav>
        <a href="#partie1">1. Traiter</a>
        <a href="#partie2b">2. Envoyer</a>
        <a href="#partie3">3. Ajouter</a>
    </nav>

    <main>

        <!-- =================== SECTION 1 : TRAITEMENT =================== -->
        <section id="partie1">
            <h2><span class="badge">1</span> Traiter un fichier Emails.txt</h2>

            <?php if ($erreurTraitement !== null): ?>
                <div class="alert alert-error"><?= htmlspecialchars($erreurTraitement) ?></div>
            <?php endif; ?>

            <form action="index.php#partie1" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="traiter">
                <label for="fichier_emails">Fichier Emails.txt</label>
                <input type="file" name="fichier_emails" id="fichier_emails" required>
                <button type="submit">Traiter le fichier</button>
            </form>

            <?php if ($resumeTraitement !== null): ?>
                <ul class="stats">
                    <li><strong><?= (int) $resumeTraitement['nb_valides'] ?></strong>valides</li>
                    <li><strong><?= (int) $resumeTraitement['nb_invalides'] ?></strong>invalides</li>
                    <li><strong><?= (int) $resumeTraitement['nb_uniques'] ?></strong>uniques après tri</li>
                    <li><strong><?= (int) $resumeTraitement['nb_domaines'] ?></strong>domaines</li>
                </ul>
            <?php endif; ?>

            <label>Fichiers disponibles</label>
            <ul class="file-list">
                <?php foreach ($fichiersDisponibles as $chemin): ?>
                    <?php $nom = basename($chemin); ?>
                    <li><a href="download.php?fichier=<?= urlencode($nom) ?>">⬇ <?= htmlspecialchars($nom) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </section>

        <!-- =================== SECTION 2 : ENVOI =================== -->
        <section id="partie2b">
            <h2><span class="badge">2</span> Envoyer des fichiers / un message</h2>

            <?php if ($erreurEnvoi !== null): ?>
                <div class="alert alert-error"><?= htmlspecialchars($erreurEnvoi) ?></div>
            <?php endif; ?>
            <?php if ($resultatEnvoi !== null): ?>
                <div class="alert alert-success"><?= $resultatEnvoi ?></div>
            <?php endif; ?>

            <form action="index.php#partie2b" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="envoyer">

                <label>Fichiers à joindre (facultatif)</label>
                <div class="checkbox-list">
                    <?php foreach ($fichiersDisponibles as $chemin): ?>
                        <?php $nom = basename($chemin); ?>
                        <label>
                            <input type="checkbox" name="fichiers[]" value="<?= htmlspecialchars($nom) ?>">
                            <?= htmlspecialchars($nom) ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <label for="destinataire">Destinataire</label>
                <input type="email" name="destinataire" id="destinataire" required>

                <label for="objet">Objet</label>
                <input type="text" name="objet" id="objet" required>

                <label for="message">Message</label>
                <textarea name="message" id="message" rows="4" required></textarea>

                <label for="piece_jointe">Pièce jointe supplémentaire (facultatif)</label>
                <input type="file" name="piece_jointe" id="piece_jointe">

                <button type="submit">Envoyer</button>
            </form>
        </section>

        <!-- =================== SECTION 3 : AJOUT =================== -->
        <section id="partie3">
            <h2><span class="badge">3</span> Ajouter une nouvelle adresse</h2>

            <?php if ($erreurAjout !== null): ?>
                <div class="alert alert-error"><?= htmlspecialchars($erreurAjout) ?></div>
            <?php endif; ?>
            <?php if ($resultatAjout !== null): ?>
                <div class="alert alert-success"><?= $resultatAjout ?></div>
            <?php endif; ?>

            <form action="index.php#partie3" method="POST">
                <input type="hidden" name="action" value="ajouter">
                <label for="nouvel_email">Nouvelle adresse email</label>
                <input
                    type="email"
                    name="nouvel_email"
                    id="nouvel_email"
                    required
                    pattern="[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}"
                    title="Format attendu : exemple@domaine.extension"
                >
                <p class="hint">Le format est vérifié par le navigateur, puis revérifié par le serveur.</p>
                <button type="submit">Ajouter</button>
            </form>
        </section>

    </main>

</body>
</html>
<?php
declare(strict_types=1);
require_once __DIR__ . '/app.php';

start_session();

header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');

final class InstallException extends RuntimeException
{
}

function install_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function install_pdo_code(PDOException $exception): int
{
    $code = (int)($exception->errorInfo[1] ?? 0);
    if ($code === 0 && preg_match('/\[(\d+)\]/', $exception->getMessage(), $match)) {
        $code = (int)$match[1];
    }
    return $code;
}

function install_connection_message(Throwable $exception): string
{
    if (!$exception instanceof PDOException) {
        return 'La connexion à la base de données a échoué. Vérifiez config/local.php.';
    }
    $code = install_pdo_code($exception);
    if (str_contains(strtolower($exception->getMessage()), 'could not find driver')) {
        return 'L’extension PHP pdo_mysql n’est pas activée sur ce serveur. Activez-la puis rechargez la page.';
    }
    return match ($code) {
        1040 => 'Le serveur MySQL refuse de nouvelles connexions (limite atteinte). Réessayez dans quelques minutes.',
        1044 => 'L’utilisateur MySQL n’a pas accès à cette base. Vérifiez le nom de la base et les droits associés.',
        1045 => 'Identifiants MySQL refusés. Vérifiez le nom d’utilisateur et le mot de passe SQL.',
        1049 => 'La base de données indiquée n’existe pas. Créez-la depuis le panneau de votre hébergeur.',
        1130 => 'Cet ordinateur n’est pas autorisé à se connecter au serveur MySQL.',
        2002, 2003, 2005 => 'Serveur MySQL injoignable. Vérifiez le hostname fourni par l’hébergeur (pas localhost) et le port.',
        default => 'Connexion MySQL impossible' . ($code > 0 ? ' (code ' . $code . ')' : '') . '. Vérifiez les paramètres de base et les droits MySQL.',
    };
}

function install_schema_message(PDOException $exception): string
{
    $code = install_pdo_code($exception);
    return 'Le schéma SQL n’a pas pu être installé' . ($code > 0 ? ' (code ' . $code . ')' : '') . '. Importez database/schema.sql via phpMyAdmin, puis rechargez cette page.';
}

function install_split_sql(string $sql): array
{
    $statements = [];
    $current = '';
    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;
    $inComment = false;
    $length = strlen($sql);
    for ($index = 0; $index < $length; $index++) {
        $char = $sql[$index];
        $next = $index + 1 < $length ? $sql[$index + 1] : '';
        if ($inComment) {
            if ($char === "\n") {
                $inComment = false;
                $current .= "\n";
            }
            continue;
        }
        if (!$inSingle && !$inDouble && !$inBacktick && $char === '-' && $next === '-') {
            $inComment = true;
            $index++;
            continue;
        }
        $current .= $char;
        if ($char === "'" && !$inDouble && !$inBacktick) {
            if ($inSingle && $next === "'") {
                $current .= $next;
                $index++;
            } else {
                $inSingle = !$inSingle;
            }
        } elseif ($char === '"' && !$inSingle && !$inBacktick) {
            if ($inDouble && $next === '"') {
                $current .= $next;
                $index++;
            } else {
                $inDouble = !$inDouble;
            }
        } elseif ($char === '`' && !$inSingle && !$inDouble) {
            $inBacktick = !$inBacktick;
        } elseif ($char === ';' && !$inSingle && !$inDouble && !$inBacktick) {
            $statement = trim($current);
            if ($statement !== '') $statements[] = $statement;
            $current = '';
        }
    }
    $statement = trim($current);
    if ($statement !== '') $statements[] = $statement;
    return $statements;
}

function install_schema_ready(PDO $pdo): bool
{
    try {
        if ((int)$pdo->query('SELECT COUNT(*) FROM regions')->fetchColumn() !== 12) return false;
        if ((int)$pdo->query('SELECT COUNT(*) FROM provinces')->fetchColumn() !== 75) return false;
        $pdo->query('SELECT 1 FROM users LIMIT 1');
        return true;
    } catch (PDOException) {
        return false;
    }
}

$configPath = __DIR__ . '/config/local.php';
$configDir = dirname($configPath);
$configFileExists = is_file($configPath);
$environmentDsn = trim((string)(getenv('DB_DSN') ?: ''));
$hasConfig = $configFileExists || $environmentDsn !== '';
$pdo = null;
$setupLocked = false;
$connectionError = false;
$connectionMessage = '';
$error = '';
$form = [
    'db_host' => '',
    'db_port' => '3306',
    'db_name' => '',
    'db_user' => '',
    'full_name' => '',
    'email' => 'basta@exemple.com',
];

if ($hasConfig) {
    try {
        $pdo = db();
        try {
            if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) $setupLocked = true;
        } catch (PDOException) {
            // Schéma pas encore créé : l'assistant peut encore l'installer.
        }
    } catch (Throwable $exception) {
        $connectionError = true;
        $connectionMessage = install_connection_message($exception);
        error_log('Installation: ' . $exception->getMessage());
    }
}

if (!$setupLocked && !$connectionError && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $form['db_host'] = trim((string)($_POST['db_host'] ?? ''));
    $form['db_port'] = trim((string)($_POST['db_port'] ?? ''));
    if ($form['db_port'] === '') $form['db_port'] = '3306';
    $form['db_name'] = trim((string)($_POST['db_name'] ?? ''));
    $form['db_user'] = trim((string)($_POST['db_user'] ?? ''));
    $form['full_name'] = trim((string)($_POST['full_name'] ?? ''));
    $form['email'] = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $confirmation = (string)($_POST['password_confirmation'] ?? '');

    try {
        if ($form['full_name'] === '' || install_length($form['full_name']) > 160) {
            throw new InvalidArgumentException('Le nom complet est obligatoire et limité à 160 caractères.');
        }
        if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || install_length($form['email']) > 190) {
            throw new InvalidArgumentException('Adresse e-mail invalide (190 caractères maximum).');
        }
        if (strlen($password) < 12) {
            throw new InvalidArgumentException('Le mot de passe doit contenir au moins 12 caractères.');
        }
        if (strlen($password) > 72) {
            throw new InvalidArgumentException('Le mot de passe ne doit pas dépasser 72 octets.');
        }
        if (!hash_equals($password, $confirmation)) {
            throw new InvalidArgumentException('Les deux mots de passe ne correspondent pas.');
        }

        if ($hasConfig) {
            $pdo = db();
        } else {
            if (!preg_match('/^[a-zA-Z0-9_](?:[a-zA-Z0-9_.-]*[a-zA-Z0-9_])?$/', $form['db_host'])) {
                throw new InvalidArgumentException('Hostname MySQL invalide. Indiquez le hostname seul, sans port, puis renseignez le port séparément.');
            }
            if (!preg_match('/^\d{1,5}$/', $form['db_port']) || (int)$form['db_port'] < 1 || (int)$form['db_port'] > 65535) {
                throw new InvalidArgumentException('Port MySQL invalide (valeur attendue entre 1 et 65535).');
            }
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $form['db_name'])) {
                throw new InvalidArgumentException('Nom de base de données invalide.');
            }
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $form['db_user'])) {
                throw new InvalidArgumentException('Nom d’utilisateur MySQL invalide.');
            }

            $databasePassword = (string)($_POST['db_password'] ?? '');
            $dsn = 'mysql:host=' . $form['db_host'] . ';port=' . (int)$form['db_port'] . ';dbname=' . $form['db_name'] . ';charset=utf8mb4';
            try {
                $pdo = new PDO($dsn, $form['db_user'], $databasePassword, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $exception) {
                error_log('Installation: ' . $exception->getMessage());
                throw new InstallException(install_connection_message($exception));
            }

            if (!is_dir($configDir)) {
                throw new InstallException('Le dossier config est introuvable. Recréez-le à partir du paquet complet, puis rechargez la page.');
            }
            $config = [
                'host' => $form['db_host'],
                'port' => (int)$form['db_port'],
                'database' => $form['db_name'],
                'username' => $form['db_user'],
                'password' => $databasePassword,
            ];
            $php = "<?php\ndeclare(strict_types=1);\n\n// Fichier généré par install.php. Ne pas versionner.\nreturn " . var_export($config, true) . ";\n";
            $tempPath = $configPath . '.tmp';
            $written = file_put_contents($tempPath, $php, LOCK_EX);
            if ($written === false || $written !== strlen($php) || !rename($tempPath, $configPath)) {
                @unlink($tempPath);
                throw new InstallException('Écriture de config/local.php refusée. Copiez config/local.example.php sous config/local.php, renseignez les identifiants, puis rechargez la page.');
            }
            @chmod($configPath, 0600);
            $hasConfig = true;
            $configFileExists = true;
        }

        if (!install_schema_ready($pdo)) {
            $schemaPath = __DIR__ . '/database/schema.sql';
            if (!is_file($schemaPath) || !is_readable($schemaPath)) {
                throw new InstallException('Schéma SQL introuvable. Téléversez le dossier database du paquet complet.');
            }
            $schema = file_get_contents($schemaPath);
            if (!is_string($schema) || trim($schema) === '') {
                throw new InstallException('Le fichier database/schema.sql est vide ou illisible.');
            }
            foreach (install_split_sql($schema) as $statement) {
                try {
                    $pdo->exec($statement);
                } catch (PDOException $exception) {
                    error_log('Installation schéma: ' . $exception->getMessage());
                    throw new InstallException(install_schema_message($exception));
                }
            }
            if (!install_schema_ready($pdo)) {
                throw new InstallException('Les 12 régions et 75 provinces ne sont pas entièrement initialisées. Importez database/schema.sql via phpMyAdmin, puis rechargez cette page.');
            }
        }

        if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
            throw new InstallException('Des comptes existent déjà : l’assistant est verrouillé. Connectez-vous avec un compte existant.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash)) {
            throw new InstallException('Le mot de passe n’a pas pu être sécurisé. Réessayez avec un autre mot de passe.');
        }
        try {
            $statement = $pdo->prepare('INSERT INTO users (email,password_hash,full_name,role,region_id,province_id) VALUES (:email,:hash,:name,:role,NULL,NULL)');
            $statement->execute([
                ':email' => $form['email'],
                ':hash' => $hash,
                ':name' => $form['full_name'],
                ':role' => ROLE_ADMINISTRATOR,
            ]);
        } catch (PDOException $exception) {
            error_log('Installation compte: ' . $exception->getMessage());
            $code = install_pdo_code($exception);
            throw new InstallException('Le compte central n’a pas pu être créé' . ($code > 0 ? ' (code ' . $code . ')' : '') . '. Vérifiez que l’adresse e-mail n’est pas déjà utilisée, puis réessayez.');
        }
        header('Location: login.php?installed=1');
        exit;
    } catch (InvalidArgumentException | InstallException $exception) {
        $error = $exception->getMessage();
    } catch (PDOException $exception) {
        error_log('Installation: ' . $exception->getMessage());
        $error = install_connection_message($exception);
    } catch (Throwable $exception) {
        error_log('Installation: ' . $exception->getMessage());
        $error = 'Installation impossible. Consultez le journal du serveur ou vérifiez les permissions du dossier config.';
    }
}
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#104b38"><meta name="color-scheme" content="light"><title>Installation — Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-</title><link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/features.css"><link rel="stylesheet" href="assets/reference-theme.css"><link rel="icon" type="image/png" href="assets/logo-poussin-jaune.png"></head><body>
<header class="site-header"><div class="brand"><img src="assets/logo-poussin-jaune.png" alt="Logo du portail avicole" class="brand-logo" width="52" height="52"><div><strong>Système Nationale de Traçabilité et de Sécurité Sanitaire Avicole -Maroc-</strong><small>Assistant de première configuration</small></div></div></header>
<main class="login"><div class="card">
<?php if ($setupLocked): ?><h1>Installation terminée</h1><p>Un compte existe déjà. L’assistant est verrouillé.</p><a class="btn" href="login.php">Aller à la connexion</a>
<?php elseif ($connectionError): ?><h1>Connexion à la base impossible</h1>
<div class="flash error"><?=e($connectionMessage)?></div>
<p class="help"><?=$configFileExists ? 'Le fichier <strong>config/local.php</strong> existe déjà. Corrigez les identifiants qu’il contient (ou supprimez-le pour relancer l’assistant), puis rechargez cette page.' : 'La connexion est définie par la variable d’environnement <strong>DB_DSN</strong>. Corrigez-la, puis rechargez cette page.'?></p>
<p><a class="btn" href="install.php">Réessayer</a> <a class="btn secondary" href="login.php">Aller à la connexion</a></p>
<?php else: ?><h1>Configurer le site</h1><p class="help">Renseignez les informations du panneau de votre hébergeur, puis créez le premier compte administrateur. Le schéma et les 75 provinces seront installés automatiquement.</p>
<?php if ($error): ?><div class="flash error"><?=e($error)?></div><?php endif; ?>
<form method="post" autocomplete="off"><?=csrf_field()?>
<?php if (!$hasConfig): ?><h2>Base MySQL</h2>
<label for="db_host">Hostname MySQL — pas localhost</label><input id="db_host" name="db_host" required maxlength="253" placeholder="sqlXXX.infinityfree.com" autocomplete="off" spellcheck="false" value="<?=e($form['db_host'])?>">
<label for="db_port">Port MySQL</label><input id="db_port" name="db_port" required inputmode="numeric" pattern="[0-9]{1,5}" maxlength="5" autocomplete="off" value="<?=e($form['db_port'])?>"><span class="help">3306 par défaut ; modifiez-le uniquement si votre hébergeur impose un autre port.</span>
<label for="db_name">Nom de la base</label><input id="db_name" name="db_name" required maxlength="64" autocomplete="off" spellcheck="false" value="<?=e($form['db_name'])?>">
<label for="db_user">Nom d’utilisateur SQL</label><input id="db_user" name="db_user" required maxlength="64" autocomplete="off" spellcheck="false" value="<?=e($form['db_user'])?>">
<label for="db_password">Mot de passe SQL</label><input id="db_password" name="db_password" type="password" required autocomplete="off">
<?php else: ?><p class="help">Connexion à la base vérifiée. Le schéma et les 75 provinces seront installés automatiquement si nécessaire.</p>
<?php endif; ?><h2>Premier administrateur</h2>
<label for="full_name">Nom complet</label><input id="full_name" name="full_name" required maxlength="160" autocomplete="name" value="<?=e($form['full_name'])?>">
<label for="email">Adresse e-mail</label><input id="email" name="email" type="email" required maxlength="190" autocomplete="username" value="<?=e($form['email'])?>">
<label for="password">Mot de passe (12 caractères minimum)</label><input id="password" name="password" type="password" required minlength="12" maxlength="72" autocomplete="new-password">
<label for="password_confirmation">Confirmer le mot de passe</label><input id="password_confirmation" name="password_confirmation" type="password" required minlength="12" maxlength="72" autocomplete="new-password">
<p><button class="btn" type="submit">Installer et créer le compte</button></p></form>
<p class="help">Le mot de passe est stocké sous forme hachée. Après l’installation, l’assistant est verrouillé si un compte est créé. En HTTPS, utilisez le cadenas du navigateur.</p>
<?php endif; ?></div><p class="footer">Après la création des comptes, l’administrateur importe les unités ; le chef de service provincial les ajoute une par une.</p></main><footer class="copyright-footer"><span>AvuSafe© All rights reserved 2026</span><span><a class="footer-contact-link" href="contact.php" aria-label="Ouvrir la page de contact">✉ Contact</a></span></footer></body></html>

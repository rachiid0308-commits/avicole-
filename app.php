<?php
declare(strict_types=1);

const ROLE_CENTRAL = 'central';
const ROLE_ADMINISTRATOR = 'administrator';
const ROLE_CENTRAL_VIEWER = 'central_viewer';
const ROLE_REGIONAL = 'regional';
const ROLE_DIVISION_HEAD = 'division_head';
const ROLE_PROVINCIAL = 'provincial';
const ROLE_VETERINARY_RESPONSIBLE = 'veterinary_responsible';
const FARM_TYPES = ['Chair', 'Pondeuses', 'Reproducteurs', 'Couvoir', 'Dinde'];
const FARM_STATUSES = ['Accord de principe', 'Accord refusé', 'Autorisé', 'Suspendue', 'Retrait'];
const SESSION_IDLE_TIMEOUT_SECONDS = 300;

function has_global_scope(array $user): bool
{
    return in_array(($user['role'] ?? ''), [ROLE_CENTRAL, ROLE_ADMINISTRATOR, ROLE_CENTRAL_VIEWER], true);
}

function has_region_scope(array $user): bool
{
    return in_array(($user['role'] ?? ''), [ROLE_REGIONAL, ROLE_DIVISION_HEAD], true);
}

function has_province_scope(array $user): bool
{
    return in_array(($user['role'] ?? ''), [ROLE_PROVINCIAL, ROLE_VETERINARY_RESPONSIBLE], true);
}

function can_print_reports(array $user): bool
{
    return in_array(($user['role'] ?? ''), [ROLE_ADMINISTRATOR, ROLE_PROVINCIAL, ROLE_VETERINARY_RESPONSIBLE], true);
}

function can_download_word_reports(array $user): bool
{
    return ($user['role'] ?? '') === ROLE_ADMINISTRATOR;
}

function can_create_farm(array $user): bool
{
    return can_manage_farm($user);
}

function can_manage_farm(array $user): bool
{
    return in_array(($user['role'] ?? ''), [ROLE_ADMINISTRATOR, ROLE_PROVINCIAL], true);
}

function can_import_farm(array $user): bool
{
    return ($user['role'] ?? '') === ROLE_ADMINISTRATOR;
}

function session_idle_expired(int $now, int $lastActivity): bool
{
    return $lastActivity <= 0 || $now - $lastActivity >= SESSION_IDLE_TIMEOUT_SECONDS;
}

function minimum_farm_distance_km(string $typeA, string $typeB): float
{
    if (!in_array($typeA, FARM_TYPES, true) || !in_array($typeB, FARM_TYPES, true)) {
        throw new InvalidArgumentException('Type d’unité avicole invalide.');
    }
    if ($typeA === 'Reproducteurs' || $typeB === 'Reproducteurs') return 3.0;
    if ($typeA === 'Couvoir' || $typeB === 'Couvoir') return 2.0;
    if ($typeA === 'Pondeuses' || $typeB === 'Pondeuses') return 2.0;
    return 1.0;
}

function haversine_distance_km(float $latA, float $lonA, float $latB, float $lonB): float
{
    $rad = static fn(float $degrees): float => deg2rad($degrees);
    $dLat = $rad($latB - $latA);
    $dLon = $rad($lonB - $lonA);
    $a = sin($dLat / 2) ** 2 + cos($rad($latA)) * cos($rad($latB)) * sin($dLon / 2) ** 2;
    return 6371.0088 * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function add_calendar_months(DateTimeImmutable $date, int $months): DateTimeImmutable
{
    $targetMonth = $date->modify('first day of this month')->modify(($months >= 0 ? '+' : '') . $months . ' months');
    $day = min((int)$date->format('j'), (int)$targetMonth->format('t'));
    return $targetMonth->setDate((int)$targetMonth->format('Y'), (int)$targetMonth->format('n'), $day);
}

function farm_timeline(array $farm, ?DateTimeImmutable $today = null): array
{
    $parseDate = static function (mixed $value): ?DateTimeImmutable {
        if (!is_string($value) || trim($value) === '') return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));
        return $date && $date->format('Y-m-d') === trim($value) ? $date : null;
    };
    $first = $parseDate($farm['first_agreement_date'] ?? null);
    $second = $parseDate($farm['second_agreement_date'] ?? null);
    $construction = $parseDate($farm['construction_deadline'] ?? null);
    $today ??= new DateTimeImmutable('today');
    $secondDue = $first ? add_calendar_months($first, 1) : null;
    $constructionDue = $second ? add_calendar_months($second, 12) : null;
    $secondOverdue = $secondDue !== null && ($second ? $second > $secondDue : $today > $secondDue);
    $constructionOverdue = $constructionDue !== null && (
        $today > $constructionDue
        || ($construction !== null && ($construction > $constructionDue || $today > $construction))
    );

    return [
        'first' => $first?->format('Y-m-d'),
        'second' => $second?->format('Y-m-d'),
        'second_due' => $secondDue?->format('Y-m-d'),
        'second_overdue' => $secondOverdue,
        'construction' => $construction?->format('Y-m-d'),
        'construction_due' => $constructionDue?->format('Y-m-d'),
        'construction_overdue' => $constructionOverdue,
    ];
}

function format_date_fr(?string $date): string
{
    if ($date === null) return '—';
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date ? $parsed->format('d/m/Y') : '—';
}

function aggregate_farm_rows(array $rows, string $dimension): array
{
    $allowed = ['region_name', 'province_name', 'farm_type', 'authorization_status'];
    if (!in_array($dimension, $allowed, true)) throw new InvalidArgumentException('Dimension statistique invalide.');
    $counts = [];
    foreach ($rows as $row) {
        $label = trim((string)($row[$dimension] ?? ''));
        if ($label !== '') $counts[$label] = ($counts[$label] ?? 0) + 1;
    }
    ksort($counts, SORT_NATURAL | SORT_FLAG_CASE);
    return $counts;
}

function read_local_config(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $loaded = require $path;
    return is_array($loaded) ? $loaded : [];
}

function google_maps_api_key(): string
{
    $environmentKey = getenv('GOOGLE_MAPS_API_KEY');
    $key = is_string($environmentKey) ? trim($environmentKey) : '';
    if ($key === '') {
        $config = read_local_config(__DIR__ . '/config/maps.local.php');
        $key = is_string($config) ? trim($config) : (is_array($config) ? trim((string)($config['google_maps_api_key'] ?? '')) : '');
    }
    return str_starts_with(strtoupper($key), 'REPLACE_') ? '' : $key;
}

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;

    $config = read_local_config(__DIR__ . '/config/local.php');
    $dsn = getenv('DB_DSN') ?: (string)($config['dsn'] ?? '');
    if ($dsn === '' && !empty($config['host']) && !empty($config['database'])) {
        $port = (int)(getenv('DB_PORT') ?: ($config['port'] ?? 0));
        $dsn = 'mysql:host=' . $config['host'] . ($port > 0 ? ';port=' . $port : '') . ';dbname=' . $config['database'] . ';charset=utf8mb4';
    }

    $user = getenv('DB_USER') ?: (string)($config['username'] ?? '');
    $password = getenv('DB_PASSWORD') ?: (string)($config['password'] ?? '');

    if ($dsn === '') {
        throw new RuntimeException('Connexion SQL manquante. Configurez DB_DSN ou config/local.php.');
    }

    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('aviculture_session');
    session_set_cookie_params(['httponly' => true, 'secure' => $secure, 'samesite' => 'Lax', 'path' => '/']);
    session_start();
}

function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pagination_html(string $label, string $pageKey, int $currentPage, int $totalRows, int $perPage = 20, string $noun = 'ligne(s)'): string
{
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $pageKey) || $perPage < 1) {
        throw new InvalidArgumentException('Paramètres de pagination invalides.');
    }
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    if ($totalPages <= 1) return '';
    $currentPage = max(1, min($currentPage, $totalPages));
    $first = ($currentPage - 1) * $perPage + 1;
    $last = min($currentPage * $perPage, $totalRows);
    $urlFor = static function (int $page) use ($pageKey): string {
        $query = $_GET;
        $query[$pageKey] = $page;
        return '?' . http_build_query($query);
    };
    $previous = $currentPage > 1
        ? '<a class="btn secondary" href="' . e($urlFor($currentPage - 1)) . '" aria-label="Page précédente">← Précédente</a>'
        : '<span class="btn secondary is-disabled" aria-disabled="true">← Précédente</span>';
    $next = $currentPage < $totalPages
        ? '<a class="btn secondary" href="' . e($urlFor($currentPage + 1)) . '" aria-label="Page suivante">Suivante →</a>'
        : '<span class="btn secondary is-disabled" aria-disabled="true">Suivante →</span>';
    return '<nav class="pagination" aria-label="' . e($label) . '"><span class="pagination-info">' . $first . '–' . $last . ' sur ' . $totalRows . ' ' . e($noun) . '</span><div class="pagination-controls">' . $previous . $next . '</div></nav>';
}

function scope_clause(array $user, string $alias = 'f'): array
{
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $alias)) throw new InvalidArgumentException('Alias SQL invalide');
    $role = $user['role'] ?? '';
    if (has_global_scope($user)) return ['1=1', []];
    if (has_region_scope($user) && !empty($user['region_id'])) return ["{$alias}.region_id = :scope_region", [':scope_region' => (int)$user['region_id']]];
    if (in_array($role, [ROLE_PROVINCIAL, ROLE_VETERINARY_RESPONSIBLE], true) && !empty($user['province_id'])) return ["{$alias}.province_id = :scope_province", [':scope_province' => (int)$user['province_id']]];
    return ['1=0', []];
}

function can_access_farm(array $user, array $farm): bool
{
    return match ($user['role'] ?? '') {
        ROLE_CENTRAL, ROLE_ADMINISTRATOR, ROLE_CENTRAL_VIEWER => true,
        ROLE_REGIONAL, ROLE_DIVISION_HEAD => isset($user['region_id'], $farm['region_id']) && (int)$user['region_id'] === (int)$farm['region_id'],
        ROLE_PROVINCIAL, ROLE_VETERINARY_RESPONSIBLE => isset($user['province_id'], $farm['province_id']) && (int)$user['province_id'] === (int)$farm['province_id'],
        default => false,
    };
}

function clear_current_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool)$params['secure'], (bool)$params['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
}

function current_user(): ?array
{
    start_session();
    if (empty($_SESSION['user_id'])) return null;
    $now = time();
    if (session_idle_expired($now, (int)($_SESSION['last_activity'] ?? 0))) {
        clear_current_session();
        return null;
    }
    $_SESSION['last_activity'] = $now;
    $stmt = db()->prepare('SELECT u.id, u.email, u.full_name, u.role, u.region_id, u.province_id, r.name AS region_name, p.name AS province_name FROM users u LEFT JOIN regions r ON r.id=u.region_id LEFT JOIN provinces p ON p.id=u.province_id WHERE u.id=:id');
    $stmt->execute([':id' => (int)$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) { clear_current_session(); return null; }
    return $user;
}

function require_auth(): array
{
    $user = current_user();
    if (!$user) { header('Location: login.php'); exit; }
    return $user;
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    start_session();
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(403);
        exit('Jeton de sécurité invalide. Rechargez la page et réessayez.');
    }
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function flash(string $message, string $type = 'success'): void
{
    start_session();
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function take_flash(): ?array
{
    start_session();
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function role_label(array $user): string
{
    return match ($user['role'] ?? '') {
        ROLE_ADMINISTRATOR => 'Administrateur',
        ROLE_CENTRAL_VIEWER => 'Profil central — consultation',
        ROLE_CENTRAL => 'Direction centrale',
        ROLE_REGIONAL => 'Direction régionale' . (!empty($user['region_name']) ? ' — ' . $user['region_name'] : ''),
        ROLE_DIVISION_HEAD => 'Chef de division' . (!empty($user['region_name']) ? ' — ' . $user['region_name'] : ''),
        ROLE_PROVINCIAL => 'Chef de service — ' . ($user['province_name'] ?? 'Province non définie'),
        ROLE_VETERINARY_RESPONSIBLE => 'Vétérinaire — ' . ($user['province_name'] ?? 'Province non définie'),
        default => 'Profil inconnu',
    };
}

function visible_geography(array $user): array
{
    $pdo = db();
    if (has_global_scope($user)) {
        return $pdo->query('SELECT p.id AS province_id, p.name AS province_name, r.id AS region_id, r.name AS region_name FROM provinces p JOIN regions r ON r.id=p.region_id ORDER BY r.name,p.name')->fetchAll();
    }
    if (has_province_scope($user)) {
        $stmt = $pdo->prepare('SELECT p.id AS province_id, p.name AS province_name, r.id AS region_id, r.name AS region_name FROM provinces p JOIN regions r ON r.id=p.region_id WHERE p.id=:id');
        $stmt->execute([':id' => (int)($user['province_id'] ?? 0)]);
        $row = $stmt->fetch();
        return $row ? [$row] : [];
    }
    if (has_region_scope($user)) {
        $stmt = $pdo->prepare('SELECT p.id AS province_id, p.name AS province_name, r.id AS region_id, r.name AS region_name FROM provinces p JOIN regions r ON r.id=p.region_id WHERE r.id=:id ORDER BY p.name');
        $stmt->execute([':id' => (int)($user['region_id'] ?? 0)]);
        return $stmt->fetchAll();
    }
    return $pdo->query('SELECT p.id AS province_id, p.name AS province_name, r.id AS region_id, r.name AS region_name FROM provinces p JOIN regions r ON r.id=p.region_id ORDER BY r.name,p.name')->fetchAll();
}

function farm_scope_where(array $user, array $filters = [], string $alias = 'f'): array
{
    [$scope, $params] = scope_clause($user, $alias);
    $parts = [$scope];
    foreach (['region_id' => 'region', 'province_id' => 'province'] as $key => $param) {
        $value = filter_var($filters[$key] ?? null, FILTER_VALIDATE_INT);
        if ($value !== false && $value !== null && $value > 0) {
            $parts[] = "{$alias}.{$key} = :filter_{$param}";
            $params[":filter_{$param}"] = $value;
        }
    }
    if (!empty($filters['type']) && in_array($filters['type'], FARM_TYPES, true)) {
        $parts[] = "{$alias}.farm_type = :filter_type";
        $params[':filter_type'] = $filters['type'];
    }
    if (!empty($filters['status']) && in_array($filters['status'], FARM_STATUSES, true)) {
        $parts[] = "{$alias}.authorization_status = :filter_status";
        $params[':filter_status'] = $filters['status'];
    }
    if (!empty($filters['q'])) {
        $parts[] = "({$alias}.farm_name LIKE :search OR {$alias}.owner_name LIKE :search OR {$alias}.authorization_number LIKE :search OR {$alias}.agreement_number LIKE :search)";
        $params[':search'] = '%' . trim((string)$filters['q']) . '%';
    }
    return [implode(' AND ', $parts), $params];
}

function list_farms(array $user, array $filters = [], string $sort = 'region', string $group = 'global'): array
{
    [$where, $params] = farm_scope_where($user, $filters);
    $sorts = [
        'region' => 'r.name, p.name, f.id',
        'province' => 'p.name, f.id',
        'name' => 'f.farm_name',
        'status' => 'f.authorization_status, f.farm_name',
        'type' => 'f.farm_type, f.farm_name',
        'date' => 'f.first_agreement_date, f.farm_name',
    ];
    $order = $sorts[$sort] ?? $sorts['region'];
    if (in_array($group, ['region','province'], true)) $order = 'r.name, p.name, ' . $order;
    $stmt = db()->prepare("SELECT f.*, r.name AS region_name, p.name AS province_name FROM farms f JOIN regions r ON r.id=f.region_id JOIN provinces p ON p.id=f.province_id WHERE {$where} ORDER BY {$order}");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function farm_by_id(int $id, array $user): ?array
{
    $stmt = db()->prepare('SELECT f.*, r.name AS region_name, p.name AS province_name FROM farms f JOIN regions r ON r.id=f.region_id JOIN provinces p ON p.id=f.province_id WHERE f.id=:id');
    $stmt->execute([':id' => $id]);
    $farm = $stmt->fetch();
    return $farm && can_access_farm($user, $farm) ? $farm : null;
}

function geography_rows(array $user): array
{
    $rows = visible_geography($user);
    $regions = [];
    foreach ($rows as $row) $regions[(int)$row['region_id']] = $row['region_name'];
    return ['rows' => $rows, 'regions' => $regions];
}

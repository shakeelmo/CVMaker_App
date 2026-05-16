<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

const INSTALL_LOCK_FILE = __DIR__ . '/config/install.lock';
define('LICENSE_API_URL', getenv('LICENSE_API_URL') ?: 'https://resume.muawia.com/api/validate');
define('LICENSE_API_SECRET', getenv('LICENSE_API_SECRET') ?: '');

$steps = [
    1 => 'Requirements',
    2 => 'Database',
    3 => 'License',
    4 => 'Admin Account',
    5 => 'Site Settings',
    6 => 'Complete',
];

if (file_exists(INSTALL_LOCK_FILE)) {
    die(renderBlockedScreen('CV Maker is already installed.', 'Installation is locked. Delete config/install.lock only if you intentionally want to reinstall.'));
}

$currentStep = isset($_GET['step']) ? max(1, min(6, (int)$_GET['step'])) : 1;
$errors = [];
$success = '';

if (!isset($_SESSION['install'])) {
    $_SESSION['install'] = [
        'db' => [],
        'license' => [],
        'admin' => [],
        'site' => [],
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedStep = (int)($_POST['step'] ?? $currentStep);

    try {
        switch ($postedStep) {
            case 1:
                $requirements = checkRequirements();
                foreach ($requirements as $req) {
                    if (!$req['ok']) {
                        throw new RuntimeException('Server requirements check failed.');
                    }
                }
                header('Location: ?step=2');
                exit;

            case 2:
                $dbHost = trim($_POST['db_host'] ?? 'localhost');
                $dbName = trim($_POST['db_name'] ?? '');
                $dbUser = trim($_POST['db_user'] ?? '');
                $dbPass = trim($_POST['db_pass'] ?? '');

                if ($dbHost === '' || $dbName === '' || $dbUser === '') {
                    throw new RuntimeException('Please fill in all database fields except password if not needed.');
                }

                $pdo = connectDatabase($dbHost, $dbName, $dbUser, $dbPass);
                $_SESSION['install']['db'] = compact('dbHost', 'dbName', 'dbUser', 'dbPass');
                $_SESSION['install']['db']['connected'] = true;
                header('Location: ?step=3');
                exit;

            case 3:
                $licenseKey = strtoupper(trim($_POST['license_key'] ?? ''));
                $licenseDomain = trim($_POST['license_domain'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost'));

                if ($licenseKey === '' || $licenseDomain === '') {
                    throw new RuntimeException('License key and domain are required.');
                }

                $licenseResponse = validateLicense($licenseKey, $licenseDomain);
                if (empty($licenseResponse['valid'])) {
                    throw new RuntimeException($licenseResponse['message'] ?? 'License validation failed.');
                }

                $_SESSION['install']['license'] = [
                    'license_key' => $licenseKey,
                    'license_domain' => $licenseDomain,
                    'response' => $licenseResponse,
                ];
                header('Location: ?step=4');
                exit;

            case 4:
                $firstName = trim($_POST['first_name'] ?? '');
                $lastName = trim($_POST['last_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $password = (string)($_POST['password'] ?? '');
                $confirmPassword = (string)($_POST['confirm_password'] ?? '');

                if ($firstName === '' || $lastName === '' || $email === '' || $password === '') {
                    throw new RuntimeException('All admin account fields are required.');
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Please enter a valid admin email address.');
                }
                if (strlen($password) < 8) {
                    throw new RuntimeException('Admin password must be at least 8 characters.');
                }
                if ($password !== $confirmPassword) {
                    throw new RuntimeException('Passwords do not match.');
                }

                $_SESSION['install']['admin'] = [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ];
                header('Location: ?step=5');
                exit;

            case 5:
                $siteName = trim($_POST['site_name'] ?? 'CV Maker');
                $siteDescription = trim($_POST['site_description'] ?? 'Create professional resumes');
                $siteEmail = trim($_POST['site_email'] ?? '');
                $contactEmail = trim($_POST['contact_email'] ?? '');
                $paypalClientId = trim($_POST['paypal_client_id'] ?? '');
                $paypalClientSecret = trim($_POST['paypal_client_secret'] ?? '');
                $geminiApiKey = trim($_POST['gemini_api_key'] ?? '');
                $smtpHost = trim($_POST['smtp_host'] ?? '');
                $smtpPort = trim($_POST['smtp_port'] ?? '587');
                $smtpUser = trim($_POST['smtp_user'] ?? '');
                $smtpPass = trim($_POST['smtp_pass'] ?? '');

                if ($siteName === '') {
                    throw new RuntimeException('Site name is required.');
                }
                if ($siteEmail !== '' && !filter_var($siteEmail, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Site email is invalid.');
                }
                if ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Contact email is invalid.');
                }

                $_SESSION['install']['site'] = compact(
                    'siteName',
                    'siteDescription',
                    'siteEmail',
                    'contactEmail',
                    'paypalClientId',
                    'paypalClientSecret',
                    'geminiApiKey',
                    'smtpHost',
                    'smtpPort',
                    'smtpUser',
                    'smtpPass'
                );

                runInstallation();
                header('Location: ?step=6');
                exit;

            default:
                break;
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
        $currentStep = $postedStep;
    }
}

echo renderLayout($steps, $currentStep, $errors, $success);

function connectDatabase(string $host, string $name, string $user, string $pass): PDO
{
    return new PDO(
        "mysql:host={$host};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function checkRequirements(): array
{
    $writableEnvDir = dirname($_SERVER['DOCUMENT_ROOT'] ?: __DIR__);

    return [
        ['label' => 'PHP 8.0+', 'ok' => version_compare(PHP_VERSION, '8.0.0', '>=')],
        ['label' => 'PDO extension', 'ok' => extension_loaded('pdo_mysql')],
        ['label' => 'cURL extension', 'ok' => extension_loaded('curl')],
        ['label' => 'JSON extension', 'ok' => extension_loaded('json')],
        ['label' => 'OpenSSL extension', 'ok' => extension_loaded('openssl')],
        ['label' => 'Config directory writable', 'ok' => is_writable(__DIR__ . '/config')],
        ['label' => 'Env parent directory writable', 'ok' => is_dir($writableEnvDir) && is_writable($writableEnvDir)],
    ];
}

function validateLicense(string $licenseKey, string $domain): array
{
    if (LICENSE_API_SECRET === '') {
        throw new RuntimeException('License API secret is not configured. Set LICENSE_API_SECRET in the environment before installing.');
    }

    $payload = json_encode([
        'license_key' => $licenseKey,
        'domain' => $domain,
        'action' => 'activate',
    ], JSON_UNESCAPED_SLASHES);

    $ch = curl_init(LICENSE_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-API-Secret: ' . LICENSE_API_SECRET,
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('License API request failed: ' . $curlError);
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('License API returned an invalid response.');
    }

    if ($httpCode >= 400) {
        throw new RuntimeException($decoded['message'] ?? 'License API rejected the request.');
    }

    return $decoded;
}

function runInstallation(): void
{
    $db = $_SESSION['install']['db'] ?? null;
    $license = $_SESSION['install']['license'] ?? null;
    $admin = $_SESSION['install']['admin'] ?? null;
    $site = $_SESSION['install']['site'] ?? null;

    if (!$db || !$license || !$admin || !$site) {
        throw new RuntimeException('Installation session is incomplete. Please restart the installer.');
    }

    $pdo = connectDatabase($db['dbHost'], $db['dbName'], $db['dbUser'], $db['dbPass']);
    $sqlPath = __DIR__ . '/database.sql';
    if (!file_exists($sqlPath)) {
        throw new RuntimeException('database.sql not found.');
    }

    importSqlFile($pdo, $sqlPath);
    writeEnvFile($db, $license, $site);
    createAdminUser($pdo, $admin);
    applySettings($pdo, $site, $license);
    file_put_contents(INSTALL_LOCK_FILE, json_encode([
        'installed_at' => date('c'),
        'domain' => $license['license_domain'],
        'admin_email' => $admin['email'],
    ], JSON_PRETTY_PRINT));
}

function importSqlFile(PDO $pdo, string $sqlPath): void
{
    $sql = file_get_contents($sqlPath);
    if ($sql === false) {
        throw new RuntimeException('Unable to read database.sql');
    }

    $pdo->exec($sql);
}

function writeEnvFile(array $db, array $license, array $site): void
{
    $envDir = dirname($_SERVER['DOCUMENT_ROOT'] ?: __DIR__);
    $envPath = $envDir . '/.env';

    $content = implode("\n", [
        'DB_HOST=' . $db['dbHost'],
        'DB_NAME=' . $db['dbName'],
        'DB_USER=' . $db['dbUser'],
        'DB_PASSWORD=' . $db['dbPass'],
        'PAYPAL_CLIENT_ID=' . ($site['paypalClientId'] ?? ''),
        'PAYPAL_CLIENT_SECRET=' . ($site['paypalClientSecret'] ?? ''),
        'GEMINI_API_KEY=' . ($site['geminiApiKey'] ?? ''),
        'SMTP_HOST=' . ($site['smtpHost'] ?? ''),
        'SMTP_PORT=' . ($site['smtpPort'] ?? '587'),
        'SMTP_USERNAME=' . ($site['smtpUser'] ?? ''),
        'SMTP_PASSWORD=' . ($site['smtpPass'] ?? ''),
        'LICENSE_KEY=' . $license['license_key'],
        '',
    ]);

    if (file_put_contents($envPath, $content) === false) {
        throw new RuntimeException('Failed to write .env file above document root.');
    }
}

function createAdminUser(PDO $pdo, array $admin): void
{
    $stmt = $pdo->prepare("DELETE FROM users WHERE role IN ('admin', 'super_admin')");
    $stmt->execute();

    $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, first_name, last_name, role, subscription_tier, is_active, email_verified, force_password_change, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
    $stmt->execute([
        $admin['email'],
        $admin['password_hash'],
        $admin['first_name'],
        $admin['last_name'],
        'admin',
        'pro',
        1,
        1,
        0,
    ]);
}

function applySettings(PDO $pdo, array $site, array $license): void
{
    $settings = [
        'site_name' => $site['siteName'] ?? 'CV Maker',
        'site_description' => $site['siteDescription'] ?? 'Create professional resumes',
        'site_email' => $site['siteEmail'] ?? '',
        'contact_email' => $site['contactEmail'] ?? '',
        'paypal_client_id' => $site['paypalClientId'] ?? '',
        'paypal_client_secret' => $site['paypalClientSecret'] ?? '',
        'gemini_api_key' => $site['geminiApiKey'] ?? '',
        'smtp_host' => $site['smtpHost'] ?? '',
        'smtp_port' => $site['smtpPort'] ?? '587',
        'smtp_username' => $site['smtpUser'] ?? '',
        'smtp_password' => $site['smtpPass'] ?? '',
        'smtp_from_email' => $site['siteEmail'] ?? '',
        'license_key' => $license['license_key'] ?? '',
    ];

    $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value, is_encrypted) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_encrypted = VALUES(is_encrypted), updated_at = CURRENT_TIMESTAMP');

    foreach ($settings as $key => $value) {
        $encrypted = in_array($key, ['paypal_client_id', 'paypal_client_secret', 'gemini_api_key', 'smtp_password'], true) ? 1 : 0;
        $stmt->execute([$key, $value, $encrypted]);
    }
}

function old(string $section, string $key, string $default = ''): string
{
    return htmlspecialchars($_SESSION['install'][$section][$key] ?? $default, ENT_QUOTES, 'UTF-8');
}

function renderLayout(array $steps, int $currentStep, array $errors, string $success): string
{
    $progress = (int)(($currentStep - 1) / 5 * 100);
    $requirements = checkRequirements();

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CV Maker v2.0 Installer</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen text-slate-800">
    <div class="max-w-5xl mx-auto px-4 py-8">
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <div class="bg-slate-900 text-white p-6 md:p-8">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-bold">CV Maker Installer</h1>
                        <p class="text-slate-300 mt-2">Production-ready 6-step setup wizard</p>
                    </div>
                    <div class="text-sm text-slate-300">Step <?= $currentStep ?> of 6</div>
                </div>
                <div class="mt-6">
                    <div class="w-full h-3 bg-slate-700 rounded-full overflow-hidden">
                        <div class="h-full bg-emerald-400 transition-all duration-500" style="width: <?= $progress ?>%"></div>
                    </div>
                </div>
            </div>

            <div class="grid lg:grid-cols-4 gap-0">
                <aside class="lg:col-span-1 bg-slate-50 border-r border-slate-200 p-6">
                    <div class="space-y-3">
                        <?php foreach ($steps as $number => $label): ?>
                            <div class="flex items-center gap-3 <?= $number === $currentStep ? 'text-slate-900 font-semibold' : 'text-slate-500' ?>">
                                <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm <?= $number < $currentStep ? 'bg-emerald-500 text-white' : ($number === $currentStep ? 'bg-slate-900 text-white' : 'bg-slate-200') ?>">
                                    <?= $number < $currentStep ? '✓' : $number ?>
                                </div>
                                <span><?= htmlspecialchars($label) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </aside>

                <main class="lg:col-span-3 p-6 md:p-8">
                    <?php if ($errors): ?>
                        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 text-red-700 px-4 py-3">
                            <ul class="list-disc list-inside space-y-1">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($success !== ''): ?>
                        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 px-4 py-3">
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>

                    <?= renderStepContent($currentStep, $requirements) ?>
                </main>
            </div>
        </div>
    </div>
</body>
</html>
    <?php
    return ob_get_clean();
}

function renderStepContent(int $step, array $requirements): string
{
    ob_start();

    switch ($step) {
        case 1:
            ?>
            <h2 class="text-2xl font-bold mb-2">Step 1: Server Requirements</h2>
            <p class="text-slate-600 mb-6">We’ll verify the server is ready before touching your database or files.</p>
            <div class="space-y-3 mb-8">
                <?php foreach ($requirements as $req): ?>
                    <div class="flex items-center justify-between rounded-xl border px-4 py-3 <?= $req['ok'] ? 'border-emerald-200 bg-emerald-50' : 'border-red-200 bg-red-50' ?>">
                        <span class="font-medium"><?= htmlspecialchars($req['label']) ?></span>
                        <span class="text-sm font-semibold <?= $req['ok'] ? 'text-emerald-700' : 'text-red-700' ?>"><?= $req['ok'] ? 'Ready' : 'Missing' ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <form method="post" class="flex justify-end">
                <input type="hidden" name="step" value="1">
                <button class="bg-slate-900 hover:bg-slate-800 text-white px-6 py-3 rounded-xl font-semibold">Continue</button>
            </form>
            <?php
            break;

        case 2:
            ?>
            <h2 class="text-2xl font-bold mb-2">Step 2: Database Connection</h2>
            <p class="text-slate-600 mb-6">Enter the database credentials for the new CV Maker installation.</p>
            <form method="post" class="grid md:grid-cols-2 gap-4">
                <input type="hidden" name="step" value="2">
                <div>
                    <label class="block text-sm font-medium mb-2">Database Host</label>
                    <input name="db_host" value="<?= old('db', 'dbHost', 'localhost') ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3" required>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Database Name</label>
                    <input name="db_name" value="<?= old('db', 'dbName') ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3" required>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Database User</label>
                    <input name="db_user" value="<?= old('db', 'dbUser') ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3" required>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Database Password</label>
                    <input type="password" name="db_pass" value="<?= old('db', 'dbPass') ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                </div>
                <div class="md:col-span-2 flex justify-between pt-4">
                    <a href="?step=1" class="px-5 py-3 rounded-xl border border-slate-300">Back</a>
                    <button class="bg-slate-900 hover:bg-slate-800 text-white px-6 py-3 rounded-xl font-semibold">Test & Continue</button>
                </div>
            </form>
            <?php
            break;

        case 3:
            ?>
            <h2 class="text-2xl font-bold mb-2">Step 3: License Activation</h2>
            <p class="text-slate-600 mb-6">Your license is activated during install using the official CV Maker validation API.</p>
            <form method="post" class="space-y-4">
                <input type="hidden" name="step" value="3">
                <div>
                    <label class="block text-sm font-medium mb-2">License Key</label>
                    <input name="license_key" value="<?= old('license', 'license_key') ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3" placeholder="RSSD-XXXX-XXXX-XXXX" required>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Domain</label>
                    <input name="license_domain" value="<?= old('license', 'license_domain', $_SERVER['HTTP_HOST'] ?? 'localhost') ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3" required>
                </div>
                <div class="flex justify-between pt-4">
                    <a href="?step=2" class="px-5 py-3 rounded-xl border border-slate-300">Back</a>
                    <button class="bg-slate-900 hover:bg-slate-800 text-white px-6 py-3 rounded-xl font-semibold">Validate License</button>
                </div>
            </form>
            <?php
            break;

        case 4:
            ?>
            <h2 class="text-2xl font-bold mb-2">Step 4: Admin Account</h2>
            <p class="text-slate-600 mb-6">Create the first administrator account for the dashboard.</p>
            <form method="post" class="grid md:grid-cols-2 gap-4">
                <input type="hidden" name="step" value="4">
                <div>
                    <label class="block text-sm font-medium mb-2">First Name</label>
                    <input name="first_name" value="<?= old('admin', 'first_name') ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3" required>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Last Name</label>
                    <input name="last_name" value="<?= old('admin', 'last_name') ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3" required>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-2">Admin Email</label>
                    <input type="email" name="email" value="<?= old('admin', 'email') ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3" required>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Password</label>
                    <input type="password" name="password" class="w-full rounded-xl border border-slate-300 px-4 py-3" required>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Confirm Password</label>
                    <input type="password" name="confirm_password" class="w-full rounded-xl border border-slate-300 px-4 py-3" required>
                </div>
                <div class="md:col-span-2 flex justify-between pt-4">
                    <a href="?step=3" class="px-5 py-3 rounded-xl border border-slate-300">Back</a>
                    <button class="bg-slate-900 hover:bg-slate-800 text-white px-6 py-3 rounded-xl font-semibold">Save Admin</button>
                </div>
            </form>
            <?php
            break;

        case 5:
            ?>
            <h2 class="text-2xl font-bold mb-2">Step 5: Site Settings</h2>
            <p class="text-slate-600 mb-6">Set the site identity and optional service credentials. These are also written to the .env file above document root.</p>
            <form method="post" class="grid md:grid-cols-2 gap-4">
                <input type="hidden" name="step" value="5">
                <div>
                    <label class="block text-sm font-medium mb-2">Site Name</label>
                    <input name="site_name" value="<?= old('site', 'siteName', 'CV Maker') ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3" required>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Site Email</label>
                    <input type="email" name="site_email" value="<?= old('site', 'siteEmail') ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-2">Site Description</label>
                    <textarea name="site_description" rows="3" class="w-full rounded-xl border border-slate-300 px-4 py-3"><?= old('site', 'siteDescription', 'Create professional resumes') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Contact Email</label>
                    <input type="email" name="contact_email" value="<?= old('site', 'contactEmail') ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                </div>
                <div></div>
                <div>
                    <label class="block text-sm font-medium mb-2">PayPal Client ID</label>
                    <input name="paypal_client_id" value="<?= old('site', 'paypalClientId') ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">PayPal Client Secret</label>
                    <input name="paypal_client_secret" value="<?= old('site', 'paypalClientSecret') ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Gemini API Key</label>
                    <input name="gemini_api_key" value="<?= old('site', 'geminiApiKey') ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                </div>
                <div></div>
                <div>
                    <label class="block text-sm font-medium mb-2">SMTP Host</label>
                    <input name="smtp_host" value="<?= old('site', 'smtpHost') ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">SMTP Port</label>
                    <input name="smtp_port" value="<?= old('site', 'smtpPort', '587') ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">SMTP Username</label>
                    <input name="smtp_user" value="<?= old('site', 'smtpUser') ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">SMTP Password</label>
                    <input type="password" name="smtp_pass" value="<?= old('site', 'smtpPass') ?>" class="w-full rounded-xl border border-slate-300 px-4 py-3">
                </div>
                <div class="md:col-span-2 flex justify-between pt-4">
                    <a href="?step=4" class="px-5 py-3 rounded-xl border border-slate-300">Back</a>
                    <button class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-3 rounded-xl font-semibold">Install CV Maker</button>
                </div>
            </form>
            <?php
            break;

        case 6:
            $adminEmail = htmlspecialchars($_SESSION['install']['admin']['email'] ?? '', ENT_QUOTES, 'UTF-8');
            ?>
            <div class="text-center py-10">
                <div class="w-20 h-20 mx-auto rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-4xl mb-6">✓</div>
                <h2 class="text-3xl font-bold mb-3">Installation Complete</h2>
                <p class="text-slate-600 max-w-2xl mx-auto mb-8">CV Maker has been installed successfully. Your database was imported, license activated, environment file created above the document root, and admin account provisioned.</p>
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 max-w-2xl mx-auto text-left">
                    <div class="grid md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <div class="text-slate-500">Admin login</div>
                            <div class="font-semibold text-slate-900"><?= $adminEmail ?></div>
                        </div>
                        <div>
                            <div class="text-slate-500">Dashboard URL</div>
                            <div class="font-semibold text-slate-900">/admin.html</div>
                        </div>
                    </div>
                </div>
                <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
                    <a href="/admin.html" class="bg-slate-900 hover:bg-slate-800 text-white px-6 py-3 rounded-xl font-semibold">Go to Admin</a>
                    <a href="/" class="border border-slate-300 px-6 py-3 rounded-xl font-semibold">View Site</a>
                </div>
            </div>
            <?php
            break;
    }

    return ob_get_clean();
}

function renderBlockedScreen(string $title, string $message): string
{
    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Installer Locked</title><script src="https://cdn.tailwindcss.com"></script></head><body class="bg-slate-100 min-h-screen flex items-center justify-center p-6"><div class="max-w-xl w-full bg-white rounded-2xl shadow-xl p-8 text-center"><div class="w-16 h-16 mx-auto rounded-full bg-amber-100 text-amber-700 flex items-center justify-center text-3xl mb-5">!</div><h1 class="text-2xl font-bold mb-3">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1><p class="text-slate-600">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p></div></body></html>';
}

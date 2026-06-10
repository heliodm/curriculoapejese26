<?php
define('VERSION_FILE', SITE_ROOT . '/version.json');

function upd_readVersion(): array {
    $defaults = [
        'commit'           => 'desconhecido',
        'branch'           => getSetting('github_branch', 'claude/resume-management-system-Aam95'),
        'updated_at'       => '',
        'latest_commit'    => '',
        'latest_date'      => '',
        'latest_message'   => '',
        'latest_author'    => '',
        'update_available' => false,
        'checked_at'       => '',
        'api_error'        => '',
    ];
    if (!file_exists(VERSION_FILE)) return $defaults;
    $data = json_decode(file_get_contents(VERSION_FILE), true) ?? [];
    return array_merge($defaults, $data);
}

function upd_writeVersion(array $data): void {
    file_put_contents(VERSION_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function upd_checkGithub(bool $force = false): array {
    $v = upd_readVersion();

    // Use cache if checked within last hour (unless forced)
    if (!$force && !empty($v['checked_at'])) {
        if ((time() - (int)strtotime($v['checked_at'])) < 3600) return $v;
    }

    $repo   = getSetting('github_repo', '');
    $branch = $v['branch'] ?: getSetting('github_branch', 'main');
    $token  = getSetting('github_token', '');

    $fail = function (string $msg) use ($v): array {
        $v['api_error']  = $msg;
        $v['checked_at'] = date('Y-m-d H:i:s');
        upd_writeVersion($v);
        return $v;
    };

    if (empty($repo)) return $fail('Repositório não configurado. Acesse Configurações › GitHub.');

    $url  = "https://api.github.com/repos/{$repo}/commits?" . http_build_query(['sha' => $branch, 'per_page' => 1]);
    $hdrs = ['Accept: application/vnd.github.v3+json', 'User-Agent: CurriculoUpdateBot/1.0'];
    if ($token) $hdrs[] = "Authorization: token {$token}";

    $body = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'CurriculoUpdateBot/1.0',
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => $hdrs,
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($err) return $fail("cURL: {$err}");
        if ($code !== 200) {
            $errData = json_decode((string)$body, true);
            $ghMsg   = $errData['message'] ?? "HTTP {$code}";
            if ($code === 404 && !$token) {
                $ghMsg .= ' — se o repositório for privado, configure um Token de acesso nas Configurações.';
            }
            return $fail("GitHub: {$ghMsg}");
        }
    } elseif (ini_get('allow_url_fopen')) {
        $ctx  = stream_context_create(['http' => [
            'method'           => 'GET',
            'header'           => implode("\r\n", $hdrs),
            'timeout'          => 10,
            'ignore_errors'    => true,
        ]]);
        $body     = @file_get_contents($url, false, $ctx);
        $respCode = 0;
        if (isset($http_response_header) && preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0], $m)) {
            $respCode = (int)$m[1];
        }
        if (!$body || $respCode !== 200) {
            $errData = json_decode((string)$body, true);
            $ghMsg   = $errData['message'] ?? ($respCode ? "HTTP {$respCode}" : 'Falha ao conectar ao GitHub.');
            if ($respCode === 404 && !$token) {
                $ghMsg .= ' — se o repositório for privado, configure um Token de acesso nas Configurações.';
            }
            return $fail("GitHub: {$ghMsg}");
        }
    } else {
        return $fail('cURL e allow_url_fopen indisponíveis no servidor.');
    }

    $data = json_decode($body, true);
    if (isset($data['message'])) return $fail('GitHub: ' . $data['message']);

    // List endpoint returns an array; grab the first (most recent) commit
    $commit = is_array($data) && isset($data[0]) ? $data[0] : null;
    if (!$commit) return $fail('Nenhum commit encontrado para o branch "' . $branch . '".');

    $latest = substr($commit['sha'] ?? '', 0, 7);
    $v['latest_commit']  = $latest;
    $v['latest_date']    = $commit['commit']['author']['date'] ?? '';
    $v['latest_message'] = $commit['commit']['message'] ?? '';
    $v['latest_author']  = $commit['commit']['author']['name'] ?? '';
    $v['api_error']      = '';
    $v['checked_at']     = date('Y-m-d H:i:s');

    if ($v['commit'] === 'desconhecido' && $latest) {
        // First check: stamp current as installed version
        $v['commit']           = $latest;
        $v['update_available'] = false;
    } else {
        $v['update_available'] = ($latest !== '' && $v['commit'] !== $latest);
    }

    upd_writeVersion($v);
    return $v;
}

function upd_canDownload(): bool {
    return function_exists('curl_init') || (bool)ini_get('allow_url_fopen');
}

function upd_canExtract(): bool {
    return class_exists('ZipArchive');
}

function upd_canWrite(): bool {
    return is_writable(SITE_ROOT . '/admin') && is_writable(SITE_ROOT . '/includes');
}

function upd_formatDate(string $d): string {
    if (!$d) return '—';
    try { return (new DateTime($d))->format('d/m/Y \à\s H:i'); }
    catch (\Exception $e) { return $d; }
}

function upd_executeUpdate(): array {
    $v       = upd_readVersion();
    $repo    = getSetting('github_repo', '');
    $branch  = $v['branch'] ?: getSetting('github_branch', 'main');
    $token   = getSetting('github_token', '');
    $logsDir = SITE_ROOT . '/logs';
    $logFile = $logsDir . '/updates.log';

    if (!is_dir($logsDir)) mkdir($logsDir, 0755, true);
    if (empty($repo)) return ['sucesso' => false, 'output' => 'Repositório GitHub não configurado.'];

    $tmpBase = sys_get_temp_dir() . '/curriculo_upd_' . time();
    $zipFile = $tmpBase . '.zip';
    $tmpDir  = $tmpBase . '_dir';

    ob_start();
    try {
        echo "Baixando {$repo}@{$branch}…\n";
        $zipUrl  = "https://github.com/{$repo}/archive/refs/heads/{$branch}.zip";
        $content = null;

        if (function_exists('curl_init')) {
            $ch = curl_init($zipUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT      => 'CurriculoUpdateBot/1.0',
                CURLOPT_TIMEOUT        => 120,
                CURLOPT_HTTPHEADER     => $token ? ["Authorization: token {$token}"] : [],
            ]);
            $content  = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err      = curl_error($ch);
            curl_close($ch);
            if ($err) throw new \Exception("cURL: {$err}");
            if ($httpCode !== 200) throw new \Exception("GitHub retornou HTTP {$httpCode}.");
        } elseif (ini_get('allow_url_fopen')) {
            $ctx = stream_context_create(['http' => [
                'method'  => 'GET',
                'header'  => "User-Agent: CurriculoUpdateBot/1.0\r\n" .
                             ($token ? "Authorization: token {$token}\r\n" : ''),
                'timeout' => 120,
            ]]);
            $content = @file_get_contents($zipUrl, false, $ctx);
            if (!$content) throw new \Exception("Falha no download. Verifique repo, branch e conexão.");
        } else {
            throw new \Exception("cURL e allow_url_fopen indisponíveis.");
        }

        echo "Download OK (" . number_format(strlen($content) / 1024, 0) . " KB). Extraindo…\n";
        mkdir($tmpDir, 0755, true);
        file_put_contents($zipFile, $content);
        unset($content);

        if (!class_exists('ZipArchive')) throw new \Exception("ZipArchive não disponível.");
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) throw new \Exception("Falha ao abrir o zip.");
        $zip->extractTo($tmpDir);
        $zip->close();
        unlink($zipFile);

        $dirs = glob($tmpDir . '/*', GLOB_ONLYDIR);
        if (empty($dirs)) throw new \Exception("Estrutura do zip inesperada.");

        echo "Copiando arquivos…\n";
        copyDirectory($dirs[0], SITE_ROOT, [
            'config/database.php', 'config/env.php', 'install.lock', 'assets/uploads', 'logs', '.htaccess', 'version.json',
        ]);
        deleteDirectory($tmpDir);

        $newCommit = $v['latest_commit'] ?: 'desconhecido';
        $v['commit']           = $newCommit;
        $v['updated_at']       = date('Y-m-d H:i:s');
        $v['update_available'] = false;
        upd_writeVersion($v);

        file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] OK | {$repo}@{$branch} → {$newCommit}\n", FILE_APPEND);
        echo "Concluído! Versão: {$newCommit}\n";

        return ['sucesso' => true, 'output' => ob_get_clean(), 'commit' => $newCommit];

    } catch (\Exception $e) {
        @unlink($zipFile);
        deleteDirectory($tmpDir);
        echo "ERRO: " . $e->getMessage() . "\n";
        $out = ob_get_clean();
        file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] ERRO | " . $e->getMessage() . "\n", FILE_APPEND);
        return ['sucesso' => false, 'output' => $out];
    }
}

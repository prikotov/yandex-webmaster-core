<?php

class WebmasterClient
{
    private const OAUTH_URL = 'https://oauth.yandex.ru/token';
    private const API_URL = 'https://api.webmaster.yandex.net/v4';
    private const REDIRECT_URI = 'https://oauth.yandex.ru/verification_code';
    
    private string $clientId;
    private string $clientSecret;
    private ?int $userId = null;
    private ?string $hostId;
    private ?string $siteName;
    private string $tokenFile;
    
    public function __construct(string $clientId, string $clientSecret, ?string $hostId = null, ?string $siteName = null, ?string $tokenFile = null)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->hostId = $hostId;
        $this->siteName = $siteName;
        $this->tokenFile = $tokenFile ?? getcwd() . '/yandex_webmaster_token.json';
    }
    
    public static function checkGitignore(): void
    {
        $gitignoreFile = getcwd() . '/.gitignore';
        $requiredEntries = [
            'yandex_webmaster_config.json',
            'yandex_webmaster_token.json',
            'yandex_webmaster_reports/'
        ];
        
        if (!file_exists($gitignoreFile)) {
            file_put_contents($gitignoreFile, implode("\n", $requiredEntries) . "\n");
            echo "  ⚠️  Создан .gitignore с защитой секретных файлов\n\n";
            return;
        }
        
        $lines = array_map('trim', file($gitignoreFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        
        $missing = [];
        foreach ($requiredEntries as $entry) {
            if (!in_array($entry, $lines)) {
                $missing[] = $entry;
            }
        }
        
        if (!empty($missing)) {
            $content = rtrim(file_get_contents($gitignoreFile)) . "\n";
            foreach ($missing as $entry) {
                $content .= $entry . "\n";
            }
            file_put_contents($gitignoreFile, $content);
            echo "  ⚠️  Добавлено в .gitignore: " . implode(', ', $missing) . "\n\n";
        }
    }
    
    public static function loadConfig(): array
    {
        $configFile = getcwd() . '/yandex_webmaster_config.json';
        
        if (!file_exists($configFile)) {
            file_put_contents($configFile, json_encode([
                'client_id' => 'ВАШ_CLIENT_ID',
                'client_secret' => 'ВАШ_CLIENT_SECRET',
                'hosts' => [
                    'сайт1.ru' => 'https:сайт1.ru:443',
                    'сайт2.ru' => 'https:сайт2.ru:443'
                ],
                'default_host' => 'сайт1.ru'
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            echo "\n  Создан файл конфигурации: $configFile\n";
            echo "  Заполните в нём:\n";
            echo "    - client_id: ID приложения Яндекс.OAuth\n";
            echo "    - client_secret: Пароль приложения\n";
            echo "    - hosts: список сайтов с их host_id\n";
            echo "    - default_host: имя сайта по умолчанию\n\n";
            echo "  Как создать OAuth-приложение:\n";
            echo "  1. https://oauth.yandex.ru/client/new\n";
            echo "  2. Платформа: Веб-сервисы\n";
            echo "  3. Redirect URI: https://oauth.yandex.ru/verification_code\n";
            echo "  4. Доступы:\n";
            echo "     - webmaster:hostinfo\n";
            echo "     - webmaster:verify\n\n";
            exit(1);
        }
        
        $config = json_decode(file_get_contents($configFile), true);
        
        if ($config['client_id'] === 'ВАШ_CLIENT_ID') {
            echo "\n  Заполните конфигурацию в файле: $configFile\n";
            exit(1);
        }
        
        return $config;
    }
    
    public static function getHostIdFromConfig(array $config, ?string $siteName = null): ?string
    {
        if (!isset($config['hosts'])) {
            return $config['host_id'] ?? null;
        }
        
        if ($siteName === null) {
            $siteName = $config['default_host'] ?? array_key_first($config['hosts']);
        }
        
        if (!isset($config['hosts'][$siteName])) {
            $available = implode(', ', array_keys($config['hosts']));
            throw new Exception("Сайт '$siteName' не найден. Доступные: $available");
        }
        
        return $config['hosts'][$siteName];
    }
    
    public static function getAvailableSites(array $config): array
    {
        if (isset($config['hosts'])) {
            return array_keys($config['hosts']);
        }
        return [];
    }
    
    public function getSiteName(): ?string
    {
        return $this->siteName;
    }
    
    private function getAuthUrl(): string
    {
        return 'https://oauth.yandex.ru/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => self::REDIRECT_URI,
            'scope' => 'webmaster:hostinfo webmaster:verify'
        ]);
    }
    
    private function saveToken(array $data): void
    {
        $data['created_at'] = time();
        file_put_contents($this->tokenFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    private function loadToken(): ?array
    {
        if (!file_exists($this->tokenFile)) {
            return null;
        }
        return json_decode(file_get_contents($this->tokenFile), true);
    }
    
    private function exchangeCodeForToken(string $code): array
    {
        $ch = curl_init(self::OAUTH_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => self::REDIRECT_URI
            ])
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        if (isset($data['error'])) {
            throw new Exception("Ошибка авторизации: " . ($data['error_description'] ?? $data['error']));
        }
        
        $this->saveToken($data);
        return $data;
    }
    
    private function refreshToken(string $refreshToken): array
    {
        $ch = curl_init(self::OAUTH_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret
            ])
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        if (isset($data['error'])) {
            throw new Exception("Ошибка обновления токена: " . ($data['error_description'] ?? $data['error']));
        }
        
        if (!isset($data['refresh_token'])) {
            $data['refresh_token'] = $refreshToken;
        }
        $this->saveToken($data);
        return $data;
    }
    
    private function getAccessToken(): string
    {
        $token = $this->loadToken();
        
        if (!$token) {
            echo "\n  Авторизация в Яндекс.Вебмастере\n";
            echo "  ================================\n\n";
            echo "  1. Откройте ссылку в браузере:\n\n";
            echo "  " . $this->getAuthUrl() . "\n\n";
            echo "  2. Разрешите доступ приложению\n";
            echo "  3. Скопируйте CODE из адресной строки (параметр code=...)\n\n";
            echo "  Введите CODE: ";
            
            $code = trim(fgets(STDIN));
            $token = $this->exchangeCodeForToken($code);
            echo "\n  Авторизация успешна!\n\n";
        }
        
        $expiresAt = ($token['created_at'] ?? 0) + ($token['expires_in'] ?? 31536000) - 300;
        
        if (time() > $expiresAt) {
            echo "  Обновление токена...\n";
            $token = $this->refreshToken($token['refresh_token']);
        }
        
        return $token['access_token'];
    }
    
    public function request(string $method, string $endpoint, array $params = []): array
    {
        $token = $this->getAccessToken();
        $url = self::API_URL . $endpoint;
        
        $ch = curl_init();
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: OAuth ' . $token,
                'Content-Type: application/json'
            ]
        ];
        
        if ($method === 'GET') {
            if (!empty($params)) {
                $queryString = self::buildQuery($params);
                $url .= '?' . $queryString;
            }
            $options[CURLOPT_URL] = $url;
        } elseif ($method === 'POST') {
            $options[CURLOPT_URL] = $url;
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($params);
        }
        
        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 && $httpCode !== 201 && $httpCode !== 202) {
            $errorData = json_decode($response, true);
            $errorMsg = $errorData['error_message'] ?? $errorData['message'] ?? $response;
            $errorCode = $errorData['error_code'] ?? '';
            throw new Exception("API Error [$httpCode]" . ($errorCode ? " $errorCode:" : ':') . " $errorMsg");
        }
        
        return json_decode($response, true) ?: [];
    }
    
    private static function buildQuery(array $params): string
    {
        $parts = [];
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $parts[] = urlencode($key) . '=' . urlencode($v);
                }
            } else {
                $parts[] = urlencode($key) . '=' . urlencode($value);
            }
        }
        return implode('&', $parts);
    }
    
    private function getUserId(): int
    {
        if ($this->userId !== null) {
            return $this->userId;
        }
        
        $data = $this->request('GET', '/user');
        $this->userId = (int)($data['user_id'] ?? 0);
        
        if ($this->userId === 0) {
            throw new Exception("Не удалось получить user_id");
        }
        
        return $this->userId;
    }
    
    public function getHosts(): array
    {
        $userId = $this->getUserId();
        return $this->request('GET', "/user/$userId/hosts");
    }
    
    public function getHostId(): string
    {
        if ($this->hostId) {
            return $this->hostId;
        }
        
        echo "  Получение списка сайтов...\n";
        $data = $this->getHosts();
        $hosts = $data['hosts'] ?? [];
        
        if (empty($hosts)) {
            throw new Exception("Не найдено ни одного сайта в Вебмастере");
        }
        
        $getDisplayName = function(array $host): string {
            return $host['unicode_host_url'] ?? $host['ascii_host_url'] ?? $host['host_id'] ?? 'unknown';
        };
        
        if (count($hosts) === 1) {
            $this->hostId = $hosts[0]['host_id'];
            echo "  Найден сайт: " . $getDisplayName($hosts[0]) . "\n";
            return $this->hostId;
        }
        
        if ($this->hostId === null && php_sapi_name() === 'cli' && function_exists('fgets') && defined('STDIN')) {
            $stdin = fopen('php://stdin', 'r');
            if (stream_get_meta_data($stdin)['blocked'] ?? true) {
                echo "\n  Выберите сайт:\n";
                foreach ($hosts as $i => $host) {
                    echo "  " . ($i + 1) . ". " . $getDisplayName($host) . " (" . $host['host_id'] . ")\n";
                }
                echo "\n  Введите номер: ";
                
                $choice = (int)trim(fgets(STDIN)) - 1;
                fclose($stdin);
                
                if (!isset($hosts[$choice])) {
                    throw new Exception("Неверный выбор");
                }
                
                $this->hostId = $hosts[$choice]['host_id'];
                return $this->hostId;
            }
        }
        
        $this->hostId = $hosts[0]['host_id'];
        echo "  Выбран сайт: " . $getDisplayName($hosts[0]) . "\n";
        return $this->hostId;
    }
    
    public function getPopularQueries(string $dateFrom, string $dateTo, int $limit = 500, string $orderBy = 'TOTAL_SHOWS'): array
    {
        $userId = $this->getUserId();
        $hostId = $this->getHostId();
        
        return $this->request('GET', "/user/$userId/hosts/$hostId/search-queries/popular", [
            'order_by' => $orderBy,
            'query_indicator' => ['TOTAL_SHOWS', 'TOTAL_CLICKS', 'AVG_SHOW_POSITION', 'AVG_CLICK_POSITION'],
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'limit' => $limit
        ]);
    }
    
    public function getPopularUrls(string $dateFrom, string $dateTo, int $limit = 500, string $orderBy = 'TOTAL_SHOWS'): array
    {
        $userId = $this->getUserId();
        $hostId = $this->getHostId();
        
        return $this->request('GET', "/user/$userId/hosts/$hostId/search-urls/popular", [
            'order_by' => $orderBy,
            'query_indicator' => 'TOTAL_SHOWS,TOTAL_CLICKS,AVG_SHOW_POSITION,AVG_CLICK_POSITION',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'limit' => $limit
        ]);
    }
    
    public static function saveCsv(array $data, string $filename): void
    {
        $fp = fopen($filename, 'w');
        fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        if (!empty($data)) {
            fputcsv($fp, array_keys($data[0]), ';');
            foreach ($data as $row) {
                fputcsv($fp, $row, ';');
            }
        }
        
        fclose($fp);
    }
    
    public static function saveMarkdown(array $data, string $filename, string $title, string $dateFrom, string $dateTo): void
    {
        $md = "# $title\n\n";
        $md .= "Период: $dateFrom — $dateTo\n\n";
        
        if (!empty($data)) {
            $headers = array_keys($data[0]);
            $md .= '| ' . implode(' | ', $headers) . " |\n";
            $md .= '| ' . implode(' | ', array_fill(0, count($headers), '---')) . " |\n";
            
            foreach ($data as $row) {
                $md .= '| ' . implode(' | ', $row) . " |\n";
            }
        } else {
            $md .= "_Нет данных_\n";
        }
        
        file_put_contents($filename, $md);
    }
    
    public static function createReportDir(): string
    {
        $reportDir = getcwd() . '/yandex_webmaster_reports';
        if (!is_dir($reportDir)) {
            mkdir($reportDir, 0755, true);
        }
        
        $dateDir = $reportDir . '/' . date('Y-m-d');
        if (!is_dir($dateDir)) {
            mkdir($dateDir, 0755);
        }
        
        return $dateDir;
    }
    
    public static function getFileTimestamp(): string
    {
        return date('Y-m-d_H-i-s');
    }
}

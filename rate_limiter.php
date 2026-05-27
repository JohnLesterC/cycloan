<?php
require_once 'env_config.php';

// Runtime check: only suppress if Redis extension is not available
if (!function_exists('redis_connect') && !class_exists('Redis')) {
    // Redis class stub for type checking when Redis extension is not installed
    /**
     * @psalm-suppress MissingConstructor
     */
    class Redis
    {
        public function connect($host, $port)
        {
        }
        public function auth($password)
        {
        }
        public function select($db)
        {
        }
        public function incr($key)
        {
            return 0;
        }
        public function get($key)
        {
            return null;
        }
        public function set($key, $value, $ttl = null)
        {
        }
        public function expire($key, $seconds)
        {
        }
        public function ttl($key)
        {
            return 0;
        }
        public function del(...$keys)
        {
        }
        public function exists(...$keys)
        {
        }
    }
}

class RateLimiter
{
    private $redis = null;
    private $use_redis = false;
    private $storage_dir = null;
    private $limits = [];

    /**
     * Initialize Rate Limiter
     */
    public function __construct()
    {
        // Initialize rate limit configurations
        $this->limits = [
            'registration' => [
                'limit' => RATE_LIMIT_REGISTRATION_PER_IP,
                'window' => RATE_LIMIT_REGISTRATION_WINDOW,
            ],
            'otp_request' => [
                'limit' => RATE_LIMIT_OTP_PER_EMAIL,
                'window' => RATE_LIMIT_OTP_WINDOW,
            ],
            'otp_verification' => [
                'limit' => RATE_LIMIT_VERIFICATION_PER_EMAIL,
                'window' => RATE_LIMIT_VERIFICATION_WINDOW,
            ],
        ];

        // Initialize storage backend
        if (REDIS_ENABLED && extension_loaded('redis')) {
            $this->initializeRedis();
        } else {
            $this->initializeFileStorage();
        }
    }

    /**
     * Initialize Redis connection
     */
    private function initializeRedis(): void
    {
        try {
            // Ensure the Redis class exists (static analyzers or builds may not have the extension)
            if (!class_exists('Redis')) {
                error_log("Redis class not available, falling back to file storage");
                $this->use_redis = false;
                $this->initializeFileStorage();
                return;
            }

            // Create Redis instance (suppressing type check as Redis is conditionally available)
            $redis = new \Redis();
            // @phpstan-ignore-next-line Conditional Redis extension
            if ($redis->connect(REDIS_HOST, REDIS_PORT) === false) {
                error_log("Redis connection failed, falling back to file storage");
                $this->use_redis = false;
                $this->initializeFileStorage();
                return;
            }

            if (!empty(REDIS_PASSWORD)) {
                // @phpstan-ignore-next-line Conditional Redis extension
                $redis->auth(REDIS_PASSWORD);
            }

            if (defined('REDIS_DB')) {
                // @phpstan-ignore-next-line Conditional Redis extension
                $redis->select(REDIS_DB);
            }

            $this->redis = $redis;
            $this->use_redis = true;
        } catch (Exception $e) {
            error_log("Redis initialization failed: " . $e->getMessage());
            $this->use_redis = false;
            $this->initializeFileStorage();
        }
    }

    /**
     * Initialize file-based storage
     */
    private function initializeFileStorage()
    {
        $this->storage_dir = sys_get_temp_dir() . '/cycloan_rate_limits';

        if (!is_dir($this->storage_dir)) {
            if (!mkdir($this->storage_dir, 0755, true)) {
                error_log("Failed to create rate limit storage directory");
            }
        }
    }

    /**
     * Check if an action is within rate limits
     * 
     * @param string $action Action type (registration, otp_request, otp_verification)
     * @param string $identifier IP address or email address
     * @return bool True if within limits, false if rate limited
     */
    public function checkLimit(string $action, string $identifier): bool
    {
        if (!RATE_LIMIT_ENABLED) {
            return true;
        }

        if (!isset($this->limits[$action])) {
            error_log("Unknown rate limit action: $action");
            return true;
        }

        $limit_config = $this->limits[$action];
        $key = $this->generateKey($action, $identifier);
        $current_count = $this->getCount($key);

        return $current_count < $limit_config['limit'];
    }

    /**
     * Record an attempt
     * 
     * @param string $action Action type
     * @param string $identifier IP address or email address
     * @return int Updated attempt count
     */
    public function recordAttempt(string $action, string $identifier): int
    {
        if (!RATE_LIMIT_ENABLED) {
            return 0;
        }

        if (!isset($this->limits[$action])) {
            return 0;
        }

        $limit_config = $this->limits[$action];
        $key = $this->generateKey($action, $identifier);

        if ($this->use_redis) {
            return $this->recordAttemptRedis($key, $limit_config['window']);
        } else {
            return $this->recordAttemptFile($key, $limit_config['window']);
        }
    }

    /**
     * Get remaining attempts
     * 
     * @param string $action Action type
     * @param string $identifier IP address or email address
     * @return int Number of remaining attempts
     */
    public function getRemainingAttempts(string $action, string $identifier): int
    {
        if (!RATE_LIMIT_ENABLED) {
            return 999;
        }

        if (!isset($this->limits[$action])) {
            return 999;
        }

        $limit_config = $this->limits[$action];
        $key = $this->generateKey($action, $identifier);
        $current_count = $this->getCount($key);

        return max(0, $limit_config['limit'] - $current_count);
    }

    /**
     * Get time until limit resets (in seconds)
     * 
     * @param string $action Action type
     * @param string $identifier IP address or email address
     * @return int Seconds until reset (0 if not limited)
     */
    public function getTimeUntilReset(string $action, string $identifier): int
    {
        if (!RATE_LIMIT_ENABLED) {
            return 0;
        }

        if (!isset($this->limits[$action])) {
            return 0;
        }

        $limit_config = $this->limits[$action];
        $key = $this->generateKey($action, $identifier);

        if ($this->use_redis) {
            $ttl = $this->redis->ttl($key);
            return max(0, $ttl);
        } else {
            $file = $this->storage_dir . '/' . $key . '.json';
            if (!file_exists($file)) {
                return 0;
            }

            $data = json_decode(file_get_contents($file), true);
            $expires_at = $data['expires_at'] ?? 0;
            $time_left = $expires_at - time();

            return max(0, $time_left);
        }
    }

    /**
     * Reset rate limit for an identifier
     * 
     * @param string $action Action type
     * @param string $identifier IP address or email address
     */
    public function reset(string $action, string $identifier): void
    {
        $key = $this->generateKey($action, $identifier);

        if ($this->use_redis) {
            $this->redis->del($key);
        } else {
            $file = $this->storage_dir . '/' . $key . '.json';
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Generate storage key
     * 
     * @param string $action Action type
     * @param string $identifier IP address or email address
     * @return string Storage key
     */
    private function generateKey(string $action, string $identifier): string
    {
        return "rate_limit:{$action}:{$identifier}";
    }

    /**
     * Get current count from storage
     * 
     * @param string $key Storage key
     * @return int Current count
     */
    private function getCount(string $key): int
    {
        if ($this->use_redis) {
            return (int) ($this->redis->get($key) ?? 0);
        } else {
            $file = $this->storage_dir . '/' . $key . '.json';
            if (!file_exists($file)) {
                return 0;
            }

            $data = json_decode(file_get_contents($file), true);

            // Check if expired
            if ($data['expires_at'] < time()) {
                unlink($file);
                return 0;
            }

            return $data['count'] ?? 0;
        }
    }

    /**
     * Record attempt in Redis
     * 
     * @param string $key Storage key
     * @param int $window Time window in seconds
     * @return int Updated count
     */
    private function recordAttemptRedis(string $key, int $window): int
    {
        $count = $this->redis->incr($key);

        // Set expiration on first attempt
        if ($count === 1) {
            $this->redis->expire($key, $window);
        }

        return $count;
    }

    /**
     * Record attempt in file storage
     * 
     * @param string $key Storage key
     * @param int $window Time window in seconds
     * @return int Updated count
     */
    private function recordAttemptFile(string $key, int $window): int
    {
        $file = $this->storage_dir . '/' . $key . '.json';

        // Create or update file
        if (!file_exists($file)) {
            $data = [
                'count' => 1,
                'created_at' => time(),
                'expires_at' => time() + $window,
            ];
        } else {
            $data = json_decode(file_get_contents($file), true);

            // Check if expired
            if ($data['expires_at'] < time()) {
                $data = [
                    'count' => 1,
                    'created_at' => time(),
                    'expires_at' => time() + $window,
                ];
            } else {
                $data['count']++;
            }
        }

        file_put_contents($file, json_encode($data));
        chmod($file, 0644);

        return $data['count'];
    }

    /**
     * Clean up expired rate limit records
     */
    public function cleanup(): void
    {
        if ($this->use_redis) {
            // Redis handles cleanup automatically with TTL
            return;
        }

        // Clean up old files
        $files = glob($this->storage_dir . '/*.json');
        foreach ($files as $file) {
            if (filemtime($file) < time() - 86400) { // Delete if older than 24 hours
                unlink($file);
            }
        }
    }
}

?>
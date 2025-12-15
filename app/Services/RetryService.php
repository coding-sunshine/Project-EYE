<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Exception;
use Throwable;

class RetryService
{
    /**
     * Maximum number of retry attempts.
     */
    protected int $maxAttempts;

    /**
     * Initial delay in milliseconds before first retry.
     */
    protected int $initialDelayMs;

    /**
     * Maximum delay in milliseconds between retries.
     */
    protected int $maxDelayMs;

    /**
     * Exponential backoff multiplier.
     */
    protected float $multiplier;

    /**
     * Whether to add jitter to prevent thundering herd.
     */
    protected bool $useJitter;

    /**
     * Operation name for logging.
     */
    protected string $operationName;

    /**
     * Create a new RetryService instance.
     *
     * @param int $maxAttempts Maximum retry attempts (default: 3)
     * @param int $initialDelayMs Initial delay in milliseconds (default: 100)
     * @param int $maxDelayMs Maximum delay in milliseconds (default: 10000)
     * @param float $multiplier Exponential backoff multiplier (default: 2.0)
     * @param bool $useJitter Add randomization to delays (default: true)
     * @param string $operationName Name for logging (default: 'operation')
     */
    public function __construct(
        int $maxAttempts = 3,
        int $initialDelayMs = 100,
        int $maxDelayMs = 10000,
        float $multiplier = 2.0,
        bool $useJitter = true,
        string $operationName = 'operation'
    ) {
        $this->maxAttempts = $maxAttempts;
        $this->initialDelayMs = $initialDelayMs;
        $this->maxDelayMs = $maxDelayMs;
        $this->multiplier = $multiplier;
        $this->useJitter = $useJitter;
        $this->operationName = $operationName;
    }

    /**
     * Execute a callable with retry logic and exponential backoff.
     *
     * @param callable $callback The operation to execute
     * @param callable|null $shouldRetry Custom retry decision function (receives Exception, attempt number)
     * @return mixed Result from the callback
     * @throws Exception When all retry attempts are exhausted
     */
    public function execute(callable $callback, ?callable $shouldRetry = null): mixed
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxAttempts) {
            $attempt++;

            try {
                // Execute the operation
                $result = $callback();

                // Success - log if there were previous attempts
                if ($attempt > 1) {
                    Log::info('Retry succeeded', [
                        'operation' => $this->operationName,
                        'attempt' => $attempt,
                        'max_attempts' => $this->maxAttempts,
                    ]);
                }

                return $result;

            } catch (Throwable $e) {
                $lastException = $e;

                // Determine if we should retry this error
                $shouldRetryError = $shouldRetry
                    ? $shouldRetry($e, $attempt)
                    : $this->isRetryable($e);

                if (!$shouldRetryError) {
                    Log::warning('Error is not retryable', [
                        'operation' => $this->operationName,
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }

                // Check if we have attempts remaining
                if ($attempt >= $this->maxAttempts) {
                    Log::error('All retry attempts exhausted', [
                        'operation' => $this->operationName,
                        'total_attempts' => $attempt,
                        'error' => $e->getMessage(),
                    ]);
                    break;
                }

                // Calculate delay with exponential backoff
                $delay = $this->calculateDelay($attempt);

                Log::warning('Retry attempt failed, waiting before retry', [
                    'operation' => $this->operationName,
                    'attempt' => $attempt,
                    'max_attempts' => $this->maxAttempts,
                    'delay_ms' => $delay,
                    'error' => $e->getMessage(),
                ]);

                // Wait before retrying (convert ms to microseconds)
                usleep($delay * 1000);
            }
        }

        // All attempts failed - throw the last exception
        throw $lastException;
    }

    /**
     * Calculate delay with exponential backoff and optional jitter.
     *
     * @param int $attempt Current attempt number (1-based)
     * @return int Delay in milliseconds
     */
    protected function calculateDelay(int $attempt): int
    {
        // Calculate exponential backoff: initialDelay * (multiplier ^ (attempt - 1))
        $delay = $this->initialDelayMs * pow($this->multiplier, $attempt - 1);

        // Cap at maximum delay
        $delay = min($delay, $this->maxDelayMs);

        // Add jitter if enabled (randomize between 0% and 100% of calculated delay)
        if ($this->useJitter) {
            $delay = $delay * (mt_rand(0, 100) / 100);
        }

        return (int) $delay;
    }

    /**
     * Determine if an error is retryable.
     * Default implementation checks for timeout and connection errors.
     *
     * @param Throwable $exception The exception to check
     * @return bool True if the error should be retried
     */
    protected function isRetryable(Throwable $exception): bool
    {
        $message = $exception->getMessage();

        // Check for common retryable error patterns
        return $this->isTimeoutError($message)
            || $this->isConnectionError($message)
            || $this->isServerError($exception);
    }

    /**
     * Check if error is a timeout error.
     *
     * @param string $message Error message
     * @return bool
     */
    protected function isTimeoutError(string $message): bool
    {
        $timeoutPatterns = [
            'timeout',
            'timed out',
            'time out',
            'deadline exceeded',
            'request timeout',
        ];

        foreach ($timeoutPatterns as $pattern) {
            if (stripos($message, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if error is a connection error.
     *
     * @param string $message Error message
     * @return bool
     */
    protected function isConnectionError(string $message): bool
    {
        $connectionPatterns = [
            'connection refused',
            'connection reset',
            'connection error',
            'connection failed',
            'could not connect',
            'unable to connect',
            'network error',
            'socket error',
            'dns error',
            'host unreachable',
        ];

        foreach ($connectionPatterns as $pattern) {
            if (stripos($message, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if error is a server error (5xx status codes).
     *
     * @param Throwable $exception The exception to check
     * @return bool
     */
    protected function isServerError(Throwable $exception): bool
    {
        // Check for HTTP status codes in common exception types
        $message = $exception->getMessage();

        // Check for 5xx status codes in message
        if (preg_match('/\b5\d{2}\b/', $message)) {
            return true;
        }

        // Check for common server error messages
        $serverErrorPatterns = [
            'internal server error',
            'service unavailable',
            'bad gateway',
            'gateway timeout',
            'server error',
        ];

        foreach ($serverErrorPatterns as $pattern) {
            if (stripos($message, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }
}

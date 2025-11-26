<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class CircuitBreakerService
{
    /**
     * Circuit breaker states.
     */
    public const STATE_CLOSED = 'closed';      // Normal operation
    public const STATE_OPEN = 'open';          // Failing, rejecting requests
    public const STATE_HALF_OPEN = 'half_open'; // Testing recovery

    /**
     * Service name for identifying the circuit.
     */
    protected string $serviceName;

    /**
     * Maximum consecutive failures before opening circuit.
     */
    protected int $failureThreshold;

    /**
     * Seconds to wait before attempting recovery.
     */
    protected int $recoveryTimeout;

    /**
     * Cache key prefix.
     */
    protected string $cachePrefix = 'circuit_breaker:';

    /**
     * Create a new CircuitBreakerService instance.
     *
     * @param string $serviceName Unique name for this circuit (e.g., 'ai_service')
     * @param int $failureThreshold Max failures before opening (default: 5)
     * @param int $recoveryTimeout Seconds before attempting recovery (default: 60)
     */
    public function __construct(
        string $serviceName = 'default',
        int $failureThreshold = 5,
        int $recoveryTimeout = 60
    ) {
        $this->serviceName = $serviceName;
        $this->failureThreshold = $failureThreshold;
        $this->recoveryTimeout = $recoveryTimeout;
    }

    /**
     * Execute a callable with circuit breaker protection.
     *
     * @param callable $callback The operation to execute
     * @return mixed Result from the callback
     * @throws Exception When circuit is open or callback fails
     */
    public function execute(callable $callback): mixed
    {
        $state = $this->getState();

        // If circuit is open, check if recovery timeout has passed
        if ($state === self::STATE_OPEN) {
            if ($this->shouldAttemptRecovery()) {
                $this->setState(self::STATE_HALF_OPEN);
                Log::info('Circuit breaker entering HALF_OPEN state', [
                    'service' => $this->serviceName,
                ]);
            } else {
                $this->logCircuitOpen();
                throw new Exception(
                    "Circuit breaker is OPEN for service '{$this->serviceName}'. " .
                    "Service is temporarily unavailable."
                );
            }
        }

        try {
            // Execute the callback
            $result = $callback();

            // Success - handle state transitions
            if ($state === self::STATE_HALF_OPEN) {
                $this->onSuccess();
                Log::info('Circuit breaker test request succeeded - closing circuit', [
                    'service' => $this->serviceName,
                ]);
            } elseif ($state === self::STATE_CLOSED) {
                // Reset failure count on success
                $this->resetFailures();
            }

            return $result;

        } catch (Exception $e) {
            // Failure - record and handle state transitions
            $this->onFailure();

            Log::error('Circuit breaker recorded failure', [
                'service' => $this->serviceName,
                'state' => $this->getState(),
                'failure_count' => $this->getFailureCount(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get the current circuit state.
     *
     * @return string One of: STATE_CLOSED, STATE_OPEN, STATE_HALF_OPEN
     */
    public function getState(): string
    {
        return Cache::get($this->getStateKey(), self::STATE_CLOSED);
    }

    /**
     * Set the circuit state.
     *
     * @param string $state
     * @return void
     */
    protected function setState(string $state): void
    {
        Cache::put($this->getStateKey(), $state, now()->addHours(24));
    }

    /**
     * Get current failure count.
     *
     * @return int
     */
    public function getFailureCount(): int
    {
        return (int) Cache::get($this->getFailureCountKey(), 0);
    }

    /**
     * Increment failure count.
     *
     * @return void
     */
    protected function incrementFailures(): void
    {
        $count = $this->getFailureCount() + 1;
        Cache::put($this->getFailureCountKey(), $count, now()->addHours(24));
    }

    /**
     * Reset failure count to zero.
     *
     * @return void
     */
    protected function resetFailures(): void
    {
        Cache::forget($this->getFailureCountKey());
    }

    /**
     * Handle a successful request.
     *
     * @return void
     */
    protected function onSuccess(): void
    {
        $this->setState(self::STATE_CLOSED);
        $this->resetFailures();
        Cache::forget($this->getOpenedAtKey());
    }

    /**
     * Handle a failed request.
     *
     * @return void
     */
    protected function onFailure(): void
    {
        $state = $this->getState();

        if ($state === self::STATE_HALF_OPEN) {
            // Test request failed - reopen circuit
            $this->openCircuit();
            Log::warning('Circuit breaker test request failed - reopening circuit', [
                'service' => $this->serviceName,
            ]);
        } elseif ($state === self::STATE_CLOSED) {
            // Increment failures and check threshold
            $this->incrementFailures();

            if ($this->getFailureCount() >= $this->failureThreshold) {
                $this->openCircuit();
                Log::warning('Circuit breaker threshold reached - opening circuit', [
                    'service' => $this->serviceName,
                    'failure_count' => $this->getFailureCount(),
                    'threshold' => $this->failureThreshold,
                ]);
            }
        }
    }

    /**
     * Open the circuit.
     *
     * @return void
     */
    protected function openCircuit(): void
    {
        $this->setState(self::STATE_OPEN);
        Cache::put($this->getOpenedAtKey(), now()->timestamp, now()->addHours(24));
    }

    /**
     * Check if we should attempt recovery.
     *
     * @return bool
     */
    protected function shouldAttemptRecovery(): bool
    {
        $openedAt = Cache::get($this->getOpenedAtKey());

        if ($openedAt === null) {
            return true;
        }

        return (now()->timestamp - $openedAt) >= $this->recoveryTimeout;
    }

    /**
     * Log that circuit is open.
     *
     * @return void
     */
    protected function logCircuitOpen(): void
    {
        $openedAt = Cache::get($this->getOpenedAtKey());
        $remainingSeconds = $this->recoveryTimeout - (now()->timestamp - $openedAt);

        Log::warning('Circuit breaker is OPEN - rejecting request', [
            'service' => $this->serviceName,
            'remaining_recovery_seconds' => max(0, $remainingSeconds),
        ]);
    }

    /**
     * Manually reset the circuit breaker to CLOSED state.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->setState(self::STATE_CLOSED);
        $this->resetFailures();
        Cache::forget($this->getOpenedAtKey());

        Log::info('Circuit breaker manually reset', [
            'service' => $this->serviceName,
        ]);
    }

    /**
     * Get circuit breaker status information.
     *
     * @return array
     */
    public function getStatus(): array
    {
        $state = $this->getState();
        $failureCount = $this->getFailureCount();
        $openedAt = Cache::get($this->getOpenedAtKey());

        $status = [
            'service' => $this->serviceName,
            'state' => $state,
            'failure_count' => $failureCount,
            'failure_threshold' => $this->failureThreshold,
            'recovery_timeout' => $this->recoveryTimeout,
        ];

        if ($openedAt !== null) {
            $elapsedSeconds = now()->timestamp - $openedAt;
            $status['opened_at'] = date('Y-m-d H:i:s', $openedAt);
            $status['elapsed_seconds'] = $elapsedSeconds;
            $status['remaining_recovery_seconds'] = max(0, $this->recoveryTimeout - $elapsedSeconds);
        }

        return $status;
    }

    /**
     * Get cache key for state.
     *
     * @return string
     */
    protected function getStateKey(): string
    {
        return $this->cachePrefix . $this->serviceName . ':state';
    }

    /**
     * Get cache key for failure count.
     *
     * @return string
     */
    protected function getFailureCountKey(): string
    {
        return $this->cachePrefix . $this->serviceName . ':failures';
    }

    /**
     * Get cache key for opened timestamp.
     *
     * @return string
     */
    protected function getOpenedAtKey(): string
    {
        return $this->cachePrefix . $this->serviceName . ':opened_at';
    }
}

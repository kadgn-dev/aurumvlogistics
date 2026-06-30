<?php

declare(strict_types=1);

namespace GOLS\Services;

use PDO;

/**
 * Database-backed rate limiter that tracks attempts by IP + action type.
 *
 * Configurable limits per action:
 * - login: 5 attempts per 15 minutes (LOGIN_MAX_ATTEMPTS / LOGIN_LOCKOUT_WINDOW)
 * - contact: 3 attempts per 1 hour (CONTACT_RATE_LIMIT / CONTACT_RATE_WINDOW)
 *
 * Requirements: 2.6, 14.5
 */
class RateLimiter
{
  private PDO $pdo;

  /**
   * Action configuration: [maxAttempts, windowSeconds]
   *
   * @var array<string, array{int, int}>
   */
  private array $limits;

  public function __construct(PDO $pdo)
  {
    $this->pdo = $pdo;

    $this->limits = [
      'login' => [LOGIN_MAX_ATTEMPTS, LOGIN_LOCKOUT_WINDOW],
      'contact' => [CONTACT_RATE_LIMIT, CONTACT_RATE_WINDOW],
    ];
  }

  /**
   * Check if the given IP is allowed to perform the action.
   *
   * Returns true if the number of attempts within the time window
   * is below the configured limit for the action.
   */
  public function isAllowed(string $ip, string $action): bool
  {
    $count = $this->getAttemptCount($ip, $action);
    [$maxAttempts] = $this->getLimitsForAction($action);

    return $count < $maxAttempts;
  }

  /**
   * Record an attempt for the given IP and action in the rate_limits table.
   */
  public function recordAttempt(string $ip, string $action): void
  {
    $stmt = $this->pdo->prepare(
      'INSERT INTO rate_limits (ip_address, action_type, attempted_at) VALUES (:ip, :action, :attempted_at)'
    );
    $stmt->execute([
      ':ip' => $ip,
      ':action' => $action,
      ':attempted_at' => date('Y-m-d H:i:s'),
    ]);
  }

  /**
   * Get the number of seconds remaining until the rate limit resets.
   *
   * Returns 0 if the IP is not currently rate-limited for the action.
   */
  public function getRemainingTime(string $ip, string $action): int
  {
    if ($this->isAllowed($ip, $action)) {
      return 0;
    }

    [, $windowSeconds] = $this->getLimitsForAction($action);

    $windowStart = date('Y-m-d H:i:s', time() - $windowSeconds);

    // Find the earliest attempt within the current window
    $stmt = $this->pdo->prepare(
      'SELECT MIN(attempted_at) AS earliest
       FROM rate_limits
       WHERE ip_address = :ip
        AND action_type = :action
        AND attempted_at > :window_start'
    );
    $stmt->execute([
      ':ip' => $ip,
      ':action' => $action,
      ':window_start' => $windowStart,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || $row['earliest'] === null) {
      return 0;
    }

    $earliestTimestamp = strtotime($row['earliest']);
    $resetTime = $earliestTimestamp + $windowSeconds;
    $remaining = $resetTime - time();

    return max(0, $remaining);
  }

  /**
   * Get the current number of attempts within the time window for the given IP and action.
   */
  public function getAttemptCount(string $ip, string $action): int
  {
    [, $windowSeconds] = $this->getLimitsForAction($action);

    $windowStart = date('Y-m-d H:i:s', time() - $windowSeconds);

    $stmt = $this->pdo->prepare(
      'SELECT COUNT(*) AS attempt_count
       FROM rate_limits
       WHERE ip_address = :ip
        AND action_type = :action
        AND attempted_at > :window_start'
    );
    $stmt->execute([
      ':ip' => $ip,
      ':action' => $action,
      ':window_start' => $windowStart,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return (int) ($row['attempt_count'] ?? 0);
  }

  /**
   * Get the configured limits for an action.
   *
   * @return array{int, int} [maxAttempts, windowSeconds]
   * @throws \InvalidArgumentException If the action is not configured
   */
  private function getLimitsForAction(string $action): array
  {
    if (!isset($this->limits[$action])) {
      throw new \InvalidArgumentException(
        sprintf('Unknown rate limit action: "%s". Supported actions: %s', $action, implode(', ', array_keys($this->limits)))
      );
    }

    return $this->limits[$action];
  }
}

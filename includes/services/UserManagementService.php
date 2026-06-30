<?php

declare(strict_types=1);

namespace GOLS\Services;

use GOLS\Repositories\UserRepository;
use GOLS\Result;

/**
 * Aurum Vault Logistics Platform (AVL)
 * UserManagementService - Admin user management operations
 *
 * Handles paginated user listing, KYC approval, account suspension,
 * user search, and single user retrieval for admin workflows.
 *
 * Requirements: 11.1, 11.2, 11.3, 11.4, 11.5, 11.6, 11.7
 */
class UserManagementService
{
  private UserRepository $userRepository;
  private ?NotificationService $notificationService;
  private ?EmailService $emailService;

  private const USERS_PER_PAGE = 20;
  private const SEARCH_MAX_RESULTS = 50;

  public function __construct(
    UserRepository $userRepository,
    ?NotificationService $notificationService = null,
    ?EmailService $emailService = null
  ) {
    $this->userRepository = $userRepository;
    $this->notificationService = $notificationService;
    $this->emailService = $emailService;
  }

  /**
   * Get a paginated list of users (20 per page, sorted by created_at DESC).
   *
   * Requirement 11.1: Display paginated list of all users (max 20/page)
   * sorted by account creation date descending.
   *
   * @param int $page Page number (1-based)
   * @return array{data: array, total: int, page: int, perPage: int}
   */
  public function getPaginatedUsers(int $page): array
  {
    $page = max(1, $page);

    $result = $this->userRepository->getPaginated($page, self::USERS_PER_PAGE);

    return [
      'data' => $result['data'],
      'total' => $result['total'],
      'page' => $page,
      'perPage' => self::USERS_PER_PAGE,
    ];
  }

  /**
   * Approve a user's KYC status.
   *
   * Requirement 11.2: Update kyc_status to "approved" and send notification email.
   * Requirement 11.6: Reject if KYC is already approved.
   *
   * @param int $userId The user whose KYC to approve
   * @param int $adminId The admin performing the action
   * @return Result Success or error result
   */
  public function approveKyc(int $userId, int $adminId): Result
  {
    // Validate user exists
    $user = $this->userRepository->findById($userId);

    if ($user === null) {
      return Result::error('USER_NOT_FOUND', 'User not found.');
    }

    // Reject if already approved (Req 11.6)
    if ($user['kyc_status'] === 'approved') {
      return Result::error('ALREADY_APPROVED', 'User KYC is already approved.');
    }

    // Update KYC status to approved
    $this->userRepository->updateKycStatus($userId, 'approved');

    // Send notification email
    if ($this->emailService !== null) {
      $this->emailService->send(
        $user['email'],
        'KYC Verification Approved',
        'kyc_approved.html',
        ['name' => $user['name']]
      );
    }

    // Create in-app notification
    if ($this->notificationService !== null) {
      $this->notificationService->create(
        $userId,
        'kyc_approved',
        'Your KYC verification has been approved.',
        $userId,
        'user'
      );
    }

    return Result::success(['user_id' => $userId, 'kyc_status' => 'approved']);
  }

  /**
   * Suspend a user account.
   *
   * Requirement 11.3: Prevent suspended user from logging in, send email.
   * Requirement 11.5: Reject if target is an admin.
   * Requirement 11.7: Reject if already suspended.
   *
   * @param int $userId The user to suspend
   * @param int $adminId The admin performing the action
   * @return Result Success or error result
   */
  public function suspendUser(int $userId, int $adminId): Result
  {
    // Validate user exists
    $user = $this->userRepository->findById($userId);

    if ($user === null) {
      return Result::error('USER_NOT_FOUND', 'User not found.');
    }

    // Reject if target is admin (Req 11.5)
    if ($user['role'] === 'admin') {
      return Result::error('CANNOT_SUSPEND_ADMIN', 'Admin accounts cannot be suspended by other Admins.');
    }

    // Reject if already suspended (Req 11.7)
    if ($user['status'] === 'suspended') {
      return Result::error('ALREADY_SUSPENDED', 'This account is already suspended.');
    }

    // Update status to suspended (prevents login via AuthService check)
    $this->userRepository->updateStatus($userId, 'suspended');

    // Send suspension notification email
    if ($this->emailService !== null) {
      $this->emailService->send(
        $user['email'],
        'Account Suspended',
        'account_suspended.html',
        ['name' => $user['name']]
      );
    }

    // Create in-app notification
    if ($this->notificationService !== null) {
      $this->notificationService->create(
        $userId,
        'account_suspended',
        'Your account has been suspended. Please contact support for more information.',
        $userId,
        'user'
      );
    }

    return Result::success(['user_id' => $userId, 'status' => 'suspended']);
  }

  /**
   * Search users by name or email with partial matching.
   *
   * Requirement 11.4: Case-insensitive partial match, max 50 results.
   *
   * @param string $term The search term
   * @return array Array of matching user records
   */
  public function searchUsers(string $term): array
  {
    $term = trim($term);

    if ($term === '') {
      return [];
    }

    return $this->userRepository->search($term, self::SEARCH_MAX_RESULTS);
  }

  /**
   * Get a single user by ID.
   *
   * @param int $userId The user ID to retrieve
   * @return array|null The user record or null if not found
   */
  public function getUserById(int $userId): ?array
  {
    return $this->userRepository->findById($userId);
  }
}

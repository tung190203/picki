<?php

use App\Http\Controllers\GuestController;
use App\Http\Controllers\MiniTournamentStaffController;
use App\Http\Controllers\UserMatchStatsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\CompetitionLocationController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MatchesController;
use App\Http\Controllers\MiniMatchController;
use App\Http\Controllers\MiniParticipantController;
use App\Http\Controllers\MiniTournamentNotificationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ParticipantController;
use App\Http\Controllers\TournamentController;
use App\Http\Controllers\TournamentTypeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\Club\ClubMemberController;
use App\Http\Controllers\Club\ClubWalletController;
use App\Http\Controllers\Club\ClubWalletTransactionController;
use App\Http\Controllers\Club\ClubActivityController;
use App\Http\Controllers\Club\ClubActivityParticipantController;
use App\Http\Controllers\Club\ClubNotificationController;
use App\Http\Controllers\Club\ClubNotificationRecipientController;
use App\Http\Controllers\Club\ClubFundCollectionController;
use App\Http\Controllers\Club\ClubFundContributionController;
use App\Http\Controllers\Club\ClubExpenseController;
use App\Http\Controllers\Club\ClubMonthlyFeeController;
use App\Http\Controllers\Club\ClubMonthlyFeePaymentController;
use App\Http\Controllers\Club\ClubDashboardController;
use App\Http\Controllers\Club\ClubJoinRequestController;
use App\Http\Controllers\Club\ClubReportController;
use App\Http\Controllers\Club\ClubMiniTournamentController;
use App\Http\Controllers\CompetitionLocationYardController;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\MiniTournamentController;
use App\Http\Controllers\SendMessageController;
use App\Http\Controllers\SportController;
use App\Http\Controllers\SystemNotificationController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TournamentStaffController;
use App\Http\Controllers\MiniTournamentPaymentController;
use App\Http\Controllers\MiniTournamentTemplateController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

/*
------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/broadcasting/auth', function () {
    return Broadcast::auth(request());
})->middleware('auth:api');

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/refresh-token', [AuthController::class, 'refresh']);
    Route::post('/fill-password', [AuthController::class, 'fillPassword']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/verify-otp-password', [AuthController::class, 'verifyOtpPassword']);
    Route::post('/resend-otp-password', [AuthController::class, 'resendOtpPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    Route::get('/google/redirect', [AuthController::class, 'redirectToGoogle']);
    Route::get('/google/callback', [AuthController::class, 'handleGoogleCallback']);
    Route::get('/facebook/redirect', [AuthController::class, 'redirectToFacebook']);
    Route::get('/facebook/callback', [AuthController::class, 'handleFacebookCallback']);
    Route::get('/apple/redirect', [AuthController::class, 'redirectToApple']);
    Route::post('/apple/callback', [AuthController::class, 'handleAppleCallback']);

    // Mobile login with Google
    Route::post('/google', [AuthController::class, 'loginWithGoogle']);
    Route::post('/facebook', [AuthController::class, 'loginWithFacebook']);
    Route::post('/apple',[AuthController::class, 'loginWithApple']);
});

Route::get('/verify-email', [VerificationController::class, 'verify']);
Route::post('/resend-email', [VerificationController::class, 'resend']);

// Public route - không cần accessToken
Route::get('/tournament-detail/{id}/bracket', [TournamentController::class, 'getBracket']);

// Clubs API: không throttle để mobile gọi nhiều không bị lỗi 429
Route::middleware(['auth:api', 'update.last_login'])->group(function () {
    Route::prefix('clubs')->group(function () {
        Route::get('/', [ClubController::class, 'index']);
        Route::post('/', [ClubController::class, 'store']);
        Route::get('/my-clubs', [ClubController::class, 'myClubs']);
        Route::get('/my-invitations', [ClubJoinRequestController::class, 'myInvitations']);
        Route::get('/search-location', [ClubController::class, 'searchLocation']);
        Route::get('/location-detail', [ClubController::class, 'detailGooglePlace']);
        Route::get('/members/candidates', [ClubMemberController::class, 'getCandidates']);
        Route::get('/{clubId}', [ClubController::class, 'show']);
        Route::put('/{clubId}', [ClubController::class, 'update']);
        Route::delete('/{clubId}', [ClubController::class, 'destroy']);
        Route::post('/{clubId}/restore', [ClubController::class, 'restore']);
        Route::post('/{clubId}/leave', [ClubController::class, 'leave']);

        Route::prefix('{clubId}')->group(function () {
            Route::get('/profile', [ClubController::class, 'getProfile']);
            Route::get('/fund', [ClubController::class, 'getFund']);
            Route::put('/fund', [ClubController::class, 'updateFund']);
            Route::get('/fund/overview', [ClubWalletController::class, 'getFundOverview']);
            Route::get('/fund/qr-code', [ClubWalletController::class, 'getFundQrCode']);
            Route::get('/dashboard', [ClubDashboardController::class, 'index']);
            Route::get('/leaderboard', [ClubController::class, 'getMonthlyLeaderboard']);
            Route::post('/report', [ClubReportController::class, 'store']);
            Route::get('/reports', [ClubReportController::class, 'index']);

            Route::prefix('members')->group(function () {
                Route::get('/', [ClubMemberController::class, 'index']);
                Route::post('/', [ClubMemberController::class, 'store']);
                Route::get('/statistics', [ClubMemberController::class, 'statistics']);
                Route::get('/{memberId}', [ClubMemberController::class, 'show']);
                Route::put('/{memberId}', [ClubMemberController::class, 'update']);
                Route::delete('/{memberId}', [ClubMemberController::class, 'destroy']);
            });

            Route::prefix('invitations')->group(function () {
                Route::post('/accept', [ClubJoinRequestController::class, 'acceptInvitation']);
                Route::post('/reject', [ClubJoinRequestController::class, 'rejectInvitation']);
            });
            Route::prefix('join-requests')->group(function () {
                Route::get('/', [ClubJoinRequestController::class, 'index']);
                Route::post('/', [ClubJoinRequestController::class, 'store']);
                Route::post('/reject', [ClubJoinRequestController::class, 'reject']);
                Route::delete('/', [ClubJoinRequestController::class, 'destroyMyRequest']);
                Route::get('/{requestId}', [ClubJoinRequestController::class, 'show']);
                Route::post('/{requestId}/approve', [ClubJoinRequestController::class, 'approve']);
                Route::post('/{requestId}/reject', [ClubJoinRequestController::class, 'reject']);
            });

            Route::prefix('wallets')->group(function () {
                Route::get('/', [ClubWalletController::class, 'index']);
                Route::post('/', [ClubWalletController::class, 'store']);
                Route::get('/{walletId}', [ClubWalletController::class, 'show']);
                Route::put('/{walletId}', [ClubWalletController::class, 'update']);
                Route::delete('/{walletId}', [ClubWalletController::class, 'destroy']);
                Route::get('/{walletId}/balance', [ClubWalletController::class, 'getBalance']);
                Route::get('/{walletId}/transactions', [ClubWalletController::class, 'getTransactions']);
            });

            Route::prefix('wallet-transactions')->group(function () {
                Route::get('/', [ClubWalletTransactionController::class, 'index']);
                Route::get('/my-transactions', [ClubWalletTransactionController::class, 'myTransactions']);
                Route::post('/', [ClubWalletTransactionController::class, 'store']);
                Route::get('/{transactionId}', [ClubWalletTransactionController::class, 'show']);
                Route::put('/{transactionId}', [ClubWalletTransactionController::class, 'update']);
                Route::post('/{transactionId}/confirm', [ClubWalletTransactionController::class, 'confirm']);
                Route::post('/{transactionId}/reject', [ClubWalletTransactionController::class, 'reject']);
            });

            Route::prefix('activities')->group(function () {
                Route::get('/', [ClubActivityController::class, 'index']);
                Route::post('/', [ClubActivityController::class, 'store']);
                Route::get('/{activityId}', [ClubActivityController::class, 'show']);
                Route::match(['put', 'post'], '/{activityId}', [ClubActivityController::class, 'update']);
                Route::delete('/{activityId}', [ClubActivityController::class, 'destroy']);
                Route::post('/{activityId}/complete', [ClubActivityController::class, 'complete']);
                Route::post('/{activityId}/cancel', [ClubActivityController::class, 'cancel']);
                Route::post('/{activityId}/recurrence-series/cancel', [ClubActivityController::class, 'cancelRecurrenceSeries']);
                Route::post('/{activityId}/check-in', [ClubActivityParticipantController::class, 'checkIn']);
                Route::get('/{activityId}/check-ins', [ClubActivityParticipantController::class, 'checkInList']);

                Route::prefix('{activityId}/participants')->group(function () {
                    Route::get('/', [ClubActivityParticipantController::class, 'index']);
                    Route::post('/', [ClubActivityParticipantController::class, 'store']);
                    Route::post('/invite', [ClubActivityParticipantController::class, 'invite']);
                    Route::put('/{participantId}', [ClubActivityParticipantController::class, 'update']);
                    Route::delete('/{participantId}', [ClubActivityParticipantController::class, 'destroy']);
                    Route::post('/{participantId}/approve', [ClubActivityParticipantController::class, 'approve']);
                    Route::post('/{participantId}/reject', [ClubActivityParticipantController::class, 'reject']);
                    Route::post('/{participantId}/accept-invite', [ClubActivityParticipantController::class, 'acceptInvite']);
                    Route::post('/{participantId}/decline-invite', [ClubActivityParticipantController::class, 'declineInvite']);
                    Route::post('/{participantId}/cancel', [ClubActivityParticipantController::class, 'cancel']);
                    Route::post('/{participantId}/withdraw', [ClubActivityParticipantController::class, 'withdraw']);
                    Route::post('/self/check-in', [ClubActivityParticipantController::class, 'selfCheckIn']);
                    Route::post('/self/absent', [ClubActivityParticipantController::class, 'selfMarkAbsent']);
                });
            });

            Route::prefix('notifications')->group(function () {
                Route::get('/types', [ClubNotificationController::class, 'getNotificationTypes']);
                Route::get('/', [ClubNotificationController::class, 'index']);
                Route::post('/', [ClubNotificationController::class, 'store']);
                Route::post('/mark-read-all', [ClubNotificationController::class, 'markAllAsRead']);
                Route::get('/{notificationId}', [ClubNotificationController::class, 'show']);
                Route::put('/{notificationId}', [ClubNotificationController::class, 'update']);
                Route::delete('/{notificationId}', [ClubNotificationController::class, 'destroy']);
                Route::post('/{notificationId}/send', [ClubNotificationController::class, 'send']);
                Route::post('/{notificationId}/pin', [ClubNotificationController::class, 'togglePin']);
                Route::post('/{notificationId}/mark-read', [ClubNotificationController::class, 'markAsRead']);

                Route::prefix('{notificationId}/recipients')->group(function () {
                    Route::get('/', [ClubNotificationRecipientController::class, 'index']);
                    Route::post('/', [ClubNotificationRecipientController::class, 'store']);
                    Route::get('/read', [ClubNotificationRecipientController::class, 'getRead']);
                    Route::get('/unread', [ClubNotificationRecipientController::class, 'getUnread']);
                });
            });

            Route::prefix('fund-collections')->group(function () {
                Route::get('/', [ClubFundCollectionController::class, 'index']);
                Route::post('/', [ClubFundCollectionController::class, 'store']);
                Route::get('/my-collections', [ClubFundCollectionController::class, 'getMyCollections']);
                Route::get('/qr-codes', [ClubFundCollectionController::class, 'listQrCodes']);
                Route::post('/qr-codes', [ClubFundCollectionController::class, 'createQrCode']);
                Route::delete('/qr-codes/main', [ClubFundCollectionController::class, 'destroyMainQrCode']);
                Route::delete('/qr-codes/{qrCodeId}', [ClubFundCollectionController::class, 'destroyQrCode']);
                Route::get('/{collectionId}', [ClubFundCollectionController::class, 'show']);
                Route::put('/{collectionId}', [ClubFundCollectionController::class, 'update']);
                Route::delete('/{collectionId}', [ClubFundCollectionController::class, 'destroy']);
                Route::get('/{collectionId}/qr-code', [ClubFundCollectionController::class, 'getQrCode']);
                Route::post('/{collectionId}/remind/{userId}', [ClubFundCollectionController::class, 'remind']);

                Route::prefix('{collectionId}/contributions')->group(function () {
                    Route::get('/', [ClubFundContributionController::class, 'index']);
                    Route::post('/receipt', [ClubFundContributionController::class, 'store']);
                    Route::post('/mark-paid', [ClubFundContributionController::class, 'markMemberPaid']);
                    Route::get('/{contributionId}', [ClubFundContributionController::class, 'show']);
                    Route::post('/{contributionId}/confirm', [ClubFundContributionController::class, 'confirm']);
                    Route::post('/{contributionId}/reject', [ClubFundContributionController::class, 'reject']);
                });
            });

            Route::prefix('expenses')->group(function () {
                Route::get('/', [ClubExpenseController::class, 'index']);
                Route::post('/', [ClubExpenseController::class, 'store']);
                Route::get('/statistics', [ClubExpenseController::class, 'getStatistics']);
                Route::get('/{expenseId}', [ClubExpenseController::class, 'show']);
                Route::put('/{expenseId}', [ClubExpenseController::class, 'update']);
                Route::delete('/{expenseId}', [ClubExpenseController::class, 'destroy']);
            });

            Route::prefix('monthly-fees')->group(function () {
                Route::get('/', [ClubMonthlyFeeController::class, 'index']);
                Route::post('/', [ClubMonthlyFeeController::class, 'store']);
                Route::get('/{feeId}', [ClubMonthlyFeeController::class, 'show']);
                Route::put('/{feeId}', [ClubMonthlyFeeController::class, 'update']);
                Route::delete('/{feeId}', [ClubMonthlyFeeController::class, 'destroy']);
            });

            Route::prefix('monthly-fee-payments')->group(function () {
                Route::get('/', [ClubMonthlyFeePaymentController::class, 'index']);
                Route::post('/', [ClubMonthlyFeePaymentController::class, 'store']);
                Route::get('/statistics', [ClubMonthlyFeePaymentController::class, 'getStatistics']);
                Route::get('/{paymentId}', [ClubMonthlyFeePaymentController::class, 'show']);
                Route::get('/member/{memberId}', [ClubMonthlyFeePaymentController::class, 'getMemberPayments']);
            });

            Route::post('/mini-tournaments', [ClubMiniTournamentController::class, 'store']);
            Route::match(['put', 'patch'], '/mini-tournaments/{miniTournamentId}', [ClubMiniTournamentController::class, 'update']);
        });
    });
});

Route::middleware(['auth:api', 'update.last_login', 'throttle:api'])->group(function () {
    Route::post('/device-token/sync', [DeviceTokenController::class, 'sync']);
    Route::post('/notifications/setting', [DeviceTokenController::class, 'update']);
    Route::delete('/device-token/delete', [DeviceTokenController::class, 'destroy']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::prefix('promotion')->group(function () {
        Route::get('/recipients', [PromotionController::class, 'recipients']);
        Route::post('/send', [PromotionController::class, 'send']);
    });
    Route::prefix('user')->group(function () {
        Route::match(['get', 'post'], '/index', [UserController::class, 'index']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::post('/update', [UserController::class, 'update']);
        Route::delete('/delete/{id}', [UserController::class, 'destroy']);
        Route::get('/matches/dataset', [UserMatchStatsController::class, 'dataset']);
        Route::get('/matches/list',[UserMatchStatsController::class, 'matchesBySportId']);
        Route::post('/change-email', [UserController::class, 'changeEmail']);
        Route::post('/verify-change-email', [UserController::class, 'verifyChangeEmail']);
        Route::post('/resend-change-email-otp', [UserController::class, 'resendChangeEmailOtp']);
    });
    Route::prefix('tournaments')->group(function () {
        Route::get('/index', [TournamentController::class, 'index']);
        Route::post('/store', [TournamentController::class, 'store']);
        Route::get('/{id}', [TournamentController::class, 'show']);
        Route::post('/update/{id}', [TournamentController::class, 'update']);
        Route::post('/delete', [TournamentController::class, 'destroy']);
        Route::get('/{id}/bracket', [TournamentController::class, 'getBracket']);
    });

    Route::prefix('tournament-staff')->group(function () {
        Route::post('/add/{tournamentId}', [TournamentStaffController::class, 'addStaff']);
    });

    Route::prefix('mini-tournament-staff')->group(function () {
        Route::post('/add/{tournamentId}', [MiniTournamentStaffController::class, 'addStaff']);
    });

    Route::prefix('tournament-types')->group(function () {
        Route::post('/store', [TournamentTypeController::class, 'store']);
        Route::match(['put', 'patch'], '/{tournamentType}', [TournamentTypeController::class, 'update']);
        Route::get('/{tournamentType}', [TournamentTypeController::class, 'show']);
        Route::delete('/{tournamentType}', [TournamentTypeController::class, 'destroy']);
        Route::get('/{tournamentType}/bracket', [TournamentTypeController::class, 'getBracket']);
        Route::get('/{tournamentId}/rank', [TournamentTypeController::class, 'getRank']);
        Route::get('/{tournamentType}/advancement-status', [TournamentTypeController::class, 'getAdvancementStatus']);
        Route::post('/{tournamentType}/regenerate-matches', [TournamentTypeController::class, 'regenerateMatches']);
        // manual
        Route::get('/{tournamentType}/groups-with-teams', [TournamentTypeController::class, 'getGroupsWithTeams']);
        Route::post('/{tournamentType}/assign-teams-and-generate', [TournamentTypeController::class, 'assignTeamsAndGenerate']);
        Route::post('/{tournamentType}/auto-generate-matches', [TournamentTypeController::class, 'autoGenerateMatches']);
    });

    Route::prefix('matches')->group(function() {
        Route::match(['get', 'post'], '/index/{tournamentTypeId}', [MatchesController::class, 'index']);
        Route::get('/detail/{matchId}', [MatchesController::class, 'detail']);
        Route::post('/update/{matchId}', [MatchesController::class, 'update']);
        Route::post('/{match}/swap', [MatchesController::class, 'swapTeams']);
        // Xác nhận kết quả (QR Code)
        Route::get('/{matchId}/generate-qr', [MatchesController::class, 'generateQr']);
        Route::post('/confirm-result/{matchId}', [MatchesController::class, 'confirmResult']);
        Route::post('/{matchId}/advance-team-manual', [MatchesController::class, 'advanceTeamManual']);
    });

    Route::prefix('participants')->group(function () {
        Route::match(['get', 'post'], '/index/{tournamentId}', [ParticipantController::class, 'index']);
        Route::post('/join/{tournamentId}', [ParticipantController::class, 'join']);
        Route::post('/confirm/{participantId}', [ParticipantController::class, 'confirm']);
        Route::post('/accept/{participantId}', [ParticipantController::class, 'acceptInvite']);
        Route::post('/decline/{participantId}', [ParticipantController::class, 'declineInvite']);
        Route::post('/invite-user/{tournamentId}', [ParticipantController::class, 'inviteUsers']);
        Route::post('/delete/{participantId}', [ParticipantController::class, 'delete']);
        Route::post('/delete-staff/{staffId}', [ParticipantController::class, 'deleteStaff']);
        Route::match(['get', 'post'], '/list-invite/{tournamentId}', [ParticipantController::class, 'listInvite']);
        Route::match(['get', 'post'], '/list-member/{tournamentId}', [ParticipantController::class, 'getParticipantsNonTeam']);
        Route::post('/candidates/{tournamentId}', [ParticipantController::class, 'getCandidates']);
    });

    Route::prefix('teams')->group(function () {
        Route::match(['get', 'post'], '/index/{tournamentId}', [TeamController::class, 'listTeams']);
        Route::post('/create/{tournamentId}', [TeamController::class, 'createTeam']);
        Route::post('/update/{teamId}', [TeamController::class, 'updateTeam']);
        Route::post('/add-member/{teamId}', [TeamController::class, 'addMember']);
        Route::post('/remove-member/{teamId}', [TeamController::class, 'removeMember']);
        Route::post('/auto-assign/{tournamentId}', [TeamController::class, 'autoAssignTeams']);
        Route::delete('/delete/{teamId}', [TeamController::class, 'deleteTeam']);
    });

    Route::prefix('clubs')->middleware('throttle:clubs')->group(function () {
        Route::get('/', [ClubController::class, 'index']);
        Route::post('/', [ClubController::class, 'store']);
        Route::get('/my-clubs', [ClubController::class, 'myClubs']);
        Route::get('/my-invitations', [ClubJoinRequestController::class, 'myInvitations']);
        Route::get('/search-location', [ClubController::class, 'searchLocation']);
        Route::get('/location-detail', [ClubController::class, 'detailGooglePlace']);
        Route::get('/members/candidates', [ClubMemberController::class, 'getCandidates']);
        Route::get('/{clubId}', [ClubController::class, 'show']);
        Route::put('/{clubId}', [ClubController::class, 'update']);
        Route::delete('/{clubId}', [ClubController::class, 'destroy']);
        Route::post('/{clubId}/restore', [ClubController::class, 'restore']);
        Route::post('/{clubId}/leave', [ClubController::class, 'leave']);

        Route::prefix('{clubId}')->group(function () {
            Route::get('/profile', [ClubController::class, 'getProfile']);
            Route::get('/fund', [ClubController::class, 'getFund']);
            Route::put('/fund', [ClubController::class, 'updateFund']);
            Route::get('/fund/overview', [ClubWalletController::class, 'getFundOverview']);
            Route::get('/fund/qr-code', [ClubWalletController::class, 'getFundQrCode']);
            Route::get('/dashboard', [ClubDashboardController::class, 'index']);
            Route::get('/leaderboard', [ClubController::class, 'getMonthlyLeaderboard']);
            Route::post('/report', [ClubReportController::class, 'store']);
            Route::get('/reports', [ClubReportController::class, 'index']);

            Route::prefix('members')->group(function () {
                Route::get('/', [ClubMemberController::class, 'index']);
                Route::post('/', [ClubMemberController::class, 'store']);
                Route::get('/statistics', [ClubMemberController::class, 'statistics']);
                Route::get('/{memberId}', [ClubMemberController::class, 'show']);
                Route::put('/{memberId}', [ClubMemberController::class, 'update']);
                Route::delete('/{memberId}', [ClubMemberController::class, 'destroy']);
            });

            Route::prefix('invitations')->group(function () {
                Route::post('/accept', [ClubJoinRequestController::class, 'acceptInvitation']);
                Route::post('/reject', [ClubJoinRequestController::class, 'rejectInvitation']);
            });
            Route::prefix('join-requests')->group(function () {
                Route::get('/', [ClubJoinRequestController::class, 'index']);
                Route::post('/', [ClubJoinRequestController::class, 'store']);
                Route::post('/reject', [ClubJoinRequestController::class, 'reject']);
                Route::delete('/', [ClubJoinRequestController::class, 'destroyMyRequest']);
                Route::get('/{requestId}', [ClubJoinRequestController::class, 'show']);
                Route::post('/{requestId}/approve', [ClubJoinRequestController::class, 'approve']);
                Route::post('/{requestId}/reject', [ClubJoinRequestController::class, 'reject']);
            });

            Route::prefix('wallets')->group(function () {
                Route::get('/', [ClubWalletController::class, 'index']);
                Route::post('/', [ClubWalletController::class, 'store']);
                Route::get('/{walletId}', [ClubWalletController::class, 'show']);
                Route::put('/{walletId}', [ClubWalletController::class, 'update']);
                Route::delete('/{walletId}', [ClubWalletController::class, 'destroy']);
                Route::get('/{walletId}/balance', [ClubWalletController::class, 'getBalance']);
                Route::get('/{walletId}/transactions', [ClubWalletController::class, 'getTransactions']);
            });

            Route::prefix('wallet-transactions')->group(function () {
                Route::get('/', [ClubWalletTransactionController::class, 'index']);
                Route::get('/my-transactions', [ClubWalletTransactionController::class, 'myTransactions']);
                Route::post('/', [ClubWalletTransactionController::class, 'store']);
                Route::get('/{transactionId}', [ClubWalletTransactionController::class, 'show']);
                Route::put('/{transactionId}', [ClubWalletTransactionController::class, 'update']);
                Route::post('/{transactionId}/confirm', [ClubWalletTransactionController::class, 'confirm']);
                Route::post('/{transactionId}/reject', [ClubWalletTransactionController::class, 'reject']);
            });

            Route::prefix('activities')->group(function () {
                Route::get('/', [ClubActivityController::class, 'index']);
                Route::post('/', [ClubActivityController::class, 'store']);
                Route::get('/{activityId}', [ClubActivityController::class, 'show']);
                Route::match(['put', 'post'], '/{activityId}', [ClubActivityController::class, 'update']);
                Route::delete('/{activityId}', [ClubActivityController::class, 'destroy']);
                Route::post('/{activityId}/complete', [ClubActivityController::class, 'complete']);
                Route::post('/{activityId}/cancel', [ClubActivityController::class, 'cancel']);
                Route::post('/{activityId}/recurrence-series/cancel', [ClubActivityController::class, 'cancelRecurrenceSeries']);
                Route::post('/{activityId}/check-in', [ClubActivityParticipantController::class, 'checkIn']);
                Route::get('/{activityId}/check-ins', [ClubActivityParticipantController::class, 'checkInList']);

                Route::prefix('{activityId}/participants')->group(function () {
                    Route::get('/', [ClubActivityParticipantController::class, 'index']);
                    Route::post('/', [ClubActivityParticipantController::class, 'store']);
                    Route::post('/invite', [ClubActivityParticipantController::class, 'invite']);
                    Route::put('/{participantId}', [ClubActivityParticipantController::class, 'update']);
                    Route::delete('/{participantId}', [ClubActivityParticipantController::class, 'destroy']);
                    Route::post('/{participantId}/approve', [ClubActivityParticipantController::class, 'approve']);
                    Route::post('/{participantId}/reject', [ClubActivityParticipantController::class, 'reject']);
                    Route::post('/{participantId}/accept-invite', [ClubActivityParticipantController::class, 'acceptInvite']);
                    Route::post('/{participantId}/decline-invite', [ClubActivityParticipantController::class, 'declineInvite']);
                    Route::post('/{participantId}/cancel', [ClubActivityParticipantController::class, 'cancel']);
                    Route::post('/{participantId}/withdraw', [ClubActivityParticipantController::class, 'withdraw']);
                });
            });

            // club_alert: list toàn bộ thông báo của CLB (khác user_notification)
            Route::prefix('notifications')->group(function () {
                Route::get('/types', [ClubNotificationController::class, 'getNotificationTypes']);
                Route::get('/', [ClubNotificationController::class, 'index']);
                Route::post('/', [ClubNotificationController::class, 'store']);
                Route::post('/mark-read-all', [ClubNotificationController::class, 'markAllAsRead']);
                Route::get('/{notificationId}', [ClubNotificationController::class, 'show']);
                Route::put('/{notificationId}', [ClubNotificationController::class, 'update']);
                Route::delete('/{notificationId}', [ClubNotificationController::class, 'destroy']);
                Route::post('/{notificationId}/send', [ClubNotificationController::class, 'send']);
                Route::post('/{notificationId}/pin', [ClubNotificationController::class, 'togglePin']);
                Route::post('/{notificationId}/mark-read', [ClubNotificationController::class, 'markAsRead']);

                Route::prefix('{notificationId}/recipients')->group(function () {
                    Route::get('/', [ClubNotificationRecipientController::class, 'index']);
                    Route::post('/', [ClubNotificationRecipientController::class, 'store']);
                    Route::get('/read', [ClubNotificationRecipientController::class, 'getRead']);
                    Route::get('/unread', [ClubNotificationRecipientController::class, 'getUnread']);
                });
            });

            Route::prefix('fund-collections')->group(function () {
                Route::get('/', [ClubFundCollectionController::class, 'index']);
                Route::post('/', [ClubFundCollectionController::class, 'store']);
                Route::get('/my-collections', [ClubFundCollectionController::class, 'getMyCollections']);
                Route::get('/qr-codes', [ClubFundCollectionController::class, 'listQrCodes']);
                Route::post('/qr-codes', [ClubFundCollectionController::class, 'createQrCode']);
                Route::delete('/qr-codes/main', [ClubFundCollectionController::class, 'destroyMainQrCode']);
                Route::delete('/qr-codes/{qrCodeId}', [ClubFundCollectionController::class, 'destroyQrCode']);
                Route::get('/{collectionId}', [ClubFundCollectionController::class, 'show']);
                Route::put('/{collectionId}', [ClubFundCollectionController::class, 'update']);
                Route::delete('/{collectionId}', [ClubFundCollectionController::class, 'destroy']);
                Route::get('/{collectionId}/qr-code', [ClubFundCollectionController::class, 'getQrCode']);
                Route::post('/{collectionId}/remind/{userId}', [ClubFundCollectionController::class, 'remind']);

                Route::prefix('{collectionId}/contributions')->group(function () {
                    Route::get('/', [ClubFundContributionController::class, 'index']);
                    Route::post('/receipt', [ClubFundContributionController::class, 'store']);
                    Route::post('/mark-paid', [ClubFundContributionController::class, 'markMemberPaid']);
                    Route::get('/{contributionId}', [ClubFundContributionController::class, 'show']);
                    Route::post('/{contributionId}/confirm', [ClubFundContributionController::class, 'confirm']);
                    Route::post('/{contributionId}/reject', [ClubFundContributionController::class, 'reject']);
                });
            });

            Route::prefix('expenses')->group(function () {
                Route::get('/', [ClubExpenseController::class, 'index']);
                Route::post('/', [ClubExpenseController::class, 'store']);
                Route::get('/statistics', [ClubExpenseController::class, 'getStatistics']);
                Route::get('/{expenseId}', [ClubExpenseController::class, 'show']);
                Route::put('/{expenseId}', [ClubExpenseController::class, 'update']);
                Route::delete('/{expenseId}', [ClubExpenseController::class, 'destroy']);
            });

            Route::prefix('monthly-fees')->group(function () {
                Route::get('/', [ClubMonthlyFeeController::class, 'index']);
                Route::post('/', [ClubMonthlyFeeController::class, 'store']);
                Route::get('/{feeId}', [ClubMonthlyFeeController::class, 'show']);
                Route::put('/{feeId}', [ClubMonthlyFeeController::class, 'update']);
                Route::delete('/{feeId}', [ClubMonthlyFeeController::class, 'destroy']);
            });

            Route::prefix('monthly-fee-payments')->group(function () {
                Route::get('/', [ClubMonthlyFeePaymentController::class, 'index']);
                Route::post('/', [ClubMonthlyFeePaymentController::class, 'store']);
                Route::get('/statistics', [ClubMonthlyFeePaymentController::class, 'getStatistics']);
                Route::get('/{paymentId}', [ClubMonthlyFeePaymentController::class, 'show']);
                Route::get('/member/{memberId}', [ClubMonthlyFeePaymentController::class, 'getMemberPayments']);
            });

            Route::post('/mini-tournaments', [ClubMiniTournamentController::class, 'store']);
            Route::match(['put', 'patch'], '/mini-tournaments/{miniTournamentId}', [ClubMiniTournamentController::class, 'update']);
        });
    });

    Route::match(['get', 'post'], '/home', [HomeController::class, 'index']);
    Route::match(['get', 'post'], '/locations', [LocationController::class, 'index']);
    // search geocoding
    Route::get('/search-location', [UserController::class, 'searchLocation']);
    Route::get('/location-detail', [UserController::class, 'detailGooglePlace']);
    // Mini Tournament Routes
    Route::prefix('mini-tournaments')->group(function (): void {
        Route::match(['get', 'post'], '/index', [MiniTournamentController::class, 'index']);
        Route::post('/store', [MiniTournamentController::class, 'store']);
        Route::get('/{id}', [MiniTournamentController::class, 'show']);
        Route::post('/update/{id}', [MiniTournamentController::class, 'update']);
        Route::post('/delete/{id}', [MiniTournamentController::class,'destroy']);
        Route::post('/{tournamentId}/recurrence-series/cancel', [MiniTournamentController::class, 'cancelRecurrenceSeries']);

        // Mini Tournament Payment Routes
        Route::get('/{id}/payments', [MiniTournamentPaymentController::class, 'index']);
        Route::post('/{id}/pay', [MiniTournamentPaymentController::class, 'pay']);
        Route::match(['get', 'post'], '/{id}/my-payment', [MiniTournamentPaymentController::class, 'myPayment']);
        Route::post('/{id}/payments/{paymentId}/mark-paid', [MiniTournamentPaymentController::class, 'markPaid']);
        Route::post('/{id}/payments/{paymentId}/confirm', [MiniTournamentPaymentController::class, 'confirm']);
        Route::post('/{id}/payments/{paymentId}/reject', [MiniTournamentPaymentController::class, 'reject']);
        Route::post('/{id}/payments/remind/{participantId}', [MiniTournamentPaymentController::class, 'remind']);
        Route::post('/{id}/payments/remind-all', [MiniTournamentPaymentController::class, 'remindAll']);

        // Guest Routes
        Route::get('/{id}/guests', [GuestController::class, 'index']);
        Route::post('/{id}/guests', [GuestController::class, 'store']);
        Route::get('/{id}/guaranteed-guests', [GuestController::class, 'guaranteedGuests']);
        Route::get('/{id}/guarantor-candidates', [GuestController::class, 'guarantorCandidates']);
        Route::get('/{id}/guarantor-guests/{userId}', [GuestController::class, 'guarantorGuests']);
    });
    // Mini Tournament Templates
    Route::prefix('mini-tournament-templates')->group(function (): void {
        Route::get('/', [MiniTournamentTemplateController::class, 'index']);
        Route::post('/', [MiniTournamentTemplateController::class, 'store']);
        Route::post('/{id}', [MiniTournamentTemplateController::class, 'update']);
        Route::delete('/{id}', [MiniTournamentTemplateController::class, 'destroy']);
    });
    // Mini Participant Routes
    Route::prefix('mini-participants')->group(function (): void {
        Route::match(['get', 'post'], '/index/{miniTournamentId}', [MiniParticipantController::class, 'index']);
        Route::post('/join/{miniTournamentId}', [MiniParticipantController::class, 'join']);
        Route::post('/confirm/{participantId}', [MiniParticipantController::class, 'confirm']);
        Route::post('accept/{participantId}', [MiniParticipantController::class, 'acceptInvite']);
        Route::post('decline/{participantId}', [MiniParticipantController::class, 'declineInvite']);
        Route::post('/invite/{miniTournamentId}', [MiniParticipantController::class, 'invite']);
        Route::post('/invite-friends/{miniTournamentId}', [MiniParticipantController::class, 'inviteFriends']);
        Route::match(['get', 'post'], '/candidates/{miniTournamentId}', [MiniParticipantController::class, 'getCandidates']);
        Route::post('/delete/{participantId}', [MiniParticipantController::class, 'delete']);
        Route::post('/delete-staff/{staffId}', [MiniParticipantController::class, 'deleteStaff']);
    });
    // Mini Match Routes
    Route::prefix('mini-matches')->group(function (): void {
        Route::match(['get', 'post'], '/index/{miniTournamentId}', [MiniMatchController::class, 'index']);
        Route::get('/{matchId}', [MiniMatchController::class, 'show']);
        Route::post('/save/{miniTournamentId}', [MiniMatchController::class, 'save']);
        Route::post('/store/{miniTournamentId}', [MiniMatchController::class, 'store']);
        Route::post('/update/{matchId}', [MiniMatchController::class, 'update']);
        Route::post('/add-set/{matchId}', [MiniMatchController::class, 'addSetResult']);
        Route::delete('/delete-set/{matchId}/{setNumber}', [MiniMatchController::class, 'deleteSetResult']);
        Route::match(['delete', 'post'], '/delete', [MiniMatchController::class, 'destroy']);
        // Xác nhận kết quả (QR Code)
        Route::get('/{matchId}/generate-qr', [MiniMatchController::class, 'generateQr']);
        Route::post('/confirm-result/{matchId}', [MiniMatchController::class, 'confirmResult']);
        // Trình lọc trận đấu
        Route::match(['get', 'post'], '/list-match', [MiniMatchController::class, 'listMiniMatch']);
    });

    Route::prefix('send-message')->group(function () {
        Route::prefix('mini-tournament')->group(function () {
            Route::post('/{tournamentId}', [SendMessageController::class, 'storeMessageMiniTour']);
            Route::match(['get', 'post'], '/index/{tournamentId}', [SendMessageController::class, 'getMessagesMiniTour']);
        });
        Route::prefix('tournament')->group(function () {
            Route::post('/{tournamentId}', [SendMessageController::class, 'storeMessageTour']);
            Route::match(['get', 'post'], '/index/{tournamentId}', [SendMessageController::class, 'getMessagesTour']);
        });
    });

    Route::prefix('messages')->group(function () {
        Route::match(['get', 'post'], '/conversation/{userId}', [MessageController::class, 'getConversation']);
        Route::post('/store', [MessageController::class, 'store']);
        Route::post('/mark-as-read/{senderId}', [MessageController::class, 'markConversationAsRead']);
    });

    Route::prefix('competition-locations')->group(function () {
        Route::match(['get', 'post'], '/index', [CompetitionLocationController::class, 'index']);
    });

    Route::prefix('follows')->group(function () {
        Route::match(['get', 'post'], '/index', [FollowController::class, 'index']);
        Route::post('/store', [FollowController::class, 'store']);
        Route::match(['delete', 'post'], '/delete', [FollowController::class, 'destroy']);
        Route::match(['get', 'post'], '/list-friends', [FollowController::class, 'getFriends']);
    });

    Route::prefix('sports')->group(function () {
        Route::match(['get', 'post'], '/index', [SportController::class, 'index']);
        Route::post('/update/{id}', [SportController::class, 'update']);
    });
    Route::prefix('facilities')->group(function () {
        Route::get('/index', [FacilityController::class, 'index']);
    });

    Route::prefix('competition-location-yards')->group(function () {
        Route::get('/index', [CompetitionLocationYardController::class, 'index']);
    });
    // user_notification: thông báo riêng từng thành viên (Laravel notifications)
    Route::prefix('user-notifications')->group(function () {
        Route::match(['get', 'post'], '/index', [NotificationController::class, 'index']);
        Route::post('/mark-as-read', [NotificationController::class, 'markAsRead']);
        Route::post('/delete', [NotificationController::class, 'delete']);
    });
    // Alias: api/notifications/* (để client gọi api/notifications/index thay vì api/user-notifications/index)
    Route::prefix('notifications')->group(function () {
        Route::match(['get', 'post'], '/index', [NotificationController::class, 'index']);
        Route::post('/mark-as-read', [NotificationController::class, 'markAsRead']);
        Route::post('/delete', [NotificationController::class, 'delete']);
    });
    Route::prefix('mini-tournament-notifications')->group(function () {
        Route::post('/subscribe/{miniTournamentId}', [MiniTournamentNotificationController::class, 'subscribe']);
        Route::post('/unsubscribe/{miniTournamentId}', [MiniTournamentNotificationController::class, 'unsubscribe']);
    });

    Route::prefix('map')->group(function () {
        Route::match(['get', 'post'], '/match', [MapController::class, 'getMatch']);
    });

    Route::prefix('banners')->group(function () {
        Route::post('/store', [BannerController::class, 'store']);
    });
});

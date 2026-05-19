<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\CollectionController;
use App\Http\Controllers\Api\V1\DeckController;
use App\Http\Controllers\Api\V1\DevController;
use App\Http\Controllers\Api\V1\EconomyController;
use App\Http\Controllers\Api\V1\FriendshipController;
use App\Http\Controllers\Api\V1\InAppNotificationController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\MatchController;
use App\Http\Controllers\Api\V1\MatchmakingController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\SocialSummaryController;
use App\Http\Controllers\Api\V1\ShopController;
use App\Http\Controllers\Api\V1\StatsController;
use App\Http\Controllers\Api\V1\WeeklyController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

    Route::get('/stats/online-players', [StatsController::class, 'onlinePlayers']);

    Route::get('/avatars/starters', [ProfileController::class, 'starters']);
    Route::get('/ranked/divisions', [ProfileController::class, 'rankedDivisionOptions']);
    Route::get('/ranked/leaderboard', [ProfileController::class, 'leaderboard']);
    Route::get('/profile/{nickname}/ranked-history', [ProfileController::class, 'publicRankedHistory'])
        ->where('nickname', '[a-zA-Z0-9_-]+');
    Route::get('/profile/{nickname}', [ProfileController::class, 'show'])
        ->where('nickname', '[a-zA-Z0-9_-]+');

    Route::middleware(['auth:sanctum', 'touch.session'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::put('/profile/cosmetics', [ProfileController::class, 'updateCosmetics']);
        Route::get('/profile/me/ranked-history', [ProfileController::class, 'myRankedHistory']);
        Route::get('/profile/me/avatars', [ProfileController::class, 'unlockSummary']);
        Route::get('/profile/me/cosmetic-unlocks', [ProfileController::class, 'cosmeticUnlocks']);

        Route::get('/collection', [CollectionController::class, 'index']);
        Route::get('/weekly/status', [WeeklyController::class, 'status']);
        Route::post('/weekly/claim', [WeeklyController::class, 'claim']);
        Route::get('/shop/catalog', [ShopController::class, 'catalog']);
        Route::post('/shop/buy', [ShopController::class, 'buy']);
        Route::get('/economy/chest/prices', [EconomyController::class, 'chestPrices']);
        Route::post('/economy/chest/open', [EconomyController::class, 'chestOpen']);
        Route::get('/economy/chest/purchases', [EconomyController::class, 'chestPurchaseHistory']);
        Route::post('/economy/chest/purchases/{purchase}/refund', [EconomyController::class, 'refundChestPurchase']);
        Route::get('/inventory/chests', [InventoryController::class, 'chestStacks']);
        Route::post('/inventory/chests/open', [InventoryController::class, 'openCosmeticChest']);
        Route::get('/inventory/chests/{slug}/preview', [InventoryController::class, 'chestPreview']);
        Route::get('/inventory/duplicates', [InventoryController::class, 'lootDuplicates']);
        Route::post('/inventory/duplicates/disenchant', [InventoryController::class, 'disenchantDuplicate']);
        Route::get('/decks', [DeckController::class, 'index']);
        Route::post('/decks', [DeckController::class, 'store']);
        Route::put('/decks/{id}', [DeckController::class, 'update']);
        Route::delete('/decks/{id}', [DeckController::class, 'destroy']);

        Route::post('/matchmaking/join', [MatchmakingController::class, 'join']);
        Route::post('/matchmaking/challenge/{user}', [MatchmakingController::class, 'challenge']);
        Route::delete('/matchmaking/leave', [MatchmakingController::class, 'leave']);
        Route::get('/matchmaking/status', [MatchmakingController::class, 'status']);
        Route::post('/matchmaking/matches/{match}/accept', [MatchmakingController::class, 'accept']);
        Route::post('/matchmaking/matches/{match}/decline', [MatchmakingController::class, 'decline']);

        Route::get('/matches/{id}', [MatchController::class, 'show']);
        Route::post('/matches/{id}/action', [MatchController::class, 'action']);
        Route::post('/matches/{id}/reconnect', [MatchController::class, 'reconnect']);
        // surrender = render voluntário | abandon = fechou a aba (mesmo resultado)
        Route::post('/matches/{id}/surrender', [MatchController::class, 'surrender']);
        Route::post('/matches/{id}/abandon', [MatchController::class, 'surrender']);

        Route::get('/social/summary', SocialSummaryController::class);

        Route::get('/social/friends', [FriendshipController::class, 'index']);
        Route::get('/social/friends/requests/incoming', [FriendshipController::class, 'incomingRequests']);
        Route::get('/social/friends/requests/outgoing', [FriendshipController::class, 'outgoingRequests']);
        Route::post('/social/friends/request', [FriendshipController::class, 'sendRequest']);
        Route::post('/social/friends/requests/{friendRequest}/accept', [FriendshipController::class, 'accept']);
        Route::post('/social/friends/requests/{friendRequest}/decline', [FriendshipController::class, 'decline']);
        Route::delete('/social/friends/requests/{friendRequest}', [FriendshipController::class, 'cancel']);
        Route::delete('/social/friends/{user}', [FriendshipController::class, 'destroy']);
        Route::post('/social/block', [FriendshipController::class, 'block']);
        Route::delete('/social/block/{user}', [FriendshipController::class, 'unblock']);

        Route::get('/social/chat/conversations', [ChatController::class, 'conversations']);
        Route::get('/social/chat/with/{user}', [ChatController::class, 'messages']);
        Route::post('/social/chat/with/{user}', [ChatController::class, 'send']);
        Route::post('/social/chat/with/{user}/read', [ChatController::class, 'markRead']);

        Route::get('/social/notifications', [InAppNotificationController::class, 'index']);
        Route::post('/social/notifications/read-all', [InAppNotificationController::class, 'markAllRead']);
        Route::post('/social/notifications/{notification}/read', [InAppNotificationController::class, 'markRead']);

        Route::post('/dev/pair-queue', [DevController::class, 'pairQueue']);
    });
});

<?php

namespace App\Services\Economy;

use App\Models\Card;
use App\Models\PlayerWeekly;
use App\Models\User;
use App\Services\Collection\PlayerCollectionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WeeklyRewardService
{
    public function __construct(
        private PlayerCollectionService $collection,
    ) {}

    public function weekStartToday(): string
    {
        return Carbon::now(config('app.timezone'))->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    public function inClaimWindow(): bool
    {
        if (config('game.progression.weekly.claim_any_time')) {
            return true;
        }

        $now = Carbon::now(config('app.timezone'));

        return $now->isSaturday() || $now->isSunday();
    }

    public function currentRow(User $user): PlayerWeekly
    {
        return PlayerWeekly::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'week_start' => $this->weekStartToday(),
            ],
            ['xp_earned' => 0]
        );
    }

    public function status(User $user): array
    {
        $cfg = config('game.progression.weekly');
        $row = $this->currentRow($user);

        $eligible = (int) $row->xp_earned >= (int) $cfg['xp_min_claim']
            && ! $row->claimed_at
            && $this->inClaimWindow();

        if ($eligible && empty($row->offers)) {
            $row->offers = $this->generateOffers();
            $row->save();
        }

        return [
            'week_start' => $row->week_start->toDateString(),
            'xp_earned' => (int) $row->xp_earned,
            'xp_cap' => (int) $cfg['xp_cap'],
            'xp_min_claim' => (int) $cfg['xp_min_claim'],
            'claimed' => (bool) $row->claimed_at,
            'claim_window_open' => $this->inClaimWindow(),
            'eligible' => $eligible,
            'offers' => $row->offers ?? [],
            'pick_count' => (int) $cfg['pick_count'],
        ];
    }

    /** @return list<array{card_id: int, nome: string, raridade: string}> */
    public function generateOffers(): array
    {
        $weights = config('game.chests.weekly_offer_weights');
        $offers = [];
        $hasLegendary = false;

        for ($i = 0; $i < 4; $i++) {
            $rarity = $this->weightedRarity($weights, $hasLegendary);
            if ($rarity === 'lendaria') {
                $hasLegendary = true;
            }
            $card = $this->randomCardForRarity($rarity);
            if (! $card) {
                $card = Card::query()->where('ativo', true)->where('colecionavel', true)
                    ->where('raridade', 'comum')->inRandomOrder()->first();
            }
            $offers[] = [
                'card_id' => $card->id,
                'nome' => $card->nome,
                'raridade' => $card->raridade,
            ];
        }

        return $offers;
    }

    /** @param  array<string, float|int>  $weights */
    private function weightedRarity(array $weights, bool $hasLegendary): string
    {
        if ($hasLegendary) {
            unset($weights['lendaria']);
        }

        $sum = (float) array_sum($weights);
        if ($sum <= 0) {
            return 'comum';
        }

        $pick = mt_rand() / mt_getrandmax() * $sum;
        $acc = 0.0;
        foreach ($weights as $rar => $w) {
            $acc += (float) $w;
            if ($pick <= $acc) {
                return $rar;
            }
        }

        return array_key_first($weights) ?: 'comum';
    }

    private function randomCardForRarity(string $rarity): ?Card
    {
        return Card::query()
            ->where('ativo', true)
            ->where('colecionavel', true)
            ->where('raridade', $rarity)
            ->inRandomOrder()
            ->first();
    }

    /**
     * @param  array<int>  $indices  dois índices 0..3
     * @return list<array{card_id: int, nome: string, duplicate_cristais: int}>
     */
    public function claim(User $user, array $indices): array
    {
        $cfg = config('game.progression.weekly');
        $need = (int) $cfg['pick_count'];

        $indices = array_values(array_unique(array_map('intval', $indices)));
        if (count($indices) !== $need) {
            throw new InvalidArgumentException('Selecione exatamente '.$need.' ofertas.');
        }
        foreach ($indices as $i) {
            if ($i < 0 || $i > 3) {
                throw new InvalidArgumentException('Índice de oferta inválido.');
            }
        }

        if (! $this->inClaimWindow()) {
            throw new InvalidArgumentException('Resgate semanal só pode ser feito no fim de semana.');
        }

        return DB::transaction(function () use ($user, $indices, $cfg) {
            $row = PlayerWeekly::query()->where('user_id', $user->id)
                ->where('week_start', $this->weekStartToday())
                ->lockForUpdate()
                ->first();

            if (! $row || $row->claimed_at) {
                throw new InvalidArgumentException('Nada a resgatar ou já resgatado.');
            }
            if ((int) $row->xp_earned < (int) $cfg['xp_min_claim']) {
                throw new InvalidArgumentException('XP semanal insuficiente (mín. '.(int) $cfg['xp_min_claim'].').');
            }

            $offers = $row->offers ?? [];
            if (count($offers) < 4) {
                $row->offers = $this->generateOffers();
                $row->save();
                $offers = $row->offers;
            }

            $results = [];
            foreach ($indices as $i) {
                if (! isset($offers[$i])) {
                    throw new InvalidArgumentException('Oferta inválida.');
                }
                $cid = (int) $offers[$i]['card_id'];
                $card = Card::query()->findOrFail($cid);
                $userFresh = User::query()->lockForUpdate()->findOrFail($user->id);
                $conv = $this->collection->applyCardGain($userFresh, $card);
                $results[] = [
                    'card_id' => $cid,
                    'nome' => $card->nome,
                    'duplicate_cristais' => $conv,
                ];
            }

            $row->claimed_at = now();
            $row->save();

            return $results;
        });
    }
}

<?php

namespace App\Services\Community;

use App\Models\Card;
use App\Models\CommunityDeck;
use App\Models\CommunityDeckCard;
use App\Models\CommunityDeckLike;
use App\Models\CommunityDeckView;
use App\Models\Deck;
use App\Models\User;
use App\Services\Client\VersaoJogoService;
use App\Services\Collection\PlayerCollectionService;
use App\Services\Deck\DeckService;
use App\Services\Moderation\TextoModeracaoService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CommunityDeckService
{
    public function __construct(
        private DeckService $deckService,
        private PlayerCollectionService $collection,
        private VersaoJogoService $versaoJogo,
        private TextoModeracaoService $moderacao,
    ) {}

    public function versaoAtualJogo(): string
    {
        return $this->versaoJogo->versaoAtual();
    }

    /** @return array{data: list<array>, meta: array} */
    public function listar(?User $viewer, array $filtros): array
    {
        $query = CommunityDeck::query()
            ->with([
                'user:id,nickname,avatar_id,is_content_creator,profile_bg_slug',
                'user.avatar:id,slug,label,image_file',
            ])
            ->whereNull('deleted_at');

        $this->aplicarFiltros($query, $filtros);

        $ordenacao = $filtros['sort'] ?? 'popular';
        match ($ordenacao) {
            'liked' => $query->orderByDesc('likes_count')->orderByDesc('views_count')->orderByDesc('published_at'),
            'recent' => $query->orderByDesc('published_at'),
            default => $query->orderByDesc('views_count')->orderByDesc('likes_count')->orderByDesc('published_at'),
        };

        /** @var LengthAwarePaginator $paginado */
        $paginado = $query->paginate(min(50, max(1, (int) ($filtros['per_page'] ?? 20))));

        $versaoAtual = $this->versaoAtualJogo();
        $ownedMap = $viewer ? $this->collection->ownedMap($viewer) : [];

        $items = collect($paginado->items())->map(function (CommunityDeck $deck) use ($viewer, $versaoAtual, $ownedMap) {
            return $this->formatarResumo($deck, $viewer, $versaoAtual, $ownedMap, incluirCartas: false);
        })->all();

        if ($viewer && ! empty($filtros['can_copy'])) {
            $items = array_values(array_filter($items, fn (array $row) => $row['can_copy'] === true));
        }

        return [
            'data' => $items,
            'meta' => [
                'current_page' => $paginado->currentPage(),
                'last_page' => $paginado->lastPage(),
                'per_page' => $paginado->perPage(),
                'total' => $paginado->total(),
                'game_version_atual' => $versaoAtual,
            ],
        ];
    }

    public function detalhe(User $viewer, int $id): array
    {
        $deck = $this->buscarPublicado($id);
        $this->registrarVisualizacao($viewer, $deck);
        $deck->refresh();

        $ownedMap = $this->collection->ownedMap($viewer);

        return $this->formatarResumo(
            $deck->load([
                'deckCards.card',
                'user:id,nickname,avatar_id,is_content_creator,profile_bg_slug',
                'user.avatar',
            ]),
            $viewer,
            $this->versaoAtualJogo(),
            $ownedMap,
            incluirCartas: true,
        );
    }

    /** @return list<array> */
    public function minhasPublicacoes(User $usuario): array
    {
        return CommunityDeck::query()
            ->where('user_id', $usuario->id)
            ->whereNull('deleted_at')
            ->orderByDesc('published_at')
            ->get()
            ->map(fn (CommunityDeck $deck) => $this->formatarResumo(
                $deck,
                $usuario,
                $this->versaoAtualJogo(),
                $this->collection->ownedMap($usuario),
                incluirCartas: false,
            ))
            ->all();
    }

    public function publicar(
        User $usuario,
        int $deckId,
        string $nome,
        string $descricao,
        string $linhagemPrincipal,
        array $tags,
    ): array {
        $minPartidas = (int) config('game.community_decks.min_matches_to_publish');
        if ((int) $usuario->total_matches_played < $minPartidas) {
            throw new InvalidArgumentException("Jogue pelo menos {$minPartidas} partidas antes de publicar um deck.");
        }

        $maxPublicacoes = (int) config('game.community_decks.max_publications_per_user');
        $ativas = CommunityDeck::query()
            ->where('user_id', $usuario->id)
            ->whereNull('deleted_at')
            ->count();
        if ($ativas >= $maxPublicacoes) {
            throw new InvalidArgumentException("Você já tem {$maxPublicacoes} decks publicados. Remova um para publicar outro.");
        }

        $deckPessoal = Deck::query()
            ->where('user_id', $usuario->id)
            ->where('id', $deckId)
            ->with(['deckCards.card'])
            ->first();
        if (! $deckPessoal) {
            throw new InvalidArgumentException('Deck não encontrado.');
        }

        $formatado = $this->deckService->listForUser($usuario);
        $deckResumo = collect($formatado)->firstWhere('id', $deckId);
        if (! $deckResumo || ! $deckResumo['valido']) {
            throw new InvalidArgumentException('O deck precisa estar válido (15 cartas) para publicar.');
        }

        $nome = trim($nome);
        $descricao = trim($descricao);
        $this->moderacao->validarTextoPublico($nome, 'Nome');
        if ($descricao !== '') {
            $this->moderacao->validarTextoPublico($descricao, 'Descrição');
        }

        $linhagemPrincipal = strtolower(trim($linhagemPrincipal));
        if (! in_array($linhagemPrincipal, config('game.community_decks.allowed_lineages', []), true)) {
            throw new InvalidArgumentException('Linhagem principal inválida.');
        }

        $tags = $this->normalizarTags($tags);
        $versao = $this->versaoAtualJogo();

        return DB::transaction(function () use ($usuario, $deckPessoal, $nome, $descricao, $linhagemPrincipal, $tags, $versao, $deckResumo) {
            $elyCode = $this->gerarElyCodeUnico();

            $publicado = CommunityDeck::create([
                'user_id' => $usuario->id,
                'source_deck_id' => $deckPessoal->id,
                'nome' => $nome,
                'descricao' => $descricao !== '' ? $descricao : null,
                'linhagem_principal' => $linhagemPrincipal,
                'game_version' => $versao,
                'ely_code' => $elyCode,
                'is_streamer_deck' => (bool) $usuario->is_content_creator,
                'tags' => $tags,
                'published_at' => now(),
            ]);

            foreach ($deckResumo['cartas'] as $carta) {
                CommunityDeckCard::create([
                    'community_deck_id' => $publicado->id,
                    'card_id' => (int) $carta['card_id'],
                    'quantidade' => (int) $carta['quantidade'],
                ]);
            }

            $publicado->load(['user.avatar', 'deckCards.card']);

            return $this->formatarResumo(
                $publicado,
                $usuario,
                $versao,
                $this->collection->ownedMap($usuario),
                incluirCartas: true,
            );
        });
    }

    public function despublicar(User $usuario, int $id): void
    {
        $deck = CommunityDeck::query()
            ->where('user_id', $usuario->id)
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();
        if (! $deck) {
            throw new InvalidArgumentException('Publicação não encontrada.');
        }

        $deck->delete();
    }

    public function curtir(User $usuario, int $id): array
    {
        $deck = $this->buscarPublicado($id);

        $criado = CommunityDeckLike::query()->firstOrCreate([
            'user_id' => $usuario->id,
            'community_deck_id' => $deck->id,
        ]);

        if ($criado->wasRecentlyCreated) {
            $deck->increment('likes_count');
        }

        return ['likes_count' => $deck->fresh()->likes_count, 'curtido' => true];
    }

    public function removerCurtida(User $usuario, int $id): array
    {
        $deck = $this->buscarPublicado($id);

        $removido = CommunityDeckLike::query()
            ->where('user_id', $usuario->id)
            ->where('community_deck_id', $deck->id)
            ->delete();

        if ($removido > 0 && $deck->likes_count > 0) {
            $deck->decrement('likes_count');
        }

        return ['likes_count' => $deck->fresh()->likes_count, 'curtido' => false];
    }

    public function copiar(User $usuario, int $id): array
    {
        $deck = $this->buscarPublicado($id);
        $cartas = $this->cartasDoDeck($deck);
        $this->assertPodeCopiar($usuario, $cartas);

        $novo = $this->deckService->create($usuario, $deck->nome.' (comunidade)', $cartas, false);
        $deck->increment('copies_count');

        return $novo;
    }

    public function importarPorElyCode(User $usuario, string $elyCode): array
    {
        $deck = CommunityDeck::query()
            ->where('ely_code', $this->normalizarElyCode($elyCode))
            ->whereNull('deleted_at')
            ->first();
        if (! $deck) {
            throw new InvalidArgumentException('Código ELY não encontrado ou expirado.');
        }

        return $this->copiar($usuario, $deck->id);
    }

    private function buscarPublicado(int $id): CommunityDeck
    {
        $deck = CommunityDeck::query()
            ->whereNull('deleted_at')
            ->where('id', $id)
            ->first();
        if (! $deck) {
            throw new InvalidArgumentException('Deck da comunidade não encontrado.');
        }

        return $deck;
    }

    private function registrarVisualizacao(User $viewer, CommunityDeck $deck): void
    {
        $criado = CommunityDeckView::query()->firstOrCreate(
            [
                'user_id' => $viewer->id,
                'community_deck_id' => $deck->id,
            ],
            ['viewed_at' => now()],
        );

        if ($criado->wasRecentlyCreated) {
            $deck->increment('views_count');
        }
    }

    /** @param  array<int, array{card_id: int, quantidade: int}>  $cartas */
    private function assertPodeCopiar(User $usuario, array $cartas): void
    {
        $maxDecks = (int) config('game.progression.decks.max_per_user');
        if ($usuario->decks()->count() >= $maxDecks) {
            throw new InvalidArgumentException("Você já tem {$maxDecks} decks. Exclua um para liberar um slot.");
        }

        $owned = $this->collection->ownedMap($usuario);
        $faltando = 0;
        foreach ($cartas as $linha) {
            $cardId = (int) $linha['card_id'];
            $necessario = (int) $linha['quantidade'];
            if (($owned[$cardId] ?? 0) < $necessario) {
                $faltando++;
            }
        }

        if ($faltando > 0) {
            throw new InvalidArgumentException('Você não possui todas as cartas deste deck na coleção.');
        }
    }

    /** @return array<int, array{card_id: int, quantidade: int}> */
    private function cartasDoDeck(CommunityDeck $deck): array
    {
        $deck->loadMissing('deckCards');

        return $deck->deckCards->map(fn (CommunityDeckCard $linha) => [
            'card_id' => $linha->card_id,
            'quantidade' => $linha->quantidade,
        ])->values()->all();
    }

    /** @param  list<string>  $tags */
    private function normalizarTags(array $tags): array
    {
        $permitidas = config('game.community_decks.allowed_tags', []);
        $limpas = [];
        foreach ($tags as $tag) {
            $tag = strtolower(trim((string) $tag));
            if ($tag !== '' && in_array($tag, $permitidas, true)) {
                $limpas[] = $tag;
            }
        }

        return array_values(array_unique($limpas));
    }

    private function gerarElyCodeUnico(): string
    {
        for ($tentativa = 0; $tentativa < 8; $tentativa++) {
            $parte = strtoupper(Str::random(4));
            $codigo = 'ELY-'.$parte.'-'.strtoupper(Str::random(4));
            if (! CommunityDeck::query()->where('ely_code', $codigo)->exists()) {
                return $codigo;
            }
        }

        throw new InvalidArgumentException('Não foi possível gerar código ELY. Tente novamente.');
    }

    private function normalizarElyCode(string $codigo): string
    {
        return strtoupper(trim(str_replace(' ', '', $codigo)));
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<CommunityDeck>  $query */
    private function aplicarFiltros($query, array $filtros): void
    {
        if (! empty($filtros['linhagem'])) {
            $query->where('linhagem_principal', strtolower((string) $filtros['linhagem']));
        }

        if (! empty($filtros['tag'])) {
            $tag = strtolower((string) $filtros['tag']);
            $query->whereJsonContains('tags', $tag);
        }

        if (! empty($filtros['streamer_only'])) {
            $query->where('is_streamer_deck', true);
        }

        if (! empty($filtros['recent'])) {
            $dias = (int) config('game.community_decks.recent_days', 7);
            $query->where('published_at', '>=', now()->subDays($dias));
        }

        if (! empty($filtros['criador'])) {
            $apelido = trim((string) $filtros['criador']);
            if ($apelido !== '') {
                $query->whereHas('user', fn ($consulta) => $consulta->where('nickname', $apelido));
            }
        }
    }

    /**
     * @param  array<int, int>  $ownedMap
     */
    private function formatarResumo(
        CommunityDeck $deck,
        ?User $viewer,
        string $versaoAtual,
        array $ownedMap,
        bool $incluirCartas,
    ): array {
        $deck->loadMissing(['user.avatar', 'deckCards.card']);

        $cartasPayload = [];
        $totalNecessario = 0;
        $totalPossui = 0;

        foreach ($deck->deckCards as $linha) {
            $card = $linha->card;
            $necessario = (int) $linha->quantidade;
            $possui = (int) ($ownedMap[$linha->card_id] ?? 0);
            $totalNecessario += $necessario;
            $totalPossui += min($possui, $necessario);

            if ($incluirCartas) {
                $cartasPayload[] = [
                    'card_id' => $linha->card_id,
                    'quantidade' => $necessario,
                    'nome' => $card?->nome,
                    'linhagem' => $card?->linhagem,
                    'raridade' => $card?->raridade,
                    'custo' => $card?->custo,
                    'ataque' => $card?->ataque,
                    'vida' => $card?->vida,
                    'imagem_path' => $card?->imagem_path,
                    'owned' => $possui,
                ];
            }
        }

        $canCopy = $viewer !== null
            && $totalPossui === $totalNecessario
            && $totalNecessario === (int) config('game.progression.decks.size')
            && $viewer->decks()->count() < (int) config('game.progression.decks.max_per_user');

        $curtido = false;
        if ($viewer) {
            $curtido = CommunityDeckLike::query()
                ->where('user_id', $viewer->id)
                ->where('community_deck_id', $deck->id)
                ->exists();
        }

        $compativelVersao = $deck->game_version === $versaoAtual;

        return [
            'id' => $deck->id,
            'nome' => $deck->nome,
            'descricao' => $deck->descricao,
            'linhagem_principal' => $deck->linhagem_principal,
            'tags' => $deck->tags ?? [],
            'game_version' => $deck->game_version,
            'game_version_atual' => $versaoAtual,
            'versao_desatualizada' => ! $compativelVersao,
            'ely_code' => $deck->ely_code,
            'is_streamer_deck' => $deck->is_streamer_deck,
            'likes_count' => $deck->likes_count,
            'views_count' => $deck->views_count,
            'copies_count' => $deck->copies_count,
            'published_at' => $deck->published_at?->toIso8601String(),
            'criador' => [
                'id' => $deck->user_id,
                'nickname' => $deck->user?->nickname,
                'is_content_creator' => (bool) ($deck->user?->is_content_creator),
                'avatar_slug' => $deck->user?->avatar?->slug,
                'avatar_image_file' => $deck->user?->avatar?->image_file,
                'profile_bg_slug' => $deck->user?->profile_bg_slug ?? 'padrao',
            ],
            'cartas_owned' => $totalPossui,
            'cartas_total' => $totalNecessario,
            'can_copy' => $canCopy,
            'curtido' => $curtido,
            'cartas' => $incluirCartas ? $cartasPayload : null,
        ];
    }
}

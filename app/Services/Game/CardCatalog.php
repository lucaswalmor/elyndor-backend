<?php

namespace App\Services\Game;

use App\Models\Card;
use Illuminate\Support\Facades\Cache;

/**
 * Catálogo de cartas com dois níveis de cache:
 *  1. In-memory (array estático) — zero queries dentro do mesmo request
 *  2. Laravel Cache (array serializado) — zero queries em requests subsequentes
 *
 * Armazena arrays simples em vez de Eloquent models para evitar problemas
 * de desserialização (__PHP_Incomplete_Class) com o driver de cache de arquivo.
 */
class CardCatalog
{
    private const CACHE_KEY = 'card_catalog_v1';
    private const CACHE_TTL = 1800; // 30 minutos

    /** Cache em memória — válido apenas no request atual */
    private static ?array $memory = null;

    public static function all(): array
    {
        if (self::$memory !== null) {
            return self::$memory;
        }

        // Tenta ler do cache persistente (array serializado — sem objetos Eloquent)
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached !== null) {
            self::$memory = $cached;
            return self::$memory;
        }

        // Cache frio: carrega do banco e transforma em array simples
        $cards = Card::with('skills')->get();
        $map   = [];

        foreach ($cards as $card) {
            $map[$card->id] = [
                'id'          => $card->id,
                'nome'        => $card->nome,
                'descricao'   => $card->descricao,
                'custo'       => $card->custo,
                'ataque'      => $card->ataque,
                'vida'        => $card->vida,
                'linhagem'    => $card->linhagem,
                'imagem_path' => $card->imagem_path,
                'skills'      => $card->skills->map(fn ($s) => [
                    'tipo'    => $s->tipo,
                    'gatilho' => $s->gatilho,
                    'efeito'  => $s->efeito,
                ])->toArray(),
            ];
        }

        Cache::put(self::CACHE_KEY, $map, self::CACHE_TTL);
        self::$memory = $map;

        return self::$memory;
    }

    /** Retorna os dados da carta como objeto anônimo (mantém compatibilidade com callers) */
    public static function get(int $cardId): ?object
    {
        $data = self::all()[$cardId] ?? null;
        if ($data === null) {
            return null;
        }

        return self::toCard($data);
    }

    /** Chama ao rodar seeders ou alterar cartas no banco */
    public static function flush(): void
    {
        self::$memory = null;
        Cache::forget(self::CACHE_KEY);
    }

    // ── Internos ────────────────────────────────────────────────────────────

    private static function toCard(array $data): object
    {
        $skills = array_map(fn ($s) => (object) $s, $data['skills'] ?? []);

        return new class($data, $skills) {
            public int    $id;
            public string $nome;
            public ?string $descricao;
            public int    $custo;
            public int    $ataque;
            public int    $vida;
            public ?string $linhagem;
            public ?string $imagem_path;
            public array  $skills;

            public function __construct(array $d, array $skills)
            {
                $this->id          = $d['id'];
                $this->nome        = $d['nome'];
                $this->descricao   = $d['descricao'] ?? null;
                $this->custo       = $d['custo'];
                $this->ataque      = $d['ataque'];
                $this->vida        = $d['vida'];
                $this->linhagem    = $d['linhagem'] ?? null;
                $this->imagem_path = $d['imagem_path'] ?? null;
                $this->skills      = $skills;
            }
        };
    }
}

# Arquitetura Laravel — Elyndor

Contrato de API: `../roadmap/api_contratos.md`  
Schema: `../roadmap/schema_banco.md`  
Fases de implementação: `../fases_desenvolvimento.md`

---

## Autenticação — Laravel Sanctum

- **SPA/API separada (Vue em `frontend/`):** tokens **Bearer** (`Authorization: Bearer {token}`).
- Instalado via `php artisan install:api` — rotas em `routes/api.php`, middleware `auth:sanctum`.
- Model `User` usa trait `Laravel\Sanctum\HasApiTokens`.
- Login/register criam token com `$user->createToken('api')->plainTextToken`.
- Logout: `$request->user()->currentAccessToken()->delete()`.

**Prefixo de rotas:** Laravel expõe `routes/api.php` em `/api/*`. Agrupar tudo em **`/api/v1`**:

```php
// routes/api.php
Route::prefix('v1')->group(function () {
    Route::post('/auth/register', ...);
    Route::middleware('auth:sanctum')->group(function () {
        // rotas protegidas
    });
});
```

Frontend: `VUE_APP_API_URL=http://localhost:8000/api/v1`

---

## Camadas e responsabilidades

Fluxo de uma requisição:

```
Request → Controller → FormRequest (validação)
         → Service (regra de negócio)
         → Model / Query
         → Resource (JSON de saída)
         → Response
```

| Camada | Responsabilidade | Não deve |
|--------|------------------|----------|
| **Controller** | HTTP: status code, chamar service, devolver Resource | Regra de combate, SQL complexo |
| **FormRequest** | Validar entrada (`rules`, `authorize`) | Lógica de jogo |
| **Resource** | Formato JSON estável para o Vue | Buscar dados pesados |
| **Service** | Orquestração e regras de negócio | Conhecer `Request` |
| **Model** | Eloquent, relações, casts, scopes | Motor de partida completo |
| **Game/** (classes puras) | Motor: dano, turno, efeitos (sem HTTP) | Acessar `Request` |

---

## Estrutura de pastas (`app/`)

```text
app/
├── Enums/
│   ├── Faccao.php
│   ├── Raridade.php
│   ├── MatchStatus.php
│   └── MatchActionType.php
│
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── V1/
│   │           ├── AuthController.php
│   │           ├── CardController.php
│   │           ├── DeckController.php
│   │           ├── MatchController.php
│   │           └── MatchmakingController.php
│   │
│   ├── Requests/
│   │   ├── Auth/
│   │   │   ├── RegisterRequest.php
│   │   │   └── LoginRequest.php
│   │   ├── Deck/
│   │   │   ├── StoreDeckRequest.php
│   │   │   └── UpdateDeckRequest.php
│   │   └── Match/
│   │       ├── PlayCardRequest.php
│   │       ├── AttackUnitRequest.php
│   │       └── EndTurnRequest.php
│   │
│   └── Resources/
│       ├── UserResource.php
│       ├── CardResource.php
│       ├── DeckResource.php
│       ├── MatchResource.php
│       └── MatchStateResource.php
│
├── Models/
│   ├── User.php
│   ├── Card.php
│   ├── CardSkill.php
│   ├── PlayerCard.php
│   ├── Deck.php
│   ├── DeckCard.php
│   ├── Match.php
│   ├── MatchPlayer.php
│   └── MatchLog.php
│
├── Services/
│   ├── Auth/
│   │   └── AuthService.php          # register, login, deck inicial
│   ├── Deck/
│   │   └── DeckService.php          # CRUD, validação 15 cartas, is_padrao
│   ├── Match/
│   │   ├── MatchStateService.php    # load/save estado JSON
│   │   └── MatchActionService.php   # invocar, atacar, fim de turno
│   ├── Matchmaking/
│   │   └── MatchmakingService.php   # fila, pareamento
│   └── Game/                        # motor puro (sem Eloquent)
│       ├── MatchEngine.php
│       ├── TurnResolver.php
│       ├── Combat/
│       │   └── CombatResolver.php
│       └── Effects/
│           ├── EffectInterface.php
│           └── ... (veneno, silêncio, etc.)
│
├── Events/                          # broadcast Reverb (Fase A+)
│   ├── MatchStarted.php
│   ├── ActionProcessed.php
│   ├── TurnChanged.php
│   └── MatchFinished.php
│
└── Policies/
    ├── DeckPolicy.php
    └── MatchPolicy.php
```

**Config de jogo (sem lógica):** `config/game/chests.php`, `config/game/progression.php`

**Seeders:** `database/seeders/CartasSeeder.php`, `AvataresSeeder.php`, `BausPoolsSemanalSeeder.php`, `ContasSubstitutasRanqueadaSeeder.php`, `DevRecargaCarteiraSeeder.php`, `ResetCompletoRanqueadaSeeder.php` (reset manual da ranked)

---

## Exemplo por feature (Auth)

```php
// AuthController@register
public function register(RegisterRequest $request): JsonResponse
{
    $result = $this->authService->register($request->validated());

    return (new UserResource($result['user']))
        ->additional(['token' => $result['token']])
        ->response()
        ->setStatusCode(201);
}
```

```php
// AuthService — deck inicial 13C+1R+1E, token Sanctum
public function register(array $data): array
{
    $user = User::create([...]);
    $this->starterDeckService->createFor($user);
    $token = $user->createToken('api')->plainTextToken;
    return ['user' => $user, 'token' => $token];
}
```

---

## Mapeamento Fase A → arquivos

| Entrega Fase A | Arquivos principais |
|----------------|---------------------|
| Auth Sanctum | `AuthController`, `RegisterRequest`, `LoginRequest`, `AuthService`, `UserResource` |
| Seed 30 cartas | `CartasSeeder`, models `Card`, `CardSkill` |
| Deck inicial | `AuthService` ou `StarterDeckService` |
| Partida | `MatchController`, `MatchActionService`, `MatchStateService`, `Game/MatchEngine` |
| Fila | `MatchmakingController`, `MatchmakingService` |
| Broadcast | `Events/*`, channels em `routes/channels.php` |

Fases B–E acrescentam pastas (`Deck/*`, `Chest/*`, etc.) sem mudar o padrão Controller → Request → Service → Resource.

---

## Convenções de código

- Controllers em `Api\V1` — uma versão explícita na URL.
- Services injetados no construtor do controller (`readonly`).
- Exceções de negócio: `App\Exceptions\GameRuleException` → HTTP 400 com `message`.
- Estado da partida: array/DTO validado em `Game/`, persistido por `MatchStateService`.
- Nomes de rotas: `matches.play-card`, `matchmaking.join`.

---

## O que não usar no MVP

- Lógica de combate em Controllers ou Resources.
- Breeze/Fortify para API (Sanctum + controllers próprios basta).
- Repository pattern obrigatório (Eloquent nos Services é suficiente no v1).

# Fase 2: Helpers PHP (design)

Data: 2026-11-27. Roteiro: `2026-11-27-atualizacao-do-boilerplate-roteiro.md`. Branch: `feature/fase-2-helpers`, criada a partir da `feature/fase-1-base-e-correcoes`, porque depende do PHP 8.2 e do `app/Helpers/` criados lá.

## Intenção

Trazer do mydaily os utilitários puros que todo projeto acaba reescrevendo: texto, arrays, números, máscaras, documentos brasileiros, datas, dinheiro e normalização de entrada. Assim, a validação (fase 3), as exportações (fase 4) e as views usam uma única fonte. A pergunta "isso já existe?" passa a ter resposta numa tabela do CLAUDE.md.

## Decisões já tomadas

- **Sem testes unitários para estes helpers** (decisão do dono, 2026-11-27, a mesma do mydaily, D-25). A garantia vem de três lugares:
  - `php -l`;
  - o teste de identificadores ASCII;
  - os testes das regras de validação da fase 3, que exercitam `Br\Cpf`, `Br\Cnpj`, `Br\Cep`, `Br\Phone` e as conversões de data, dinheiro e decimal.
- **CNPJ alfanumérico** (IN RFB 2.229/2024): 12 posições `A-Z0-9` mais 2 dígitos verificadores, sempre guardado como texto. O `Br\Cnpj` do mydaily já faz isso.
- **PHP 8.2:** o código vem do mydaily como está (8.2).
- **Nomes em inglês e sem acento.** O que vier em português é renomeado ao portar.

## Objetivos

1. `App\Helpers` com o conjunto listado abaixo, todo puro: sem sessão, sem banco, sem `header()`. A exceção é `e()`, que é função global.
2. Entrada brasileira convertida sem `float`: `"1.234,56"` → centavos, `"1.234,567"` → `"1234.567"`, `"24/11/2026"` → `"2026-11-24"`.
3. Saída brasileira: `R$ 1.234,56`, `1.234,567 kg`, `24/11/2026`, `24 de novembro de 2026`, CPF/CNPJ/CEP/telefone formatados e ocultação LGPD só na exibição.
4. `Core\Text`, `Core\Uuid` e `Core\Asset` passam para `App\Helpers`, com todos os usos atualizados e sem aliases.
5. Tabela "o que já existe" no CLAUDE.md.

## Não-objetivos

- `JsonResponse` (depende de `ServiceResult`), que vai para a fase 3.
- `UploadedFiles`, `FileName`, `ContentDisposition` e `Export\*`, que vão para a fase 4, junto com upload e exportação.
- O que é específico do mydaily e fica de fora: `Redirect`, `PageTitle`, `ChartData`, `Markdown`, `Csv` (legado), `Input::teamSchedule`, `Input::workdayHours` e `Input::daysOfWeek`.
- Máscaras e validação no navegador, que são da fase 6.

## Anti-objetivos

- Converter dinheiro, peso ou decimal passando por `float` em algum ponto do caminho de gravação.
- Helper que lê `$_POST` ou `$_SESSION` sozinho. O controller passa o valor.

## Conjunto

Origem `mydaily/app/Helpers/` salvo indicação. "Portar" significa copiar e ajustar namespace, comentários que citam o mydaily (equipe, reunião, spec) e nomes.

| Classe | Métodos | Nota |
|---|---|---|
| `Str` | os 34 do mydaily (`after` … `random`) | `slug()` delega a `Text::slugify()` |
| `Arr` | os 21 do mydaily (`get` … `isAssoc`) | |
| `Number` | `format`, `percentage`, `fileSize`, `abbreviate`, `parse`, `clamp` | formato BR, sem `intl` |
| `Text` | `slugify`, `truncate` (do `Core\Text` daqui) + `plainExcerpt` (mydaily) | substitui o `Core\Text` |
| `Mask` | `apply`, `remove` + constantes `CPF`, `CNPJ`, `CEP`, `PHONE`, `MOBILE` | o padrão `#` aceita letra para o CNPJ alfanumérico; conferir ao portar |
| `Lgpd` | `hide`, `cpf`, `cnpj`, `email`, `phone`, `name`, `cep`, `ip` | |
| `Br\Cpf` | `clean`, `isValid`, `format`, `checkDigits` | |
| `Br\Cnpj` | `clean`, `isValid`, `format`, `isAlphanumeric`, `checkDigits` | alfanumérico |
| `Br\Cep` | `clean`, `isValid`, `format` | |
| `Br\Phone` | `clean`, `isValid`, `isMobile`, `format` | |
| `Br\Date` | `parse(string $br): ?string` (`dd/mm/aaaa` → `Y-m-d`, `null` se inválida; aceita `Y-m-d` já normalizada), `format(?string $ymd): string` (`Y-m-d` ou `Y-m-d H:i:s` → `dd/mm/aaaa`, vazio → `''`) | **novo**, a partir de `Br::parseDate()`/`Br::date()` do lorencine |
| `DateOnly` | `normalize`, `shift`, `daysBetween` | |
| `DateFormat` | `long`, `numeric`, `short`, `shortRange` | |
| `LocalTime` | `isClockTime`, `toUtc`, `fromUtc` | usa `date.timezone`; conferir se depende do `AppTimezone` do mydaily e, se depender, ficar só com o fuso do `php.ini` |
| `Duration` | `label` | minutos → "1h30" |
| `MoneyFormat` | `format(int $cents, string $currency = 'BRL')` | `R$ 1.234,56`; o padrão BRL é novo |
| `Input` | `string`, `nullableString`, `nullableInt`, `accessEmail`, `isDigitsOrBlank`, `time`, `duration`, `money`, `cents` + **novos** `decimal(mixed $value, int $scale): ?string` (`"1.234,567"` → `"1234.567"`, sem float, `null` se inválido) e `date(mixed $value): ?string` (delega a `Br\Date::parse`) | sem os três métodos de equipe |
| `HtmlSanitizer` | `clean`, `isSafeLink` | lista branca de tags e links |
| `Color` | `hexOr` | |
| `Url` | `absolute` | a partir de `APP_URL` |
| `Uuid` | `v4` | movido de `Core\Uuid` |
| `Asset` | `version` (do `Core\Asset` daqui) + `url` (mydaily: caminho com `?v=filemtime`) | movido de `Core\Asset`; `asset_test.php` acompanha a mudança de namespace |
| `LogPath` | já existe (fase 1) | |
| `e()` | função global em `app/functions.php`: `htmlspecialchars` com `ENT_QUOTES \| ENT_SUBSTITUTE`, `null` → `''` | carregada por `public/index.php`, `scripts/migrate.php` e `scripts/test_install.php` logo após o autoloader |

Peso não ganha classe própria. Formatar é `Number::format($kg, 3) . ' kg'`, e ler é `Input::decimal($valor, 3)`. *(Decisão do Claude.)*

## Migração do que existe

- `App\Core\Text` → `App\Helpers\Text`. Usado em `app/Views/partials/site-top.php`.
- `App\Core\Uuid` → `App\Helpers\Uuid`. Usado em `UserRepository`, `Core\Model` e `scripts/tests/user_password_test.php`.
- `App\Core\Asset` → `App\Helpers\Asset`. Usado em `partials/site-top.php`, `admin/partials/head.php` e `scripts/tests/asset_test.php`.
- Os arquivos antigos em `app/Core/` são apagados. Um `grep` por `Core\\Text`, `Core\\Uuid` e `Core\\Asset` precisa voltar vazio.
- As views passam a usar `e()` no lugar de `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` **só quando forem tocadas por outro motivo**. Não há reescrita em massa.

## CLAUDE.md

- Seção "Helpers: o que já existe", com a tabela acima resumida: classe, para que serve e um exemplo de chamada. Antes de escrever tratamento de texto, data, número ou entrada à mão, procure ali.
- Regra: `App\Helpers` é puro, sem requisição, sem negócio e sem estado. Os helpers simples não têm teste unitário por decisão do dono.
- Regra: dinheiro em centavos (`int`); decimal e peso como string decimal no PHP e `DECIMAL` no banco, nunca `float` no caminho de gravação.
- Regra: CNPJ é texto alfanumérico, nunca número.
- Toda view nova escapa com `e()`.

## Verificação

- `php -l` (8.2) em cada arquivo novo ou alterado.
- Suíte verde como `www-data`. Isso inclui `asset_test`, `user_password_test` e `model_test` depois da mudança de namespace, e o `ascii_identifiers_test`.
- Smoke manual, sem teste versionado: um script descartável no scratchpad chama cada helper com um exemplo do design e confere a saída:
  - `Br\Cnpj::isValid('12.ABC.345/01DE-35')`;
  - `Input::money('1.234,56', 'BRL') === 123456`;
  - `Input::decimal('1.234,567', 3) === '1234.567'`;
  - `Br\Date::parse('24/11/2026') === '2026-11-24'`;
  - `MoneyFormat::format(123456) === 'R$ 1.234,56'`;
  - `Lgpd::cpf(...)`;
  - `e(null) === ''`.

  O script não é commitado.
- O site público (`/`) e o admin continuam renderizando (`curl` 200 nas páginas que usam `Asset` e `Text`).

## Decisões do Claude (confira ao ler)

- `Br\Date`, `Input::decimal` e `Input::date` são novos, porque o mydaily não tinha leitura de data BR nem de decimal sem float.
- Sem classe de peso: `Number` + `Input::decimal` resolvem.
- `MoneyFormat::format` ganha BRL como padrão.
- Helpers que dependem de outra camada (`JsonResponse`) ou que pertencem a uma fase futura (arquivos e exportação) ficam para depois.
- Mudança de namespace sem aliases: os poucos usos são atualizados no mesmo commit.

# Opisy procesów biznesowych – System Kart Podarunkowych

Poniżej opisano sześć kluczowych procesów biznesowych systemu. Każdy z nich
odwzorowano jako diagram BPMN 2.0 (plik `.bpmn`) możliwy do otwarcia
w Camunda Modeler lub draw.io i wyeksportowania do PNG/SVG.

---

## Diagram 1 – Rejestracja najemcy (`pl-01-rejestracja-najemcy.bpmn`)

### Uczestnicy (pule)
| Pula | Rola |
|------|------|
| Administrator Backoffice | Pracownik platformy inicjujący onboarding |
| Platforma GiftCard | System obsługujący kreator i logikę biznesową |
| Serwis Dokumentów | Komponent generowania i przechowywania PDF |
| Przedstawiciel Najemcy | Firma, której konto jest zakładane |

### Opis procesu
Proces rozpoczyna się, gdy administrator otwiera kreator rejestracji najemcy
(`/admin/tenants/wizard/step1`). Kreator składa się z czterech kroków:
w pierwszym administrator podaje dane firmy (nazwa, e-mail, NIP, telefon),
w drugim – adres i dane przedstawiciela, w trzecim następuje podgląd
wygenerowanej umowy współpracy w formacie PDF.

Po zatwierdzeniu danych platforma waliduje każde pole przy użyciu obiektów
wartości domeny (`TenantName`, `TenantEmail`, `NIP`, `PhoneNumber`,
`Address`, `RepresentativeName`). W przypadku błędu walidacji kreator
powraca do odpowiedniego kroku z komunikatem błędu. Po pozytywnej walidacji
system tworzy najemcę, generuje unikalny klucz API i sekret (`ApiKey`,
`ApiSecret`) oraz zleca serwisowi dokumentów wygenerowanie umowy współpracy.

Serwis dokumentów renderuje umowę za pomocą biblioteki Dompdf, zapisuje plik
w systemie plików i zwraca ścieżkę zapisu. Administrator przegląda umowę,
a jeśli dysponuje już podpisaną wersją, może ją opcjonalnie wgrać (PDF,
max 10 MB). Platforma weryfikuje format i rozmiar pliku, po czym zapisuje
dokument jako `SIGNED_COOPERATION_AGREEMENT` w tabeli `tenant_documents`.
Najemca otrzymuje potwierdzenie aktywacji konta.

### Wyjątki
- **Błąd walidacji danych** – kreator powraca do kroku z błędem; dane
  z poprzednich kroków są przechowywane w sesji HTTP.
- **Nieprawidłowy plik umowy** – system odrzuca plik niebędący PDF-em lub
  przekraczający limit 10 MB; administrator jest informowany komunikatem.

---

## Diagram 2 – Aktywacja karty przez klienta końcowego (`pl-02-aktywacja-karty-klienta.bpmn`)

### Uczestnicy (pule)
| Pula | Rola |
|------|------|
| Klient Końcowy | Posiadacz fizycznej karty podarunkowej |
| Portal Aktywacji | Publiczny system HTTP obsługujący formularz |
| Serwis Pocztowy | Komponent wysyłki wiadomości e-mail z kodem |
| System Kart (async) | Worker przetwarzający `ActivateCommand` przez RabbitMQ |

### Opis procesu
Klient końcowy (osoba fizyczna) odwiedza formularz aktywacji dostępny pod
publicznym adresem URL udostępnionym przez najemcę. Formularz wymaga podania
adresu e-mail, numeru karty oraz kodu PIN.

Portal aktywacji weryfikuje: poprawność formatu adresu e-mail
(`CustomerEmail`), istnienie karty o podanym numerze w modelu odczytu
(`gift_cards_read`), zgodność PIN-u z zapisanym hashem, status karty
(`INACTIVE`) oraz przynależność domeny URL powrotnego do listy dozwolonych
domen najemcy (`AllowedRedirectDomain`). Przy niepowodzeniu dowolnej
weryfikacji formularz jest ponownie wyświetlany z komunikatem błędu.

Po pozytywnej walidacji system tworzy encję `ActivationRequest`
z 15-minutowym czasem ważności i generuje sześciocyfrowy kod weryfikacyjny
(`VerificationCode`). Kod jest asynchronicznie wysyłany e-mailem przez serwis
pocztowy. Klient wpisuje kod w kolejnym kroku formularza.

System sprawdza czas ważności żądania (15 minut) i zgodność kodu. Po
pozytywnej weryfikacji klient widzi podsumowanie karty (saldo, data
ważności, e-mail). Po potwierdzeniu portal dyspatchuje `ActivateCommand`
do kolejki RabbitMQ. Worker przetwarza komendę synchronicznie: ładuje agregat
`GiftCard` z Event Store, wywołuje metodę `activate()`, zapisuje zdarzenie
`GiftCardActivated` do tabeli `events`. Portal tworzy encję `CardAssignment`
w tabeli `card_assignments`, oznacza `ActivationRequest` jako zakończony
i opcjonalnie wywołuje callback HTTP do systemu najemcy. Na końcu klient jest
przekierowany pod `return_url` z parametrami `status=success&card_id=...`.

### Wyjątki
- **Karta nie znaleziona / błędny PIN** – formularz z komunikatem błędu.
- **Karta w nieprawidłowym statusie** – np. `ACTIVE`, `CANCELLED` –
  komunikat o niemożności aktywacji.
- **Wygaśnięcie żądania (15 min)** – przekierowanie na stronę błędu
  z możliwością ponowienia procesu.
- **Błędny kod weryfikacyjny** – formularz z komunikatem; ponowna próba
  bez tworzenia nowego żądania.
- **Nieprawidłowa domena return_url** – odrzucenie formularza na etapie
  walidacji.

---

## Diagram 3 – Wydawanie kart podarunkowych (`pl-03-wydawanie-kart.bpmn`)

### Uczestnicy (pule)
| Pula | Rola |
|------|------|
| Administrator | Pracownik tworzący karty w panelu (`/admin/giftcards/issue`) |
| System Symfony | HTTP controller, walidacja, dispatch komend |
| Worker RabbitMQ | Asynchroniczny konsument przetwarzający `CreateCommand` |
| Serwis Dokumentów | Komponent generowania faktury PDF |

### Opis procesu
Administrator wypełnia formularz masowego wydawania kart: wybiera najemcę
(tylko aktywni), walutę, stawkę VAT i definiuje pozycje (ilość × kwota
na kartę). System weryfikuje poprawność formularza i brak pustych pozycji.

Dla każdej pozycji controller dyspatchuje do magistrali komunikatów
`N × CreateCommand` (gdzie N to podana ilość). Każda komenda jest kierowana
do transportu `async` (RabbitMQ, exchange: `gift_card_commands`). Worker
pobiera komendę z kolejki, wywołuje agregat `GiftCard::create()`, który
emituje zdarzenie `GiftCardCreated`. Zdarzenie zapisywane jest w tabeli
`events`, a następnie publikowane do fanout exchange `gift_card_events`.
Projekcja Read Model tworzy nowy wiersz w `gift_cards_read` ze statusem
`INACTIVE`. Tworzone są automatycznie numery kart i kody PIN.

Równolegle system dyspatchuje `GenerateInvoiceCommand`. Worker generuje
numer faktury w formacie `RRRR/MM/XXXX`, oblicza wartości netto, VAT
i brutto dla każdej pozycji, renderuje dokument PDF przez Dompdf i zapisuje
go jako `TenantDocument` typu `INVOICE` w tabeli `tenant_documents`.

Administrator otrzymuje potwierdzenie z liczbą utworzonych kart i informacją
o wygenerowaniu faktury.

### Wyjątki
- **Brak aktywnego najemcy** – formularz z komunikatem (walidacja przed
  dispatch).
- **Pusta lista pozycji** – walidacja formularza, powrót z błędem.
- **Błąd workera** – komenda trafia do kolejki `failed`
  (`messenger_messages`), Messenger ponawia 3 razy z wykładniczym
  opóźnieniem (exponential backoff).

---

## Diagram 4 – Realizacja środków z karty (`pl-04-realizacja-karty.bpmn`)

### Uczestnicy (pule)
| Pula | Rola |
|------|------|
| Klient API (Najemca) | System najemcy wywołujący REST API |
| API Controller | HTTP endpoint, uwierzytelnienie HMAC |
| Worker Async | Konsument RabbitMQ przetwarzający `RedeemCommand` |
| System Webhooków | Komponent wysyłki powiadomień do najemcy |

### Opis procesu
Najemca wywołuje `POST /api/gift-cards/{id}/redeem` z nagłówkami
uwierzytelniającymi (`X-Tenant-Id`, `X-Timestamp`, `X-Signature`).
API Controller weryfikuje poprawność sygnatury HMAC-SHA256 względem
`ApiSecret` najemcy oraz aktualność timestampu (okno 5 minut).
Przy błędzie uwierzytelnienia zwracana jest odpowiedź `401 Unauthorized`.

Po pozytywnym uwierzytelnieniu controller waliduje dane żądania
(identyfikator UUID karty, kwota > 0, poprawny kod waluty) i dyspatchuje
`RedeemCommand` do kolejki RabbitMQ. Klient API otrzymuje `202 Accepted`
z identyfikatorem śledzenia. Przetwarzanie odbywa się asynchronicznie.

Worker pobiera komendę, ładuje agregat `GiftCard` z Event Store przez
odtworzenie strumienia zdarzeń (`playhead`). Wywołuje metodę `redeem(Money)`:
weryfikowana jest zgodność waluty, status (`ACTIVE`), aktualność karty (data
ważności) oraz wystarczające saldo. Przy naruszeniu dowolnego warunku rzucany
jest odpowiedni wyjątek domenowy (`GiftCardNotActiveException`,
`InsufficientBalanceException`, `WrongGiftCardStatusException`), a komenda
ląduje w kolejce `failed`. W przypadku sukcesu emitowane jest zdarzenie
`GiftCardRedeemed`. Jeżeli saldo po realizacji wynosi 0, automatycznie
emitowane jest też `GiftCardDepleted` i status karty zmienia się na
`DEPLETED`.

Oba zdarzenia trafiają na fanout exchange. Projekcja Read Model aktualizuje
`balance_amount` i opcjonalnie ustawia `depleted_at`. Serwis webhooków
wysyła podpisane powiadomienie HTTP POST na skonfigurowany endpoint najemcy
z danymi zdarzenia i sygnaturą HMAC.

### Wyjątki
- **Błąd uwierzytelnienia HMAC** → `401 Unauthorized`.
- **Nieprawidłowe dane wejściowe** → `400 Bad Request` (walidacja).
- **Karta nieaktywna / zła waluta / niewystarczające saldo** → wyjątek
  domenowy, komenda w `failed`, Messenger ponawia 3 razy.
- **Webhook niedostępny** → logowany błąd, nie blokuje przetwarzania.

---

## Diagram 5 – Zarządzanie cyklem życia karty (`pl-05-cykl-zycia-karty.bpmn`)

### Uczestnicy (pule)
| Pula | Rola |
|------|------|
| Inicjator (API / Admin) | Klient API lub panel administratora |
| System Kart | Handler komend + agregat `GiftCard` |
| Worker Zdarzeń | Projekcje i webhooki (async, RabbitMQ) |

### Opis procesu
Diagram modeluje cztery operacje zmiany stanu karty poza normalnym
przepływem tworzenia i realizacji: zawieszenie, reaktywację, anulowanie
i wygasanie.

**Zawieszenie** (`SuspendCommand`): dostępne tylko dla kart `ACTIVE`.
Agregat wywołuje `suspend(reason, durationSeconds)`, emituje
`GiftCardSuspended`. Zdarzenie przechowuje czas trwania zawieszenia
(`suspensionDuration`), który zostanie użyty do korekty daty ważności
przy reaktywacji.

**Reaktywacja** (`ReactivateCommand`): dostępna tylko dla kart `SUSPENDED`.
Agregat wywołuje `reactivate(reason, reactivatedAt)`, oblicza nową datę
ważności: `expiresAt_nowa = expiresAt_stara + suspensionDuration`. Emituje
`GiftCardReactivated` z zaktualizowaną datą ważności. Projekcja czyści pole
`suspended_at`, aktualizuje `expires_at` i przywraca status `ACTIVE`.

**Anulowanie** (`CancelCommand`): dostępne dla kart `ACTIVE` lub `SUSPENDED`.
Agregat wywołuje `cancel(reason)`, emituje `GiftCardCancelled`. Status
zmienia się na `CANCELLED` — stan końcowy, bez możliwości dalszych operacji.

**Wygaśnięcie** (`ExpireCommand`): inicjowane przez komendę konsolową
`app:gift-card:expire-cards` lub ręcznie przez API. Dostępne tylko dla
kart `ACTIVE` z `expiresAt <= teraz`. Agregat wywołuje `expire(expiredAt)`,
emituje `GiftCardExpired`. Status zmienia się na `EXPIRED`.

We wszystkich czterech ścieżkach zdarzenie domenowe trafia na fanout
exchange, a Worker Zdarzeń aktualizuje model odczytu i opcjonalnie wysyła
webhook do najemcy.

### Wyjątki
- **Nieprawidłowy status karty dla danej operacji** →
  `WrongGiftCardStatusException`.
- **Próba wygaśnięcia karty z datą ważności w przyszłości** →
  `GiftCardNotExpiredException`.
- **Brak daty ważności przy wywołaniu expire** →
  `NoExpirationDateException`.

---

## Diagram 6 – Asynchroniczne przetwarzanie zdarzeń (`pl-06-przetwarzanie-asynchroniczne.bpmn`)

### Uczestnicy (pule)
| Pula | Rola |
|------|------|
| Aplikacja Symfony | Warstwa HTTP / handler komend |
| RabbitMQ | Broker wiadomości (exchange + kolejki) |
| Worker – Projekcja | Konsument aktualizujący `gift_cards_read` |
| Worker – Webhook | Konsument wysyłający powiadomienia HTTP |
| Kolejka DLQ | Transport `failed` (tabela `messenger_messages`) |

### Opis procesu
Diagram opisuje infrastrukturę asynchronicznego przetwarzania wspólną dla
wszystkich operacji domenowych.

Po obsłużeniu żądania HTTP aplikacja dyspatchuje komendę lub zdarzenie
do magistrali komunikatów Symfony Messenger. Komendy trafiają na transport
`async` (exchange `gift_card_commands`, routing direct), zdarzenia domenowe –
na transport `async_events` (exchange `gift_card_events`, routing fanout).

RabbitMQ dostarcza wiadomości do zarejestrowanych konsumentów.
Worker Projekcji odbiera każde zdarzenie domenowe, aktualizuje odpowiedni
wiersz w tabeli `gift_cards_read` i potwierdza odebranie wiadomości (ACK).
Worker Webhook odbiera te same zdarzenia (fanout), wyszukuje aktywne webhooki
najemcy w bazie danych, buduje podpisany ładunek JSON (HMAC-SHA256) i wysyła
żądanie HTTP POST do skonfigurowanego endpointu w timeout 10 sekund.

Jeżeli worker nie potwierdzi wiadomości (wyjątek), Messenger ponawia
próbę 3 razy z wykładniczym opóźnieniem (1 s, 2 s, 4 s). Po wyczerpaniu
prób wiadomość trafia do transportu `failed` (tabela `messenger_messages`)
z pełnym stack trace. Administrator może przejrzeć nieudane wiadomości
i ręcznie ponowić przetwarzanie komendą
`messenger:failed:retry` lub je odrzucić (`messenger:failed:reject`).

### Wyjątki
- **Timeout workera / wyjątek domenowy** – ponowienie 3×, następnie DLQ.
- **Niedostępność RabbitMQ** – Messenger przełącza się na synchroniczne
  przetwarzanie (konfiguracja środowiska testowego: `sync://`).
- **Webhook endpoint niedostępny** – NACK + retry; po 3 próbach wpis do DLQ,
  bez blokowania przetwarzania innych zdarzeń.

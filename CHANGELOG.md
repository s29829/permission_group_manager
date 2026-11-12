# Changelog - Permission Group Manager

Numeracja wtyczki: 1.00, 1.01, 1.02 … (+0.01 przy każdej zmianie, `tools/release.py bump`).
Wersja wtyczki w `permission_group_manager.yaml`; wersja całego repozytorium w pliku `VERSION` (zob. `CHANGELOG.md` w katalogu głównym).

## [1.23] - 2026-11-12
- Rozwijana ramka Reset plugin (jak w Custom CSS).

## [1.22] - 2026-11-12
- Nowa nazwa: Permission Group Manager (katalog permission_group_manager, wcześniej Group Permissions / groupperms); przy pierwszym użyciu kopiuje dane starej wtyczki (historia wersji, opisy własnych uprawnień) bez usuwania starego katalogu; strona opcji przypomina o wyłączeniu groupperms; nowe repozytorium s29829/permission_group_manager.

## [1.21] - 2026-11-11
- Porządki w historii zmian (bez zmian w działaniu wtyczki).

## [1.20] - 2026-11-11
- Dokumentacja: instalacja przez Plugin Manager albo CLI (curl, unzip, chown) bez skryptów bash; wydania zawierają tylko archiwum zip.

## [1.19] - 2026-11-11
- Dane wtyczki (historia wersji, opisy własnych uprawnień) w <filestore>/permission_group_manager/data_<klucz>/; automatyczne przeniesienie z <filestore>/permission_group_manager_versions_<klucz>/; opcja „Reset plugin” (usuwa dane wtyczki, uprawnień grup nie zmienia).

## [1.18] - 2026-11-11
- Eksport i import grup użytkowników (strona pages/transfer.php): eksport wybranych grup do JSON (nazwa, grupa nadrzędna, dziedziczenie, uprawnienia); import z przeglądem – wybór nowych grup do dodania, dla istniejących grup z innymi uprawnieniami wybór: zostaw istniejące / użyj z importu / pokaż różnice (tabela: w systemie, w imporcie, wynik – wstępnie zaznaczone zgodne uprawnienia, różnice wyróżnione i niezaznaczone); stan przed importem w historii wersji.

## [1.17] - 2026-11-11
- Zmiany w dokumentacji i narzędziach wydawniczych (bez zmian w działaniu wtyczki).

## [1.16] - 2026-11-11
- Zmiany w dokumentacji i narzędziach wydawniczych (bez zmian w działaniu wtyczki).

## [1.15] - 2026-11-11
- Samodzielny pakiet zip (release.py package): plik LICENSE-COMMERCIAL.md w katalogu wtyczki, licencja i dokumentacja w paczce; instrukcja instalacji z archiwum zip.

## [1.14] - 2026-11-11
- Licencja PolyForm Noncommercial 1.0.0 (plik LICENSE; użycie komercyjne tylko za pisemną zgodą autora); sekcja „Licencja” w dokumentacji.

## [1.13] - 2026-11-11
- Dokumentacja w katalogu wtyczki: README.md (EN) i README.pl.md (PL), ze znacznikami wersji, od której dostępna jest każda funkcja.

## [1.12] - 2026-11-10
- Wyraźny odstęp (z cienką linią) przed polami „Columns” / „Page” w panelu grup.
- Kolumna „Select”: małe przyciski „All” / „None” w wersji outline (szara ramka, mniejsza czcionka, bez niebieskiego koloru).

## [1.11] - 2026-11-10
- Panele filtrów połączone z tabelą (bez przerwy i zaokrągleń); cienka linia pod wierszem nagłówka zamiast pogrubionej.
- Nazwy grup w nagłówkach skrócone do 20 znaków (pełna nazwa w dymku); 10, 15 lub 20 grup na stronę.
- Lista „Page” tylko z zakresami liczbowymi (1-10, 11-20 …); więcej miejsca w panelu grup.
- Kolumna „Row” → „Select” (ikony zamiast przycisków „All” / „None”).

## [1.10] - 2026-11-10
- Poprawka błędu „Sorry, an error has occurred”: odczyt nieistniejącego jeszcze pliku `custom_codes.json` przez `@file_get_contents()` wywoływał stronę błędu ResourceSpace (jego `errorhandler` reaguje także na ostrzeżenia wyciszone `@`). Usunięte wszystkie wywołania z `@` – pliki i katalogi są sprawdzane przed użyciem.

## [1.09] - 2026-11-10
- Własne uprawnienia jako wiersze tabeli: każdy kod spoza katalogu to osobny wiersz z polami wyboru, edytowalnym opisem i linkiem „remove”; nowy wiersz dodaje się na końcu sekcji „Custom permissions” (opis + kod). Opisy w `custom_codes.json`, zapisywane w historii wersji. Kody `j<ref>` / `-j<ref>` dostają domyślny opis z nazwą featured collection.
- Przyciemnione tło także w nagłówkach „Permission” i „Code”; pod „Permission” przełączniki „Sections: open all / close all”.

## [1.08] - 2026-11-10
- Przyciemnione tło w kolumnie „Code” i w panelu wyszukiwania uprawnień; przywrócone przyciski « » obok listy „Page”; „Show only: differing / unsaved” w jednej linii.

## [1.07] - 2026-11-10
- Lekko przyciemnione tło kolumny z nazwami uprawnień; „Show rows” jako pola wyboru; link „Open / Close all sections” w nagłówku „Permission”.

## [1.06] - 2026-11-10
- Pola filtrów jak w innych formularzach ResourceSpace (`input type="text"`, styl motywu), etykieta nad każdym polem.
- Mniej przycisków: lista „Show rows”, jeden link otwierający / zamykający sekcje, ikonka × w polach (oraz Esc), lista „Page” zamiast « ».

## [1.05] - 2026-11-10
- Kolory paneli filtrów: niebieski (uprawnienia) i szary (grupy); panel uprawnień ma dokładnie szerokość kolumn „Permission” + „Code”; kolumna „Shown groups” → „Row”.

## [1.04] - 2026-11-10
- Filtry nad tabelą w dwóch podpisanych panelach: „Filter permissions (table rows)” i „Filter user groups (table columns)”, z licznikami pokazanych wierszy / grup.
- Kolumny grup o szerokości dopasowanej do nazwy, kolumna „Shown groups” o stałej szerokości.

## [1.03] - 2026-11-10
- Uprawnienia wtyczek ResourceSpace jako osobne sekcje (tylko dla aktywnych wtyczek; kody i warunki jak w hookach `additionalperms`): Workflow actions (`wf<id>`), Brand guidelines (`bgv`, `bge`), AI smart search (`clip-sb`, `clip-v`), Consent management (`cm`), AI faces (`faces-v`), License management (`lm`).
- Pole tekstowe „Custom permissions” dla każdej grupy (zamiast sekcji tylko do odczytu).

## [1.02] - 2026-11-09
- Historia wersji (jak w Custom CSS): „Save changes” zapisuje poprzedni stan uprawnień, „Save as new version” tworzy kolejny numer; „Load into table”, „Restore this version”, „Delete this version”, „Delete all history”; ostatnie 50 wpisów jako pliki JSON w `<filestore>/permission_group_manager_versions_<klucz>`. Pierwsza wersja (v1) powstaje po aktywacji wtyczki (hook `initialise`).

## [1.01] - 2026-11-09
- Poprawki układu: tło tabeli odświeża się po przełączeniu trybu jasny / ciemny; zwykła pisownia nazw (bez kapitalików); kody w osobnej kolumnie „Code”; przyciski „All” / „None” na końcu wiersza; wyszukiwanie nad kolumnami; kopiowanie uprawnień pod tabelą; sekcje rozwijane kliknięciem w nagłówek.

## [1.00] - 2026-11-09
- Pierwsza wersja: macierz uprawnień – wiersze to uprawnienia (sekcje jak na stronie uprawnień grupy w ResourceSpace 11, etykiety z `$lang`), kolumny to grupy użytkowników; stronicowanie kolumn, filtry, kopiowanie uprawnień między grupami; zapis przez `save_usergroup()` + `log_activity()`; ochrona przed odebraniem własnej grupie `t` / `a`.

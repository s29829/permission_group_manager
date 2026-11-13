# Permission Group Manager – dokumentacja

[English](README.md) | **Polski**

Dokumentacja dla wersji wtyczki **1.24** (ResourceSpace 11).
Znacznik *(od X.YY)* oznacza wersję wtyczki, od której funkcja jest dostępna. Funkcje bez
znacznika są dostępne od wersji 1.00. Historia zmian: [CHANGELOG.md](CHANGELOG.md).

Wtyczka pokazuje uprawnienia **wszystkich grup użytkowników w jednej tabeli**. Wiersze to
uprawnienia, kolumny to grupy. Zamiast otwierać stronę uprawnień każdej grupy osobno, można
zaznaczać pola w tabeli, porównywać grupy, kopiować uprawnienia między nimi i wracać do
wcześniejszych wersji.

**Gdzie:** System > Plugins > Permission Group Manager > **Options**. Strona jest dostępna tylko
dla administratorów (uprawnienie `a`).


**Nazwa** *(od 1.22)*: do wersji 1.21 wtyczka nazywała się **Group Permissions** (katalog
`groupperms`). Dla ResourceSpace to inna wtyczka: zainstaluj `permission_group_manager`, aktywuj
ją, a potem wyłącz i usuń `groupperms` (strona opcji przypomina o tym, dopóki stara wtyczka jest
aktywna). Przy pierwszym użyciu wtyczka kopiuje dane starej (historię wersji i opisy własnych
uprawnień); stary katalog `<filestore>/groupperms` zostaje. Uprawnienia grup zapisuje
ResourceSpace – nie zmieniają się.

---

## Tabela uprawnień

- **Wiersze** mają te same sekcje i kolejność co strona uprawnień grupy w ResourceSpace 11.
  Nazwy pochodzą z tłumaczeń ResourceSpace, więc są w języku użytkownika.
- **Code** *(osobna kolumna: od 1.01)* – kod uprawnienia zapisywany w bazie (np. `t`, `a`, `e0`).
- **Pole zaznaczone** oznacza, że grupa ma uprawnienie.
- **↺ (opcje odwrócone)** – jak w ResourceSpace: zaznaczenie oznacza, że kod **nie** jest
  ustawiony.
- **Szare pola** nie dotyczą danej grupy, bo wymagają innego uprawnienia (np. najpierw dostępu
  do panelu administracyjnego `t`).
- **Niebieska ramka** oznacza zmianę jeszcze niezapisaną.
- **Grupy dziedziczące uprawnienia** po grupie nadrzędnej są tylko do odczytu (wyszarzone).
- **Sekcje** zwija się i rozwija kliknięciem nazwy *(od 1.01)*. Pod nagłówkiem „Permission” są
  przełączniki „Sections: open all / close all” *(od 1.09)*.
- **Select > All / None** na końcu wiersza zaznacza lub odznacza uprawnienie we wszystkich
  pokazanych grupach *(od 1.01; kolumna „Select” od 1.11)*.
- **Nazwy grup** w nagłówkach są skrócone do 20 znaków, pełna nazwa jest w dymku *(od 1.11)*.

### Sekcje wtyczek *(od 1.03)*

Uprawnienia aktywnych wtyczek ResourceSpace mają własne sekcje oznaczone „plugin”, z tymi samymi
kodami i warunkami co w samych wtyczkach:

- Workflow actions (`wf<id>`),
- Brand guidelines (`bgv`, `bge`),
- AI smart search (`clip-sb`, `clip-v`),
- Consent management (`cm`),
- AI faces (`faces-v`),
- License management (`lm`).

### Custom permissions – własne uprawnienia *(od 1.09)*

Na końcu tabeli są wiersze dla kodów spoza katalogu, np. uprawnień innych wtyczek albo dostępu
do kategorii featured collections (`j<ref>`, `-j<ref>`).

- Każdy taki kod to osobny wiersz z polami wyboru, **edytowalnym opisem** i linkiem **remove**.
- **Dodawanie:** w ostatnim wierszu wpisz opis i kod, potem kliknij **Add permission row**.
- **remove** usuwa wiersz i odznacza kod we wszystkich grupach (po zapisie).
- Kody `j<ref>` / `-j<ref>` dostają domyślny opis z nazwą kolekcji.
- W wersjach 1.03–1.08 własne kody wpisywało się w jedno pole tekstowe dla każdej grupy.

## Filtry nad tabelą

Filtry są w dwóch podpisanych panelach z licznikami *(od 1.04)*.

**Permissions (table rows)** – które wiersze są widoczne:

- **Search permissions** – szukanie po nazwie lub kodzie. Pole czyści × albo Esc *(od 1.06)*.
- **Show only: differing** – tylko wiersze, w których pokazane grupy mają różne ustawienia
  *(jako pole wyboru: od 1.07)*.
- **Show only: unsaved** – tylko wiersze z niezapisanymi zmianami *(jako pole wyboru: od 1.07)*.

**User groups (table columns)** – które grupy są widoczne:

- **Search groups** – szukanie po nazwie grupy,
- **Parent group** – tylko podgrupy wybranej grupy,
- **Columns** – 10, 15 lub 20 grup na stronę *(od 1.11)*,
- **Page** – zakres pokazanych grup: 1-10, 11-20 … *(od 1.11)*, z przyciskami « » *(od 1.08)*.

## Zapisywanie

| Przycisk | Działanie |
|---|---|
| **Save changes** | Zapisuje zmienione grupy. Od wersji 1.02 poprzedni stan wszystkich grup trafia do historii wersji. |
| **Save as new version** *(od 1.02)* | Zapisuje zmiany (jeśli są) i zaczyna kolejny numer wersji. |
| **Discard changes** | Cofa wszystkie niezapisane zmiany w tabeli. |

- Zapis używa tych samych funkcji co ResourceSpace (`save_usergroup`), a zmiana jest odnotowana
  w dzienniku aktywności.
- Zmiany są nakładane na **aktualny** stan w bazie. Kody, których nie ma w tabeli, nie są
  usuwane.
- **Twoja własna grupa nigdy nie traci** dostępu do panelu administracyjnego (`t`) ani
  ustawień systemu (`a`). Chroni to przed odcięciem sobie dostępu.
- Wyjście ze strony z niezapisanymi zmianami trzeba potwierdzić.

## Copy permissions – kopiowanie między grupami

Wybierz **From** (grupę źródłową) i **to** (grupę docelową), potem kliknij **Copy**. Kopiowane są
wszystkie uprawnienia z tabeli, łącznie z wierszami własnych uprawnień. Zmiana jest widoczna
w tabeli, ale zapisuje się dopiero po **Save changes**. Grup dziedziczących nie można wybrać jako
celu.

## Version history *(od 1.02)*

- **Pierwsza wersja (v1)** powstaje automatycznie po aktywacji wtyczki i zawiera uprawnienia
  wszystkich grup.
- Każde **Save changes** zapisuje poprzedni stan pod bieżącym numerem. **Save as new version**
  zaczyna kolejny numer. Przechowywanych jest 50 ostatnich wpisów.
- **Load into table** – pokazuje wybraną wersję w tabeli (różnice w niebieskiej ramce). Zostanie
  zastosowana dopiero po **Save changes**.
- **Restore this version** – od razu przywraca całą wersję, także kody spoza tabeli.
- **Delete this version** – usuwa wszystkie pliki o danym numerze.
- **Delete all history** – usuwa historię poza najnowszym wpisem.

Przy Load i Restore bieżący stan zostaje w historii, więc każdą operację można cofnąć.

## Eksport i import grup użytkowników *(od 1.18)*

**Gdzie:** strona wtyczki > **Export / import user groups** (pod tabelą). Strona ma własny
adres `pages/transfer.php`.

### Eksport

Zaznacz grupy (domyślnie wszystkie) i kliknij **Export**. Powstaje plik
`permission_group_manager-groups-<data>-<godzina>.json`, a dla każdej grupy zapisywane są:
- nazwa,
- grupa nadrzędna (jej nazwa),
- ustawienia dziedziczone po grupie nadrzędnej (`inherit_flags`),
- lista kodów uprawnień.

Plik można zaimportować w innej instalacji ResourceSpace z tą wtyczką. **Grupy dopasowywane są
po nazwie** (bez rozróżniania wielkości liter), bo numery grup są różne w każdej instalacji.

### Import

1. Wybierz plik eksportu i kliknij **Check file**. Na tym etapie nic nie jest zmieniane.
2. Strona przeglądu dzieli grupy z pliku na trzy części:
   - **Nowe grupy** (nie ma ich w systemie):
     - jedna nowa grupa: pytanie „Add it?” z polem wyboru,
     - kilka nowych grup: lista z polem wyboru przy każdej (domyślnie zaznaczone) oraz „select
       all / none”.

     Widać też grupę nadrzędną, dziedziczenie i liczbę uprawnień („show” pokazuje kody).
   - **Istniejące grupy z innymi uprawnieniami** – dla każdej do wyboru:
     - *Keep the existing permissions* – zostawić uprawnienia z systemu (domyślnie),
     - *Use the permissions from the import* – użyć uprawnień z pliku,
     - *Show the differences and choose* – pokazuje tabelę uprawnień z kolumnami: nazwa
       uprawnienia, kod, **In the system**, **In the import** i **Result** (pole wyboru).
       - **Result** jest wstępnie zaznaczone tam, gdzie system i import się zgadzają.
       - **Wiersze z różnicą są wyróżnione, a ich pole Result zostaje niezaznaczone** (brak
         uprawnienia). Zaznacz je, żeby nadać uprawnienie.
       - Skróty: „use all from import”, „use all from system”, „show only differences”.
   - **Istniejące grupy z takimi samymi uprawnieniami** – tylko lista, nic nie trzeba robić.
3. **Import** wykonuje wybrane zmiany (z potwierdzeniem), a **Cancel** kończy bez zmian.
   Plik czeka na decyzję maksymalnie godzinę.

**Szczegóły importu:**
- Przed importem uprawnienia wszystkich grup trafiają do **historii wersji** wtyczki. „Restore
  this version” je przywraca, ale nie usuwa grup utworzonych przez import.
- Nowe grupy powstają tą samą funkcją co w ResourceSpace (`save_usergroup`), z wpisem
  w dzienniku aktywności.
- **Grupa nadrzędna:** jeśli istnieje w systemie albo jest importowana z tego samego pliku,
  nowa grupa staje się jej podgrupą (najpierw tworzona jest grupa nadrzędna). W przeciwnym razie
  nowa grupa trafia na najwyższy poziom, bez dziedziczenia.
- **Własna grupa** nigdy nie traci dostępu do panelu administracyjnego (`t`) ani ustawień systemu
  (`a`).
- Grupy dziedziczące uprawnienia są oznaczone: ich własne uprawnienia zostaną zapisane, ale nie
  działają, dopóki dziedziczenie jest włączone.
- Nieprawidłowe kody i powtórzone nazwy grup w pliku są pomijane, a strona przeglądu to pokazuje.

## Zasady korzystania

1. **Przy większych zmianach zacznij od „Save as new version”.** Masz wtedy wyraźny punkt
   powrotu z własnym numerem.
2. **Porównując grupy, włącz „differing”.** Zobaczysz tylko wiersze, w których grupy się różnią.
3. **Przed zapisem zaznacz „unsaved”**, żeby sprawdzić, co dokładnie się zmieni. Licznik obok
   przycisków pokazuje liczbę zmian.
4. **Grup dziedziczących nie zmienia się w tej tabeli.** Zmień grupę nadrzędną albo wyłącz
   dziedziczenie na stronie grupy w ResourceSpace.
5. **Własne kody wpisuj dokładnie tak, jak oczekuje ich ResourceSpace lub wtyczka.** Kod jest
   zapisywany bez sprawdzania znaczenia, sprawdzany jest tylko format.

## Instalacja i pliki na serwerze

### Pobranie i instalacja

Wtyczka jest dystrybuowana jako archiwum `permission_group_manager-<wersja>.zip` na stronie wydań:
<https://github.com/s29829/permission_group_manager/releases>. Archiwum zawiera katalog `permission_group_manager/` z kodem, licencją
i dokumentacją. Poniżej `<RS_ROOT>` to katalog instalacji ResourceSpace (np.
`/var/www/resourcespace`), a `www-data:www-data` to użytkownik i grupa, które uruchamiają PHP – przy Apache w RHEL, Rocky, Alma
czy Fedorze `apache:apache` (zob. „nginx czy Apache” niżej).

**Przez przeglądarkę:** Plugin Manager > Upload a plugin > wybierz plik `.zip` > Upload (przy
aktualizacji: **Overwrite**).

**Z wiersza poleceń (CLI)** – pobranie, rozpakowanie i uprawnienia:

```bash
VER=<wersja>                     # numer wersji ze strony wydań, np. 1.00
cd /tmp
curl -fLO https://github.com/s29829/permission_group_manager/releases/download/v$VER/permission_group_manager-$VER.zip
sudo rm -rf <RS_ROOT>/plugins/permission_group_manager        # tylko przy aktualizacji (dane wtyczki są w filestore)
sudo unzip -q permission_group_manager-$VER.zip -d <RS_ROOT>/plugins/
sudo chown -R www-data:www-data <RS_ROOT>/plugins/permission_group_manager
sudo find <RS_ROOT>/plugins/permission_group_manager -type d -exec chmod 755 {} \;
sudo find <RS_ROOT>/plugins/permission_group_manager -type f -exec chmod 644 {} \;
```

**Przez git:** repozytorium <https://github.com/s29829/permission_group_manager> zawiera pełny katalog wtyczki:

```bash
sudo git clone https://github.com/s29829/permission_group_manager <RS_ROOT>/plugins/permission_group_manager
sudo chown -R www-data:www-data <RS_ROOT>/plugins/permission_group_manager
```

Aktualizacja: `sudo git -C <RS_ROOT>/plugins/permission_group_manager pull`.

### nginx czy Apache

Wtyczka działa tak samo z oboma serwerami WWW. Różnią się tylko użytkownik uruchamiający PHP, nazwy
usług i kilka ustawień serwera – w poleceniach wyżej użyj użytkownika i grupy z tabeli:

| | nginx + PHP-FPM (Ubuntu / Debian) | Apache (Ubuntu / Debian) | Apache (RHEL, Rocky, Alma, Fedora) |
|---|---|---|---|
| Użytkownik:grupa uruchamiające PHP | `www-data:www-data` | `www-data:www-data` | `apache:apache` |
| Przeładowanie po aktualizacji | `sudo systemctl restart php8.3-fpm` | mod_php: `sudo systemctl reload apache2`; PHP-FPM: `sudo systemctl restart php8.3-fpm` | mod_php: `sudo systemctl reload httpd`; PHP-FPM: `sudo systemctl restart php-fpm` |

- **Kto uruchamia PHP:** przy Apache z mod_php – użytkownik Apache; przy PHP-FPM (nginx lub Apache) –
  użytkownik puli PHP-FPM (`user =` / `group =` w `/etc/php/<wersja>/fpm/pool.d/www.conf`, w rodzinie RHEL
  `/etc/php-fpm.d/www.conf`). Sprawdzenie: `ps -o user= -C php-fpm8.3,php-fpm,apache2,httpd | sort -u`.
- **Uprawnienia:** katalogi `755`, pliki `644`, właściciel = ten użytkownik i grupa (polecenia wyżej).
  Pliki wtyczki muszą być tylko czytelne; `<filestore>` musi być zapisywalny dla tego użytkownika – tak
  samo, jak wymaga tego ResourceSpace.
- **Bez listowania katalogów (Apache):** Apache w Ubuntu / Debian pozwala listować katalogi w `/var/www`
  (`Options Indexes` w `apache2.conf`). Wtyczka trzyma ustawienia w `<filestore>/<wtyczka>/data_<klucz>/`,
  a listing pokazałby nazwę tego katalogu. Wyłącz listowanie dla ResourceSpace (VirtualHost albo
  `apache2.conf` / `httpd.conf`) i przeładuj Apache:

  ```apache
  <Directory <RS_ROOT>>
      Options -Indexes +FollowSymLinks
  </Directory>
  ```

  nginx nie listuje katalogów, jeśli nie ustawiono `autoindex on`.
- **SELinux (rodzina RHEL):** po rozpakowaniu przywróć etykiety plików:
  `sudo restorecon -R <RS_ROOT>/plugins/permission_group_manager`. Filestore potrzebuje etykiety do zapisu (zwykle ustawionej
  przy instalacji ResourceSpace): `sudo semanage fcontext -a -t httpd_sys_rw_content_t "<filestore>(/.*)?"`
  i `sudo restorecon -R <filestore>`.

Na koniec: System > Plugins, aktywuj „Permission Group Manager” i kliknij **Options**. Wtyczka sama tworzy
swój katalog danych `<filestore>/permission_group_manager` – potrzebuje tylko prawa zapisu do filestore, które
ResourceSpace i tak wymaga.

### Pliki na serwerze

Wszystko, co zapisuje wtyczka, jest w `<filestore>/permission_group_manager/` *(od 1.22)*, w podkatalogu
`data_<klucz>` (klucz wynika z `scramble_key`, więc plików nie da się odczytać, zgadując adres):

- `v<N>_<data>_<godz>.json` – stan uprawnień wszystkich grup,
- `current_version.txt` – bieżący numer wersji,
- `custom_codes.json` – opisy wierszy „Custom permissions” *(od 1.09)*.

`<filestore>` to katalog `$storagedir` ResourceSpace (domyślnie `<RS_ROOT>/filestore`). Aktualizacja ani usunięcie kodu wtyczki nie usuwa tych plików. Wersje 1.19–1.21 (`groupperms`) zapisywały dane w
`<filestore>/groupperms/`, wersje 1.02–1.18 w `<filestore>/groupperms_versions_<klucz>/` – wersja 1.22 kopiuje je automatycznie.

**Katalogi wtyczka tworzy sama** przy pierwszym użyciu (aktywacja, otwarcie strony opcji).
Potrzebuje tylko prawa zapisu do filestore, które ResourceSpace i tak wymaga.

## Reset wtyczki *(od 1.19)*

Panel jest zwinięty – kliknij tytuł, aby go rozwinąć *(od 1.23)*; po nieudanym resecie otwiera się sam.

Panel **Reset plugin** na stronie wtyczki (po wpisaniu `RESET`) usuwa wszystko, co wtyczka
zapisała w `<filestore>/permission_group_manager`: historię wersji i opisy własnych uprawnień. **Uprawnienia
grup użytkowników nie są zmieniane** – bieżący stan staje się znowu wersją v1. Tej operacji nie
da się cofnąć.

## Licencja *(od 1.14)*

- Wtyczka jest udostępniona na licencji **PolyForm Noncommercial License 1.0.0** (plik
  [LICENSE](LICENSE)).
- **Bezpłatnie** można jej używać prywatnie i w celach niekomercyjnych, a także w organizacjach
  niekomercyjnych (edukacja, instytucje publiczne, organizacje charytatywne). Można ją zmieniać
  i udostępniać w tych celach, z zachowaniem tekstu licencji.
- **Użycie komercyjne** (firma, usługi dla klientów, płatny produkt) wymaga pisemnej zgody
  autora: <https://github.com/s29829>. Szczegóły: `LICENSE-COMMERCIAL.md` w repozytorium.

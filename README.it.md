# NHS - No Half Sends

Lingue: [English](README.md) | [Italiano](README.it.md)

No Half Sends e' un social network orientato allo sport in cui gli utenti registrati possono pubblicare attivita', seguire altri atleti, iscriversi a club sportivi e leggere consigli su allenamento, nutrizione e recupero.

Il nome nasce dall'idea di non fare mai le cose a meta'.

## Funzionalita' principali

- Registrazione e login degli utenti
- Profilo atleta con immagine, descrizione, utenti seguiti, follower, sport praticati, statistiche e attivita' recenti
- Feed con le attivita' dell'utente e degli atleti seguiti
- Dati specifici per attivita' di corsa, ciclismo, nuoto, sci, palestra ed escursionismo
- Foto, like, commenti e modifica delle attivita'
- Sistema di follow/unfollow tra utenti
- Atleti suggeriti nel feed
- Club divisi per sport, con ruoli membro/admin e modifica del club
- Pagina advice con like, commenti, foto e creazione/modifica riservata agli admin
- Interfaccia responsive con navigazione e stile condivisi

## Framework personalizzato

Il progetto usa un framework PHP leggero basato su `.htaccess`, `pages.json` e `menuChoice.php`.

Il file `.htaccess` usa `auto_prepend_file` per caricare `include/menu/menuChoice.php` prima della maggior parte delle pagine PHP:

```apache
php_value auto_prepend_file "/XAMPP/htdocs/projects/NoHalfSends/include/menu/menuChoice.php"
```

`menuChoice.php` legge `include/pages.json` e decide quali elementi servono a ogni pagina:

- `loggedInPages`: pagine che richiedono un utente autenticato
- `DBPages`: pagine che richiedono il database handler
- `userpages`: pagine che usano la navbar dell'utente loggato
- `homeOnly`: pagine pubbliche che usano la navbar pubblica
- `adminpages`: pagine riservate agli admin

Questo permette di evitare sessioni, controlli di login, connessioni al database e navigazione ripetuti manualmente in ogni pagina.

## Controllo accessi

Le pagine inserite in `loggedInPages` sono protette tramite `include/header.php`, che avvia la sessione, include il database handler e verifica l'autenticazione tramite `include/loggedIn.php`.

Le pagine che richiedono solo il database, come login e register, sono inserite in `DBPages`.

Le funzionalita' admin vengono gestite nelle pagine interessate. Per esempio, i controlli per creare e modificare advice sono visibili solo agli utenti con ruolo `admin`.

## Database

Lo schema del database si trova in `nohalfsends.sql`.

Le tabelle principali includono:

- `User`
- `Sport`
- `Club`
- `UserClub`
- `SportUser`
- `Activity`
- Tabelle di specializzazione sportiva come `Run`, `Cycling`, `Swimming`, `Ski`, `Gym` ed `Excursion`
- `ActivityLike`, `ActivityComment`, `ActivityPhoto`
- `Follow`
- `Advice`, `AdviceLike`, `AdviceComment`, `AdvicePhoto`

Il progetto usa anche alcune view per rendere piu' pulite le query ricorrenti:

- `v_user_profile`
- `v_user_sports`
- `v_user_activities`
- `v_club_detail`
- `v_user_followers`
- `v_user_following`

Al momento non ci sono stored procedure richieste dal codice applicativo attivo.

## Specializzazione delle attivita'

Ogni attivita' salva le informazioni comuni nella tabella `Activity`, come utente, sport, data, durata, calorie, dislivello e descrizione.

I dati specifici dello sport vengono salvati in tabelle dedicate. Per esempio:

- Corsa e ciclismo possono salvare distanza, passo/velocita', frequenza cardiaca, cadenza e dati di dislivello
- Nuoto puo' salvare dati su piscina/acque libere, distanza, stile, vasche e passo
- Sci puo' salvare informazioni specifiche tramite le tabelle collegate allo sci
- Palestra puo' salvare dati relativi all'allenamento

Questa struttura mantiene il database normalizzato ed evita molte colonne inutilizzate nella tabella base delle attivita'.

## Club

Gli utenti possono creare club e iscriversi a club esistenti. Ogni club appartiene a uno sport e contiene:

- Nome, descrizione, immagine e data di creazione
- Numero di membri
- Numero di attivita' pubblicate dai membri
- Informazioni sul creatore/admin
- Anteprima delle attivita' recenti

I creatori dei club vengono salvati come admin nella tabella di relazione `UserClub`.

## Advice

La pagina advice contiene articoli divisi per categoria:

- Nutrition
- Training
- Recovery

Gli utenti possono mettere like e commentare gli advice. Gli utenti admin possono creare e modificare gli advice, includendo anche immagini opzionali.

## Upload media

I file caricati vengono salvati in:

- `images/users/`
- `images/activities/`
- `images/clubs/`
- `images/advice/`

Gli asset di default e le icone si trovano in `media/` e `images/sports/`.

## Schema

Schema del progetto:

![Scheme](media/ProjectScheme.svg)

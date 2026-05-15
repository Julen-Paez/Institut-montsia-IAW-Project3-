# 🏫 Institut Montsià — Gestió de Material Informàtic

Aplicació web per gestionar el material informàtic, assignacions i incidències del departament d'informàtica de l'Institut Montsià.

> **CFGS ASIX · Mòdul 0376 Implantació d'Aplicacions Web · Curs 2025–2026**  
> Professor: Javier López

---

## 📋 Índex

1. [Requisits previs](#1-requisits-previs)
2. [Estructura del projecte](#2-estructura-del-projecte)
3. [Explicació dels arxius principals](#3-explicació-dels-arxius-principals)
4. [Posada en marxa amb Docker](#4-posada-en-marxa-amb-docker)
5. [Accés a l'aplicació](#5-accés-a-laplicació)
6. [Rols i permisos](#6-rols-i-permisos)
7. [Model de dades](#7-model-de-dades)
8. [Tecnologies utilitzades](#8-tecnologies-utilitzades)
9. [Seguretat](#9-seguretat)
10. [Webgrafia](#10-webgrafia)

---

## 1. Requisits previs

Abans de començar, necessites tenir instal·lat:

| Eina | Versió mínima | Descàrrega |
|---|---|---|
| Docker Desktop | 4.x | [docker.com](https://www.docker.com/products/docker-desktop/) |
| Git | 2.x | [git-scm.com](https://git-scm.com/) |
| Navegador web | Qualsevol modern | — |

> ⚠️ **Important:** Docker Desktop ha d'estar **en execució** (icona de la balena 🐳 a la barra de tasques) abans de continuar.

---

## 2. Estructura del projecte

```
projecte/
│
├── 📄 docker-compose.yml       → Defineix els 3 serveis Docker
├── 📄 Dockerfile               → Construeix la imatge PHP+Apache
├── 📄 README.md                → Aquest arxiu
│
├── 📁 sql/
│   └── 📄 01_init.sql          → Crea les taules i insereix dades de prova
│
└── 📁 institut/                → Tot el codi PHP de l'aplicació
    │
    ├── 📄 index.php            → Punt d'entrada, redirigeix al login
    ├── 📄 login.php            → Formulari d'autenticació
    ├── 📄 logout.php           → Tanca la sessió
    │
    ├── 📁 config/
    │   └── 📄 connexio.php     → Configuració de la connexió a la BD
    │
    ├── 📁 includes/
    │   ├── 📄 auth.php         → Sistema de rols i control d'accés
    │   ├── 📄 header.php       → Navbar comuna a totes les pàgines
    │   └── 📄 footer.php       → Peu de pàgina comú
    │
    ├── 📁 professor/           → Pàgines accessibles per admin/professor/editor
    │   ├── 📄 dashboard.php    → Panell principal amb estadístiques
    │   ├── 📄 alumnes.php      → CRUD complet d'alumnes
    │   ├── 📄 material.php     → CRUD complet de material i inventari
    │   ├── 📄 assignacions.php → Gestió de préstecs de dispositius
    │   └── 📄 incidencies.php  → CRUD complet d'incidències
    │
    ├── 📁 alumne/
    │   └── 📄 dashboard.php    → Vista de l'alumne: dispositius i incidències
    │
    └── 📁 assets/
        └── 📄 style.css        → Estils CSS de tota l'aplicació
```

---

## 3. Explicació dels arxius principals

### 🐳 `docker-compose.yml`
Defineix tres serveis que treballen junts:
- **web** — Servidor Apache amb PHP 8.2. Serveix els arxius de la carpeta `institut/` al port `8080`.
- **db** — Base de dades MariaDB. Executa automàticament el `sql/01_init.sql` la primera vegada.
- **phpmyadmin** — Interfície web per gestionar la BD visualment, accessible al port `8081`.

### 🐳 `Dockerfile`
Construeix la imatge del servidor web a partir de `php:8.2-apache` i instal·la l'extensió `mysqli` necessària per connectar amb la BD.

### 🗄️ `sql/01_init.sql`
S'executa automàticament quan el contenidor de la BD s'inicia per primer cop. Crea totes les taules, les relacions entre elles i insereix dades de prova (alumnes, material, incidències i usuaris).

### 🔌 `config/connexio.php`
Conté la funció `getConnexio()` que retorna una connexió activa a la BD. Utilitza variables d'entorn per funcionar tant en Docker (`host: db`) com en XAMPP local (`host: localhost`).

```php
// En Docker el host és 'db' (nom del servei al docker-compose)
define('DB_HOST', getenv('DB_HOST') ?: 'db');
```

### 🔒 `includes/auth.php`
El cor del sistema de seguretat. Defineix:
- **`requireLogin()`** — Redirigeix al login si no hi ha sessió activa.
- **`requireGestio()`** — Permet accés només a admin/professor/editor.
- **`potCrear()`**, **`potEditar()`**, **`potEliminar()`** — Retornen `true/false` segons el rol.
- **`denyIfCannot($accio)`** — Atura l'execució si el rol no té permís.

### 🧩 `includes/header.php`
Navbar comuna inclosa a totes les pàgines. Mostra:
- Navegació principal (Alumnes, Material, Assignacions, Incidències)
- Nom i rol de l'usuari connectat
- Barra de permisos actius (Crear / Editar / Eliminar / Llegir)

### 📄 `login.php`
Gestiona l'autenticació. Comprova les credencials amb `password_verify()` (bcrypt), crea la sessió PHP i redirigeix al dashboard corresponent segons el rol.

### 📚 `professor/alumnes.php`
CRUD complet d'alumnes:
- **Llistar** amb cerca per nom/correu i filtre per grup
- **Crear** nou alumne (també crea automàticament l'usuari de login)
- **Editar** dades i veure dispositius assignats
- **Eliminar** (bloquejat si té assignacions actives)

### 💻 `professor/material.php`
CRUD complet del material:
- **Llistar** amb resum per aula i tipus (quantitats totals)
- **Filtres** per tipus, aula i estat (lliure/assignat)
- **Crear/Editar** amb tots els camps tècnics (MAC, núm. sèrie, SACE…)
- **Eliminar** (bloquejat si el dispositiu té assignació activa)

### 📋 `professor/assignacions.php`
Gestió del préstec de portàtils:
- **Crear** assignació (només mostra dispositius lliures)
- **Retornar** ràpidament un dispositiu (boto 📦)
- **Editar** dates i modificar assignació
- **Eliminar** assignació de l'historial

### ⚠️ `professor/incidencies.php`
Gestió d'incidències:
- **Crear/Editar** amb descripció, alumne, dispositiu i estat
- **Tancar** ràpidament amb un clic (botó ✅)
- **Filtrar** per obertes / tancades

### 🎓 `alumne/dashboard.php`
Vista restringida per a l'alumnat. Mostra únicament:
- Els dispositius que té assignats
- Les seves incidències (obertes i tancades)

---

## 4. Posada en marxa amb Docker

### Pas 1 — Clona el repositori

Obre una terminal (PowerShell a Windows, Terminal a Mac/Linux):

```bash
git clone https://github.com/EL_TEU_USUARI/institut-montsia.git
cd institut-montsia
```

### Pas 2 — Assegura't que Docker Desktop està en execució

A Windows, cerca **Docker Desktop** al menú inici i espera fins que la barra inferior mostri **"Engine running"**.

### Pas 3 — Arrenca els contenidors

```bash
docker compose up -d --build
```

La primera vegada descarregarà les imatges (~500MB). Pot trigar 2-5 minuts.

Quan acabi hauries de veure:
```
✔ Container institut_db   Started
✔ Container institut_web  Started
✔ Container institut_pma  Started
```

### Pas 4 — Espera que la BD estigui llesta

La base de dades triga uns 15-20 segons en inicialitzar-se. Pots comprovar l'estat amb:

```bash
docker ps
```

Els 3 contenidors han d'estar en estat **Up**.

### Pas 5 — Accedeix a l'aplicació

Obre el navegador i ves a: **http://localhost:8080**

### Aturar el projecte

```bash
docker compose down
```

### Reiniciar (si cal aplicar canvis)

```bash
docker compose restart web
```

### Eliminar completament (inclosa la BD)

```bash
docker compose down -v
```

> ⚠️ Això esborra totes les dades. La propera vegada que arrenquis es tornarà a crear la BD amb les dades de prova.

---

## 5. Accés a l'aplicació

| URL | Descripció |
|---|---|
| http://localhost:8080 | Aplicació web principal |
| http://localhost:8081 | phpMyAdmin (gestió BD) |

### Credencials de prova

Tots els usuaris tenen la mateixa contrasenya: **`admin1234`**

| Usuari | Rol | Accés |
|---|---|---|
| `admin` | ⚙️ Admin | Panell de gestió complet |
| `professor` | 👨‍🏫 Professor | Panell de gestió complet |
| `editor` | ✏️ Editor | Panell de gestió (sense crear ni eliminar) |
| `joan.garcia` | 🎓 Alumne | Només els seus dispositius |

### Credencials phpMyAdmin

| Camp | Valor |
|---|---|
| Usuari | `root` |
| Contrasenya | `root` |

---

## 6. Rols i permisos

El sistema implementa 4 rols amb permisos CRUD diferenciats:

| Acció | Admin | Professor | Editor | Alumne |
|---|---|---|---|---|
| Crear alumnes/material/incidències | ✅ | ✅ | ❌ | ❌ |
| Llegir / consultar | ✅ | ✅ | ✅ | ✅ * |
| Editar registres | ✅ | ✅ | ✅ | ❌ |
| Eliminar registres | ✅ | ✅ | ❌ | ❌ |

> \* L'alumne només pot veure els seus propis dispositius i incidències.

La barra de permisos a la part superior de cada pàgina mostra en temps real quines accions pot fer l'usuari connectat.

---

## 7. Model de dades

```
┌─────────────┐     ┌──────────────┐     ┌──────────────┐
│  TipusMat.  │────▶│   Material   │────▶│  Ubicacions  │
└─────────────┘     └──────┬───────┘     └──────────────┘
                           │
              ┌────────────┴────────────┐
              ▼                         ▼
    ┌──────────────────┐     ┌─────────────────────┐
    │   Assignacions   │     │     Incidencies      │
    └────────┬─────────┘     └──────────┬──────────┘
             │                          │
             ▼                          ▼
    ┌──────────────────┐     ┌─────────────────────┐
    │     Alumnes      │     │        Estats        │
    └──────────────────┘     └─────────────────────┘
             │
             ▼
    ┌──────────────────┐
    │     Usuaris      │
    └──────────────────┘
```

---

## 8. Tecnologies utilitzades

| Tecnologia | Versió | Ús |
|---|---|---|
| PHP | 8.2 | Llenguatge de servidor |
| MariaDB | 10.6 | Base de dades relacional |
| Apache | 2.4 | Servidor web |
| Docker | 4.x | Contenidors i desplegament |
| Docker Compose | 2.x | Orquestració de serveis |
| HTML5 + CSS3 | — | Frontend |
| phpMyAdmin | Latest | Gestió visual de la BD |

---

## 9. Seguretat

- **Contrasenyes** emmagatzemades amb `bcrypt` via `password_hash()`
- **Protecció session fixation** amb `session_regenerate_id(true)` al login
- **Control d'accés** per rol a cada pàgina i acció CRUD
- **Escapament XSS** amb `htmlspecialchars()` a totes les sortides
- **SQL Injection** previngut amb `mysqli prepared statements`
- **Error 403** personalitzat per accés no autoritzat

---

## 10. Webgrafia

- [PHP Documentation](https://www.php.net/docs.php) — Referència oficial del llenguatge PHP
- [MariaDB Documentation](https://mariadb.com/kb/en/documentation/) — Documentació de MariaDB
- [Docker Documentation](https://docs.docker.com/) — Guia oficial de Docker
- [MDN Web Docs](https://developer.mozilla.org/) — Referència HTML, CSS i JavaScript
- [PHP The Right Way](https://phptherightway.com/) — Bones pràctiques PHP
- [OWASP PHP Security](https://owasp.org/www-project-php-security/) — Seguretat en aplicacions PHP
- [mysqli Manual](https://www.php.net/manual/en/book.mysqli.php) — Extensió MySQLi de PHP

---

*Institut Montsià © 2026 · Gestió de Material Informàtic · CFGS ASIX*

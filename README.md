# Institut Montsia - Gestio de Material Informatic

Aplicacio web per gestionar el material informatic, assignacions i incidencies del departament d'informatica de l'Institut Montsia.

CFGS ASIX - Modul 0376 Implantacio d'Aplicacions Web - Curs 2025-2026
Professor: Javier Lopez

---

## Index

1. [Requisits previs](#1-requisits-previs)
2. [Estructura del projecte](#2-estructura-del-projecte)
3. [Explicacio dels arxius principals](#3-explicacio-dels-arxius-principals)
4. [Posada en marxa amb Docker](#4-posada-en-marxa-amb-docker)
5. [Acces a l'aplicacio](#5-acces-a-laplicacio)
6. [Rols i permisos](#6-rols-i-permisos)
7. [Model de dades](#7-model-de-dades)
8. [Tecnologies utilitzades](#8-tecnologies-utilitzades)
9. [Seguretat](#9-seguretat)
10. [Webgrafia](#10-webgrafia)

---

## 1. Requisits previs

Abans de comecar, necessites tenir installat:

| Eina            | Versio minima | Descarrega                                      |
|-----------------|---------------|-------------------------------------------------|
| Docker Desktop  | 4.x           | https://www.docker.com/products/docker-desktop/ |
| Git             | 2.x           | https://git-scm.com/                            |
| Navegador web   | Qualsevol modern | -                                            |

IMPORTANT: Docker Desktop ha d'estar en execucio (icona de la balena a la barra de tasques) abans de continuar.

---

## 2. Estructura del projecte

```
projecte/                             Arrel del projecte. Conte la configuracio de Docker i el codi font.
|
+-- Dockerfile                        Defineix com construir la imatge del servidor web (PHP 8.2 + Apache).
+-- docker-compose.yml                Orquestra els tres serveis: web, base de dades i phpMyAdmin.
+-- docker-entrypoint-custom.sh       Script que s'executa en arrencar el contenidor web per inicialitzar les contrasenyes.
+-- README.md                         Documentacio i tutorial de posada en marxa del projecte.
|
+-- sql/                              Scripts SQL que s'executen automaticament quan la BD s'inicia per primer cop.
|   +-- 01_init.sql                   Crea totes les taules, relacions i inserta les dades de prova.
|   +-- 02_passwords.sql              Procediment auxiliar de seguretat per a les contrasenyes.
|
+-- institut/                         Tot el codi font PHP de l'aplicacio web.
    |
    +-- index.php                     Punt d'entrada de l'aplicacio. Redirigeix al login o al dashboard segons la sessio.
    +-- login.php                     Pagina d'autenticacio. Gestiona el formulari i la creacio de sessio.
    +-- logout.php                    Tanca la sessio activa i redirigeix al login.
    +-- docker-init.php               Script intern que genera els hashes bcrypt correctes en arrencar Docker.
    |
    +-- config/                       Configuracio global de l'aplicacio.
    |   +-- connexio.php              Funcio getConnexio() per connectar a la base de dades. Compatible amb Docker i XAMPP.
    |
    +-- includes/                     Arxius reutilitzables inclosos a totes les pagines.
    |   +-- auth.php                  Sistema de control d'acces: rols, permisos CRUD i proteccio de pagines.
    |   +-- header.php                Capçalera HTML comuna: navbar, navegacio, rol de l'usuari i barra de permisos.
    |   +-- footer.php                Peu de pagina HTML comu amb el copyright del centre.
    |
    +-- professor/                    Pagines del panell de gestio. Accessibles per admin, professor i editor.
    |   +-- dashboard.php             Panell principal amb estadistiques generals i incidencies recents.
    |   +-- alumnes.php               Gestio completa d'alumnes: llistar, cercar, crear, editar i eliminar.
    |   +-- material.php              Gestio de l'inventari: llistar per aula/tipus, crear, editar i eliminar dispositius.
    |   +-- assignacions.php          Gestio del prestec de portatils: assignar, retornar, modificar i eliminar.
    |   +-- incidencies.php           Gestio d'incidencies: crear, editar, tancar i eliminar. Filtre per estat.
    |
    +-- alumne/                       Pagines de la vista restringida per a l'alumnat.
    |   +-- dashboard.php             Mostra unicament els dispositius i incidencies de l'alumne connectat.
    |
    +-- assets/                       Recursos estaticos de frontend.
        +-- style.css                 Full d'estils CSS de tota l'aplicacio. Disseny institucional sense dependències externes.
```

---

## 3. Explicacio dels arxius principals

### docker-compose.yml
Defineix tres serveis que treballen junts:
- web: Servidor Apache amb PHP 8.2. Serveix els arxius de la carpeta institut/ al port 8080.
- db: Base de dades MariaDB. Executa automaticament els scripts de la carpeta sql/ la primera vegada.
- phpmyadmin: Interficie web per gestionar la BD visualment, accessible al port 8081.

### Dockerfile
Construeix la imatge del servidor web a partir de php:8.2-apache, installa l'extensio mysqli i configura l'script d'inici personalitzat.

### docker-entrypoint-custom.sh
S'executa quan el contenidor web arrenca. Espera que la BD estigui llesta i crida el docker-init.php per generar les contrasenyes correctes. Despres arrenca Apache normalment.

### sql/01_init.sql
S'executa automaticament quan el contenidor de la BD s'inicia per primer cop. Crea totes les taules, les relacions entre elles i inserta dades de prova (alumnes, material, incidencies i usuaris).

### sql/02_passwords.sql
Script SQL auxiliar amb un procediment per actualitzar les contrasenyes si cal.

### institut/docker-init.php
Script PHP que s'executa automaticament en arrencar el contenidor. Genera hashes bcrypt correctes per a tots els usuaris de prova. No es accessible des del navegador.

### config/connexio.php
Conte la funcio getConnexio() que retorna una connexio activa a la BD. Utilitza variables d'entorn per funcionar tant en Docker (host: db) com en XAMPP local (host: localhost).

```php
// En Docker el host es 'db' (nom del servei al docker-compose)
define('DB_HOST', getenv('DB_HOST') ?: 'db');
```

### includes/auth.php
El cor del sistema de seguretat. Defineix:
- requireLogin(): Redirigeix al login si no hi ha sessio activa.
- requireGestio(): Permet acces nomes a admin/professor/editor.
- potCrear(), potEditar(), potEliminar(): Retornen true/false segons el rol.
- denyIfCannot($accio): Atura l'execucio si el rol no te permis.

### includes/header.php
Navbar comuna inclosa a totes les pagines. Mostra la navegacio principal, el nom i rol de l'usuari connectat i la barra de permisos actius (Crear / Editar / Eliminar / Llegir).

### login.php
Gestiona l'autenticacio. Comprova les credencials amb password_verify() (bcrypt), crea la sessio PHP i redirigeix al dashboard corresponent segons el rol.

### professor/alumnes.php
CRUD complet d'alumnes:
- Llistar amb cerca per nom/correu i filtre per grup
- Crear nou alumne (tambe crea automaticament l'usuari de login)
- Editar dades i veure dispositius assignats
- Eliminar (bloquejat si te assignacions actives)

### professor/material.php
CRUD complet del material:
- Llistar amb resum per aula i tipus (quantitats totals)
- Filtres per tipus, aula i estat (lliure/assignat)
- Crear/Editar amb tots els camps tecnics (MAC, num. serie, SACE...)
- Eliminar (bloquejat si el dispositiu te assignacio activa)

### professor/assignacions.php
Gestio del prestec de portatils:
- Crear assignacio (nomes mostra dispositius lliures)
- Retornar rapidament un dispositiu
- Editar dates i modificar assignacio
- Eliminar assignacio de l'historial

### professor/incidencies.php
Gestio d'incidencies:
- Crear/Editar amb descripcio, alumne, dispositiu i estat
- Tancar rapidament amb un clic
- Filtrar per obertes / tancades

### alumne/dashboard.php
Vista restringida per a l'alumnat. Mostra unicament els dispositius que te assignats i les seves incidencies.

---

## 4. Posada en marxa amb Docker

### Pas 1 - Clona el repositori

Obre una terminal (PowerShell a Windows, Terminal a Mac/Linux):

```bash
git clone https://github.com/EL_TEU_USUARI/institut-montsia.git
cd institut-montsia
```

### Pas 2 - Assegura't que Docker Desktop esta en execucio

A Windows, cerca Docker Desktop al menu inici i espera fins que la barra inferior mostri "Engine running".

### Pas 3 - Arrenca els contenidors

```bash
docker compose up -d --build
```

La primera vegada descarregara les imatges (aproximadament 500MB). Pot trigar 2-5 minuts.

Quan acabi hauries de veure:
```
Container institut_db   Started
Container institut_web  Started
Container institut_pma  Started
```

### Pas 4 - Espera que la BD estigui llesta

La base de dades triga uns 15-20 segons en inicialitzar-se. Pots comprovar l'estat amb:

```bash
docker ps
```

Els 3 contenidors han d'estar en estat Up.

### Pas 5 - Accedeix a l'aplicacio

Obre el navegador i ves a: http://localhost:8080

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

ATENCIO: Aixo esborra totes les dades. La propera vegada que arrenquis es tornara a crear la BD amb les dades de prova.

---

## 5. Acces a l'aplicacio

| URL                    | Descripcio                  |
|------------------------|-----------------------------|
| http://localhost:8080  | Aplicacio web principal     |
| http://localhost:8081  | phpMyAdmin (gestio BD)      |

### Credencials de prova

Tots els usuaris tenen la mateixa contrasenya: admin1234

| Usuari       | Rol       | Acces                                       |
|--------------|-----------|---------------------------------------------|
| admin        | Admin     | Panell de gestio complet                    |
| professor    | Professor | Panell de gestio complet                    |
| editor       | Editor    | Panell de gestio (sense crear ni eliminar)  |
| joan.garcia  | Alumne    | Nomes els seus dispositius                  |

### Credencials phpMyAdmin

| Camp        | Valor |
|-------------|-------|
| Usuari      | root  |
| Contrasenya | root  |

---

## 6. Rols i permisos

El sistema implementa 4 rols amb permisos CRUD diferenciats:

| Accio                               | Admin | Professor | Editor | Alumne |
|-------------------------------------|-------|-----------|--------|--------|
| Crear alumnes/material/incidencies  |  Si   |    Si     |   No   |   No   |
| Llegir / consultar                  |  Si   |    Si     |   Si   |   Si * |
| Editar registres                    |  Si   |    Si     |   Si   |   No   |
| Eliminar registres                  |  Si   |    Si     |   No   |   No   |

* L'alumne nomes pot veure els seus propis dispositius i incidencies.

La barra de permisos a la part superior de cada pagina mostra en temps real quines accions pot fer l'usuari connectat.

---

## 7. Model de dades

```
TipusMaterial -----> Material -----> Ubicacions
                        |
             +----------+----------+
             |                     |
       Assignacions           Incidencies
             |                     |
          Alumnes               Estats
             |
          Usuaris
```

---

## 8. Tecnologies utilitzades

| Tecnologia      | Versio  | Us                          |
|-----------------|---------|-----------------------------|
| PHP             | 8.2     | Llenguatge de servidor      |
| MariaDB         | 10.6    | Base de dades relacional    |
| Apache          | 2.4     | Servidor web                |
| Docker          | 4.x     | Contenidors i desplegament  |
| Docker Compose  | 2.x     | Orquestracio de serveis     |
| HTML5 + CSS3    | -       | Frontend                    |
| phpMyAdmin      | Latest  | Gestio visual de la BD      |

---

## 9. Seguretat

- Contrasenyes emmagatzemades amb bcrypt via password_hash()
- Proteccio session fixation amb session_regenerate_id(true) al login
- Control d'acces per rol a cada pagina i accio CRUD
- Escapament XSS amb htmlspecialchars() a totes les sortides
- SQL Injection previngut amb mysqli prepared statements
- Error 403 personalitzat per acces no autoritzat

---

## 10. Webgrafia

- PHP Documentation: https://www.php.net/docs.php
- MariaDB Documentation: https://mariadb.com/kb/en/documentation/
- Docker Documentation: https://docs.docker.com/
- MDN Web Docs: https://developer.mozilla.org/
- PHP The Right Way: https://phptherightway.com/
- OWASP PHP Security: https://owasp.org/www-project-php-security/
- mysqli Manual: https://www.php.net/manual/en/book.mysqli.php

---

Institut Montsia - 2026 - Gestio de Material Informatic - CFGS ASIX

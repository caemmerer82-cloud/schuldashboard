# Schul-Dashboard – Strato-Edition
**URL:** https://schule.familiecaemmerer.de

---

## Installation auf Strato

### 1. Dateien hochladen
Alle Dateien per FTP/SFTP in das Webverzeichnis hochladen:
```
schule.familiecaemmerer.de/   ← Webroot bei Strato
├── .htaccess
├── index.php
├── login.php
├── logout.php
├── settings.php
├── admin.php
├── install.sql
├── includes/
│   ├── config.php
│   ├── jwt.php
│   ├── auth.php
│   └── db.php
├── api/
│   ├── homework.php
│   ├── events.php
│   ├── settings.php
│   ├── users.php
│   ├── admin.php
│   └── proxy.php
├── assets/
│   ├── css/styles.css
│   └── js/app.js
└── data/
    └── .htaccess
```

### 2. Datenbank einrichten (Strato phpMyAdmin)
1. Strato-Kundenbereich → Datenbanken → phpMyAdmin öffnen
2. Oben „Importieren" klicken
3. `install.sql` auswählen → OK

### 3. Erster Login
→ https://schule.familiecaemmerer.de/login.php

| Feld | Wert |
|---|---|
| Benutzername | `admin` |
| Passwort | `$chulPl@n3r!` |

### 4. Ersten Mandanten anlegen
1. Als `admin` einloggen → **🛡️ Super-Admin**
2. **Mandanten** → „+ Neuer Mandant" (z.B. „Familie Caemmerer")
3. **Benutzer** → Mandanten-Admin anlegen (Rolle: `🔑 Mandanten-Admin`)
4. Ausloggen → als Mandanten-Admin einloggen
5. **⚙️ Einstellungen** → URLs für Stundenplan etc. eintragen

---

## Datenbankzugangsdaten
```
Host:     database-5020287861.webspace-host.com
Datenbank: dbs15593008
Benutzer: dbu299572
Passwort: schul_dashboard
```

---

## Rollen

| Rolle | Zugang |
|---|---|
| `admin` (superadmin) | Alles: Mandanten + Benutzer anlegen → `admin.php` |
| `tenant_admin` | Einstellungen + eigene Benutzer verwalten → `settings.php` |
| `user` | Dashboard + Passwort ändern → `settings.php` |

---

## Technischer Überblick

- **Backend:** PHP 7.4+/8.x, PDO, MySQL – keine externen Bibliotheken
- **Auth:** JWT (HS256, HttpOnly-Cookie, 8h Laufzeit)
- **Frontend:** Vanilla JS, kein Build-Schritt
- **Multitenancy:** Vollständig getrennte Daten pro Mandant (tenant_id auf allen Tabellen)

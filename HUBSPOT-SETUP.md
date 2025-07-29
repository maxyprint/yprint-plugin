# 🍪 YPrint HubSpot Integration Setup

## Übersicht
Die YPrint HubSpot Integration erstellt automatisch Kontakte in HubSpot bei jeder Benutzerregistrierung. **NEU:** Die Integration erstellt jetzt auch automatisch Aktivitäten bei Cookie-Aktualisierungen und erstmaligen Cookie-Auswahlen.

## 📋 Setup-Schritte

### 1. HubSpot Private App erstellen

**WICHTIG**: Es gibt zwei verschiedene Arten von API-Schlüsseln bei HubSpot:
- **API Key (HAPI Key)**: Ältere Methode, funktioniert nicht mehr für neue Konten
- **Private App Access Token**: Neue, empfohlene Methode

**Verwende NUR Private App Access Tokens!**

1. **Gehe zu deinem HubSpot Account**
   - Öffne deinen HubSpot Account
   - Navigiere zu **Settings** → **Integrations** → **Private Apps**

2. **Erstelle eine neue Private App**
   - Klicke auf **"Create private app"**
   - Gib einen Namen ein (z.B. "YPrint Integration")
   - Wähle die folgenden **Scopes** aus:
     - ✅ `crm.objects.contacts.read`
     - ✅ `crm.objects.contacts.write`
     - ✅ `crm.objects.notes.read` (NEU: Für Cookie-Aktivitäten)
     - ✅ `crm.objects.notes.write` (NEU: Für Cookie-Aktivitäten)
   - **WICHTIG**: Stelle sicher, dass du die Scopes aktivierst (Häkchen setzen)
   - Klicke auf **"Create app"**

3. **Kopiere den Access Token**
   - Nach der Erstellung findest du den **Access Token**
   - Kopiere diesen Token (wird nur einmal angezeigt!)
   - **WICHTIG**: Verwende den "Access Token", NICHT den "Client Secret"
   - Der Token sollte etwa so aussehen: `pat-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`

### 2. WordPress Admin konfigurieren

1. **Gehe zu WordPress Admin**
   - Navigiere zu **Settings** → **HubSpot Integration**

2. **Aktiviere die Integration**
   - Setze ein Häkchen bei **"HubSpot Integration aktivieren"**

3. **Füge deinen API Key ein**
   - Füge den kopierten Access Token in das Feld **"HubSpot API Key"** ein

4. **Teste die Verbindung**
   - Klicke auf **"Verbindung testen"**
   - Du solltest eine Erfolgsmeldung sehen

### 3. Testen der Integration

1. **Führe eine Testregistrierung durch**
   - Registriere einen neuen Benutzer über das Frontend
   - Prüfe die WordPress Error Logs auf HubSpot-Nachrichten

2. **Teste Cookie-Aktivitäten**
   - Ändere Cookie-Einstellungen als eingeloggter Benutzer
   - Prüfe HubSpot auf neue Aktivitäten

3. **Verifiziere in HubSpot**
   - Gehe zu deinem HubSpot CRM
   - Prüfe, ob der neue Kontakt erstellt wurde
   - Prüfe die Aktivitäten für Cookie-bezogene Notizen

## 🔧 Konfiguration

### Admin-Einstellungen

- **HubSpot Integration aktivieren**: Schaltet die Integration ein/aus
- **HubSpot API Key**: Dein Private App Access Token
- **Debug-Modus**: Aktiviert detailliertes Logging

### Automatische Kontakt-Erstellung

Bei jeder Registrierung werden folgende Daten an HubSpot gesendet:

```php
$hubspot_contact_data = array(
    'email' => $email,
    'username' => $username,
    'firstname' => $username, // Fallback
    'registration_date' => current_time('Y-m-d H:i:s'),
    'cookie_preferences' => $cookie_preferences // Optional
);
```

## 🍪 NEU: Cookie-Aktivitäten

### Erstmalige Cookie-Auswahl
- **Trigger**: Erste Cookie-Auswahl durch Benutzer
- **Aktivität**: Notiz in HubSpot mit Cookie-Präferenzen
- **Inhalt**: Detaillierte Auflistung aller Cookie-Kategorien

### Cookie-Aktualisierungen
- **Trigger**: Änderung bestehender Cookie-Einstellungen
- **Aktivität**: Notiz in HubSpot mit Änderungsvergleich
- **Inhalt**: Vorher/Nachher-Vergleich der Cookie-Präferenzen

### Aktivitäts-Details

**Erstmalige Cookie-Auswahl:**
```
🍪 **Erstmalige Cookie-Auswahl**

**Zeitpunkt:** 15.01.2024 14:30:25

**Cookie-Präferenzen:**
- Essenzielle Cookies: ✅ Akzeptiert
- Analytics Cookies: ❌ Abgelehnt
- Marketing Cookies: ❌ Abgelehnt
- Funktionale Cookies: ✅ Akzeptiert

**Prozess:** Erstmalige Cookie-Auswahl durch Benutzer
**Quelle:** YPrint Cookie-Consent-System
```

**Cookie-Aktualisierung:**
```
🍪 **Cookie-Präferenzen aktualisiert**

**Zeitpunkt:** 15.01.2024 16:45:12

**Änderungen:**
- Analytics Cookies: Abgelehnt → Akzeptiert

**Aktuelle Cookie-Präferenzen:**
- Essenzielle Cookies: ✅ Akzeptiert
- Analytics Cookies: ✅ Akzeptiert
- Marketing Cookies: ❌ Abgelehnt
- Funktionale Cookies: ✅ Akzeptiert

**Prozess:** Cookie-Präferenzen aktualisiert
**Quelle:** YPrint Cookie-Consent-System
```

## 📊 Monitoring

### WordPress Error Logs
Alle HubSpot-Aktivitäten werden geloggt:
```
YPrint Registration: HubSpot contact created for user username with ID: 12345
YPrint Registration: Initial cookie activity created for user username during registration
YPrint HubSpot: Cookie-Aktivität erfolgreich erstellt für User 123 - Type: initial
YPrint HubSpot: Cookie-Aktivität erfolgreich erstellt für User 123 - Type: update
```

### Admin-Statistiken
Im HubSpot Admin-Panel findest du:
- Anzahl erfolgreich erstellter HubSpot-Kontakte
- Registrierungen heute
- Cookie-Aktivitäten (erstmalig/Updates)
- Verbindungstest-Ergebnisse

## 🛠️ Fehlerbehebung

### Häufige Probleme

1. **"Verbindung fehlgeschlagen"**
   - Prüfe, ob der API Key korrekt kopiert wurde
   - Stelle sicher, dass die Private App die richtigen Scopes hat
   - Teste die Verbindung erneut

2. **"Kontakt wird nicht erstellt"**
   - Prüfe die WordPress Error Logs
   - Stelle sicher, dass die Integration aktiviert ist
   - Teste mit einer neuen Registrierung

3. **"Cookie-Aktivitäten werden nicht erstellt"**
   - Prüfe, ob die Notes-Scopes aktiviert sind
   - Stelle sicher, dass der Kontakt in HubSpot existiert
   - Prüfe die WordPress Error Logs für Details

4. **"API Key nicht gültig"**
   - Erstelle eine neue Private App
   - Kopiere den neuen Access Token
   - Aktualisiere die Einstellungen

### Debug-Modus

Aktiviere den Debug-Modus für detaillierte Logs:
1. Gehe zu **Settings** → **HubSpot Integration**
2. Aktiviere **"Debug-Modus"**
3. Prüfe die WordPress Error Logs nach Aktivitäten

## 🔒 Sicherheit

- **API Keys** werden verschlüsselt gespeichert
- **Fehlerbehandlung** verhindert Blockierung der Registrierung
- **Logging** für Audit-Trails
- **Nonce-Verifikation** für AJAX-Requests

## 📈 Erweiterte Funktionen

### Cookie-Präferenzen
Falls Cookie-Consent-Daten verfügbar sind, werden diese als JSON in HubSpot gespeichert:

```json
{
  "essential": "true",
  "analytics": "false", 
  "marketing": "false",
  "functional": "false"
}
```

### Kontakt-Update
Die Integration kann auch bestehende Kontakte aktualisieren:
- Cookie-Präferenzen werden bei Änderungen aktualisiert
- Registrierungsdatum wird gespeichert

### Automatische Aktivitäten
- **Bei Registrierung**: Erstmalige Cookie-Aktivität (falls vorhanden)
- **Bei Cookie-Änderungen**: Update-Aktivität mit Vergleich
- **Für Gäste**: Aktivitäten nur bei verfügbarer E-Mail

## 🚀 Nächste Schritte

Nach erfolgreicher Integration kannst du:

1. **HubSpot Workflows** erstellen basierend auf Registrierungen
2. **E-Mail-Marketing** Kampagnen starten
3. **Lead-Scoring** implementieren
4. **Automatisierte Follow-ups** einrichten
5. **Cookie-basierte Segmentierung** erstellen
6. **Aktivitäts-basierte Automatisierung** einrichten

## 📞 Support

Bei Problemen:
1. Prüfe die WordPress Error Logs
2. Teste die API-Verbindung im Admin-Panel
3. Stelle sicher, dass alle Setup-Schritte befolgt wurden
4. Kontaktiere den Support mit den Log-Details

### 4. Custom Properties in HubSpot erstellen

Für die vollständige Integration musst du Custom Properties in HubSpot erstellen:

1. **Gehe zu HubSpot → Settings → Properties**
2. **Erstelle diese Custom Properties:**

   **Property 1:**
   - Name: `YPrint Username`
   - Internal Name: `yprint_username`
   - Type: `Single-line text`
   - Group: `Contact information`

   **Property 2:**
   - Name: `YPrint Registration Date`
   - Internal Name: `yprint_registration_date`
   - Type: `Date`
   - Group: `Contact information`

   **Property 3:**
   - Name: `YPrint Cookie Preferences`
   - Internal Name: `yprint_cookie_preferences`
   - Type: `Multi-line text`
   - Group: `Contact information`

3. **Speichere die Properties**
4. **Teste die Integration erneut**

### 5. Testen der Integration

1. **Registriere einen neuen Benutzer**
   - Gehe zur Registrierungsseite
   - Fülle das Formular aus
   - Prüfe die WordPress Error Logs

2. **Teste Cookie-Aktivitäten**
   - Ändere Cookie-Einstellungen als eingeloggter Benutzer
   - Prüfe HubSpot auf neue Aktivitäten

3. **Verifiziere in HubSpot**
   - Gehe zu deinem HubSpot CRM
   - Suche nach dem neuen Kontakt
   - Prüfe die Aktivitäten für Cookie-Notizen

4. **Debug-Informationen**
   - Aktiviere den Debug-Modus im Admin-Panel
   - Prüfe die WordPress Error Logs
   - Teste die API-Verbindung

## 🎯 Erfolgsmetriken

Nach der Integration solltest du folgende Aktivitäten in HubSpot sehen:

- ✅ **Kontakte** werden bei jeder Registrierung erstellt
- ✅ **Cookie-Aktivitäten** werden bei erstmaliger Auswahl erstellt
- ✅ **Update-Aktivitäten** werden bei Cookie-Änderungen erstellt
- ✅ **Automatische Verknüpfung** zwischen Notizen und Kontakten
- ✅ **Detaillierte Logs** in WordPress Error Logs

## 🔄 Automatisierung

Die Integration ermöglicht folgende Automatisierungen:

1. **Registrierungs-Workflows**
   - Willkommens-E-Mails bei Registrierung
   - Onboarding-Sequenzen
   - Lead-Scoring basierend auf Cookie-Präferenzen

2. **Cookie-basierte Segmentierung**
   - Analytics-Akzeptierer für Marketing
   - Marketing-Akzeptierer für personalisierte Kampagnen
   - Datenschutzbewusste Nutzer für spezielle Kommunikation

3. **Aktivitäts-basierte Follow-ups**
   - Nachfragen bei Cookie-Änderungen
   - Personalisierte Inhalte basierend auf Präferenzen
   - Automatische Lead-Qualifizierung
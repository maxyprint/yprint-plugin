# 🍪 YPrint HubSpot Integration Setup

## Übersicht
Die YPrint HubSpot Integration erstellt automatisch Kontakte in HubSpot bei jeder Benutzerregistrierung. Die Integration ist so konzipiert, dass sie auch bei HubSpot-Fehlern die Registrierung nicht blockiert.

## 📋 Setup-Schritte

### 1. HubSpot Private App erstellen

1. **Gehe zu deinem HubSpot Account**
   - Öffne deinen HubSpot Account
   - Navigiere zu **Settings** → **Integrations** → **Private Apps**

2. **Erstelle eine neue Private App**
   - Klicke auf **"Create private app"**
   - Gib einen Namen ein (z.B. "YPrint Integration")
   - Wähle die folgenden **Scopes** aus:
     - `crm.objects.contacts.read`
     - `crm.objects.contacts.write`
   - **WICHTIG**: Stelle sicher, dass du die Scopes aktivierst (Häkchen setzen)

3. **Kopiere den Access Token**
   - Nach der Erstellung findest du den **Access Token**
   - Kopiere diesen Token (wird nur einmal angezeigt!)
   - **WICHTIG**: Verwende den "Access Token", NICHT den "Client Secret"

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

2. **Verifiziere in HubSpot**
   - Gehe zu deinem HubSpot CRM
   - Prüfe, ob der neue Kontakt erstellt wurde
   - Die Kontaktdaten sollten enthalten:
     - E-Mail-Adresse
     - Benutzername (als Vorname)
     - Registrierungsdatum
     - Cookie-Präferenzen (falls verfügbar)

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

## 📊 Monitoring

### WordPress Error Logs
Alle HubSpot-Aktivitäten werden geloggt:
```
YPrint Registration: HubSpot contact created for user username with ID: 12345
YPrint Registration: Failed to create HubSpot contact for user username: API Error
```

### Admin-Statistiken
Im HubSpot Admin-Panel findest du:
- Anzahl erfolgreich erstellter HubSpot-Kontakte
- Registrierungen heute
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

3. **"API Key nicht gültig"**
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

## 🚀 Nächste Schritte

Nach erfolgreicher Integration kannst du:

1. **HubSpot Workflows** erstellen basierend auf Registrierungen
2. **E-Mail-Marketing** Kampagnen starten
3. **Lead-Scoring** implementieren
4. **Automatisierte Follow-ups** einrichten

## 📞 Support

Bei Problemen:
1. Prüfe die WordPress Error Logs
2. Teste die API-Verbindung im Admin-Panel
3. Stelle sicher, dass alle Setup-Schritte befolgt wurden
4. Kontaktiere den Support mit den Log-Details
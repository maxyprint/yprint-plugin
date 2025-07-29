# Cookie Consent Verbesserungen

## 🎯 **Neue Funktionalität: Intelligente Banner-Anzeige**

### **Problem gelöst:**
Eingeloggte Nutzer wurden bei jedem Besuch erneut nach Cookie-Consent gefragt, auch wenn sie bereits gültige Einstellungen hatten.

### **Lösung:**
Das System prüft jetzt, ob eingeloggte Nutzer bereits gültige Consent-Einstellungen haben und zeigt das Banner nur noch bei Bedarf.

## 🔧 **Technische Details:**

### **Für eingeloggte Nutzer:**
- ✅ **Prüfung auf aktuelle Consent-Einstellungen** (nicht älter als 1 Jahr)
- ✅ **Validierung der essenziellen Cookies** (müssen immer akzeptiert sein)
- ✅ **Banner wird nur angezeigt wenn:**
  - Keine Consent-Entscheidung vorhanden ODER
  - Consent-Einstellungen sind veraltet (> 1 Jahr) ODER
  - Essenzielle Cookies fehlen

### **Für Gäste:**
- ✅ **Prüfung auf gültige Cookie-Timestamps** (nicht älter als 1 Jahr)
- ✅ **Validierung der Cookie-Präferenzen**
- ✅ **Banner wird nur angezeigt wenn:**
  - Keine Consent-Cookies vorhanden ODER
  - Cookies sind veraltet (> 1 Jahr) ODER
  - Essenzielle Cookies fehlen

## 📊 **Neue Methoden:**

### **`has_valid_user_consent($user_id)`**
- Prüft ob eingeloggter User aktuelle Consent-Einstellungen hat
- Validiert das Alter der Einstellungen (max. 1 Jahr)
- Stellt sicher, dass essenzielle Cookies akzeptiert sind

### **`has_valid_guest_consent()`**
- Prüft ob Gast gültige Cookie-Präferenzen hat
- Validiert das Cookie-Alter
- Stellt sicher, dass essenzielle Cookies akzeptiert sind

### **Erweiterte `has_guest_consent_decision()`**
- Prüft zusätzlich auf gültige Timestamps
- Validiert alle erforderlichen Cookies

## 🍪 **Neue Cookies:**

### **`yprint_consent_timestamp`**
- Speichert den Zeitpunkt der Consent-Entscheidung
- Wird für Gültigkeitsprüfungen verwendet
- Gültigkeitsdauer: 1 Jahr

### **`yprint_consent_decision`**
- Markiert, dass eine Entscheidung getroffen wurde
- Wird für schnelle Validierung verwendet
- Gültigkeitsdauer: 1 Jahr

## 🎯 **Vorteile:**

1. **Bessere User Experience:**
   - Keine unnötigen Banner-Anfragen
   - Kontinuierliche Nutzung ohne Unterbrechungen

2. **DSGVO-Konformität:**
   - Automatische Erneuerung nach 1 Jahr
   - Klare Dokumentation der Consent-Entscheidungen

3. **Technische Robustheit:**
   - Mehrfache Validierung der Consent-Daten
   - Fallback-Mechanismen bei fehlenden Daten

## 🔍 **Debugging:**

Das System loggt detaillierte Informationen:
- Consent-Status für eingeloggte Nutzer
- Cookie-Validierung für Gäste
- Banner-Anzeige-Entscheidungen

**Log-Beispiele:**
```
🍪 PHP: User logged in, has_decision: true, has_valid_consent: true, show_banner: false
🍪 PHP: Guest user, has_guest_decision: true, has_valid_guest_consent: true, show_banner: false
```

## 🚀 **Nächste Schritte:**

1. **Testen der neuen Funktionalität**
2. **Überwachung der Logs**
3. **Anpassung der Gültigkeitsdauer bei Bedarf**
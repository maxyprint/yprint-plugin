-- 🍪 YPrint Cookie Consent - Datenbank-Bereinigung
-- Korrigiere fehlerhafte essenziellen Cookie-Ablehnungen

-- ✅ 1. Korrigiere fehlerhafte essenzielle Cookie-Ablehnungen
UPDATE wp_yprint_consents 
SET granted = 1, updated_at = NOW()
WHERE consent_type = 'COOKIE_ESSENTIAL' AND granted = 0;

-- ✅ 2. Protokolliere die Änderungen
SELECT 
    user_id, 
    consent_type, 
    granted, 
    created_at,
    updated_at,
    CASE 
        WHEN consent_type = 'COOKIE_ESSENTIAL' AND granted = 1 
        THEN '✅ KORRIGIERT' 
        ELSE 'OK' 
    END as status
FROM wp_yprint_consents 
WHERE consent_type = 'COOKIE_ESSENTIAL'
ORDER BY user_id;

-- ✅ 3. Finde verdächtige automatische Klicks (identische Zeitstempel)
SELECT 
    user_id,
    COUNT(*) as consent_count,
    MIN(created_at) as first_consent,
    MAX(created_at) as last_consent,
    CASE 
        WHEN COUNT(*) > 1 AND MIN(created_at) = MAX(created_at) 
        THEN '⚠️ VERDÄCHTIG: Automatischer Klick möglich'
        ELSE 'OK'
    END as pattern
FROM wp_yprint_consents 
GROUP BY user_id
HAVING COUNT(*) > 1
ORDER BY consent_count DESC;

-- ✅ 4. Finde logische Inkonsistenzen
SELECT 
    c1.user_id,
    c1.consent_type as privacy_policy,
    c1.granted as privacy_granted,
    c2.consent_type as cookie_type,
    c2.granted as cookie_granted,
    CASE 
        WHEN c1.granted = 1 AND c2.granted = 0 
        THEN '❌ INKONSISTENT: Privacy akzeptiert, Cookie abgelehnt'
        ELSE 'OK'
    END as consistency
FROM wp_yprint_consents c1
JOIN wp_yprint_consents c2 ON c1.user_id = c2.user_id
WHERE c1.consent_type = 'PRIVACY_POLICY' 
  AND c2.consent_type LIKE 'COOKIE_%'
  AND c1.granted = 1 
  AND c2.granted = 0;

-- ✅ 5. Statistiken nach Consent-Typ
SELECT 
    consent_type,
    COUNT(*) as total_consents,
    SUM(CASE WHEN granted = 1 THEN 1 ELSE 0 END) as granted_count,
    SUM(CASE WHEN granted = 0 THEN 1 ELSE 0 END) as denied_count,
    ROUND((SUM(CASE WHEN granted = 1 THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as grant_percentage
FROM wp_yprint_consents 
GROUP BY consent_type
ORDER BY consent_type;

-- ✅ 6. Aktuelle Konsistenz-Prüfung
SELECT 
    'ESSENTIELLE COOKIES' as check_type,
    COUNT(*) as total,
    SUM(CASE WHEN granted = 1 THEN 1 ELSE 0 END) as correctly_granted,
    SUM(CASE WHEN granted = 0 THEN 1 ELSE 0 END) as incorrectly_denied,
    CASE 
        WHEN SUM(CASE WHEN granted = 0 THEN 1 ELSE 0 END) > 0 
        THEN '❌ KRITISCH: Essenzielle Cookies abgelehnt!'
        ELSE '✅ OK: Alle essenziellen Cookies korrekt akzeptiert'
    END as status
FROM wp_yprint_consents 
WHERE consent_type = 'COOKIE_ESSENTIAL';
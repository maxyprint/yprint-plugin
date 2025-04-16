<?php
/**
 * SVG-Handler für Vectorize WP
 *
 * Diese Klasse behandelt die Verarbeitung und Speicherung von SVG-Dateien.
 */

// Sicherheitscheck: Direkten Zugriff auf diese Datei verhindern
if (!defined('ABSPATH')) {
    exit;
}

class Vectorize_WP_SVG_Handler {
    /**
     * Konstruktor
     */
    public function __construct() {
        // Initialisierung
        add_action('init', array($this, 'enable_svg_upload'));
    }
    
    /**
     * Ermöglicht das Hochladen von SVG-Dateien in die WordPress-Mediathek
     */
    public function enable_svg_upload() {
        add_filter('upload_mimes', array($this, 'add_svg_mime_type'));
        add_filter('wp_check_filetype_and_ext', array($this, 'fix_svg_mime_type'), 10, 5);
    }
    
    /**
     * Fügt SVG zum erlaubten MIME-Typ hinzu
     * 
     * @param array $mimes Erlaubte MIME-Typen
     * @return array Modifizierte MIME-Typen
     */
    public function add_svg_mime_type($mimes) {
        $mimes['svg'] = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
        return $mimes;
    }
    
    /**
     * Korrigiert den MIME-Typ für SVG-Dateien
     * 
     * @param array $data Dateitypinformationen
     * @param string $file Pfad zur Datei
     * @param string $filename Dateiname
     * @param array $mimes Erlaubte MIME-Typen
     * @param string $real_mime Echter MIME-Typ
     * @return array Korrigierte Dateitypinformationen
     */
    public function fix_svg_mime_type($data, $file, $filename, $mimes, $real_mime = null) {
        // WP 5.1 +
        if (version_compare($GLOBALS['wp_version'], '5.1.0', '>=')) {
            $wp_filetype = wp_check_filetype($filename, $mimes);
            $ext = $wp_filetype['ext'];
            $type = $wp_filetype['type'];
            $proper_filename = $filename;
            
            if ($ext === 'svg' || $ext === 'svgz') {
                $data['ext'] = $ext;
                $data['type'] = 'image/svg+xml';
                $data['proper_filename'] = $proper_filename;
            }
        } else {
            // WP 5.0 oder älter
            if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'svg') {
                $data['ext'] = 'svg';
                $data['type'] = 'image/svg+xml';
            }
        }
        
        return $data;
    }
    
    /**
     * Sanitized eine SVG-Datei, um potenziell schädlichen Code zu entfernen
     *
     * @param string $svg_content SVG-Inhalt
     * @return string Sanitierter SVG-Inhalt
     */
    public function sanitize_svg($svg_content) {
        // SVG-Inhalt sichern, indem potenziell schädliche Tags und Attribute entfernt werden
        $allowed_tags = array(
            'svg', 'g', 'path', 'circle', 'ellipse', 'line', 'polygon', 'polyline',
            'rect', 'text', 'tspan', 'use', 'defs', 'linearGradient', 'radialGradient',
            'stop', 'clipPath', 'filter', 'feGaussianBlur', 'feOffset', 'feBlend',
            'title', 'desc', 'style', 'metadata', 'pattern', 'image', 'marker', 'symbol',
            'foreignObject', 'mask'
        );
        
        $allowed_attributes = array(
            'id', 'class', 'x', 'y', 'width', 'height', 'viewBox', 'preserveAspectRatio',
            'cx', 'cy', 'r', 'rx', 'ry', 'x1', 'y1', 'x2', 'y2', 'd', 'fill', 'stroke',
            'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-dasharray',
            'transform', 'style', 'text-anchor', 'dominant-baseline', 'opacity',
            'fill-opacity', 'stroke-opacity', 'points', 'offset', 'stop-color',
            'stop-opacity', 'font-family', 'font-size', 'font-weight', 'text-decoration',
            'clip-path', 'mask', 'filter', 'xlink:href', 'href', 'xmlns', 'xmlns:xlink',
            'version', 'patternUnits', 'markerWidth', 'markerHeight', 'markerUnits',
            'refX', 'refY', 'orient', 'gradientUnits', 'gradientTransform', 'spreadMethod',
            'filterUnits', 'dx', 'dy', 'rotate', 'textLength', 'lengthAdjust', 'result',
            'in', 'in2', 'mode', 'type', 'values', 'stdDeviation', 'baseFrequency',
            'numOctaves', 'seed', 'edgeMode', 'operator', 'amount', 'radius', 'preserveAlpha',
            'color-interpolation-filters'
        );
        
        // Einfache Validierung der SVG-Struktur
        if (!preg_match('/<svg[^>]*>/i', $svg_content)) {
            return ''; // Keine <svg>-Tags gefunden
        }
        
        // DOMDocument zum Parsen und Sanitieren verwenden
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadXML($svg_content);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        
        // Wenn es Fehler gibt, versuchen wir, die Datei als HTML zu laden
        if (!empty($errors)) {
            // Versuchen, als HTML zu laden
            $doc = new DOMDocument();
            $doc->loadHTML($svg_content);
            
            // SVG-Element suchen
            $svg_element = null;
            $svg_tags = $doc->getElementsByTagName('svg');
            
            if ($svg_tags->length > 0) {
                $svg_element = $svg_tags->item(0);
            } else {
                // Keine SVG-Tags gefunden
                return '';
            }
            
            // Neues DOMDocument mit nur dem SVG-Element erstellen
            $new_doc = new DOMDocument();
            $imported_svg = $new_doc->importNode($svg_element, true);
            $new_doc->appendChild($imported_svg);
            
            $doc = $new_doc;
        }
        
        // Alle Elemente durchlaufen und nur erlaubte Tags und Attribute behalten
        $xpath = new DOMXPath($doc);
        $elements = $xpath->query('//*');
        
        foreach ($elements as $element) {
            // Überprüfen, ob das Tag erlaubt ist
            if (!in_array(strtolower($element->tagName), $allowed_tags)) {
                $element->parentNode->removeChild($element);
                continue;
            }
            
            // Attribute überprüfen
            for ($i = $element->attributes->length - 1; $i >= 0; $i--) {
                $attr = $element->attributes->item($i);
                
                // Überprüfen, ob das Attribut erlaubt ist
                if (!in_array(strtolower($attr->name), $allowed_attributes)) {
                    $element->removeAttributeNode($attr);
                    continue;
                }
                
                // JavaScript in Attributen entfernen
                if (preg_match('/^\s*javascript:/i', $attr->value)) {
                    $element->removeAttributeNode($attr);
                }
            }
        }
        
        // Skript-Tags entfernen
        $scripts = $xpath->query('//script');
        foreach ($scripts as $script) {
            $script->parentNode->removeChild($script);
        }
        
        // Event-Handler-Attribute entfernen
        $elements = $xpath->query('//*');
        foreach ($elements as $element) {
            for ($i = $element->attributes->length - 1; $i >= 0; $i--) {
                $attr = $element->attributes->item($i);
                if (preg_match('/^on[a-z]+/i', $attr->name)) {
                    $element->removeAttributeNode($attr);
                }
            }
        }
        
        // Sicherstellen, dass das SVG das xmlns-Attribut hat
        $root = $doc->documentElement;
        if (!$root->hasAttribute('xmlns')) {
            $root->setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        }
        
        // SVG zurückgeben
        return $doc->saveXML($doc->documentElement);
    }
    
    /**
     * Speichert eine SVG-Datei in der WordPress-Mediathek
     *
     * @param string $svg_content SVG-Inhalt
     * @param string $filename Dateiname
     * @param array $attachment_data Zusätzliche Daten für den Anhang
     * @return int|WP_Error ID des Anhangs oder Fehler
     */
    public function save_to_media_library($svg_content, $filename, $attachment_data = array()) {
        // SVG-Inhalt sanitieren
        $svg_content = $this->sanitize_svg($svg_content);
        
        if (empty($svg_content)) {
            return new WP_Error('invalid_svg', __('Ungültiger SVG-Inhalt.', 'vectorize-wp'));
        }
        
        // Upload-Verzeichnis abrufen
        $upload_dir = wp_upload_dir();
        
        // Dateinamen sicherstellen
        $filename = sanitize_file_name($filename);
        if (!preg_match('/\.svg$/i', $filename)) {
            $filename .= '.svg';
        }
        
        // Vollständigen Dateipfad erstellen
        $file_path = $upload_dir['path'] . '/' . $filename;
        
        // Überprüfen, ob die Datei bereits existiert
        $counter = 0;
        $file_info = pathinfo($file_path);
        while (file_exists($file_path)) {
            $counter++;
            $filename = $file_info['filename'] . '-' . $counter . '.svg';
            $file_path = $upload_dir['path'] . '/' . $filename;
        }
        
        // SVG-Inhalt in Datei schreiben
        $result = file_put_contents($file_path, $svg_content);
        
        if ($result === false) {
            return new WP_Error('write_error', __('Fehler beim Schreiben der SVG-Datei.', 'vectorize-wp'));
        }
        
        // Standard-Anhangsinfos
        $attachment = array(
            'post_mime_type' => 'image/svg+xml',
            'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        // Benutzerdefinierte Daten zusammenführen
        if (is_array($attachment_data) && !empty($attachment_data)) {
            $attachment = array_merge($attachment, $attachment_data);
        }
        
        // Anhang in die Datenbank einfügen
        $attachment_id = wp_insert_attachment($attachment, $file_path);
        
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }
        
        // Anhangsinformationen generieren
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        
        return $attachment_id;
    }
    
    /**
     * Erstellt einen herunterladbaren Link für eine SVG-Datei
     *
     * @param string $svg_content SVG-Inhalt
     * @param string $filename Dateiname
     * @return string URL zum Herunterladen
     */
    public function create_download_link($svg_content, $filename) {
        // SVG-Inhalt sanitieren
        $svg_content = $this->sanitize_svg($svg_content);
        
        if (empty($svg_content)) {
            return '';
        }
        
        // Dateinamen sicherstellen
        $filename = sanitize_file_name($filename);
        if (!preg_match('/\.svg$/i', $filename)) {
            $filename .= '.svg';
        }
        
        // Temporäre Datei erstellen
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/vectorize-wp/temp';
        
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        
        // Timestamp für eindeutigen Dateinamen
        $timestamp = time();
        $temp_file = $temp_dir . '/' . $timestamp . '-' . $filename;
        
        // SVG-Inhalt in temporäre Datei schreiben
        $result = file_put_contents($temp_file, $svg_content);
        
        if ($result === false) {
            return '';
        }
        
        // URL zur temporären Datei erstellen
        $file_url = $upload_dir['baseurl'] . '/vectorize-wp/temp/' . $timestamp . '-' . $filename;
        
        // Einen Cron-Job erstellen, um temporäre Dateien nach einer Stunde zu löschen
        if (!wp_next_scheduled('vectorize_wp_cleanup_temp_files')) {
            wp_schedule_single_event(time() + 3600, 'vectorize_wp_cleanup_temp_files');
        }
        
        return $file_url;
    }
    
    /**
     * Bereinigt temporäre Dateien
     */
    public function cleanup_temp_files() {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/vectorize-wp/temp';
        
        if (!file_exists($temp_dir)) {
            return;
        }
        
        $files = glob($temp_dir . '/*');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file)) {
                // Dateien löschen, die älter als eine Stunde sind
                if ($now - filemtime($file) >= 3600) {
                    @unlink($file);
                }
            }
        }
    }
    
    /**
     * Extrahiert Pfade aus einem SVG-Inhalt für die Zauberstab-Funktion
     *
     * @param string $svg_content SVG-Inhalt
     * @return array Array mit Pfaddaten
     */
    public function extract_paths($svg_content) {
        // SVG-Inhalt sanitieren
        $svg_content = $this->sanitize_svg($svg_content);
        
        if (empty($svg_content)) {
            return array();
        }
        
        $paths = array();
        
        // SVG mit DOMDocument parsen
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadXML($svg_content);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        
        if (!empty($errors)) {
            return array();
        }
        
        // Pfade extrahieren
        $xpath = new DOMXPath($doc);
        $path_elements = $xpath->query('//path');
        
        foreach ($path_elements as $path) {
            $d = $path->getAttribute('d');
            $fill = $path->getAttribute('fill') ?: '#000000';
            $stroke = $path->getAttribute('stroke') ?: 'none';
            $stroke_width = $path->getAttribute('stroke-width') ?: '0';
            $id = $path->getAttribute('id') ?: 'path_' . count($paths);
            
            $paths[] = array(
                'id' => $id,
                'd' => $d,
                'fill' => $fill,
                'stroke' => $stroke,
                'stroke_width' => $stroke_width
            );
        }
        
        return $paths;
    }
    
    /**
     * Bringt einen Pfad mit einer bestimmten Farbe in den Vordergrund
     *
     * @param string $svg_content SVG-Inhalt
     * @param string $color Farbe, die hervorgehoben werden soll
     * @return string Modifizierter SVG-Inhalt
     */
    public function bring_color_to_front($svg_content, $color) {
        // SVG-Inhalt sanitieren
        $svg_content = $this->sanitize_svg($svg_content);
        
        if (empty($svg_content)) {
            return '';
        }
        
        // Farbwert normalisieren
        $color = strtolower($color);
        if ($color[0] === '#' && strlen($color) === 4) {
            // Kurze HEX-Farbe (#RGB) in lange Form (#RRGGBB) umwandeln
            $color = '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3];
        }
        
        // SVG mit DOMDocument parsen
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadXML($svg_content);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        
        if (!empty($errors)) {
            return $svg_content;
        }
        
        // Root SVG-Element finden
        $svg = $doc->getElementsByTagName('svg')->item(0);
        
        if (!$svg) {
            return $svg_content;
        }
        
        // Pfade finden, die die angegebene Farbe haben
        $xpath = new DOMXPath($doc);
        $matching_paths = $xpath->query('//path[@fill="' . $color . '"]');
        
        // Wenn keine passenden Pfade gefunden wurden, Original zurückgeben
        if ($matching_paths->length === 0) {
            return $svg_content;
        }
        
// Pfade mit der angegebenen Farbe ans Ende des SVG verschieben (werden zuletzt gerendert)
foreach ($matching_paths as $path) {
    $clone = $path->cloneNode(true);
    $path->parentNode->removeChild($path);
    $svg->appendChild($clone);
}

// Modifiziertes SVG zurückgeben
return $doc->saveXML($doc->documentElement);
}

/**
* Ändert die Farbe eines Pfads in einem SVG
*
* @param string $svg_content SVG-Inhalt
* @param string $path_id ID des Pfads
* @param string $new_color Neue Farbe
* @return string Modifizierter SVG-Inhalt
*/
public function change_path_color($svg_content, $path_id, $new_color) {
// SVG-Inhalt sanitieren
$svg_content = $this->sanitize_svg($svg_content);

if (empty($svg_content)) {
    return '';
}

// SVG mit DOMDocument parsen
libxml_use_internal_errors(true);
$doc = new DOMDocument();
$doc->loadXML($svg_content);
$errors = libxml_get_errors();
libxml_clear_errors();

if (!empty($errors)) {
    return $svg_content;
}

// Pfad mit der angegebenen ID finden
$xpath = new DOMXPath($doc);
$path = $xpath->query('//path[@id="' . $path_id . '"]')->item(0);

// Wenn kein passender Pfad gefunden wurde, Original zurückgeben
if (!$path) {
    return $svg_content;
}

// Farbe des Pfads ändern
$path->setAttribute('fill', $new_color);

// Modifiziertes SVG zurückgeben
return $doc->saveXML($doc->documentElement);
}

/**
* Implementiert die Zauberstab-Funktion, um ähnliche Farben zu finden und zu ersetzen
*
* @param string $svg_content SVG-Inhalt
* @param string $target_color Zielfarbe
* @param string $new_color Neue Farbe
* @param float $tolerance Toleranz für Farbähnlichkeit (0.0 - 1.0)
* @return string Modifiziertes SVG-Inhalt
*/
public function magic_wand($svg_content, $target_color, $new_color, $tolerance = 0.1) {
// SVG-Inhalt sanitieren
$svg_content = $this->sanitize_svg($svg_content);

if (empty($svg_content)) {
    return '';
}

// Farben in RGB umwandeln für Vergleich
$target_rgb = $this->hex_to_rgb($target_color);

if (!$target_rgb) {
    return $svg_content;
}

// SVG mit DOMDocument parsen
libxml_use_internal_errors(true);
$doc = new DOMDocument();
$doc->loadXML($svg_content);
$errors = libxml_get_errors();
libxml_clear_errors();

if (!empty($errors)) {
    return $svg_content;
}

// Alle Pfade durchgehen
$xpath = new DOMXPath($doc);
$paths = $xpath->query('//path');

foreach ($paths as $path) {
    $fill = $path->getAttribute('fill');
    
    // Wenn der Pfad eine Füllfarbe hat
    if ($fill && $fill !== 'none') {
        // Füllfarbe in RGB umwandeln
        $fill_rgb = $this->hex_to_rgb($fill);
        
        if ($fill_rgb) {
            // Farbähnlichkeit berechnen
            $color_distance = $this->calculate_color_distance($target_rgb, $fill_rgb);
            
            // Wenn die Farbe ähnlich genug ist
            if ($color_distance <= $tolerance) {
                // Farbe ändern
                $path->setAttribute('fill', $new_color);
            }
        }
    }
    
    // Das Gleiche für Striche
    $stroke = $path->getAttribute('stroke');
    
    if ($stroke && $stroke !== 'none') {
        // Strichfarbe in RGB umwandeln
        $stroke_rgb = $this->hex_to_rgb($stroke);
        
        if ($stroke_rgb) {
            // Farbähnlichkeit berechnen
            $color_distance = $this->calculate_color_distance($target_rgb, $stroke_rgb);
            
            // Wenn die Farbe ähnlich genug ist
            if ($color_distance <= $tolerance) {
                // Farbe ändern
                $path->setAttribute('stroke', $new_color);
            }
        }
    }
}

// Modifiziertes SVG zurückgeben
return $doc->saveXML($doc->documentElement);
}

/**
* Konvertiert einen HEX-Farbwert in RGB
*
* @param string $hex HEX-Farbwert
* @return array|false RGB-Array oder false bei ungültigem Input
*/
private function hex_to_rgb($hex) {
// # entfernen, falls vorhanden
$hex = ltrim($hex, '#');

// Hex-Farbwert validieren
if (!preg_match('/^[0-9A-Fa-f]{3}$|^[0-9A-Fa-f]{6}$/', $hex)) {
    return false;
}

// 3-stelligen Hex zu 6-stellig konvertieren
if (strlen($hex) === 3) {
    $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
}

// RGB-Werte extrahieren
$r = hexdec(substr($hex, 0, 2));
$g = hexdec(substr($hex, 2, 2));
$b = hexdec(substr($hex, 4, 2));

return array($r, $g, $b);
}

/**
* Berechnet die Distanz zwischen zwei Farben
*
* @param array $color1 RGB-Array für Farbe 1
* @param array $color2 RGB-Array für Farbe 2
* @return float Distanz zwischen 0.0 und 1.0
*/
private function calculate_color_distance($color1, $color2) {
// Euklidische Distanz im RGB-Raum
$r_diff = ($color1[0] - $color2[0]) / 255;
$g_diff = ($color1[1] - $color2[1]) / 255;
$b_diff = ($color1[2] - $color2[2]) / 255;

// Normalisierte Distanz zwischen 0 und 1
$distance = sqrt($r_diff * $r_diff + $g_diff * $g_diff + $b_diff * $b_diff) / sqrt(3);

return $distance;
}

/**
* Konvertiert ein SVG in ein anderes Format (AI, EPS)
* Dies ist ein Platzhalter, da die tatsächliche Konvertierung
* eine externe Bibliothek oder API erfordern würde
*
* @param string $svg_content SVG-Inhalt
* @param string $format Zielformat (ai, eps)
* @return string Konvertierter Inhalt oder leerer String bei Fehler
*/
public function convert_svg_to_format($svg_content, $format) {
// SVG-Inhalt sanitieren
$svg_content = $this->sanitize_svg($svg_content);

if (empty($svg_content)) {
    return '';
}

// Je nach Zielformat
switch (strtolower($format)) {
    case 'ai':
        // In einer echten Implementierung würde hier eine Konvertierung zu AI stattfinden
        // Zum Beispiel durch Aufruf eines externen Service oder einer Bibliothek
        return $svg_content;
        
    case 'eps':
        // In einer echten Implementierung würde hier eine Konvertierung zu EPS stattfinden
        return $svg_content;
        
    default:
        // Bei unbekanntem Format das Original zurückgeben
        return $svg_content;
}
}

/**
* Optimiert ein SVG durch Entfernen unnötiger Elemente und Attribute
*
* @param string $svg_content SVG-Inhalt
* @return string Optimiertes SVG
*/
public function optimize_svg($svg_content) {
// SVG-Inhalt sanitieren
$svg_content = $this->sanitize_svg($svg_content);

if (empty($svg_content)) {
    return '';
}

// SVG mit DOMDocument parsen
libxml_use_internal_errors(true);
$doc = new DOMDocument();
$doc->loadXML($svg_content);
$errors = libxml_get_errors();
libxml_clear_errors();

if (!empty($errors)) {
    return $svg_content;
}

// Kommentare entfernen
$xpath = new DOMXPath($doc);
$comments = $xpath->query('//comment()');

foreach ($comments as $comment) {
    $comment->parentNode->removeChild($comment);
}

// Leere Gruppen entfernen
$groups = $xpath->query('//g[not(*)]');

foreach ($groups as $group) {
    $group->parentNode->removeChild($group);
}

// SVG-Attribute minimieren
$svg = $doc->getElementsByTagName('svg')->item(0);

if ($svg) {
    // Unnötige Attribute entfernen
    $unnecessary_attrs = array('version');
    
    foreach ($unnecessary_attrs as $attr) {
        if ($svg->hasAttribute($attr)) {
            $svg->removeAttribute($attr);
        }
    }
}

// Optimiertes SVG zurückgeben
return $doc->saveXML($doc->documentElement);
}
}
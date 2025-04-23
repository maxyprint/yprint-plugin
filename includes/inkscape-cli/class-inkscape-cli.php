<?php
/**
 * Inkscape CLI Integration für Vectorize WP
 *
 * Diese Klasse stellt die Schnittstelle zur Inkscape Command Line Interface bereit.
 * 
 * @package VectorizeWP
 * @subpackage InkscapeCLI
 */

// Sicherheitscheck: Direkten Zugriff auf diese Datei verhindern
if (!defined('ABSPATH')) {
    exit;
}

class Vectorize_WP_Inkscape_CLI {
    /**
     * Pfad zur Inkscape-Executable
     *
     * @var string
     */
    private $inkscape_path;
    
    /**
     * Ob Inkscape korrekt installiert und nutzbar ist
     *
     * @var bool
     */
    private $is_available = false;
    
    /**
     * Temporäres Verzeichnis für Dateien
     *
     * @var string
     */
    private $temp_dir;
    
    /**
     * Konstruktor
     *
     * @param string $inkscape_path Optionaler Pfad zur Inkscape-Executable
     */
    public function __construct($inkscape_path = '') {
        // Temp-Verzeichnis setzen
        $upload_dir = wp_upload_dir();
        $this->temp_dir = $upload_dir['basedir'] . '/vectorize-wp/inkscape-temp';
        
        // Verzeichnis erstellen, falls es nicht existiert
        if (!file_exists($this->temp_dir)) {
            wp_mkdir_p($this->temp_dir);
        }
        
        // Inkscape-Pfad setzen
        if (!empty($inkscape_path)) {
            $this->inkscape_path = $inkscape_path;
        } else {
            // Automatic detection based on OS
            if (stripos(PHP_OS, 'WIN') === 0) {
                // Windows
                $possible_paths = array(
                    'C:\\Program Files\\Inkscape\\bin\\inkscape.exe',
                    'C:\\Program Files (x86)\\Inkscape\\bin\\inkscape.exe'
                );
            } else {
                // Linux/Mac
                $possible_paths = array(
                    '/usr/bin/inkscape',
                    '/usr/local/bin/inkscape',
                    '/opt/homebrew/bin/inkscape',
                    '/Applications/Inkscape.app/Contents/MacOS/inkscape'
                );
            }
            
            foreach ($possible_paths as $path) {
                if (file_exists($path)) {
                    $this->inkscape_path = $path;
                    break;
                }
            }
        }
        
        // Prüfen, ob Inkscape verfügbar ist
        $this->check_availability();
    }
    
    /**
     * Prüft, ob Inkscape verfügbar ist
     *
     * @return bool True wenn verfügbar, false wenn nicht
     */
    public function check_availability() {
        // Wenn bereits geprüft wurde
        if ($this->is_available) {
            return true;
        }
        
        // Wenn kein Pfad gesetzt wurde
        if (empty($this->inkscape_path)) {
            // Versuche, Inkscape im PATH zu finden
            $command = $this->get_os_specific_command('inkscape --version');
            exec($command, $output, $return_var);
            
            if ($return_var === 0 && !empty($output)) {
                // Inkscape ist im PATH
                $this->inkscape_path = 'inkscape';
                $this->is_available = true;
                return true;
            }
            
            return false;
        }
        
        // Prüfen, ob die Executable existiert
        if (!file_exists($this->inkscape_path)) {
            return false;
        }
        
        // Versuchen, die Version zu bekommen
        $command = $this->get_os_specific_command('"' . $this->inkscape_path . '" --version');
        exec($command, $output, $return_var);
        
        if ($return_var === 0 && !empty($output)) {
            $this->is_available = true;
            return true;
        }
        
        return false;
    }
    
    /**
     * Gibt zurück, ob Inkscape verfügbar ist
     *
     * @return bool
     */
    public function is_available() {
        return $this->is_available;
    }
    
    /**
     * Bereinigt temporäre Dateien
     */
    public function cleanup_temp_files() {
        if (!file_exists($this->temp_dir)) {
            return;
        }
        
        $files = glob($this->temp_dir . '/*');
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
     * Gibt einen OS-spezifischen Befehl zurück
     *
     * @param string $command Der Befehl
     * @return string Der angepasste Befehl
     */
    private function get_os_specific_command($command) {
        if (stripos(PHP_OS, 'WIN') === 0) {
            // Windows: Ausgabe unterdrücken
            return $command . ' 2>nul';
        } else {
            // Linux/Mac: Ausgabe in /dev/null umleiten
            return $command . ' 2>/dev/null';
        }
    }
    
    /**
     * Führt einen Inkscape-Befehl aus
     *
     * @param string $args Befehlsargumente
     * @param bool $return_output Ob die Ausgabe zurückgegeben werden soll
     * @return array|bool Array mit Ausgabe oder false bei Fehler
     */
    public function run_command($args, $return_output = false) {
        if (!$this->is_available) {
            return false;
        }
        
        // Befehl zusammensetzen
        $command = '"' . $this->inkscape_path . '" ' . $args;
        $command = $this->get_os_specific_command($command);
        
        // Debugging
        error_log('Inkscape CLI command: ' . $command);
        
        // Befehl ausführen
        exec($command, $output, $return_var);
        
        // Fehler prüfen
        if ($return_var !== 0) {
            error_log('Inkscape CLI error: ' . implode("\n", $output));
            return false;
        }
        
        if ($return_output) {
            return $output;
        }
        
        return true;
    }
    
    /**
     * Vektorisiert ein Bitmap-Bild mit Inkscape
     *
     * @param string $image_path Pfad zum Bild
     * @param array $options Optionen für die Vektorisierung
     * @return array|WP_Error Array mit Ergebnis oder WP_Error bei Fehler
     */
    public function vectorize_image($image_path, $options = array()) {
        // Debug-Log initialisieren
        $debug_log = "=== Inkscape Vectorize Image Debug Log ===\n";
        $debug_log .= "Time: " . date('Y-m-d H:i:s') . "\n";
        $debug_log .= "Image Path: " . $image_path . "\n";
        $debug_log .= "Options: " . print_r($options, true) . "\n\n";
        
        if (!$this->is_available) {
            $debug_log .= "Error: Inkscape ist nicht verfügbar.\n";
            error_log($debug_log);
            return new WP_Error('inkscape_not_available', __('Inkscape ist nicht verfügbar. Bitte installiere Inkscape oder prüfe den Pfad.', 'vectorize-wp'));
        }
        
        if (!file_exists($image_path)) {
            $debug_log .= "Error: Das angegebene Bild existiert nicht.\n";
            error_log($debug_log);
            return new WP_Error('image_not_found', __('Das angegebene Bild existiert nicht.', 'vectorize-wp'));
        }
        
        // Standard-Optionen
        $default_options = array(
            'detail' => 'medium', // low, medium, high
            'colors' => 8,        // Anzahl der Farben (1-256)
            'format' => 'svg',    // svg, png, pdf
        );
        
        // Optionen zusammenführen
        $options = wp_parse_args($options, $default_options);
        $debug_log .= "Merged Options: " . print_r($options, true) . "\n";
        
        // Detailgrad in Inkscape-Parameter umwandeln
        $scans = 8; // Default für medium
        if ($options['detail'] === 'low') {
            $scans = 4;
        } elseif ($options['detail'] === 'high') {
            $scans = 16;
        }
        
        // Ziel-SVG-Datei erstellen
        $file_info = pathinfo($image_path);
        $output_svg = $this->temp_dir . '/' . $file_info['filename'] . '.svg';
        
        // Inkscape-Befehl für Vektorisierung vorbereiten
        // --batch-process für Stapelverarbeitung ohne GUI
        // --actions definiert die Schritte, die Inkscape ausführen soll
        $actions = 'SelectAll;';
        $actions .= 'TraceBitmap:scans=' . $scans . ':colors=' . $options['colors'] . ':stack=false:invert=false:removeBackground=true:livePreviews=false;';
        $actions .= 'FileSave;';
        $actions .= 'FileClose';
        
        $args = '--batch-process ';
        $args .= '--actions="' . $actions . '" ';
        $args .= '"' . $image_path . '" ';
        $args .= '--export-filename="' . $output_svg . '" ';
        
        $debug_log .= "Inkscape Command Args: " . $args . "\n";
        
        // Befehl ausführen
        $result = $this->run_command($args);
        
        if ($result === false) {
            $debug_log .= "Error: Inkscape-Befehl fehlgeschlagen.\n";
            error_log($debug_log);
            return new WP_Error('inkscape_command_failed', __('Der Inkscape-Befehl ist fehlgeschlagen.', 'vectorize-wp'));
        }
        
        // Prüfen, ob die Ausgabedatei existiert
        if (!file_exists($output_svg)) {
            $debug_log .= "Error: Ausgabe-SVG wurde nicht erstellt.\n";
            error_log($debug_log);
            return new WP_Error('svg_not_created', __('Das SVG wurde nicht erstellt.', 'vectorize-wp'));
        }
        
        // SVG-Inhalt lesen
        $svg_content = file_get_contents($output_svg);
        
        if (empty($svg_content)) {
            $debug_log .= "Error: SVG-Inhalt ist leer.\n";
            error_log($debug_log);
            return new WP_Error('empty_svg', __('Das erzeugte SVG ist leer.', 'vectorize-wp'));
        }
        
        $debug_log .= "Vectorization successful!\n";
        
        // Debug-Log in die WordPress-Debug-Datei schreiben
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($debug_log);
        }
        
        // Speicherort für das Ergebnis
        $upload_dir = wp_upload_dir();
        $result_dir = $upload_dir['basedir'] . '/vectorize-wp';
        
        if (!file_exists($result_dir)) {
            wp_mkdir_p($result_dir);
        }
        
        // Dateiname für das Ergebnis erstellen
        $result_file = $result_dir . '/' . $file_info['filename'] . '.' . $options['format'];
        
        // Wenn das Format nicht SVG ist, konvertieren
        if ($options['format'] !== 'svg') {
            // Konvertierung implementieren
            // ...
        } else {
            // Für SVG einfach kopieren
            copy($output_svg, $result_file);
        }
        
        return array(
            'file_path' => $result_file,
            'file_url' => $upload_dir['baseurl'] . '/vectorize-wp/' . $file_info['filename'] . '.' . $options['format'],
            'format' => $options['format'],
            'content' => $svg_content,
            'is_test_mode' => false,
        );
    }
    
    /**
     * Konvertiert Text in Pfade
     *
     * @param string $svg_content SVG-Inhalt
     * @return string|WP_Error Konvertierter SVG-Inhalt oder WP_Error bei Fehler
     */
    public function text_to_path($svg_content) {
        if (!$this->is_available) {
            return new WP_Error('inkscape_not_available', __('Inkscape ist nicht verfügbar. Bitte installiere Inkscape oder prüfe den Pfad.', 'vectorize-wp'));
        }
        
        // Temporäre Dateien erstellen
        $temp_input = $this->temp_dir . '/text_to_path_input_' . time() . '.svg';
        $temp_output = $this->temp_dir . '/text_to_path_output_' . time() . '.svg';
        
        // SVG-Inhalt in Datei schreiben
        file_put_contents($temp_input, $svg_content);
        
        // Inkscape-Befehl für Text-zu-Pfad vorbereiten
        $actions = 'SelectAll;';
        $actions .= 'ObjectToPath;';
        $actions .= 'FileSave;';
        $actions .= 'FileClose';
        
        $args = '--batch-process ';
        $args .= '--actions="' . $actions . '" ';
        $args .= '"' . $temp_input . '" ';
        $args .= '--export-filename="' . $temp_output . '" ';
        
        // Befehl ausführen
        $result = $this->run_command($args);
        
        if ($result === false) {
            @unlink($temp_input);
            return new WP_Error('inkscape_command_failed', __('Der Inkscape-Befehl ist fehlgeschlagen.', 'vectorize-wp'));
        }
        
        // Prüfen, ob die Ausgabedatei existiert
        if (!file_exists($temp_output)) {
            @unlink($temp_input);
            return new WP_Error('svg_not_created', __('Das SVG wurde nicht erstellt.', 'vectorize-wp'));
        }
        
        // SVG-Inhalt lesen
        $converted_svg = file_get_contents($temp_output);
        
        // Temporäre Dateien löschen
        @unlink($temp_input);
        @unlink($temp_output);
        
        if (empty($converted_svg)) {
            return new WP_Error('empty_svg', __('Das konvertierte SVG ist leer.', 'vectorize-wp'));
        }
        
        return $converted_svg;
    }
    
    /**
     * Optimiert ein SVG
     *
     * @param string $svg_content SVG-Inhalt
     * @return string|WP_Error Optimierter SVG-Inhalt oder WP_Error bei Fehler
     */
    public function optimize_svg($svg_content) {
        if (!$this->is_available) {
            return new WP_Error('inkscape_not_available', __('Inkscape ist nicht verfügbar. Bitte installiere Inkscape oder prüfe den Pfad.', 'vectorize-wp'));
        }
        
        // Temporäre Dateien erstellen
        $temp_input = $this->temp_dir . '/optimize_input_' . time() . '.svg';
        $temp_output = $this->temp_dir . '/optimize_output_' . time() . '.svg';
        
        // SVG-Inhalt in Datei schreiben
        file_put_contents($temp_input, $svg_content);
        
        // Inkscape-Befehl für Optimierung vorbereiten - wir nutzen die Export-Funktionen mit Optimierungen
        $args = '"' . $temp_input . '" ';
        $args .= '--export-plain-svg ';
        $args .= '--export-filename="' . $temp_output . '" ';
        
        // Befehl ausführen
        $result = $this->run_command($args);
        
        if ($result === false) {
            @unlink($temp_input);
            return new WP_Error('inkscape_command_failed', __('Der Inkscape-Befehl ist fehlgeschlagen.', 'vectorize-wp'));
        }
        
        // Prüfen, ob die Ausgabedatei existiert
        if (!file_exists($temp_output)) {
            @unlink($temp_input);
            return new WP_Error('svg_not_created', __('Das SVG wurde nicht erstellt.', 'vectorize-wp'));
        }
        
        // SVG-Inhalt lesen
        $optimized_svg = file_get_contents($temp_output);
        
        // Temporäre Dateien löschen
        @unlink($temp_input);
        @unlink($temp_output);
        
        if (empty($optimized_svg)) {
            return new WP_Error('empty_svg', __('Das optimierte SVG ist leer.', 'vectorize-wp'));
        }
        
        return $optimized_svg;
    }
    
    /**
     * Kombiniert Pfade mit einer booleschen Operation
     *
     * @param string $svg_content SVG-Inhalt
     * @param string $operation Operation (union, difference, intersection, exclusion)
     * @return string|WP_Error Kombinierter SVG-Inhalt oder WP_Error bei Fehler
     */
    public function combine_paths($svg_content, $operation = 'union') {
        if (!$this->is_available) {
            return new WP_Error('inkscape_not_available', __('Inkscape ist nicht verfügbar. Bitte installiere Inkscape oder prüfe den Pfad.', 'vectorize-wp'));
        }
        
        // Operation validieren
        $valid_operations = array('union', 'difference', 'intersection', 'exclusion');
        if (!in_array($operation, $valid_operations)) {
            return new WP_Error('invalid_operation', __('Ungültige Operation.', 'vectorize-wp'));
        }
        
        // Inkscape-Aktion bestimmen
        $action = '';
        switch ($operation) {
            case 'union':
                $action = 'SelectAll;PathUnion';
                break;
            case 'difference':
                $action = 'SelectAll;PathDifference';
                break;
            case 'intersection':
                $action = 'SelectAll;PathIntersection';
                break;
            case 'exclusion':
                $action = 'SelectAll;PathExclusion';
                break;
        }
        
        // Temporäre Dateien erstellen
        $temp_input = $this->temp_dir . '/combine_input_' . time() . '.svg';
        $temp_output = $this->temp_dir . '/combine_output_' . time() . '.svg';
        
        // SVG-Inhalt in Datei schreiben
        file_put_contents($temp_input, $svg_content);
        
        // Inkscape-Befehl vorbereiten
        $args = '--batch-process ';
        $args .= '--actions="' . $action . ';FileSave;FileClose" ';
        $args .= '"' . $temp_input . '" ';
        $args .= '--export-filename="' . $temp_output . '" ';
        
        // Befehl ausführen
        $result = $this->run_command($args);
        
        if ($result === false) {
            @unlink($temp_input);
            return new WP_Error('inkscape_command_failed', __('Der Inkscape-Befehl ist fehlgeschlagen.', 'vectorize-wp'));
        }
        
        // Prüfen, ob die Ausgabedatei existiert
        if (!file_exists($temp_output)) {
            @unlink($temp_input);
            return new WP_Error('svg_not_created', __('Das SVG wurde nicht erstellt.', 'vectorize-wp'));
        }
        
        // SVG-Inhalt lesen
        $combined_svg = file_get_contents($temp_output);
        
        // Temporäre Dateien löschen
        @unlink($temp_input);
        @unlink($temp_output);
        
        if (empty($combined_svg)) {
            return new WP_Error('empty_svg', __('Das kombinierte SVG ist leer.', 'vectorize-wp'));
        }
        
        return $combined_svg;
    }
    
    /**
     * Konvertiert ein SVG in ein anderes Format
     *
     * @param string $svg_content SVG-Inhalt
     * @param string $format Zielformat (png, pdf)
     * @param array $options Optionen für die Konvertierung
     * @return string|WP_Error Pfad zur erzeugten Datei oder WP_Error bei Fehler
     */
    public function convert_svg_to_format($svg_content, $format = 'png', $options = array()) {
        if (!$this->is_available) {
            return new WP_Error('inkscape_not_available', __('Inkscape ist nicht verfügbar. Bitte installiere Inkscape oder prüfe den Pfad.', 'vectorize-wp'));
        }
        
        // Format validieren
        $valid_formats = array('png', 'pdf');
        if (!in_array($format, $valid_formats)) {
            return new WP_Error('invalid_format', __('Ungültiges Format.', 'vectorize-wp'));
        }
        
        // Standard-Optionen
        $default_options = array(
            'dpi' => 96,         // DPI für Rasterformate
            'width' => null,      // Breite in px
            'height' => null,     // Höhe in px
            'background' => null, // Hintergrundfarbe (z.B. '#FFFFFF' für weiß)
        );
        
        // Optionen zusammenführen
        $options = wp_parse_args($options, $default_options);
        
        // Temporäre Dateien erstellen
        $temp_input = $this->temp_dir . '/convert_input_' . time() . '.svg';
        $temp_output = $this->temp_dir . '/convert_output_' . time() . '.' . $format;
        
        // SVG-Inhalt in Datei schreiben
        file_put_contents($temp_input, $svg_content);
        
        // Inkscape-Befehl vorbereiten
        $args = '"' . $temp_input . '" ';
        $args .= '--export-filename="' . $temp_output . '" ';
        $args .= '--export-dpi=' . intval($options['dpi']) . ' ';
        
        // Bei Bedarf Größe setzen
        if (!empty($options['width']) && !empty($options['height'])) {
            $args .= '--export-width=' . intval($options['width']) . ' ';
            $args .= '--export-height=' . intval($options['height']) . ' ';
        }
        
        // Bei Bedarf Hintergrundfarbe setzen
        if (!empty($options['background'])) {
            $args .= '--export-background="' . $options['background'] . '" ';
            $args .= '--export-background-opacity=1.0 ';
        }
        
        // Befehl ausführen
        $result = $this->run_command($args);
        
        if ($result === false) {
            @unlink($temp_input);
            return new WP_Error('inkscape_command_failed', __('Der Inkscape-Befehl ist fehlgeschlagen.', 'vectorize-wp'));
        }
        
        // Prüfen, ob die Ausgabedatei existiert
        if (!file_exists($temp_output)) {
            @unlink($temp_input);
            return new WP_Error('file_not_created', __('Die Datei wurde nicht erstellt.', 'vectorize-wp'));
        }
        
        // Aufräumen
        @unlink($temp_input);
        
        // Upload-Verzeichnis für das Ergebnis
        $upload_dir = wp_upload_dir();
        $result_dir = $upload_dir['basedir'] . '/vectorize-wp';
        
        if (!file_exists($result_dir)) {
            wp_mkdir_p($result_dir);
        }
        
        // Endgültige Datei erstellen
        $filename = 'converted_' . time() . '.' . $format;
        $result_file = $result_dir . '/' . $filename;
        
        // Temporäre Datei verschieben
        rename($temp_output, $result_file);
        
        return array(
            'file_path' => $result_file,
            'file_url' => $upload_dir['baseurl'] . '/vectorize-wp/' . $filename,
            'format' => $format
        );
    }
}
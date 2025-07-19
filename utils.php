<?php
// utils.php

function load_records($filename = 'data.csv', $max_size_mb = 10) {
    $records = [];
    if (!file_exists($filename)) return $records;

    // Sécurité : Vérifier la taille du fichier pour éviter un DoS
    $filesize = filesize($filename);
    if ($filesize > $max_size_mb * 1024 * 1024) {
        error_log("Le fichier $filename est trop volumineux (> $max_size_mb MB)");
        return $records; // Retourne un tableau vide pour stopper l'opération
    }

    $handle = fopen($filename, 'r');
    if (!$handle) return $records;

    $headers = fgetcsv($handle, 0, ';');
    while (($row = fgetcsv($handle, 0, ';')) !== false) {
        $record = array_combine($headers, $row);
        if (!isset($record['identifier']) || empty($record['identifier'])) {
            static $id = 0;
            $record['identifier'] = 'oai:example:' . (++$id);
        }
        if (!isset($record['date']) || empty($record['date'])) {
            $record['date'] = date('Y-m-d');
        }
        if (!isset($record['set'])) {
            $record['set'] = '';
        }
        $records[] = $record;
    }

    fclose($handle);
    return $records;
}

function get_record_by_id($identifier, $records) {
    foreach ($records as $record) {
        if ($record['identifier'] === $identifier) return $record;
    }
    return null;
}

function validate_date($date) {
    return preg_match('/^\\d{4}(-\\d{2})?(-\\d{2})?$/', $date);
}

function get_base_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script_name = htmlspecialchars($_SERVER['SCRIPT_NAME'], ENT_QUOTES, 'UTF-8');
    return $protocol . '://' . $host . $script_name;
}

function extract_sets($records) {
    $sets = [];
    foreach ($records as $r) {
        if (!empty($r['set']) && !in_array($r['set'], $sets)) {
            $sets[] = $r['set'];
        }
    }
    return $sets;
}

function load_records_from_ead_directory($dir) {
    $records = [];
    if (!is_dir($dir)) return $records;

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'xml') continue;

        $dom = new DOMDocument();
        // On utilise @ pour supprimer les avertissements si le XML est mal formé
        if (!@$dom->load($file->getPathname())) continue;

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ead', 'urn:isbn:1-931666-22-9');

        // Requêtes XPath pour extraire les métadonnées
        $identifier = $xpath->query('//ead:eadheader/ead:eadid')->item(0)->nodeValue ?? '';
        $title = $xpath->query('//ead:archdesc/ead:did/ead:unittitle')->item(0)->nodeValue ?? '';
        $date = $xpath->query('//ead:archdesc/ead:did/ead:unitdate')->item(0)->nodeValue ?? date('Y-m-d');
        $creator = $xpath->query('//ead:archdesc/ead:did/ead:origination')->item(0)->nodeValue ?? '';

        // Le nom du sous-dossier devient le set
        $set = basename(dirname($file->getPathname()));
        if ($set === basename($dir)) $set = ''; // Pas de set si à la racine de ead/

        if (!$identifier) {
            // Si pas d'eadid, on en crée un à partir du nom de fichier
            $identifier = 'oai:' . basename($dir) . ':' . pathinfo($file->getFilename(), PATHINFO_FILENAME);
        }

        $records[] = [
            'set' => $set,
            'identifier' => $identifier,
            'title' => trim($title),
            'creator' => trim($creator),
            'subject' => '', // L'EAD n'a pas toujours de sujet simple
            'description' => '',
            'publisher' => '',
            'date' => trim($date),
            'type' => 'PhysicalObject', // Type par défaut pour un EAD
            'format' => 'application/xml',
            'language' => '',
            'coverage' => '',
            'rights' => '',
            'relation' => '',
            'ead_filename' => $file->getFilename() // On garde le nom du fichier pour la fonction format_record
        ];
    }
    return $records;
}
?>
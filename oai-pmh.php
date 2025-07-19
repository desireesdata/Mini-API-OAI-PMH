<?php
// oai-pmh.php

require_once 'utils.php';

// --- Sécurité : Clé secrète pour signer les resumptionTokens. À CHANGER POUR VOTRE PROD ! ---
define('OAI_SECRET_KEY', 'une-longue-chaine-aleatoire-et-secrete');

header('Content-Type: text/xml; charset=UTF-8');
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";

// --- Sécurité : Whitelist des valeurs autorisées ---
$allowedVerbs = ['Identify', 'ListMetadataFormats', 'ListSets', 'ListIdentifiers', 'ListRecords', 'GetRecord'];
$allowedMetadataPrefixes = ['oai_dc', 'ead'];

// --- Sécurité : Nettoyage des entrées GET pour prévenir les XSS ---
$verb = isset($_GET['verb']) ? htmlspecialchars($_GET['verb'], ENT_QUOTES, 'UTF-8') : '';
$identifier = isset($_GET['identifier']) ? htmlspecialchars($_GET['identifier'], ENT_QUOTES, 'UTF-8') : '';
$metadataPrefix = isset($_GET['metadataPrefix']) ? htmlspecialchars($_GET['metadataPrefix'], ENT_QUOTES, 'UTF-8') : '';
$resumptionToken = isset($_GET['resumptionToken']) ? $_GET['resumptionToken'] : null; // Ne pas échapper le token ici
$setParam = isset($_GET['set']) ? htmlspecialchars($_GET['set'], ENT_QUOTES, 'UTF-8') : null;

// Charger les enregistrements depuis les deux sources
$csv_records = load_records('data.csv');
$ead_records = load_records_from_ead_directory('ead/');

// Fusionner les deux listes
$records = array_merge($csv_records, $ead_records);
$sets = extract_sets($records);

$batchSize = 10;
$baseURL = get_base_url();

function format_record($record, $metadataPrefix) {
    $xml = "<record>\n";
    $xml .= "  <header>\n";
    $xml .= "    <identifier>" . htmlspecialchars($record['identifier']) . "</identifier>\n";
    $xml .= "    <datestamp>" . htmlspecialchars($record['date']) . "</datestamp>\n";
    if (!empty($record['set'])) {
        $xml .= "    <setSpec>" . htmlspecialchars($record['set']) . "</setSpec>\n";
    }
    $xml .= "  </header>\n";
    $xml .= "  <metadata>\n";

    if ($metadataPrefix == 'ead' && !empty($record['ead_filename'])) {
        $safe_filename = basename($record['ead_filename']);
        $ead_file_path = 'ead/' . $safe_filename;
        if (file_exists($ead_file_path)) {
            $old_libxml_setting = libxml_disable_entity_loader(true);
            $dom = new DOMDocument();
            $dom->load($ead_file_path, LIBXML_NOENT | LIBXML_DTDLOAD);
            libxml_disable_entity_loader($old_libxml_setting);
            $xml .= $dom->saveXML($dom->documentElement);
        }
    } else {
        $xml .= "    <oai_dc:dc xmlns:oai_dc=\"http://www.openarchives.org/OAI/2.0/oai_dc/\" \n                 xmlns:dc=\"http://purl.org/dc/elements/1.1/\" \n                 xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" \n                 xsi:schemaLocation=\"http://www.openarchives.org/OAI/2.0/oai_dc/ \n                 http://www.openarchives.org/OAI/2.0/oai_dc.xsd\">\n";
        foreach ($record as $key => $value) {
            if (in_array($key, ['title','creator','subject','description','publisher','date','type','format','language','coverage','rights']) && !empty($value)) {
                $xml .= "      <dc:$key>" . htmlspecialchars($value) . "</dc:$key>\n";
            }
        }
        $xml .= "    </oai_dc:dc>\n";
    }

    $xml .= "  </metadata>\n";
    $xml .= "</record>\n";
    return $xml;
}

function format_header($record) {
    $xml = "<header>\n";
    $xml .= "  <identifier>" . htmlspecialchars($record['identifier']) . "</identifier>\n";
    $xml .= "  <datestamp>" . htmlspecialchars($record['date']) . "</datestamp>\n";
    if (!empty($record['set'])) {
        $xml .= "  <setSpec>" . htmlspecialchars($record['set']) . "</setSpec>\n";
    }
    $xml .= "</header>\n";
    return $xml;
}

function list_sets($sets) {
    $xml = "<ListSets>\n";
    foreach ($sets as $set) {
        $xml .= "  <set>\n";
        $xml .= "    <setSpec>" . htmlspecialchars($set) . "</setSpec>\n";
        $xml .= "    <setName>" . htmlspecialchars(ucfirst($set)) . "</setName>\n";
        $xml .= "  </set>\n";
    }
    $xml .= "</ListSets>\n";
    return $xml;
}

$date = gmdate('Y-m-d\TH:i:s\Z');
echo "<OAI-PMH xmlns=\"http://www.openarchives.org/OAI/2.0/\">\n";
echo "  <responseDate>$date</responseDate>\n";
echo "  <request verb=\"$verb\">$baseURL</request>\n";

if (!in_array($verb, $allowedVerbs)) {
    echo "  <error code=\"badVerb\">Verbe OAI inconnu ou non pris en charge.</error>\n";
} else {
    switch ($verb) {
        case 'Identify':
            echo "  <Identify>\n";
            echo "    <repositoryName>Mon Entrepot OAI</repositoryName>\n";
            echo "    <baseURL>$baseURL</baseURL>\n";
            echo "    <protocolVersion>2.0</protocolVersion>\n";
            echo "    <adminEmail>admin@example.org</adminEmail>\n";
            echo "    <earliestDatestamp>2000-01-01</earliestDatestamp>\n";
            echo "    <deletedRecord>no</deletedRecord>\n";
            echo "    <granularity>YYYY-MM-DD</granularity>\n";
            echo "  </Identify>\n";
            break;

        case 'ListMetadataFormats':
            echo "  <ListMetadataFormats>\n";
            echo "    <metadataFormat>\n";
            echo "      <metadataPrefix>oai_dc</metadataPrefix>\n";
            echo "      <schema>http://www.openarchives.org/OAI/2.0/oai_dc.xsd</schema>\n";
            echo "      <metadataNamespace>http://www.openarchives.org/OAI/2.0/oai_dc/</metadataNamespace>\n";
            echo "    </metadataFormat>\n";
            echo "    <metadataFormat>\n";
            echo "      <metadataPrefix>ead</metadataPrefix>\n";
            echo "      <schema>urn:isbn:1-931666-22-9</schema>\n";
            echo "      <metadataNamespace>urn:isbn:1-931666-22-9</metadataNamespace>\n";
            echo "    </metadataFormat>\n";
            echo "  </ListMetadataFormats>\n";
            break;
        
        case 'ListIdentifiers':
        case 'ListRecords':
            $start = 0;
            if ($resumptionToken) {
                list($token_data, $hmac) = explode('.', $resumptionToken, 2);
                if (!hash_equals(hash_hmac('sha256', $token_data, OAI_SECRET_KEY), $hmac)) {
                    echo "  <error code=\"badResumptionToken\">Le resumptionToken est invalide ou a expiré.</error>\n";
                    break;
                }
                $context = json_decode(base64_decode($token_data), true);
                $start = $context['offset'];
                $setParam = $context['set'];
                $metadataPrefix = $context['prefix'];
            }

            if ($metadataPrefix && !in_array($metadataPrefix, $allowedMetadataPrefixes)) {
                echo "  <error code=\"cannotDisseminateFormat\">Le format de métadonnées demandé n'est pas disponible.</error>\n";
                break;
            }

            echo "  <" . $verb . ">\n";
            $filteredRecords = $records;
            if ($setParam) {
                $filteredRecords = array_filter($records, function ($record) use ($setParam) {
                    return isset($record['set']) && $record['set'] === $setParam;
                });
                $filteredRecords = array_values($filteredRecords);
            }

            $chunk = array_slice($filteredRecords, $start, $batchSize);
            foreach ($chunk as $record) {
                if ($verb === 'ListRecords') {
                    echo format_record($record, $metadataPrefix);
                } else {
                    echo format_header($record);
                }
            }

            if ($start + $batchSize < count($filteredRecords)) {
                $new_offset = $start + $batchSize;
                $context = ['offset' => $new_offset, 'set' => $setParam, 'prefix' => $metadataPrefix];
                $token_data = base64_encode(json_encode($context));
                $hmac = hash_hmac('sha256', $token_data, OAI_SECRET_KEY);
                echo "  <resumptionToken>" . htmlspecialchars($token_data . '.' . $hmac) . "</resumptionToken>\n";
            }

            echo "  </" . $verb . ">\n";
            break;

        case 'GetRecord':
            if ($metadataPrefix && !in_array($metadataPrefix, $allowedMetadataPrefixes)) {
                echo "  <error code=\"cannotDisseminateFormat\">Le format de métadonnées demandé n'est pas disponible.</error>\n";
                break;
            }
            $record = get_record_by_id($identifier, $records);
            if ($record) {
                echo "  <GetRecord>\n";
                echo format_record($record, $metadataPrefix);
                echo "  </GetRecord>\n";
            } else {
                echo "  <error code=\"idDoesNotExist\">L'identifiant demandé n'existe pas.</error>\n";
            }
            break;

        case 'ListSets':
            echo list_sets($sets);
            break;
    }
}

echo "</OAI-PMH>\n";
?>
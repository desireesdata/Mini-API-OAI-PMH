import requests
import xml.etree.ElementTree as ET
import os
import time

# --- Paramètres ---
# URL de base du point d'accès OAI-PMH
OAI_BASE_URL = "https://francearchives.gouv.fr/oai"

# Le 'set' du service d'archives que vous voulez moissonner.
# Remplacez 'findingaid:service:FRAD027' par celui de votre choix.
SET_TO_HARVEST = "findingaid:service:FRAD004"

# Le préfixe de métadonnées pour les EAD sur FranceArchives
METADATA_PREFIX = "ape_ead"

# Nom du dossier où seront sauvegardés les fichiers XML
OUTPUT_DIR = f"harvest_{SET_TO_HARVEST.replace(':', '_')}"

# --- Fonctions ---

def get_all_identifiers(set_spec):
    """
    Parcourt la liste des identifiants d'un set en gérant la pagination
    via le resumptionToken.
    Retourne une liste de tous les identifiants de záznam.
    """
    identifiers = []
    params = {
        "verb": "ListIdentifiers",
        "set": set_spec,
        "metadataPrefix": METADATA_PREFIX,
    }
    
    print(f"[*] Début de la collecte des identifiants pour le set : {set_spec}")

    # Variable pour stocker la taille totale, initialisée à 'inconnu'
    total_size = '?'

    while True:
        try:
            response = requests.get(OAI_BASE_URL, params=params)
            response.raise_for_status()
        except requests.exceptions.RequestException as e:
            print(f"[!] Erreur HTTP lors de la collecte des identifiants : {e}")
            break

        namespaces = {"oai": "http://www.openarchives.org/OAI/2.0/"}
        root = ET.fromstring(response.content)

        # Vérification des erreurs OAI-PMH
        error_element = root.find("oai:error", namespaces)
        if error_element is not None:
            error_code = error_element.get("code", "unknown")
            error_text = error_element.text or "Pas de message d'erreur."
            print(f"[!] Erreur OAI-PMH reçue : {error_code} - {error_text.strip()}")
            if params.get("resumptionToken"):
                print(f"    -> Le problème est survenu avec le token : {params['resumptionToken']}")
            break
        
        # Annonce la taille totale dès la première réponse
        if total_size == '?': # On ne le fait qu'une fois
            token_element_for_size = root.find(".//oai:resumptionToken", namespaces)
            if token_element_for_size is not None:
                size_from_attr = token_element_for_size.get("completeListSize")
                if size_from_attr:
                    total_size = size_from_attr
                    print(f"[*] Information du serveur : ce set contient {total_size} instruments de recherche.")

        # Ajoute les identifiants de la page actuelle à notre liste
        current_identifiers = [
            header.find("oai:identifier", namespaces).text
            for header in root.findall(".//oai:header", namespaces)
        ]
        if current_identifiers:
            identifiers.extend(current_identifiers)
            # Message de progression amélioré
            print(f"[*] ... {len(identifiers)}/{total_size} identifiants collectés.")

        # Cherche le resumptionToken pour la page suivante
        token_element = root.find(".//oai:resumptionToken", namespaces)
        if token_element is not None and token_element.text is not None:
            params = {
                "verb": "ListIdentifiers", 
                "resumptionToken": token_element.text,
                "metadataPrefix": METADATA_PREFIX
            }
            time.sleep(1) # Soyons polis
        else:
            break
            
    print(f"[*] Collecte terminée. Total de {len(identifiers)} identifiants trouvés.")
    return identifiers


def get_and_save_record(identifier, output_dir):
    """
    Récupère un záznam complet via GetRecord et sauvegarde le contenu EAD
    dans un fichier XML.
    """
    params = {
        "verb": "GetRecord",
        "identifier": identifier,
        "metadataPrefix": METADATA_PREFIX,
    }
    
    try:
        response = requests.get(OAI_BASE_URL, params=params)
        response.raise_for_status()
    except requests.exceptions.RequestException as e:
        print(f"[!] Erreur HTTP pour l'identifiant {identifier}: {e}")
        return

    namespaces = {
        "oai": "http://www.openarchives.org/OAI/2.0/",
        "ead": "urn:isbn:1-931666-22-9"
    }
    root = ET.fromstring(response.content)
    
    metadata_element = root.find(".//oai:metadata", namespaces)
    
    if metadata_element is not None:
        ead_element = metadata_element[0]
        if ead_element is not None:
            filename = os.path.join(output_dir, f"{identifier.replace(':', '_')}.xml")
            try:
                # CORRECTION : Enregistre l'espace de noms EAD pour éviter les préfixes 'ns0:'
                ET.register_namespace('', "urn:isbn:1-931666-22-9")
                tree = ET.ElementTree(ead_element)
                tree.write(filename, encoding="utf-8", xml_declaration=True)
                print(f"    -> Sauvegardé : {filename}")
            except Exception as e:
                print(f"[!] Erreur lors de la sauvegarde du fichier pour {identifier}: {e}")
        else:
            print(f"[!] Pas de contenu EAD trouvé dans les métadonnées pour {identifier}")
    else:
        error_element = root.find("oai:error", namespaces)
        if error_element is not None:
            error_code = error_element.get("code", "unknown")
            error_text = error_element.text or "Pas de message d'erreur."
            print(f"[!] Erreur OAI-PMH pour l'identifiant {identifier}: {error_code} - {error_text.strip()}")
        else:
            print(f"[!] Pas de balise <metadata> trouvée pour {identifier}")


# --- Exécution principale ---
if __name__ == "__main__":
    os.makedirs(OUTPUT_DIR, exist_ok=True)

    all_ids = get_all_identifiers(SET_TO_HARVEST)

    if all_ids:
        print(f"\n[*] Début du téléchargement des {len(all_ids)} documents EAD...")
        for i, identifier in enumerate(all_ids):
            print(f"[*] Traitement du document {i+1}/{len(all_ids)} : {identifier}")
            # CORRECTION: Utilisation de la bonne variable (OUTPUT_DIR au lieu de output_dir)
            get_and_save_record(identifier, OUTPUT_DIR)
            time.sleep(1) # Soyons polis
        print("\n[*] Moissonnage terminé !")
    else:
        print("\n[*] Aucun identifiant n'a été trouvé. Le script s'arrête.")
